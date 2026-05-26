<?php

/**
 * AhgHeritageManageServiceProvider - Laravel service provider for the package.
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

namespace AhgHeritageManage\Providers;

use AhgHeritageManage\Console\Commands\HeritageDisclosureNoteCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgHeritageManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-heritage-manage');

        if ($this->app->runningInConsole()) {
            $this->commands([
                HeritageDisclosureNoteCommand::class,
            ]);
        }

        // Idempotent in-place migration:
        // wire the existing heritage_valuation_history table to the new
        // ahg_valuer registry by adding a valuer_id FK if it isn't already
        // present. Probe via information_schema (MySQL has no ADD COLUMN IF
        // NOT EXISTS in 8.0); guarded inside a try so a non-MySQL connection
        // doesn't crash boot.
        try {
            if (Schema::hasTable('heritage_valuation_history') && ! Schema::hasColumn('heritage_valuation_history', 'valuer_id')) {
                DB::statement('ALTER TABLE `heritage_valuation_history` ADD COLUMN `valuer_id` INT UNSIGNED NULL AFTER `valuer_organization`, ADD KEY `idx_valuer` (`valuer_id`)');
            }
        } catch (\Throwable $e) {
            // boot must not fail
        }
    }
}
