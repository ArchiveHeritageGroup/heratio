<?php

/**
 * BranchUnmatchedCommand - list the legacy free-text branch names the #1473
 * backfill could not match to a repository, so an operator can reconcile them
 * before the old library_copy.branch and library_hold.pickup_branch columns
 * are dropped. Reads only, unless --drop is given: then each column with
 * nothing left unmatched is dropped (the 2026_11_03 migration does the same
 * on deploy; --drop is for an install that reconciled its rows afterwards).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BranchUnmatchedCommand extends Command
{
    protected $signature = 'ahg:library-branch-unmatched {--drop : Drop each legacy column that has nothing left unmatched}';

    protected $description = 'List legacy branch names on copies and holds that have no branch yet (reads only unless --drop)';

    /** table => legacy text column */
    private const LEGACY = ['library_copy' => 'branch', 'library_hold' => 'pickup_branch'];

    public function handle(): int
    {
        $rows = [];
        $present = [];
        foreach (self::LEGACY as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }
            $present[$table] = $column;
            foreach (DB::table($table)->whereNull('branch_id')->whereNotNull($column)->where($column, '<>', '')
                ->groupBy($column)->orderByDesc(DB::raw('COUNT(*)'))->get([$column.' as name', DB::raw('COUNT(*) as n')]) as $r) {
                $rows[] = [$table, $r->name, $r->n];
                unset($present[$table]);
            }
        }

        if ($rows !== []) {
            $this->table(['Table', 'Legacy branch name', 'Rows'], $rows);
            $this->line('Set these rows\' branch_id (or rename the branch to match a repository), then run this again.');
        }

        if ($present === []) {
            if ($rows === []) {
                $this->info('No legacy branch columns are left.');
            }

            return self::SUCCESS;
        }

        if (! $this->option('drop')) {
            $this->info('Nothing unmatched in: '.implode(', ', array_map(fn ($t, $c) => "{$t}.{$c}", array_keys($present), $present)).'. Run with --drop to remove them.');

            return self::SUCCESS;
        }

        foreach ($present as $table => $column) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            $this->info("Dropped {$table}.{$column}.");
        }

        return self::SUCCESS;
    }
}
