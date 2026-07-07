<?php

/**
 * AhgArchivematicaServiceProvider - service provider for Heratio
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

namespace AhgArchivematica;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgArchivematicaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config so config('archivematica.*') resolves even
        // before the app publishes/overrides it.
        $this->mergeConfigFrom(__DIR__ . '/../config/archivematica.php', 'archivematica');
    }

    public function boot(): void
    {
        // Web routes (admin trigger + status pages, settings page).
        Route::middleware('web')
            ->group(__DIR__ . '/../routes/web.php');

        // API routes (inbound DIP push endpoint). Paths are fully qualified
        // (/api/...) in the file itself.
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // Views under the ahg-archivematica:: namespace.
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ahg-archivematica');

        // Auto-install the schema on first boot if am_link is missing. Mirror
        // the other packages: one outer try/catch so a transient DB hiccup or
        // a fresh DB (where Schema::hasTable throws before the table exists)
        // never fatals boot - the next request retries.
        try {
            if (! Schema::hasTable('am_link')) {
                $sql = @file_get_contents(__DIR__ . '/../database/install.sql');
                if ($sql) {
                    DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            // best-effort; never break boot.
        }

        // Register console commands, guarded with class_exists() because the
        // service/command classes are owned + delivered by other agents and
        // may not be present yet in an intermediate checkout.
        if ($this->app->runningInConsole()) {
            $commands = [];
            foreach ([
                \AhgArchivematica\Commands\PingCommand::class,
                \AhgArchivematica\Commands\IngestDipsCommand::class,
                \AhgArchivematica\Commands\PollArchivematicaCommand::class,
            ] as $command) {
                if (class_exists($command)) {
                    $commands[] = $command;
                }
            }
            if ($commands) {
                $this->commands($commands);
            }
        }
    }
}
