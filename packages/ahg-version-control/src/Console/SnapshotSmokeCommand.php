<?php

/**
 * php artisan ahg:version-snapshot --entity=information_object --id=N
 *
 * Smoke-test wrapper around SnapshotBuilder. Mirrors the symfony task on the
 * AtoM side at atom-ahg-plugins/ahgVersionControlPlugin/lib/task/versionSnapshotTask.class.php
 *
 * @phase B
 */

namespace AhgVersionControl\Console;

use AhgVersionControl\Services\SnapshotBuilder;
use Illuminate\Console\Command;

class SnapshotSmokeCommand extends Command
{
    protected $signature = 'ahg:version-snapshot {--entity=information_object : information_object | actor} {--id= : Entity primary key} {--pretty : Pretty-print JSON}';
    protected $description = 'Print a SnapshotBuilder JSON snapshot for an entity (smoke test)';

    public function handle(): int
    {
        $entity = (string) $this->option('entity');
        $id = (int) $this->option('id');
        if ($id <= 0) {
            $this->error('--id is required and must be > 0');
            return self::FAILURE;
        }

        $builder = new SnapshotBuilder();
        $snapshot = match ($entity) {
            'information_object' => $builder->buildForInformationObject($id),
            'actor'              => $builder->buildForActor($id),
            default              => null,
        };
        if ($snapshot === null) {
            $this->error("Unknown entity: {$entity}");
            return self::FAILURE;
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }
        $this->line(json_encode($snapshot, $flags));
        return self::SUCCESS;
    }
}
