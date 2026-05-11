<?php

/**
 * php artisan ahg:version-diff --entity=information_object --id=N --v1=A --v2=B
 *
 * Loads two stored snapshots and prints the structured diff.
 *
 * @phase E
 */

namespace AhgVersionControl\Console;

use AhgVersionControl\Services\DiffComputer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiffCommand extends Command
{
    protected $signature = 'ahg:version-diff {--entity=information_object} {--id=} {--v1=} {--v2=} {--pretty}';
    protected $description = 'Print a structured diff between two stored versions';

    public function handle(DiffComputer $computer): int
    {
        $entity = (string) $this->option('entity');
        $id = (int) $this->option('id');
        $v1 = (int) $this->option('v1');
        $v2 = (int) $this->option('v2');
        if ($id <= 0 || $v1 <= 0 || $v2 <= 0) {
            $this->error('--id, --v1, --v2 are required and must be > 0');
            return self::FAILURE;
        }
        $tableMap = [
            'information_object' => ['table' => 'information_object_version', 'fk' => 'information_object_id'],
            'actor'              => ['table' => 'actor_version',              'fk' => 'actor_id'],
        ];
        if (!isset($tableMap[$entity])) {
            $this->error("Unknown entity: {$entity}");
            return self::FAILURE;
        }
        $table = $tableMap[$entity]['table'];
        $fk = $tableMap[$entity]['fk'];

        $snap1 = DB::table($table)->where($fk, $id)->where('version_number', $v1)->value('snapshot');
        $snap2 = DB::table($table)->where($fk, $id)->where('version_number', $v2)->value('snapshot');
        if (!is_string($snap1)) {
            $this->error("Version {$v1} not found for {$entity} {$id}");
            return self::FAILURE;
        }
        if (!is_string($snap2)) {
            $this->error("Version {$v2} not found for {$entity} {$id}");
            return self::FAILURE;
        }
        $diff = $computer->diff(json_decode($snap1, true) ?? [], json_decode($snap2, true) ?? []);

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }
        $this->line(json_encode($diff, $flags));
        return self::SUCCESS;
    }
}
