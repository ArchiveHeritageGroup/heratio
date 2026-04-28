<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NestedSetRebuildCommand extends Command
{
    protected $signature = 'ahg:nested-set-rebuild
        {--model=information_object : Table to rebuild lft/rgt for (information_object or term)}
        {--connection=atom : Source DB connection}';

    protected $description = 'Rebuild MPTT lft/rgt nested-set values via parent_id traversal';

    public function handle(): int
    {
        $table = (string) $this->option('model');
        if (! in_array($table, ['information_object', 'term'], true)) {
            $this->error("Refusing to rebuild '{$table}' — only information_object and term are supported.");
            return self::FAILURE;
        }
        $conn = (string) $this->option('connection');
        $db = DB::connection($conn);

        $rows = $db->table($table)->select('id', 'parent_id')->orderBy('id')->get();
        $children = [];
        foreach ($rows as $r) {
            $pid = (int) ($r->parent_id ?? 0);
            $children[$pid][] = (int) $r->id;
        }
        $this->info("loaded " . $rows->count() . " {$table} rows");

        $counter = 0; $bounds = [];
        $walk = function (int $id) use (&$walk, &$counter, &$bounds, $children) {
            $counter++; $left = $counter;
            foreach ($children[$id] ?? [] as $childId) $walk($childId);
            $counter++; $right = $counter;
            $bounds[$id] = [$left, $right];
        };

        $roots = $children[0] ?? $children[1] ?? [];
        foreach ($roots as $root) $walk($root);
        $this->info("walked " . count($bounds) . " rows; max counter={$counter}");

        $written = 0;
        foreach (array_chunk($bounds, 1000, true) as $chunk) {
            foreach ($chunk as $id => [$l, $r]) {
                $db->table($table)->where('id', $id)->update(['lft' => $l, 'rgt' => $r]);
                $written++;
            }
            $this->line("  written {$written}/" . count($bounds));
        }
        $this->info("done");
        return self::SUCCESS;
    }
}
