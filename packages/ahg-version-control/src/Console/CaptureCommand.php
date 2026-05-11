<?php

/**
 * php artisan ahg:version-capture --entity=information_object --id=N [--summary="..."] [--user-id=N]
 *
 * Combines SnapshotBuilder + VersionWriter into a single CLI invocation.
 * Mirrors the symfony task at
 *   atom-ahg-plugins/ahgVersionControlPlugin/lib/task/versionCaptureTask.class.php
 *
 * @phase C
 */

namespace AhgVersionControl\Console;

use AhgVersionControl\Services\SnapshotBuilder;
use AhgVersionControl\Services\VersionWriter;
use Illuminate\Console\Command;

class CaptureCommand extends Command
{
    protected $signature = 'ahg:version-capture {--entity=information_object} {--id=} {--summary=} {--user-id=}';
    protected $description = 'Build snapshot + write as the next version for an entity (smoke/backfill)';

    public function handle(SnapshotBuilder $builder, VersionWriter $writer): int
    {
        $entity = (string) $this->option('entity');
        $id = (int) $this->option('id');
        if ($id <= 0) {
            $this->error('--id is required and must be > 0');
            return self::FAILURE;
        }

        $snapshot = match ($entity) {
            'information_object' => $builder->buildForInformationObject($id),
            'actor'              => $builder->buildForActor($id),
            default              => null,
        };
        if ($snapshot === null) {
            $this->error("Unknown entity: {$entity}");
            return self::FAILURE;
        }

        $version = $writer->write(
            entityType: $entity,
            entityId: $id,
            snapshot: $snapshot,
            changeSummary: $this->option('summary') ?: null,
            userId: $this->option('user-id') !== null ? (int) $this->option('user-id') : null,
        );

        $this->line("version_number={$version} entity_type={$entity} entity_id={$id}");
        return self::SUCCESS;
    }
}
