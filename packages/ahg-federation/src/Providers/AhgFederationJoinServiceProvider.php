<?php

/**
 * AhgFederationJoinServiceProvider - wires the public "Join the network"
 * request workflow (#1203 join-request slice).
 *
 * Responsibilities:
 *   - register the PUBLIC join routes (GET/POST /federation/join, the thanks
 *     confirmation page) and the ADMIN moderation routes
 *     (GET /federation/join-requests, POST /federation/join-requests/{id}).
 *     All paths are two-segment+ so the locked single-segment /{slug}
 *     catch-all in ahg-information-object-manage never intercepts them. Routes
 *     are registered in register() via callAfterResolving('router') for the
 *     same ordering safety the union-catalogue provider uses.
 *   - auto-install the federation_join_request table on first boot
 *     (Schema::hasTable + install in ONE outer try/catch per
 *     reference_ci_schema_hastable.md).
 *
 * Carved out as a THIRD chained provider (alongside the union-catalogue and
 * loan providers) so the existing federation provider and the locked F3
 * controllers stay untouched. Chained from AhgFederationServiceProvider::
 * register() the same way the other two are.
 *
 * Path-collision note: the public /federation/harvest path is already taken by
 * the locked F3 admin harvest page (federation.harvest). This slice uses
 * /federation/join and /federation/join-requests, neither of which collides
 * with any existing /federation/* route (index, peers, harvest, log, search,
 * provenance, europeana, network, members).
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

use AhgFederation\Controllers\JoinNetworkController;
use AhgFederation\Controllers\JoinRequestModerationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgFederationJoinServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register routes during register() (via callAfterResolving('router'))
        // for ordering parity with the union-catalogue provider. Every path
        // here is two-segment or deeper, so the locked single-segment /{slug}
        // catch-all does not intercept them - registration order is belt and
        // braces, not strictly required.
        $this->callAfterResolving('router', function ($router) {
            // PUBLIC join surface - anonymous-readable and anonymous-submittable.
            $router->middleware('web')->group(function () use ($router) {
                // Register the more specific /join/thanks before /join so the
                // confirmation route is matched first.
                $router->get('/federation/join/thanks',
                    [JoinNetworkController::class, 'thanks'])
                    ->name('federation.join.thanks');

                $router->get('/federation/join',
                    [JoinNetworkController::class, 'index'])
                    ->name('federation.join');

                $router->post('/federation/join',
                    [JoinNetworkController::class, 'store'])
                    ->name('federation.join.submit');
            });

            // ADMIN moderation surface - auth + admin gated.
            $router->middleware(['web', 'auth', 'admin'])
                ->prefix('federation/join-requests')
                ->group(function () use ($router) {
                    $router->get('/',
                        [JoinRequestModerationController::class, 'index'])
                        ->name('federation.joinRequests.index');

                    $router->post('/{id}',
                        [JoinRequestModerationController::class, 'update'])
                        ->whereNumber('id')
                        ->name('federation.joinRequests.update');
                });
        });
    }

    public function boot(): void
    {
        // Views live in the same resources/views tree already registered under
        // the ahg-federation namespace by AhgFederationServiceProvider, so no
        // second loadViewsFrom is needed.

        // Auto-install the join-request table on first boot. Single outer
        // try/catch wrapping the hasTable probe + the install run so CI without
        // a DB connection stays green (reference_ci_schema_hastable.md).
        try {
            if (! Schema::hasTable('federation_join_request')) {
                $sqlPath = __DIR__.'/../../database/install_join_request.sql';
                if (is_file($sqlPath)) {
                    DB::unprepared(file_get_contents($sqlPath));
                }
            }
        } catch (\Throwable $e) {
            // Fresh install before the connection is ready; the table gets
            // created by `php artisan ahg:install` or the next real boot.
        }
    }
}
