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

            // Web archiving (WARC 1.1): the single warc_capture table is owned + installed
            // by the ahg-core base package's service provider (alongside the reusable
            // WarcCaptureService + WarcReplayService engines). The ahg-scan web-archive
            // surface is just a thin controller over those engines, so there is NO separate
            // ahg-scan web-archive table to install here. The former duplicate
            // web_archive_capture table + its installer were removed in the #1244 merge.
        });
    }
}
