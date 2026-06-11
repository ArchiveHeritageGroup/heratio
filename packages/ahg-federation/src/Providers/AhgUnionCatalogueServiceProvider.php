<?php

/**
 * AhgUnionCatalogueServiceProvider - wires the opt-in union-catalogue slice
 * of the federated GLAM network (#1203).
 *
 * Responsibilities:
 *   - register the public union-catalogue routes EARLY (in register(), via
 *     callAfterResolving('router')) so the single-segment /union-catalogue
 *     path is defined before the locked /{slug} catch-all in
 *     ahg-information-object-manage boots - the catch-all's exclusion list is
 *     locked and cannot be edited, so we beat it on registration order
 *     instead (see memory/reference_slug_catchall_route_precedence.md).
 *   - register the admin member-registry + share-config + publish routes.
 *   - auto-install the three union tables on first boot (Schema::hasTable +
 *     install in ONE outer try/catch per reference_ci_schema_hastable.md).
 *   - register the ahg:federation-publish console command + a daily schedule
 *     gated on the opt-in sharing switch (default OFF).
 *
 * Carved out as a SECOND provider so the existing (working) federation
 * provider and the locked F3 controllers stay untouched.
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

use AhgFederation\Console\UnionPublishCommand;
use AhgFederation\Controllers\UnionCatalogueController;
use AhgFederation\Controllers\UnionMemberController;
use AhgFederation\Services\UnionCatalogueService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgUnionCatalogueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the PUBLIC single-segment routes during register() so they
        // are defined before the locked /{slug} catch-all (which is added in
        // the IO package's boot()). Routes defined earlier win the match, so
        // /union-catalogue and /union-catalogue.json resolve here instead of
        // being swallowed by the catch-all. The catch-all's exclusion regex
        // is locked and cannot list our prefix, so registration order is the
        // sanctioned mechanism.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware('web')->group(function () use ($router) {
                $router->get('/union-catalogue.json',
                    [UnionCatalogueController::class, 'json'])
                    ->name('union.catalogue.json');

                $router->get('/union-catalogue',
                    [UnionCatalogueController::class, 'index'])
                    ->name('union.catalogue');
            });

            // Admin member registry + opt-in sharing config + publish trigger.
            $router->middleware(['web', 'auth', 'admin'])
                ->prefix('federation/members')
                ->group(function () use ($router) {
                    $router->get('/', [UnionMemberController::class, 'index'])
                        ->name('union.members.index');
                    $router->get('/add', [UnionMemberController::class, 'create'])
                        ->name('union.members.add');
                    $router->get('/{id}/edit', [UnionMemberController::class, 'edit'])
                        ->whereNumber('id')
                        ->name('union.members.edit');
                    $router->post('/save', [UnionMemberController::class, 'save'])
                        ->name('union.members.save');
                    $router->post('/{id}/delete', [UnionMemberController::class, 'destroy'])
                        ->whereNumber('id')
                        ->name('union.members.delete');
                    $router->post('/share', [UnionMemberController::class, 'saveShare'])
                        ->name('union.members.share');
                    $router->post('/publish', [UnionMemberController::class, 'publish'])
                        ->name('union.members.publish');
                });
        });
    }

    public function boot(): void
    {
        // Views are already registered under the ahg-federation namespace by
        // AhgFederationServiceProvider; our union.* views live in the same
        // resources/views tree, so no second loadViewsFrom is needed.

        // Auto-install the three union tables on first boot. Single outer
        // try/catch wrapping the hasTable probe + the install run so CI
        // without a DB connection stays green.
        try {
            if (! Schema::hasTable('federation_union_record')) {
                $sqlPath = __DIR__.'/../../database/install_union.sql';
                if (is_file($sqlPath)) {
                    DB::unprepared(file_get_contents($sqlPath));
                }
            }
        } catch (\Throwable $e) {
            // Fresh install before the connection is ready; tables get
            // created by `php artisan ahg:install` or the next real boot.
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                UnionPublishCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);

                $schedule->command('ahg:federation-publish')
                    ->dailyAt('04:00')
                    ->withoutOverlapping(60)
                    ->when(function () {
                        try {
                            return $this->app->make(UnionCatalogueService::class)
                                ->isSharingEnabled();
                        } catch (\Throwable $e) {
                            return false;
                        }
                    });
            });
        }
    }
}
