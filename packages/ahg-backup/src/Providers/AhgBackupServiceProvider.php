<?php

/**
 * AhgBackupServiceProvider - service provider for ahg-backup
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

namespace AhgBackup\Providers;

use AhgBackup\Console\Commands\ReplicateBackupCommand;
use AhgBackup\Console\Commands\VerifyBackupIntegrityCommand;
use AhgBackup\Services\OffsiteReplicator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgBackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/backup.php', 'backup');
        $this->app->singleton(OffsiteReplicator::class, function () {
            return new OffsiteReplicator();
        });
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-backup');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReplicateBackupCommand::class,
                VerifyBackupIntegrityCommand::class,
            ]);

            // Daily-cron schedule. The push runs at 03:15 (before the
            // default 03:30 audit-prune) and the integrity sweep runs
            // at 04:00 after the night's replication has had time to
            // settle. Both honour `withoutOverlapping()`.
            $this->app->afterResolving(\Illuminate\Console\Scheduling\Schedule::class, function (\Illuminate\Console\Scheduling\Schedule $schedule) {
                $schedule->command('backup:replicate')->dailyAt('03:15')->withoutOverlapping();
                $schedule->command('backup:verify-integrity')->dailyAt('04:00')->withoutOverlapping();
            });
        }

        // Auto-seed the replication ledger table on first boot after
        // upgrade. Probe + install are wrapped in a single outer
        // try/catch per reference_ci_schema_hastable.md so a missing
        // database connection during a fresh install can't fault the
        // whole provider chain.
        try {
            if (!Schema::hasTable('ahg_backup_replication')) {
                $this->seedReplicationTable();
            }
        } catch (\Throwable $e) {
            // Operator can replay manually with:
            //   mysql heratio < packages/ahg-backup/database/install.sql
        }
    }

    /**
     * Run the replication-ledger CREATE TABLE statement straight from
     * the package's install.sql so the schema lives in exactly one
     * place. Statements are split on `;` and other tables in the same
     * file are CREATE TABLE IF NOT EXISTS so re-running is safe.
     */
    private function seedReplicationTable(): void
    {
        $path = __DIR__.'/../../database/install.sql';
        if (!is_file($path)) {
            return;
        }
        $sql = (string) file_get_contents($path);
        $lines = preg_split('/\r?\n/', $sql) ?: [];
        $stripped = '';
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $stripped .= $line."\n";
        }
        foreach (array_filter(array_map('trim', explode(';', $stripped))) as $stmt) {
            if ($stmt !== '') {
                try {
                    DB::statement($stmt);
                } catch (\Throwable $e) {
                    // Sibling table already present - keep going. The
                    // outer try/catch in boot() will swallow any real
                    // failure.
                }
            }
        }
    }
}
