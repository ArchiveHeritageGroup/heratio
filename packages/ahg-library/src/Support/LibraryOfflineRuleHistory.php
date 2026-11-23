<?php

/**
 * LibraryOfflineRuleHistory - dated versions of the loan rules (#1474 item 4).
 *
 * library_loan_rule holds only the current rules, so a loan captured offline
 * last Tuesday could not otherwise be judged by Tuesday's rules. A version is
 * recorded when a rule or the default loan period is saved through Heratio,
 * and whenever a desk downloads its offline data. An edit made directly in SQL
 * is only noticed at the next download, so its effective date can be late by
 * that interval.
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgLibrary\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LibraryOfflineRuleHistory
{
    public const TABLE = 'library_offline_rule_snapshot';

    /** Record the current rules if they differ from the latest version. */
    public static function capture(string $source): ?object
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasTable('library_loan_rule')) {
            return null;
        }

        $rules = DB::table('library_loan_rule')->get()->map(fn ($r) => [
            'branch_id' => (int) ($r->branch_id ?? LibraryBranch::ALL),
            'material_type' => (string) $r->material_type,
            'patron_type' => (string) $r->patron_type,
            'loan_period_days' => (int) $r->loan_period_days,
            'max_renewals' => (int) $r->max_renewals,
            'fine_per_day' => (float) $r->fine_per_day,
            'fine_cap' => $r->fine_cap !== null ? (float) $r->fine_cap : null,
            'grace_period_days' => (int) $r->grace_period_days,
            'is_loanable' => (int) $r->is_loanable,
        ])->sortBy(fn ($r) => sprintf('%012d|%s|%s', $r['branch_id'], $r['material_type'], $r['patron_type']))
            ->values()->all();

        $defaults = [(string) LibraryBranch::ALL => LibrarySettings::defaultLoanDays()];
        foreach (self::branchIds() as $branchId) {
            $defaults[(string) $branchId] = LibrarySettings::defaultLoanDays($branchId);
        }
        ksort($defaults);

        $rulesJson = json_encode($rules, JSON_THROW_ON_ERROR);
        $defaultsJson = json_encode($defaults, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $rulesJson . '|' . $defaultsJson);

        $latest = DB::table(self::TABLE)->orderByDesc('effective_from')->orderByDesc('id')->first();
        if ($latest && hash_equals($latest->rules_hash, $hash)) {
            return $latest;
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'rules_hash' => $hash,
            'rules_json' => $rulesJson,
            'default_loan_days_json' => $defaultsJson,
            'effective_from' => now()->utc()->format('Y-m-d H:i:s'),
            'source' => mb_substr($source, 0, 30),
            'created_at' => now(),
        ]);

        return DB::table(self::TABLE)->where('id', $id)->first();
    }

    /**
     * Rule edits must not fail because the history could not be written, but
     * the gap is logged since it weakens later as-at decisions.
     */
    public static function captureQuietly(string $source): void
    {
        try {
            self::capture($source);
        } catch (\Throwable $e) {
            Log::warning('Loan rule history not recorded: ' . $e->getMessage());
        }
    }

    /**
     * The version in force at a moment, with how it was determined. Before the
     * first recorded version the earliest one is the closest evidence there is,
     * and is labelled so rather than passed off as exact.
     *
     * @return array{0: ?object, 1: ?string}
     */
    public static function forTime(string $utc): array
    {
        $row = DB::table(self::TABLE)->where('effective_from', '<=', $utc)
            ->orderByDesc('effective_from')->orderByDesc('id')->first();
        if ($row) {
            return [$row, 'as_at'];
        }

        $row = DB::table(self::TABLE)->orderBy('effective_from')->orderBy('id')->first()
            ?? self::capture('replay');

        return [$row, $row ? 'earliest_known' : null];
    }

    /** @return int[] Branches holding copies or patrons. */
    private static function branchIds(): array
    {
        if (! LibraryBranch::available()) {
            return [];
        }

        $ids = [];
        foreach (['library_copy', 'library_patron'] as $table) {
            if (Schema::hasColumn($table, 'branch_id')) {
                $ids = array_merge($ids, DB::table($table)->whereNotNull('branch_id')->distinct()->pluck('branch_id')->all());
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
