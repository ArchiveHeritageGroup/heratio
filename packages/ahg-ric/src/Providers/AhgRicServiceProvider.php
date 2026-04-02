<?php

/**
 * AhgRicServiceProvider - RIC-O Services Provider
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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

namespace AhgRic\Providers;

use AhgRic\Services\RelationshipService;
use AhgRic\Services\RicEntityService;
use AhgRic\Services\RicSerializationService;
use AhgRic\Services\ShaclValidationService;
use AhgRic\Services\SparqlQueryService;
use Illuminate\Support\ServiceProvider;

class AhgRicServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register RelationshipService
        $this->app->singleton(RelationshipService::class);

        // Register RicSerializationService
        $this->app->singleton(RicSerializationService::class);

        // Register ShaclValidationService
        $this->app->singleton(ShaclValidationService::class);

        // Register SparqlQueryService
        $this->app->singleton(SparqlQueryService::class);

        // Register RicEntityService
        $this->app->singleton(RicEntityService::class, function () {
            return new RicEntityService(app()->getLocale());
        });

        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ahg-ric.php',
            'ahg-ric'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load web routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Load API routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-ric');

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/ahg-ric.php' => config_path('ahg-ric.php'),
        ], 'ahg-ric-config');

        // Publish assets
        $this->publishes([
            __DIR__ . '/../../resources' => resource_path('views/vendor/ahg-ric'),
        ], 'ahg-ric-views');
    }
}
