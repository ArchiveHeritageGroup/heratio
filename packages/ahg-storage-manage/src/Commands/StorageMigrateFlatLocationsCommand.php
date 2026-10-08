<?php

/**
 * StorageMigrateFlatLocationsCommand - place physical objects in the storage
 * location tree from the old flat location fields (heratio#1528, parity with
 * the atom-ahg-plugins storage:migrate-flat-locations task).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3.0 or later. This
 * file is part of Heratio. See <https://www.gnu.org/licenses/> for details.
 */

namespace AhgStorageManage\Commands;

use AhgStorageManage\Services\StorageFlatMigrationService;
use Illuminate\Console\Command;

/**
 * Reports what it would do and changes nothing, unless --apply is given. See
 * StorageFlatMigrationService for what is read, in what order, and what is
 * deliberately left alone.
 *
 *   php artisan ahg:storage-migrate-flat-locations
 *   php artisan ahg:storage-migrate-flat-locations --apply
 *   php artisan ahg:storage-migrate-flat-locations --free-text-type=room --apply
 */
class StorageMigrateFlatLocationsCommand extends Command
{
    protected $signature = 'ahg:storage-migrate-flat-locations
        {--apply : Write the changes. Without this, only report}
        {--free-text-type= : File objects that have only a free-text location under a root location of this type}
        {--culture=en : Culture to read object names and free text in}';

    protected $description = 'Place physical objects in the storage location tree from the old flat location fields (dry run unless --apply)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $service = new StorageFlatMigrationService((string) ($this->option('culture') ?: 'en'));

        try {
            $report = $service->run($apply, array_filter(['free_text_type' => $this->option('free-text-type') ?: null]));
        } catch (\Throwable $e) {
            $this->error('Nothing was changed: '.$e->getMessage());

            return self::FAILURE;
        }

        $verb = $apply ? 'created' : 'would be created';

        foreach ($report['locations_created'] as $label) {
            $this->line('location  '.$label.' - '.$verb);
        }
        foreach ($report['placed'] as $row) {
            $this->line(sprintf('object    %s (%d) -> %s, from %s', $row['name'], $row['object_id'], $row['path'], $row['source']));
        }
        foreach ($report['skipped'] as $row) {
            $this->line(sprintf('skipped   %s (%d): %s', $row['name'], $row['object_id'], $row['reason']));
        }

        $this->info(sprintf(
            '%s: %d location(s) %s, %d reused, %d object(s) %s, %d left alone.',
            $apply ? 'Done' : 'Dry run, nothing changed',
            count($report['locations_created']),
            $verb,
            count($report['locations_reused']),
            count($report['placed']),
            $apply ? 'placed' : 'would be placed',
            count($report['skipped'])
        ));

        if (! $apply) {
            $this->line('Run again with --apply to write this.');
        }

        return self::SUCCESS;
    }
}
