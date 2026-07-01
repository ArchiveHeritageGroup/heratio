<?php

/**
 * AhgZ3950ServiceProvider — Z39.50 package service provider for Heratio
 *
 * Auto-runs: publishes config, registers routes, runs migrations for the
 * z3950_targets and z3950_query_log / z3950_import_log tables.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 * Email: johan@theahg.co.za
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

namespace AhgZ3950\Providers;

use AhgZ3950\Commands\Z3950ServerCommand;
use AhgZ3950\Services\BerEncoder;
use AhgZ3950\Services\Z3950ServerService;
use AhgZ3950\Services\Z3950Service;
use AhgZ3950\Services\SruService;
use Illuminate\Support\ServiceProvider;

class AhgZ3950ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge config from config/ahg-z3950.php
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ahg-z3950.php',
            'ahg-z3950'
        );

        // Register BER encoder as singleton
        $this->app->singleton(BerEncoder::class, fn() => new BerEncoder());

        // Register Z39.50 server service
        $this->app->singleton(Z3950ServerService::class, function ($app) {
            return new Z3950ServerService($app->make(BerEncoder::class));
        });

        // Register Z39.50 client service
        $this->app->singleton(Z3950Service::class, fn() => new Z3950Service());

        // Register SRU service
        $this->app->singleton(SruService::class, fn() => new SruService());
    }

    public function boot(): void
    {
        // Register views under the ahg-z3950:: namespace
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-z3950');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register the package's anonymous-component directory with no prefix so
        // the Z39.50 views can use <x-app-layout>. Without this the unprefixed
        // tag never resolves and view:cache aborts with "Unable to locate a
        // class or view for component [app-layout]".
        \Illuminate\Support\Facades\Blade::anonymousComponentPath(
            __DIR__ . '/../../resources/views/components'
        );

        // Register package routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/ahg-z3950.php' => config_path('ahg-z3950.php'),
        ], 'ahg-z3950-config');

        // Register the Z39.50 server daemon command
        if ($this->app->runningInConsole()) {
            $this->commands([Z3950ServerCommand::class]);
        }

        // Register migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations/2026_05_19_000001_create_z3950_tables.php'
                => database_path('migrations/2026_05_19_000001_create_z3950_tables.php'),
        ], 'ahg-z3950-migrations');
    }
}
