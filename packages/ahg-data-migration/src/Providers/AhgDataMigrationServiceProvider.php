<?php

/**
 * AhgDataMigrationServiceProvider - Service provider for Heratio
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

namespace AhgDataMigration\Providers;

use AhgDataMigration\Services\DataMigrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgDataMigrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DataMigrationService::class, function ($app) {
            return new DataMigrationService;
        });
    }

    public function boot(): void
    {
        try {
            Route::middleware('web')->group(__DIR__.'/../../routes/web.php');
            $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-data-migration');
            $this->seedDropdownsIfMissing();
        } catch (\Throwable $e) {
            // boot is best-effort - never break the framework if DB isn't ready
        }
    }

    /**
     * Seed export_format / sheet_type / data_migration_sector taxonomies
     * once on first boot. Idempotent: only runs when the table exists and
     * no rows for these taxonomies are present.
     */
    protected function seedDropdownsIfMissing(): void
    {
        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return;
            }
            $count = DB::table('ahg_dropdown')
                ->whereIn('taxonomy', ['export_format', 'sheet_type', 'data_migration_sector'])
                ->count();
            if ($count > 0) {
                return;
            }
            $sql = __DIR__.'/../../database/seed_dropdowns.sql';
            if (file_exists($sql)) {
                DB::unprepared(file_get_contents($sql));
            }
        } catch (\Throwable $e) {
            // swallow seed errors - install scripts handle it
        }
    }
}
