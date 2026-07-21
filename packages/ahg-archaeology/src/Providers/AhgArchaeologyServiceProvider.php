<?php

/**
 * AhgArchaeologyServiceProvider — archaeology collections management.
 *
 * Registered explicitly in bootstrap/providers.php with a PSR-4 autoload entry
 * in the root composer.json, following ahg-articles and ahg-marketing. That
 * avoids a composer require and so leaves composer.lock untouched.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgArchaeology\Providers;

use Illuminate\Support\ServiceProvider;

class AhgArchaeologyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ahg-archaeology.php', 'ahg-archaeology');

        // `/archaeology` is a single top-level segment, so it must be registered
        // before the locked `/{slug}` catch-all or the dashboard 404s while its
        // own child routes resolve normally. Loading the route file from boot()
        // is too late. Same approach as ahg-articles; see
        // memory/reference_slug_catchall_route_precedence.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware(['web', 'auth'])->prefix('archaeology')->group(function () use ($router) {
                $router->get('/', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'index'])
                    ->name('archaeology.index');

                $router->get('/sites', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'sites'])
                    ->name('archaeology.sites');
                $router->get('/site/{id}', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'site'])
                    ->whereNumber('id')->name('archaeology.site');

                $router->get('/objects', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'objects'])
                    ->name('archaeology.objects');
                $router->get('/object/{id}', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'object'])
                    ->whereNumber('id')->name('archaeology.object');
            });
        });
    }

    public function boot(): void
    {
        // Migrations must be loaded here or they silently never run and prod
        // drifts behind dev without any error.
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-archaeology');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgArchaeology\Console\Commands\ArchaeologySeedVocabulariesCommand::class,
            ]);
        }
    }
}
