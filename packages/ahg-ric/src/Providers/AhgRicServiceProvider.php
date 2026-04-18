<?php

/**
 * AhgRicServiceProvider - RIC-O Services Provider
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
        // Expose RicApiClient as a singleton so controllers can inject it.
        $this->app->singleton(\AhgRic\Http\RicApiClient::class, function () {
            return new \AhgRic\Http\RicApiClient();
        });

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

        // Load API routes ONLY when this process is itself serving the RiC API
        // (i.e. RIC_API_URL is unset). When RIC_API_URL is set, this process is
        // a client of an external RiC service (Heratio post-split), and
        // loading the API routes would just duplicate a surface that's served
        // authoritatively elsewhere.
        if (empty(config('ric.api_url'))) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        }

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-ric');

        // Register artisan commands (only when running in console — cheap guard).
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgRic\Console\Commands\VerifySplit::class,
                \AhgRic\Console\Commands\IssueKey::class,
                \AhgRic\Console\Commands\RebuildNestedSet::class,
                \AhgRic\Console\Commands\SeedDemo::class,
            ]);
        }

        // Expose the RiC API base URL to every Blade view so embedded JS can
        // resolve `/api/ric/v1` OR a post-split external service URL with
        // zero template changes.
        \Illuminate\Support\Facades\View::share(
            'ricApiBase',
            rtrim(config('ric.api_url') ?: url('/api/ric/v1'), '/')
        );

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
