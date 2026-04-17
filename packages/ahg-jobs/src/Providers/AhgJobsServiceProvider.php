<?php

/**
 * AhgJobsServiceProvider - Service provider for AHG Jobs
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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

namespace Ahg\Jobs\Providers;

use Illuminate\Support\ServiceProvider;
use Ahg\Jobs\Services\JobsService;

class AhgJobsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JobsService::class, function ($app) {
            return new JobsService();
        });
    }

    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-jobs');

        // Publish assets
        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/ahg-jobs'),
        ], 'ahg-jobs-views');
    }
}
