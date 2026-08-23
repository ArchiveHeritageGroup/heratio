<?php

/**
 * Add a branch axis to circulation - #1473.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Circulation had no concept of a branch: loan rules, patrons, holds and
 * transactions were system-wide singletons, and the only two branch columns
 * that existed (library_copy.branch, library_hold.pickup_branch) were free
 * text that nothing wrote and nothing could join to.
 *
 * Branch identity is a `repository` row, per the SLIMS/Brocade evaluation's
 * foundational decision to model outlets as repositories inside one shared
 * instance rather than one instance per branch. That reuses the tenancy
 * substrate the catalogue side already uses via information_object.repository_id.
 *
 * Two different NULL/sentinel conventions here, deliberately:
 *
 *   library_loan_rule.branch_id  NOT NULL DEFAULT 0, where 0 means "applies to
 *   every branch". A sentinel rather than NULL because this column is part of
 *   a UNIQUE key, and MySQL treats NULLs as distinct - a nullable column would
 *   permit unlimited duplicate system-default rules for the same
 *   (material_type, patron_type) pair, which is the exact ambiguity the
 *   existing uk_type_patron key was there to prevent. It also mirrors the
 *   wildcard idiom the table already uses for patron_type ('*' = all types).
 *
 *   Everywhere else  NULL, meaning "not attributed to a branch". Resolution
 *   falls back to the configured default at read time. Nothing here invents a
 *   branch for a row that never had one.
 *
 * The legacy free-text columns are NOT dropped. The backfill below matches
 * them against repository names, and a name that does not match leaves the
 * text in place for an operator to reconcile. Dropping the only copy of that
 * data in the same migration that adds its replacement would make a partial
 * match unrecoverable.
 */
return new class extends Migration
{
    /** Tables that gain a nullable branch_id, and the column it sits after. */
    private const NULLABLE_BRANCH = [
        'library_copy'     => 'branch',
        'library_patron'   => 'patron_type',
        'library_hold'     => 'pickup_branch',
        'library_checkout' => 'patron_id',
    ];

    public function up(): void
    {
        $this->addLoanRuleBranch();

        foreach (self::NULLABLE_BRANCH as $table => $after) {
            $this->addNullableBranch($table, $after);
        }

        $this->backfill();
    }

    /**
     * library_loan_rule gains the branch axis and its unique key is widened.
     * Existing rows take branch_id = 0, so every current rule keeps applying
     * everywhere and a single-outlet installation behaves identically.
     */
    private function addLoanRuleBranch(): void
    {
        if (! Schema::hasTable('library_loan_rule')) {
            return;
        }

        if (! Schema::hasColumn('library_loan_rule', 'branch_id')) {
            Schema::table('library_loan_rule', function (Blueprint $table) {
                $table->integer('branch_id')->default(0)->after('id')
                    ->comment('repository.id, or 0 = applies to all branches');
                $table->index('branch_id', 'idx_loan_rule_branch');
            });
        }

        // Widen the uniqueness to include the branch. Guarded on both sides:
        // a re-run, or an instance whose key was already replaced, must not
        // fail on a missing or duplicate index.
        if ($this->hasIndex('library_loan_rule', 'uk_type_patron')) {
            Schema::table('library_loan_rule', function (Blueprint $table) {
                $table->dropUnique('uk_type_patron');
            });
        }

        if (! $this->hasIndex('library_loan_rule', 'uk_branch_type_patron')) {
            Schema::table('library_loan_rule', function (Blueprint $table) {
                $table->unique(['branch_id', 'material_type', 'patron_type'], 'uk_branch_type_patron');
            });
        }
    }

    private function addNullableBranch(string $table, string $after): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
            return;
        }

        // `after` targets a column the original migration created; if a given
        // instance lacks it, append rather than fail.
        $position = Schema::hasColumn($table, $after) ? $after : null;

        Schema::table($table, function (Blueprint $t) use ($table, $position) {
            $column = $t->integer('branch_id')->nullable()
                ->comment('repository.id - the branch this row belongs to');
            if ($position !== null) {
                $column->after($position);
            }
            $t->index('branch_id', 'idx_' . $this->shortName($table) . '_branch_id');
        });
    }

    /**
     * Attribute existing rows to a branch from evidence already in the data -
     * the free-text branch names, and for a checkout the branch of the copy it
     * lent. Rows with no evidence are left NULL rather than assigned a guess.
     */
    private function backfill(): void
    {
        if (Schema::hasTable('library_copy') && Schema::hasColumn('library_copy', 'branch')) {
            $this->matchNameToRepository('library_copy', 'branch');
        }

        if (Schema::hasTable('library_hold') && Schema::hasColumn('library_hold', 'pickup_branch')) {
            $this->matchNameToRepository('library_hold', 'pickup_branch');
        }

        // A checkout happened where the copy lives. This is the only branch
        // attribution available for historical transactions, and it is the
        // right one for a single-outlet history.
        if (Schema::hasTable('library_checkout') && Schema::hasTable('library_copy')
            && Schema::hasColumn('library_checkout', 'branch_id')
            && Schema::hasColumn('library_copy', 'branch_id')) {
            $rows = DB::update(
                'UPDATE library_checkout c
                    JOIN library_copy cp ON cp.id = c.copy_id
                     SET c.branch_id = cp.branch_id
                   WHERE c.branch_id IS NULL AND cp.branch_id IS NOT NULL'
            );
            Log::info("#1473 backfill: library_checkout.branch_id set from the copy on {$rows} row(s).");
        }
    }

    /**
     * Resolve a free-text branch name to a repository id by authorised name.
     * Deterministic: lowest repository id wins if two repositories somehow
     * carry the same name, rather than letting the join pick arbitrarily.
     */
    private function matchNameToRepository(string $table, string $textColumn): void
    {
        if (! Schema::hasTable('repository') || ! Schema::hasTable('actor_i18n')) {
            return;
        }

        $total = DB::table($table)
            ->whereNull('branch_id')
            ->whereNotNull($textColumn)
            ->whereRaw("TRIM({$textColumn}) <> ''")
            ->count();

        if ($total === 0) {
            return;
        }

        DB::update(
            "UPDATE {$table} t
                SET t.branch_id = (
                    SELECT r.id
                      FROM repository r
                      JOIN actor_i18n ai ON ai.id = r.id
                     WHERE TRIM(LOWER(ai.authorized_form_of_name)) = TRIM(LOWER(t.{$textColumn}))
                     ORDER BY r.id
                     LIMIT 1
                )
              WHERE t.branch_id IS NULL
                AND t.{$textColumn} IS NOT NULL
                AND TRIM(t.{$textColumn}) <> ''"
        );

        // The UPDATE also "touches" rows whose subquery returned NULL, so its
        // affected-row count overstates the match. Re-count what is still
        // unresolved instead.
        $unmatched = DB::table($table)
            ->whereNull('branch_id')
            ->whereNotNull($textColumn)
            ->whereRaw("TRIM({$textColumn}) <> ''")
            ->count();

        Log::info("#1473 backfill: {$table}.{$textColumn} -> branch_id, {$total} row(s) carried a branch name, "
            . ($total - $unmatched) . " matched a repository, {$unmatched} did not "
            . '(their text is left in place for reconciliation).');
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    /** Index names are capped at 64 chars; keep them short and stable. */
    private function shortName(string $table): string
    {
        return str_replace('library_', '', $table);
    }

    public function down(): void
    {
        foreach (array_keys(self::NULLABLE_BRANCH) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex('idx_' . $this->shortName($table) . '_branch_id');
                    $t->dropColumn('branch_id');
                });
            }
        }

        if (! Schema::hasTable('library_loan_rule')) {
            return;
        }

        if ($this->hasIndex('library_loan_rule', 'uk_branch_type_patron')) {
            Schema::table('library_loan_rule', function (Blueprint $table) {
                $table->dropUnique('uk_branch_type_patron');
            });
        }

        if (Schema::hasColumn('library_loan_rule', 'branch_id')) {
            // Collapsing the branch axis can leave duplicate (material, patron)
            // pairs that the narrower key would reject. Keep the lowest id of
            // each pair, which is the rule that predated the branch axis.
            DB::statement(
                'DELETE r FROM library_loan_rule r
                   JOIN library_loan_rule keep
                     ON keep.material_type = r.material_type
                    AND keep.patron_type = r.patron_type
                    AND keep.id < r.id'
            );
            Schema::table('library_loan_rule', function (Blueprint $table) {
                $table->dropIndex('idx_loan_rule_branch');
                $table->dropColumn('branch_id');
            });
        }

        if (! $this->hasIndex('library_loan_rule', 'uk_type_patron')) {
            Schema::table('library_loan_rule', function (Blueprint $table) {
                $table->unique(['material_type', 'patron_type'], 'uk_type_patron');
            });
        }
    }
};
