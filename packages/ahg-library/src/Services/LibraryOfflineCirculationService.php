<?php

/**
 * LibraryOfflineCirculationService - replay of desk transactions captured
 * while the link was down (#1474 item 4).
 *
 * Each transaction is judged as at the moment it happened: the loan rules in
 * force then, the loans open then, and a due date and fine counted from then.
 * What cannot be proved safe becomes a conflict for staff and changes nothing.
 * Patron standing and the fine balance have no history in the schema, so those
 * two checks use the present value and can raise a conflict staff may override.
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgLibrary\Services;

use AhgCore\Services\EncryptionService;
use AhgCore\Support\I18nFallback;
use AhgLibrary\Support\LibraryBranch;
use AhgLibrary\Support\LibraryOfflineRuleHistory;
use AhgLibrary\Support\LibrarySettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryOfflineCirculationService extends LibraryCirculationService
{
    public const TXN = 'library_offline_txn';

    public const ACTION_CHECKOUT = 'checkout';
    public const ACTION_RETURN = 'return';

    public const PENDING = 'pending';
    public const APPLIED = 'applied';
    public const CONFLICT = 'conflict';
    public const REJECTED = 'rejected';

    public const RESOLVE_RETRY = 'retry';
    public const RESOLVE_APPLY = 'apply';
    public const RESOLVE_REJECT = 'reject';

    /** These settle only by a named staff decision with a note; a re-check cannot clear them. */
    public const NEEDS_DECISION = ['clock_invalid', 'clock_behind', 'outside_window', 'out_of_order', 'fine_reduction'];

    /** Policy and timing checks can be overridden; facts about a copy cannot. */
    public const OVERRIDABLE = ['patron_suspended', 'over_limit', 'fine_threshold', 'not_loanable', ...self::NEEDS_DECISION];

    /** Tolerated difference between the desk clock and the server, in seconds. */
    private const CLOCK_TOLERANCE = 300;

    private array $snapshots = [];

    public static function available(): bool
    {
        return Schema::hasTable(self::TXN) && Schema::hasTable(LibraryOfflineRuleHistory::TABLE);
    }

    // Desk data

    /** The loan rules a desk carries offline. */
    public function rulesPackage(): array
    {
        $snapshot = LibraryOfflineRuleHistory::capture('desk_download');

        return [
            'snapshot_id' => $snapshot ? (int) $snapshot->id : null,
            'rules' => $snapshot ? json_decode($snapshot->rules_json, true) : [],
            'default_loan_days' => $snapshot ? json_decode($snapshot->default_loan_days_json, true) : [],
        ];
    }

    /** One page of copies for the operator's branch, ordered by id. */
    public function copiesPage(?int $branchId, int $after, int $limit): array
    {
        $query = DB::table('library_copy as cp')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as i18n', 'li.information_object_id', app()->getLocale()))
            ->leftJoin('library_checkout as c', function ($join) {
                $join->on('c.copy_id', '=', 'cp.id')->whereIn('c.status', LibraryCirculationService::OPEN_LOAN_STATUSES);
            })
            ->leftJoin('library_patron as lp', 'c.patron_id', '=', 'lp.id')
            ->whereNotNull('cp.barcode')
            ->where('cp.id', '>', $after);

        if ($branchId !== null && $this->hasBranchColumn('library_copy')) {
            $query->where(fn ($w) => $w->where('cp.branch_id', $branchId)->orWhereNull('cp.branch_id'));
        }

        $columns = ['cp.id', 'cp.barcode', 'cp.status', 'li.material_type', 'c.due_date as loan_due', 'lp.card_number as loan_card', I18nFallback::col('i18n', 'title', 'title')];
        if ($this->hasBranchColumn('library_copy')) {
            $columns[] = 'cp.branch_id';
        }

        return $query->orderBy('cp.id')->limit($limit)->get($columns)->all();
    }

    /**
     * One page of patrons. Only what the desk needs to recognise a card and
     * warn: no contact details, and fines reduced to a yes/no.
     */
    public function patronsPage(?int $branchId, int $after, int $limit): array
    {
        $query = DB::table('library_patron as p')->where('p.id', '>', $after);

        if ($branchId !== null && $this->hasBranchColumn('library_patron')) {
            $query->where(fn ($w) => $w->where('p.branch_id', $branchId)->orWhereNull('p.branch_id'));
        }

        $threshold = LibrarySettings::patronFineThreshold();

        return $query->orderBy('p.id')->limit($limit)
            ->get(['p.id', 'p.card_number', 'p.first_name', 'p.last_name', 'p.patron_type',
                'p.borrowing_status', 'p.max_checkouts', 'p.total_fines_owed',
                DB::raw("(SELECT COUNT(*) FROM library_checkout lc WHERE lc.patron_id = p.id AND lc.status IN ('active', 'overdue')) AS active_loans")])
            ->map(function ($p) use ($threshold) {
                $initial = mb_substr(trim((string) $p->first_name), 0, 1);

                return (object) [
                    'id' => (int) $p->id,
                    'card_number' => $p->card_number,
                    'display_name' => trim(($initial !== '' ? $initial . '. ' : '') . $p->last_name),
                    'patron_type' => $p->patron_type,
                    'borrowing_status' => $p->borrowing_status,
                    'max_checkouts' => (int) $p->max_checkouts,
                    'over_fine_threshold' => (float) $p->total_fines_owed >= $threshold,
                    'active_loans' => (int) $p->active_loans,
                ];
            })->all();
    }

    // Replay

    /**
     * Replay a batch in capture order. Each transaction commits on its own, so
     * a dropped connection leaves earlier ones applied, and the client's retry
     * is harmless because client_uuid makes every transaction idempotent.
     */
    public function replay(array $transactions, ?int $userId, ?int $operatorBranch, bool $branchLocked = false, ?string $deskClock = null): array
    {
        usort($transactions, fn ($a, $b) => [(string) $a['device_id'], (int) $a['seq']] <=> [(string) $b['device_id'], (int) $b['seq']]);

        // Positive means the desk clock is behind the server.
        $skew = $deskClock !== null ? time() - CarbonImmutable::parse($deskClock)->getTimestamp() : null;

        return array_map(fn ($t) => $this->replayOne($t, $userId, $operatorBranch, $branchLocked, $skew), $transactions);
    }

    private function replayOne(array $t, ?int $userId, ?int $operatorBranch, bool $branchLocked, ?int $skew): array
    {
        return DB::transaction(function () use ($t, $userId, $operatorBranch, $branchLocked, $skew) {
            $uuid = strtolower((string) $t['uuid']);

            // Queued under one operator, sent under another: never re-attribute it.
            if (isset($t['operator_id']) && (int) $t['operator_id'] !== (int) $userId) {
                return ['uuid' => $uuid, 'status' => 'refused', 'conflict_code' => null, 'due_date' => null];
            }

            $occurred = CarbonImmutable::parse($t['occurred_at'])->utc()->format('Y-m-d H:i:s');

            $inserted = DB::table(self::TXN)->insertOrIgnore([
                'client_uuid' => $uuid,
                'device_id' => (string) $t['device_id'],
                'seq' => (int) $t['seq'],
                'action' => (string) $t['action'],
                'copy_barcode' => trim((string) $t['copy_barcode']),
                'patron_card' => isset($t['patron_card']) ? trim((string) $t['patron_card']) : null,
                'expected_patron_card' => isset($t['expected_patron_card']) ? trim((string) $t['expected_patron_card']) : null,
                'branch_id' => $this->trustedBranch($t['branch_id'] ?? null, $operatorBranch, $branchLocked),
                'occurred_at' => $occurred,
                'received_at' => now()->utc()->format('Y-m-d H:i:s'),
                'status' => self::PENDING,
                'captured_by' => $userId,
                'created_at' => now(),
            ]);

            $row = DB::table(self::TXN)->where('client_uuid', $uuid)->lockForUpdate()->first();

            if (! $inserted) {
                if ((int) $row->captured_by !== (int) $userId) {
                    return ['uuid' => $uuid, 'status' => 'refused', 'conflict_code' => null, 'due_date' => null];
                }

                return $this->result($row);
            }

            $timing = $this->timeProblem($row, $skew);
            if ($timing !== null) {
                return $this->result($this->markConflict($row, $timing[0], $timing[1]));
            }

            $code = $this->sequenceProblem($row);
            if ($code !== null) {
                return $this->result($this->markConflict($row, $code, []));
            }

            return $this->result($this->process($row, []));
        });
    }

    /** A desk's branch claim is honoured only if real and the operator is free to choose. */
    private function trustedBranch(mixed $claimed, ?int $operatorBranch, bool $locked): ?int
    {
        if ($locked || ! LibraryBranch::available()) {
            return $operatorBranch;
        }
        $claimed = is_numeric($claimed) ? (int) $claimed : 0;

        return $claimed > 0 && array_key_exists($claimed, LibraryBranch::options()) ? $claimed : $operatorBranch;
    }

    private function isFuture(object $row): bool
    {
        return strtotime($row->occurred_at . ' UTC') > time() + self::CLOCK_TOLERANCE;
    }

    /**
     * The desk's time decides due dates and fines, so it must be believable:
     * not ahead of the server, from a desk whose clock agrees with the server
     * at sync, and inside the offline window.
     *
     * @return array{0: string, 1: array}|null
     */
    private function timeProblem(object $row, ?int $skew): ?array
    {
        if ($this->isFuture($row)) {
            return ['clock_invalid', []];
        }
        if ($skew !== null && abs($skew) > self::CLOCK_TOLERANCE) {
            return [$skew > 0 ? 'clock_behind' : 'clock_invalid', ['skew_seconds' => $skew]];
        }
        $maxDays = LibrarySettings::offlineMaxDays();
        if (strtotime($row->occurred_at . ' UTC') < time() - $maxDays * 86400) {
            return ['outside_window', ['max_days' => $maxDays]];
        }

        return null;
    }

    private function sequenceProblem(object $row): ?string
    {
        $previous = DB::table(self::TXN)
            ->where('device_id', $row->device_id)
            ->where('captured_by', $row->captured_by)
            ->where('id', '!=', $row->id)
            // A reading from a clock already found wrong proves nothing about order.
            ->where(fn ($w) => $w->whereNull('conflict_code')->orWhere('conflict_code', '!=', 'clock_invalid'))
            ->orderByDesc('seq')
            ->first();

        return $previous && ((int) $row->seq <= (int) $previous->seq || $row->occurred_at < $previous->occurred_at)
            ? 'out_of_order'
            : null;
    }

    private function process(object $row, array $override, ?int $decidedBy = null, ?string $note = null): object
    {
        // A later action on a copy cannot be judged until the earlier one is settled.
        $blocked = DB::table(self::TXN)
            ->where('copy_barcode', $row->copy_barcode)
            ->where('status', self::CONFLICT)
            ->where('id', '!=', $row->id)
            ->where(fn ($w) => $w->where('occurred_at', '<', $row->occurred_at)
                ->orWhere(fn ($same) => $same->where('occurred_at', $row->occurred_at)->where('id', '<', $row->id)))
            ->orderBy('occurred_at')->value('id');
        if ($blocked) {
            return $this->markConflict($row, 'depends_on_conflict', ['transaction_id' => (int) $blocked]);
        }

        [$snapshot, $basis] = LibraryOfflineRuleHistory::forTime($row->occurred_at);
        DB::table(self::TXN)->where('id', $row->id)->update([
            'rule_snapshot_id' => $snapshot->id ?? null,
            'rule_basis' => $basis,
        ]);

        return $row->action === self::ACTION_RETURN
            ? $this->replayReturn($row, $snapshot, $override, $decidedBy, $note)
            : $this->replayCheckout($row, $snapshot, $override);
    }

    private function replayCheckout(object $row, ?object $snapshot, array $override): object
    {
        $at = $row->occurred_at;

        $copy = DB::table('library_copy')->where('barcode', $row->copy_barcode)->lockForUpdate()->first();
        if (! $copy) {
            return $this->markConflict($row, 'copy_not_found', []);
        }
        $patron = DB::table('library_patron')->where('card_number', $row->patron_card)->lockForUpdate()->first();
        if (! $patron) {
            return $this->markConflict($row, 'patron_not_found', ['copy_id' => (int) $copy->id]);
        }
        DB::table(self::TXN)->where('id', $row->id)->update(['copy_id' => $copy->id, 'patron_id' => $patron->id]);

        // Any loan still open, or closed only after this moment, means the copy
        // could not have been handed over then.
        $clash = DB::table('library_checkout')->where('copy_id', $copy->id)
            ->where(fn ($w) => $w->whereNull('return_date')->orWhere('return_date', '>', $at))
            ->orderByDesc('id')->first();
        if ($clash) {
            return $this->markConflict($row, 'already_on_loan', ['checkout_id' => (int) $clash->id, 'patron_id' => (int) $clash->patron_id]);
        }

        if ($copy->status !== self::STATUS_AVAILABLE) {
            return $this->markConflict($row, 'copy_unavailable', ['copy_status' => $copy->status]);
        }
        if ($patron->borrowing_status !== 'active' && ! in_array('patron_suspended', $override, true)) {
            return $this->markConflict($row, 'patron_suspended', ['borrowing_status' => $patron->borrowing_status]);
        }

        $openThen = DB::table('library_checkout')->where('patron_id', $patron->id)
            ->where('checkout_date', '<=', $at)
            ->where(fn ($w) => $w->whereNull('return_date')->orWhere('return_date', '>', $at))
            ->count();
        if ($openThen >= (int) $patron->max_checkouts && ! in_array('over_limit', $override, true)) {
            return $this->markConflict($row, 'over_limit', ['open_loans' => $openThen, 'limit' => (int) $patron->max_checkouts]);
        }
        if ((float) $patron->total_fines_owed >= LibrarySettings::patronFineThreshold() && ! in_array('fine_threshold', $override, true)) {
            return $this->markConflict($row, 'fine_threshold', []);
        }

        $item = DB::table('library_item')->where('id', $copy->library_item_id)->first();
        $materialType = $item->material_type ?? 'monograph';
        $branchId = LibraryBranch::resolve(
            $row->branch_id !== null ? (int) $row->branch_id : ($copy->branch_id ?? null),
            null,
            (int) $patron->id
        );

        // Same test as the online checkout, against the rules in force then. The
        // copy has already left the building, so staff can apply it with a note.
        $governing = $this->ruleFromSnapshot($snapshot, $materialType, $patron->patron_type, $branchId, false);
        if ($governing && (int) $governing['is_loanable'] !== 1 && ! in_array('not_loanable', $override, true)) {
            return $this->markConflict($row, 'not_loanable', ['copy_id' => (int) $copy->id]);
        }

        $rule = $this->ruleFromSnapshot($snapshot, $materialType, $patron->patron_type, $branchId, true);
        $days = $rule ? (int) $rule['loan_period_days'] : $this->defaultDaysFromSnapshot($snapshot, $branchId);
        // A loan never falls due on a day the branch is shut, as online.
        $due = app(LibraryCalendarService::class)->nextOpenDay(
            CarbonImmutable::parse($at, 'UTC')->addDays($days)->toDateString(),
            $branchId
        );

        $loan = [
            'copy_id' => $copy->id,
            'patron_id' => $patron->id,
            'checkout_date' => $at,
            'due_date' => $due,
            'renewed_count' => 0,
            'status' => 'active',
            'checkout_notes' => 'Offline desk transaction ' . $row->client_uuid,
            'checked_out_by' => $row->captured_by,
            'created_at' => now(),
        ];
        if ($this->hasBranchColumn('library_checkout')) {
            $loan['branch_id'] = $branchId;
        }
        $checkoutId = DB::table('library_checkout')->insertGetId($loan);

        DB::table('library_copy')->where('id', $copy->id)->update(['status' => self::STATUS_CHECKED_OUT, 'updated_at' => now()]);
        DB::table('library_patron')->where('id', $patron->id)->increment('total_checkouts');

        return $this->markApplied($row, (int) $checkoutId, $due);
    }

    private function replayReturn(object $row, ?object $snapshot, array $override, ?int $decidedBy, ?string $note): object
    {
        $at = $row->occurred_at;

        $copy = DB::table('library_copy')->where('barcode', $row->copy_barcode)->lockForUpdate()->first();
        if (! $copy) {
            return $this->markConflict($row, 'copy_not_found', []);
        }
        DB::table(self::TXN)->where('id', $row->id)->update(['copy_id' => $copy->id]);

        $loan = DB::table('library_checkout')->where('copy_id', $copy->id)->whereIn('status', LibraryCirculationService::OPEN_LOAN_STATUSES)
            ->orderByDesc('id')->lockForUpdate()->first();
        if (! $loan) {
            return $this->markConflict($row, 'no_active_loan', []);
        }
        DB::table(self::TXN)->where('id', $row->id)->update(['patron_id' => $loan->patron_id]);

        if ($loan->checkout_date > $at) {
            return $this->markConflict($row, 'loan_after_return', ['checkout_id' => (int) $loan->id]);
        }

        if ($row->expected_patron_card !== null && $row->expected_patron_card !== '') {
            $holder = DB::table('library_patron')->where('id', $loan->patron_id)->value('card_number');
            if ($holder !== $row->expected_patron_card) {
                return $this->markConflict($row, 'loan_patron_mismatch', ['checkout_id' => (int) $loan->id, 'patron_id' => (int) $loan->patron_id]);
            }
        }

        if (LibrarySettings::autoFine()) {
            [$amount, $days] = $this->fineAsAt($loan, $copy, $at, $snapshot);
            // What was already paid or waived on this loan is not charged again, as online.
            $amount = LibraryFineService::chargeableOverdue((int) $loan->id, (float) $amount);
            $existing = DB::table('library_fine')->where('checkout_id', $loan->id)
                ->where('fine_type', 'overdue')->where('status', 'outstanding')->lockForUpdate()->first();

            // The return's time comes from the desk, so it cannot cut a fine unreviewed.
            if ($existing && ! in_array('fine_reduction', $override, true)
                && round(max($amount, (float) $existing->paid_amount), 2) < round((float) $existing->amount, 2)) {
                return $this->markConflict($row, 'fine_reduction', [
                    'checkout_id' => (int) $loan->id,
                    'fine_id' => (int) $existing->id,
                    'current' => (float) $existing->amount,
                    'proposed' => max($amount, (float) $existing->paid_amount),
                ]);
            }

            $this->settleFine($loan, $existing, $amount, $days, $at, $decidedBy ?? ($row->captured_by !== null ? (int) $row->captured_by : null), $note);
        }

        DB::table('library_checkout')->where('id', $loan->id)->update([
            'status' => 'returned',
            'return_date' => $at,
            'return_notes' => 'Offline desk transaction ' . $row->client_uuid,
            'checked_in_by' => $row->captured_by,
            'updated_at' => now(),
        ]);

        $promoted = $this->promoteNextHold(
            (int) $copy->library_item_id,
            isset($copy->branch_id) && $copy->branch_id !== null ? (int) $copy->branch_id : null
        );
        DB::table('library_copy')->where('id', $copy->id)->update([
            'status' => $promoted ? self::STATUS_ON_HOLD : self::STATUS_AVAILABLE,
            'updated_at' => now(),
        ]);

        return $this->markApplied($row, (int) $loan->id, (string) $loan->due_date);
    }

    /** @return array{0: float, 1: int} The fine as at the return, and the days it covers. */
    private function fineAsAt(object $loan, object $copy, string $at, ?object $snapshot): array
    {
        $patron = DB::table('library_patron')->where('id', $loan->patron_id)->first();
        $item = DB::table('library_item')->where('id', $copy->library_item_id)->first();
        $branchId = $loan->branch_id ?? $copy->branch_id ?? null;
        $rule = $this->ruleFromSnapshot($snapshot, $item->material_type ?? 'monograph', $patron->patron_type, $branchId !== null ? (int) $branchId : null, false);

        // Same defaults as generateOverdueFine().
        $perDay = $rule ? (float) $rule['fine_per_day'] : 1.00;
        $cap = $rule && $rule['fine_cap'] !== null ? (float) $rule['fine_cap'] : null;
        $grace = $rule ? (int) $rule['grace_period_days'] : 0;

        $dueTs = strtotime($loan->due_date . ' 00:00:00 UTC');
        $atTs = strtotime($at . ' UTC');
        $days = app(LibraryCalendarService::class)->chargeableDays(
            substr((string) $loan->due_date, 0, 10),
            substr($at, 0, 10),
            $atTs > $dueTs ? (int) floor(($atTs - $dueTs) / 86400) : 0,
            $grace,
            $branchId !== null ? (int) $branchId : null
        );
        $amount = $days > 0 ? $perDay * $days : 0.0;
        if ($cap !== null && $amount > $cap) {
            $amount = $cap;
        }

        return [$amount, $days];
    }

    /**
     * Record the fine as at the real return. The nightly run may have kept
     * accruing while the desk was offline; a cut to that figure reaches here
     * only after a staff decision, and never goes below what has been paid.
     */
    private function settleFine(object $loan, ?object $existing, float $amount, int $days, string $at, ?int $userId, ?string $note): void
    {
        if ($existing) {
            $paid = (float) $existing->paid_amount;
            if ($amount <= 0 && $paid <= 0) {
                DB::table('library_fine')->where('id', $existing->id)->update([
                    'amount' => 0,
                    'status' => 'waived',
                    'waived_by' => $userId,
                    'waived_date' => now(),
                    'waive_reason' => 'Offline return was made before any fine was due' . ($note ? ' - ' . $note : ''),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('library_fine')->where('id', $existing->id)->update(['amount' => max($amount, $paid), 'updated_at' => now()]);
            }
        } elseif ($amount > 0) {
            $description = (new EncryptionService())->encrypt(
                EncryptionService::CATEGORY_FINANCIAL_DATA,
                "Overdue: {$days} day(s) past due",
                'library_fine',
                'description',
                null
            );
            DB::table('library_fine')->insert([
                'patron_id' => $loan->patron_id,
                'checkout_id' => $loan->id,
                'fine_type' => 'overdue',
                'amount' => $amount,
                'paid_amount' => 0.00,
                'currency' => LibrarySettings::currency(),
                'status' => 'outstanding',
                'description' => $description,
                'fine_date' => substr($at, 0, 10),
                'created_at' => now(),
            ]);
        } else {
            return;
        }

        LibraryFineService::refreshPatronTotals((int) $loan->patron_id);
    }

    /** matchRule() over a recorded version of the rules: same precedence. */
    private function ruleFromSnapshot(?object $snapshot, string $material, string $patronType, ?int $branchId, bool $loanableOnly): ?array
    {
        if (! $snapshot) {
            return null;
        }
        $this->snapshots[$snapshot->id] ??= json_decode($snapshot->rules_json, true) ?: [];
        $exact = $branchId !== null && $branchId !== LibraryBranch::ALL ? $branchId : null;

        $candidates = array_filter($this->snapshots[$snapshot->id], function ($r) use ($material, $patronType, $exact, $loanableOnly) {
            return $r['material_type'] === $material
                && in_array($r['patron_type'], [$patronType, '*'], true)
                && (! $loanableOnly || (int) $r['is_loanable'] === 1)
                && in_array((int) $r['branch_id'], $exact !== null ? [$exact, LibraryBranch::ALL] : [LibraryBranch::ALL], true);
        });
        usort($candidates, fn ($a, $b) => [(int) $a['branch_id'] !== $exact, $a['patron_type'] !== $patronType]
            <=> [(int) $b['branch_id'] !== $exact, $b['patron_type'] !== $patronType]);

        return $candidates[0] ?? null;
    }

    private function defaultDaysFromSnapshot(?object $snapshot, ?int $branchId): int
    {
        $map = $snapshot ? (json_decode($snapshot->default_loan_days_json, true) ?: []) : [];

        return (int) ($map[(string) ($branchId ?? LibraryBranch::ALL)]
            ?? $map[(string) LibraryBranch::ALL]
            ?? LibrarySettings::defaultLoanDays($branchId));
    }

    private function markConflict(object $row, string $code, array $detail): object
    {
        DB::table(self::TXN)->where('id', $row->id)->update([
            'status' => self::CONFLICT,
            'conflict_code' => $code,
            'conflict_detail' => $detail ? json_encode($detail) : null,
            'updated_at' => now(),
        ]);

        return DB::table(self::TXN)->where('id', $row->id)->first();
    }

    private function markApplied(object $row, int $checkoutId, string $due): object
    {
        DB::table(self::TXN)->where('id', $row->id)->update([
            'status' => self::APPLIED,
            'conflict_code' => null,
            'conflict_detail' => null,
            'checkout_id' => $checkoutId,
            'due_date' => $due,
            'updated_at' => now(),
        ]);

        return DB::table(self::TXN)->where('id', $row->id)->first();
    }

    private function result(object $row): array
    {
        return [
            'uuid' => $row->client_uuid,
            'status' => $row->status,
            'conflict_code' => $row->conflict_code,
            'due_date' => $row->due_date,
        ];
    }

    // Conflicts report

    /** Rows visible to this operator, newest first. */
    public function reportQuery(?int $operatorBranch, ?string $status)
    {
        $query = DB::table(self::TXN . ' as t')
            ->leftJoin('library_patron as p', 't.patron_id', '=', 'p.id')
            ->leftJoin('library_copy as cp', 't.copy_id', '=', 'cp.id')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as i18n', 'li.information_object_id', app()->getLocale()))
            ->select('t.*', 'p.first_name', 'p.last_name', I18nFallback::col('i18n', 'title', 'title'));

        if ($operatorBranch !== null) {
            $query->where(fn ($w) => $w->where('t.branch_id', $operatorBranch)->orWhereNull('t.branch_id'));
        }
        if ($status !== null && $status !== '') {
            $query->where('t.status', $status);
        }

        return $query->orderByDesc('t.occurred_at')->orderByDesc('t.id');
    }

    /**
     * Settle one conflict. Returns the row, or null when it is not an open
     * conflict this operator can see. Apply re-runs every check except the one
     * being overridden, so it cannot force a copy that is physically out. A
     * time still in the future stays in conflict whatever is chosen, and a
     * timing or fine conflict is never cleared by a plain re-check.
     */
    public function resolve(int $id, string $resolution, ?string $note, ?int $userId, ?int $operatorBranch): ?object
    {
        return DB::transaction(function () use ($id, $resolution, $note, $userId, $operatorBranch) {
            $query = DB::table(self::TXN)->where('id', $id)->where('status', self::CONFLICT);
            if ($operatorBranch !== null) {
                $query->where(fn ($w) => $w->where('branch_id', $operatorBranch)->orWhereNull('branch_id'));
            }
            $row = $query->lockForUpdate()->first();
            if (! $row) {
                return null;
            }

            $overrides = array_values(array_filter(explode(',', (string) $row->overrides)));

            if ($resolution !== self::RESOLVE_REJECT && $this->isFuture($row)) {
                return $row->conflict_code === 'clock_invalid' ? $row : $this->markConflict($row, 'clock_invalid', []);
            }

            if ($resolution === self::RESOLVE_REJECT) {
                DB::table(self::TXN)->where('id', $id)->update(['status' => self::REJECTED, 'updated_at' => now()]);
            } elseif ($resolution === self::RESOLVE_APPLY) {
                if (! in_array($row->conflict_code, self::OVERRIDABLE, true)) {
                    return $row;
                }
                // Kept, so overriding a second check does not undo the first.
                $overrides = array_values(array_unique([...$overrides, $row->conflict_code]));
                DB::table(self::TXN)->where('id', $id)->update(['overrides' => implode(',', $overrides)]);
                $this->process($row, $overrides, $userId, $note);
            } elseif (in_array($row->conflict_code, self::NEEDS_DECISION, true)) {
                return $row;
            } else {
                $this->process($row, $overrides, $userId, $note);
            }

            if (DB::table(self::TXN)->where('id', $id)->value('status') !== self::CONFLICT) {
                DB::table(self::TXN)->where('id', $id)->update([
                    'resolved_by' => $userId,
                    'resolved_at' => now()->utc()->format('Y-m-d H:i:s'),
                    'resolution' => $resolution,
                    'resolution_note' => $note,
                    'updated_at' => now(),
                ]);
            }

            return DB::table(self::TXN)->where('id', $id)->first();
        });
    }
}
