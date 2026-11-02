<?php

/**
 * BranchUnmatchedCommand - list the legacy free-text branch names the #1473
 * backfill could not match to a repository, so an operator can reconcile them
 * before the old library_copy.branch and library_hold.pickup_branch columns
 * are dropped. Reads only.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BranchUnmatchedCommand extends Command
{
    protected $signature = 'ahg:library-branch-unmatched';

    protected $description = 'List legacy branch names on copies and holds that have no branch yet (reads only)';

    /** table => legacy text column */
    private const LEGACY = ['library_copy' => 'branch', 'library_hold' => 'pickup_branch'];

    public function handle(): int
    {
        $rows = [];
        foreach (self::LEGACY as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }
            foreach (DB::table($table)->whereNull('branch_id')->whereNotNull($column)->where($column, '<>', '')
                ->groupBy($column)->orderByDesc(DB::raw('COUNT(*)'))->get([$column.' as name', DB::raw('COUNT(*) as n')]) as $r) {
                $rows[] = [$table, $r->name, $r->n];
            }
        }

        if ($rows === []) {
            $this->info('Every legacy branch name is matched (or none was entered). The old text columns can be dropped.');

            return self::SUCCESS;
        }
        $this->table(['Table', 'Legacy branch name', 'Rows'], $rows);
        $this->line('Set these rows\' branch_id (or rename the branch to match a repository), then run this again.');

        return self::SUCCESS;
    }
}
