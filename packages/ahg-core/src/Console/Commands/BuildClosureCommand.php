<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Build / rebuild the closure tables from parent_id (heratio#1333).
 *
 * Set-based, depth-by-depth construction (NOT per-record CTE): seed the
 * self-reference rows (X, X, 0), then repeatedly extend every depth-d path by
 * one parent->child edge to produce the depth-(d+1) rows, until a level adds
 * nothing. This is O(edges) in a handful of bulk INSERTs and runs in minutes on
 * the 322k atom/ANC instance, versus hours for a per-record build.
 *
 * lft/rgt are left untouched (still authoritative); this only (re)derives the
 * closure tables, which are regenerable at any time from parent_id.
 *
 * Usage:
 *   php artisan ahg:build-closure                         # information_object
 *   php artisan ahg:build-closure --table=term
 *   php artisan ahg:build-closure --all                   # all three
 *   php artisan ahg:build-closure --verify                # parity check vs nested set, no writes
 *   php artisan ahg:build-closure --dry-run               # report intended counts only
 */
class BuildClosureCommand extends Command
{
    protected $signature = 'ahg:build-closure
        {--table=information_object : Base table (information_object|term|menu)}
        {--all : Build all three closure tables}
        {--verify : Verify closure vs the existing nested set; no writes}
        {--dry-run : Report what would be built without writing}';

    protected $description = 'Build/rebuild hierarchy closure tables from parent_id (heratio#1333).';

    /** Base table => closure table. */
    private const TABLES = [
        'information_object' => 'information_object_closure',
        'term'               => 'term_closure',
        'menu'               => 'menu_closure',
    ];

    public function handle(): int
    {
        $tables = $this->option('all')
            ? array_keys(self::TABLES)
            : [(string) $this->option('table')];

        foreach ($tables as $table) {
            if (! isset(self::TABLES[$table])) {
                $this->error("Unknown/unsafe table: {$table}");

                return self::FAILURE;
            }
            $rc = $this->option('verify')
                ? $this->verify($table)
                : $this->build($table, (bool) $this->option('dry-run'));
            if ($rc !== self::SUCCESS) {
                return $rc;
            }
        }

        return self::SUCCESS;
    }

    private function build(string $base, bool $dry): int
    {
        $closure = self::TABLES[$base];
        if (! Schema::hasTable($closure)) {
            $this->error("Closure table {$closure} does not exist - run the installer first.");

            return self::FAILURE;
        }

        $nodes = (int) DB::table($base)->count();
        $this->info("[$base] {$nodes} nodes -> {$closure}");

        if ($dry) {
            $directEdges = (int) DB::table($base)->whereNotNull('parent_id')
                ->whereIn('parent_id', function ($q) use ($base) {
                    $q->select('id')->from($base);
                })->count();
            $this->line("  dry-run: would seed {$nodes} self rows + build the transitive closure over {$directEdges} parent->child edges.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($base, $closure, $nodes) {
            // Reset (FKs ON DELETE CASCADE are between closure and base, not
            // self-referential, so a plain DELETE is safe and avoids TRUNCATE's
            // implicit commit inside the transaction).
            DB::table($closure)->delete();

            // Depth 0: self rows for every node.
            DB::statement("INSERT INTO `{$closure}` (ancestor, descendant, depth)
                SELECT id, id, 0 FROM `{$base}`");

            // Iteratively extend depth d -> d+1 via one parent->child edge.
            $depth = 0;
            $totalLevels = 0;
            do {
                $added = DB::affectingStatement(
                    "INSERT IGNORE INTO `{$closure}` (ancestor, descendant, depth)
                     SELECT c.ancestor, ch.id, c.depth + 1
                     FROM `{$closure}` c
                     JOIN `{$base}` ch ON ch.parent_id = c.descendant
                     WHERE c.depth = ?",
                    [$depth]
                );
                $depth++;
                $totalLevels = $depth;
            } while ($added > 0);

            $edges = (int) DB::table($closure)->count();
            $this->info("  built {$edges} closure rows across {$totalLevels} depth level(s).");

            // Sibling order: replace lft-as-ordering with the ahg_ sidecar.
            // Seed it from the current lft order within each parent group.
            if (Schema::hasTable('ahg_node_sibling_order')) {
                DB::table('ahg_node_sibling_order')->where('entity', $base)->delete();
                DB::statement(
                    "INSERT INTO `ahg_node_sibling_order` (entity, node_id, parent_id, sibling_order)
                     SELECT ?, id, parent_id, (ROW_NUMBER() OVER (PARTITION BY parent_id ORDER BY lft, id)) - 1
                     FROM `{$base}`",
                    [$base]
                );
                $this->info('  seeded sibling order for '.$base.'.');
            }
        });

        return self::SUCCESS;
    }

    private function verify(string $base): int
    {
        $closure = self::TABLES[$base];
        if (! Schema::hasTable($closure)) {
            $this->error("Closure table {$closure} does not exist.");

            return self::FAILURE;
        }

        $nodes = (int) DB::table($base)->count();
        $selfRows = (int) DB::table($closure)->where('depth', 0)->count();
        $this->info("[$base] nodes={$nodes} self-rows={$selfRows}");
        if ($nodes !== $selfRows) {
            $this->error("  MISMATCH: every node must have exactly one (X,X,0) self row.");

            return self::FAILURE;
        }

        // Nested-set parity (only where lft/rgt are populated): the count of a
        // node's descendants from the closure (depth>0) must equal the nested
        // set's subtree width (rgt - lft - 1) / 2.
        $hasNested = Schema::hasColumn($base, 'lft') && Schema::hasColumn($base, 'rgt');
        if ($hasNested) {
            $sample = DB::table($base)
                ->whereNotNull('lft')->whereNotNull('rgt')
                ->where('rgt', '>', DB::raw('lft + 1'))
                ->select('id', 'lft', 'rgt')
                ->limit(2000)
                ->get();
            $mismatch = 0;
            $checked = 0;
            foreach ($sample as $n) {
                $expected = (int) (($n->rgt - $n->lft - 1) / 2);
                $actual = (int) DB::table($closure)->where('ancestor', $n->id)->where('depth', '>', 0)->count();
                $checked++;
                if ($expected !== $actual) {
                    $mismatch++;
                    if ($mismatch <= 10) {
                        $this->warn("  node {$n->id}: nested-set subtree={$expected} vs closure descendants={$actual}");
                    }
                }
            }
            $this->info("  nested-set parity: checked {$checked} branch nodes, {$mismatch} mismatch(es).");
            if ($mismatch > 0) {
                return self::FAILURE;
            }
        } else {
            $this->line("  (no lft/rgt on {$base}; skipped nested-set parity check.)");
        }

        $this->info('  closure verified OK.');

        return self::SUCCESS;
    }
}
