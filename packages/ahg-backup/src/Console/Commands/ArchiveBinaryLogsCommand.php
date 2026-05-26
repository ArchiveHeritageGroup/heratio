<?php

/**
 * ArchiveBinaryLogsCommand - rotate + archive MySQL binary logs
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

use AhgBackup\Services\BinaryLogArchiver;
use Illuminate\Console\Command;

/**
 * Hourly-cron candidate. Flushes the active binary log and copies all
 * now-closed binlog files into `storage/backups/binlogs/`. The Phase 3
 * off-site replicator picks them up on its next sweep.
 *
 *   php artisan backup:archive-binlogs
 *   php artisan backup:archive-binlogs --dest=/mnt/nas/heratio/binlogs
 *
 * Issue #671 Phase 4.
 */
class ArchiveBinaryLogsCommand extends Command
{
    protected $signature = 'backup:archive-binlogs
                            {--dest= : Override the destination directory (default: <backups>/binlogs)}';

    protected $description = 'Rotate and archive MySQL binary logs for point-in-time recovery (#671 Phase 4).';

    public function handle(BinaryLogArchiver $archiver): int
    {
        $dest = (string) ($this->option('dest') ?: $archiver->defaultBinlogDir());

        $this->info("Archiving rotated binary logs to: {$dest}");

        try {
            $files = $archiver->archiveRotatedLogs($dest);
        } catch (\Throwable $e) {
            $this->error('Archive failed: '.$e->getMessage());
            return self::FAILURE;
        }

        if (empty($files)) {
            $this->warn('No new binary log files archived. Confirm `log_bin=ON` and `binlog_format=ROW` on the MySQL server.');
            return self::SUCCESS;
        }

        foreach ($files as $f) {
            $this->line('  + '.$f);
        }
        $this->info('Archived '.count($files).' binary log file(s).');

        return self::SUCCESS;
    }
}
