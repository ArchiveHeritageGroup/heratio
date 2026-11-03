<?php

/**
 * #1473 follow-up - drop the legacy free-text branch columns
 * (library_copy.branch, library_hold.pickup_branch) now that branch_id carries
 * the branch.
 *
 * Self-gating, per table: a column is dropped only when no row still has text
 * in it without a branch_id, which is exactly what
 * `php artisan ahg:library-branch-unmatched` lists. An install with unmatched
 * names keeps its column and logs a warning; once the rows are reconciled,
 * `php artisan ahg:library-branch-unmatched --drop` finishes the job.
 *
 * down() puts the columns back empty. The text is not recoverable from here,
 * which is why the drop waits for every name to be matched.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** table => legacy text column */
    private const LEGACY = ['library_copy' => 'branch', 'library_hold' => 'pickup_branch'];

    public function up(): void
    {
        foreach (self::LEGACY as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }
            $unmatched = DB::table($table)->whereNull('branch_id')->whereNotNull($column)->where($column, '<>', '')->count();
            if ($unmatched > 0) {
                Log::warning("[ahg-library] kept {$table}.{$column}: {$unmatched} row(s) have a branch name but no branch_id. Run ahg:library-branch-unmatched.");

                continue;
            }
            // A single-column index goes with its column.
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
        }
    }

    public function down(): void
    {
        foreach (self::LEGACY as $table => $column) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->string($column, 100)->nullable());
            }
        }
    }
};
