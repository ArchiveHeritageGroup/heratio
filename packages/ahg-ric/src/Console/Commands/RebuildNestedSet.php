<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Rebuild the MPTT lft/rgt columns on information_object from scratch.
 *
 * When to run:
 *   - After bulk imports (the write API's createRecord uses naive append,
 *     which is safe for single inserts but leaves gaps when parents are
 *     moved or many records are created in mixed order).
 *   - After re-parenting many records.
 *   - Whenever the GLAM browse "ancestor" filter starts returning wrong
 *     descendant sets (symptom of a corrupt nested-set tree).
 *
 * Usage:
 *   php artisan openric:rebuild-nested-set            # live rebuild (wraps in a tx)
 *   php artisan openric:rebuild-nested-set --dry-run  # report what would change
 *   php artisan openric:rebuild-nested-set --verify   # check tree for corruption, no writes
 *
 * Algorithm:
 *   Depth-first walk from the roots (parent_id IS NULL), assigning
 *   lft = counter++ on descent, rgt = counter++ on ascent. This is the
 *   standard MPTT rebuild; O(n) in table rows.
 */

namespace AhgRic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildNestedSet extends Command
{
    protected $signature = 'openric:rebuild-nested-set
        {--dry-run : Report intended changes without writing}
        {--verify  : Check tree for corruption; non-zero exit if broken}
        {--table=information_object : Which table (defaults to information_object)}';

    protected $description = 'Rebuild MPTT lft/rgt columns after bulk imports or re-parenting.';

    public function handle(): int
    {
        $table = (string) $this->option('table');
        if (!in_array($table, ['information_object', 'actor', 'term'])) {
            $this->error("Unsafe table: {$table}");
            return self::FAILURE;
        }

        if ($this->option('verify')) {
            return $this->verify($table);
        }

        $dry = (bool) $this->option('dry-run');
        return $this->rebuild($table, $dry);
    }

    private function verify(string $table): int
    {
        $this->info("Verifying nested-set invariants on {$table}…");
        $issues = 0;

        // Invariant 1: every node has lft < rgt
        $bad = DB::table($table)->whereRaw('lft >= rgt')->count();
        if ($bad) { $this->error("  ✗ {$bad} rows have lft >= rgt"); $issues++; }
        else      { $this->info("  ✓ all rows have lft < rgt"); }

        // Invariant 2: every child's [lft,rgt] is strictly inside its parent's
        $orphans = DB::select("
            SELECT c.id FROM {$table} c
            JOIN {$table} p ON c.parent_id = p.id
            WHERE NOT (c.lft > p.lft AND c.rgt < p.rgt)
            LIMIT 10
        ");
        if ($orphans) {
            $this->error('  ✗ ' . count($orphans) . ' child rows outside parent bounds (first 10: ' . implode(', ', array_column($orphans, 'id')) . ')');
            $issues++;
        } else {
            $this->info('  ✓ all children are inside their parent bounds');
        }

        // Invariant 3: no lft/rgt collisions
        $dupLft = DB::table($table)->select('lft')->groupBy('lft')->havingRaw('COUNT(*) > 1')->count();
        $dupRgt = DB::table($table)->select('rgt')->groupBy('rgt')->havingRaw('COUNT(*) > 1')->count();
        if ($dupLft || $dupRgt) {
            $this->error("  ✗ {$dupLft} duplicate lft values, {$dupRgt} duplicate rgt values");
            $issues++;
        } else {
            $this->info('  ✓ lft/rgt values are unique');
        }

        if ($issues) {
            $this->warn("Tree has {$issues} structural issue(s). Run without --verify to rebuild.");
            return self::FAILURE;
        }
        $this->info('Tree is consistent.');
        return self::SUCCESS;
    }

    private function rebuild(string $table, bool $dry): int
    {
        $total = DB::table($table)->count();
        $this->info("Rebuilding nested-set on {$table} ({$total} rows){$this->tag($dry)}…");

        // Build in-memory parent→children map in one query for O(n) walk.
        $rows = DB::table($table)->select('id', 'parent_id')->get();
        $children = [];
        foreach ($rows as $r) {
            $parent = $r->parent_id ?? 0;  // 0 = virtual root
            $children[$parent][] = (int) $r->id;
        }
        foreach ($children as &$list) sort($list);  // stable ordering by id
        unset($list);

        // Recursive DFS.
        $counter = 1;
        $updates = [];  // id => [lft, rgt]
        $walk = function (int $id) use (&$walk, &$children, &$counter, &$updates) {
            $lft = $counter++;
            foreach (($children[$id] ?? []) as $child) {
                $walk($child);
            }
            $rgt = $counter++;
            $updates[$id] = [$lft, $rgt];
        };
        foreach (($children[0] ?? []) as $rootId) {
            $walk($rootId);
        }

        $this->info('Walked ' . count($updates) . ' reachable rows. Unreachable: ' . ($total - count($updates)) . '.');

        if ($total !== count($updates)) {
            $this->warn('Some rows are unreachable — they have a parent_id pointing at a non-existent or out-of-table id. They will keep their current lft/rgt.');
        }

        if ($dry) {
            $this->info('--dry-run: no writes performed. Sample of first 3 updates:');
            foreach (array_slice($updates, 0, 3, true) as $id => [$l, $r]) {
                $this->line("  id={$id}  lft={$l}  rgt={$r}");
            }
            return self::SUCCESS;
        }

        $this->info('Writing lft/rgt in a single transaction…');
        DB::transaction(function () use ($table, $updates) {
            $bar = $this->output->createProgressBar(count($updates));
            foreach ($updates as $id => [$l, $r]) {
                DB::table($table)->where('id', $id)->update(['lft' => $l, 'rgt' => $r]);
                $bar->advance();
            }
            $bar->finish();
        });
        $this->newLine();
        $this->info('Done. Re-verifying…');
        return $this->verify($table);
    }

    private function tag(bool $dry): string { return $dry ? ' [dry-run]' : ''; }
}
