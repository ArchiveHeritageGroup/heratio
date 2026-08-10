<?php

/*
 * AhgArtworkRequestServiceProvider - staff artwork placement requests (#1459).
 *
 * Registered explicitly in bootstrap/providers.php with a PSR-4 autoload entry
 * in the root composer.json, following ahg-archaeology / ahg-articles. That
 * avoids a composer require and so leaves composer.lock untouched.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgArtworkRequest\Providers;

use AhgArtworkRequest\Controllers\ArtworkRequestController;
use Illuminate\Support\ServiceProvider;

class AhgArtworkRequestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ahg-artwork-request.php', 'ahg-artwork-request');

        // `/artwork-request` is a single top-level segment, so it must be
        // registered before the locked `/{slug}` catch-all or the screens 404
        // while their own child routes resolve. Loading the route file from
        // boot() is too late. Same approach as ahg-archaeology.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware(['web', 'auth'])->prefix('artwork-request')->group(function () use ($router) {
                // My requests.
                $router->get('/', [ArtworkRequestController::class, 'index'])
                    ->name('artwork-request.index');

                // Ask for works (any authenticated user). Declared before /{id}.
                $router->match(['get', 'post'], '/new', [ArtworkRequestController::class, 'requestForm'])
                    ->name('artwork-request.new');

                // Clash JSON for the request form.
                $router->get('/availability', [ArtworkRequestController::class, 'availability'])
                    ->name('artwork-request.availability');

                // Staff screens - editors and administrators.
                $router->match(['get', 'post'], '/review', [ArtworkRequestController::class, 'review'])
                    ->middleware('acl:update')->name('artwork-request.review');
                $router->get('/placements', [ArtworkRequestController::class, 'placements'])
                    ->middleware('acl:update')->name('artwork-request.placements');

                // Approver settings - administrator-only (enforced in the controller).
                $router->match(['get', 'post'], '/approvers', [ArtworkRequestController::class, 'approvers'])
                    ->name('artwork-request.approvers');

                // Loan hand-off for an approved request.
                $router->post('/{id}/create-loan', [ArtworkRequestController::class, 'createLoan'])
                    ->whereNumber('id')->middleware('acl:update')->name('artwork-request.create-loan');

                // One request. Numeric-only, and declared last, so none of the
                // word routes above are ever captured as an id.
                $router->get('/{id}', [ArtworkRequestController::class, 'view'])
                    ->whereNumber('id')->name('artwork-request.view');
            });
        });
    }

    public function boot(): void
    {
        // Migrations must be loaded here or they silently never run and prod
        // drifts behind dev without any error.
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-artwork-request');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgArtworkRequest\Console\Commands\ArtworkRemindCommand::class,
            ]);
        }
    }
}
