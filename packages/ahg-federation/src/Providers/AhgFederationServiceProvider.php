<?php

/**
 * AhgFederationServiceProvider - registers federation routes, views, and commands
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

namespace AhgFederation\Providers;

use AhgFederation\Console\HarvestCommand;
use AhgFederation\Console\SearchCacheCleanCommand;
use AhgFederation\Console\VocabSyncCommand;
use AhgFederation\Services\FederationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AhgFederationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-federation');

        if ($this->app->runningInConsole()) {
            $this->commands([
                HarvestCommand::class,
                SearchCacheCleanCommand::class,
                VocabSyncCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);

                $enabled = function () {
                    try {
                        return $this->app->make(FederationService::class)->isEnabled();
                    } catch (\Throwable $e) {
                        return false;
                    }
                };

                $schedule->command('ahg:federation-harvest --all-active')
                    ->dailyAt('02:00')
                    ->withoutOverlapping(60)
                    ->when($enabled);

                $schedule->command('ahg:federation-search-cache-clean')
                    ->hourly()
                    ->when($enabled);

                $schedule->command('ahg:federation-vocab-sync')
                    ->dailyAt('03:00')
                    ->withoutOverlapping(60)
                    ->when($enabled);
            });
        }
    }
}
