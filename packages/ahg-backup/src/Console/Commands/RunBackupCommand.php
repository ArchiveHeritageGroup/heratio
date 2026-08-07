<?php

/**
 * RunBackupCommand - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 */

namespace AhgBackup\Console\Commands;

use AhgBackup\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Create a backup HEADLESSLY - no php-fpm request timeout. The web UI backup
 * (BackupController::create) runs mysqldump + tar synchronously inside a web
 * request, so a real-world uploads/content directory (tens of GB) blows the
 * request_terminate_timeout and returns a non-JSON error ("JSON error") while
 * the content never finishes archiving. Run this from cron or by hand instead.
 *
 *   php artisan backup:run                         # all four components
 *   php artisan backup:run --components=database,uploads
 */
class RunBackupCommand extends Command
{
    protected $signature = 'backup:run
        {--components=database,uploads,plugins,framework : Comma-separated: database,uploads,plugins,framework}';

    protected $description = 'Create a backup headlessly (mysqldump + per-component tar), with no web-request timeout.';

    public function handle(BackupService $service): int
    {
        $valid = ['database', 'uploads', 'plugins', 'framework'];
        $components = array_values(array_intersect(
            array_map('trim', explode(',', (string) $this->option('components'))),
            $valid
        ));

        if (empty($components)) {
            $this->error('No valid components. Use --components=database,uploads,plugins,framework');

            return self::FAILURE;
        }

        $this->info('Backup starting: '.implode(', ', $components).' -> '.$service->backupPath());
        $result = $service->run($components, fn ($line) => $this->line('  '.$line));

        foreach ($result['files'] as $f) {
            $this->info("  OK    {$f['component']}: {$f['filename']} ({$f['size']})");
        }
        foreach ($result['errors'] as $e) {
            $this->error("  FAIL  {$e}");
        }

        $this->line(sprintf(
            'Finished in %.1fs, %d file(s), %.1f MB total.',
            $result['duration_ms'] / 1000,
            count($result['files']),
            $result['total_bytes'] / 1048576
        ));

        // Non-zero exit if ANY requested component failed, so cron/monitoring
        // surfaces a partial backup instead of a false "success".
        return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
