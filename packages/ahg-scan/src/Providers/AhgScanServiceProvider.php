<?php

/**
 * AhgScanServiceProvider - Heratio
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

namespace AhgScan\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgScanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../../routes/web.php');
        Route::middleware('web')->group(__DIR__.'/../../routes/web-archive.php');
        Route::middleware('api')->group(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-scan');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgScan\Console\ScanWatchCommand::class,
                \AhgScan\Console\ScanProcessCommand::class,
                \AhgScan\Console\ScanInstallCommand::class,
                \AhgScan\Console\ScanRetryFailedCommand::class,
                \AhgScan\Console\WebCaptureCommand::class,
            ]);
        }

        $this->app->booted(function () {
            try {
                if (! Schema::hasTable('scan_folder')
                    || (Schema::hasTable('ahg_dropdown') && ! DB::table('ahg_dropdown')->where('taxonomy', 'scan_folder_layout')->exists())) {
                    \Illuminate\Support\Facades\Artisan::call('ahg:scan-install');
                }
            } catch (\Throwable $e) {
                // Silently skip during migrations / early boot
            }

            // Web-archiving (WARC 1.1) capture store. Guarded auto-create on
            // first boot, independent of the scan-install path above so a
            // missing scan_folder install never blocks web archiving.
            try {
                $this->installWebArchiveSchema();
            } catch (\Throwable $e) {
                // Silently skip during migrations / early boot
            }
        });
    }

    /**
     * Create the web_archive_capture table if it is absent. Uses
     * CREATE TABLE IF NOT EXISTS guarded by Schema::hasTable so it is safe to
     * run on every boot and never issues an ALTER on an existing table.
     */
    protected function installWebArchiveSchema(): void
    {
        if (Schema::hasTable('web_archive_capture')) {
            return;
        }

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `web_archive_capture` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `url` VARCHAR(2048) NOT NULL,
  `title` VARCHAR(1024) NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `http_status` INT NULL,
  `content_type` VARCHAR(255) NULL,
  `warc_path` VARCHAR(1024) NULL,
  `byte_size` BIGINT UNSIGNED NULL,
  `captured_by` INT NULL,
  `captured_at` DATETIME NULL,
  `error` VARCHAR(2048) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_web_archive_status` (`status`),
  KEY `ix_web_archive_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
    }
}
