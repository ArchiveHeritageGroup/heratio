<?php

/**
 * RestoreInformationObjectCommand - granular restore for a single information_object
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgBackup\Console\Commands;

use AhgBackup\Services\GranularRestoreService;
use Illuminate\Console\Command;

/**
 * Restore a single information_object (and its i18n rows) from a full
 * mysqldump backup without rewinding the rest of the database.
 *
 *   php artisan backup:restore-io 42 /mnt/nas/heratio/backups/database_heratio_2026-05-25_020000.sql.gz
 *
 * Wraps `GranularRestoreService` and runs the restore inside a
 * transaction. See docs/help/backup-granular-restore.md for the
 * referential-integrity caveats.
 *
 * Issue #671 Phase 4.
 */
class RestoreInformationObjectCommand extends Command
{
    protected $signature = 'backup:restore-io
                            {id : information_object.id to restore}
                            {backup : Absolute path to .sql.gz full backup}
                            {--yes : Skip the interactive confirmation prompt}';

    protected $description = 'Restore a single archival description (information_object) from a full backup (#671 Phase 4).';

    public function handle(GranularRestoreService $service): int
    {
        $id = (int) $this->argument('id');
        $backup = (string) $this->argument('backup');

        if ($id <= 0) {
            $this->error('information_object id must be a positive integer.');
            return self::FAILURE;
        }
        if (!is_file($backup)) {
            $this->error("Backup file not found: {$backup}");
            return self::FAILURE;
        }

        $this->warn('Granular restore can break referential integrity. Verify in dev first.');
        $this->line("  io_id  : {$id}");
        $this->line("  backup : {$backup}");

        if (!$this->option('yes') && !$this->confirm('Proceed with restore?', false)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        try {
            $result = $service->restoreInformationObject($id, $backup);
        } catch (\Throwable $e) {
            $this->error('Restore failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Granular restore complete.');
        foreach ($result['tables'] as $table => $count) {
            $this->line(sprintf('  %-32s %d row(s) applied', $table, $count));
        }
        $this->line(sprintf('  total statements applied: %d', $result['statements']));

        if ($result['statements'] === 0) {
            $this->warn('No matching rows found in backup. The IO may not have existed at backup time.');
        }
        return self::SUCCESS;
    }
}
