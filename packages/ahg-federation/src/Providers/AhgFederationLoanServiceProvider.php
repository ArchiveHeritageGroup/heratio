<?php

/**
 * AhgFederationLoanServiceProvider - wires the inter-institution loan-request
 * slice of the federated GLAM network (#1203).
 *
 * Responsibilities:
 *   - register the admin-gated loan-request routes under the two-segment
 *     /federation/loans prefix (catch-all-safe: the locked /{slug} catch-all
 *     only matches single-segment paths, so a /federation/loans/... path is
 *     never intercepted - no need to win the registration-order race the way
 *     the single-segment /union-catalogue route does).
 *   - auto-install the federation_loan_request table on first boot
 *     (Schema::hasTable probe + install run wrapped in ONE outer try/catch,
 *     per reference_ci_schema_hastable.md, so a DB-less CI boot stays green).
 *
 * Carved out as a THIRD provider so the existing federation provider, the
 * union-catalogue provider, and the locked F3 controllers all stay untouched.
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

use AhgFederation\Controllers\LoanAnalyticsController;
use AhgFederation\Controllers\LoanRequestController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgFederationLoanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // All loan routes live under the two-segment /federation/loans prefix,
        // so the single-segment /{slug} catch-all can never swallow them. We
        // still register in register()/callAfterResolving for parity with the
        // union-catalogue provider and to keep the named routes available
        // early.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware(['web', 'auth', 'admin'])
                ->prefix('federation/loans')
                ->group(function () use ($router) {
                    $router->get('/', [LoanRequestController::class, 'index'])
                        ->name('federation.loans.index');
                    $router->get('/new', [LoanRequestController::class, 'create'])
                        ->name('federation.loans.create');
                    $router->post('/save', [LoanRequestController::class, 'save'])
                        ->name('federation.loans.save');

                    // Loan-analytics dashboard (#1203 loan-analytics slice).
                    // Read-only aggregate report over federation_loan_request.
                    // The static "analytics" / "analytics.json" segments are
                    // registered BEFORE the numeric /{id} route below; the
                    // {id} route is also ->whereNumber-constrained so the word
                    // "analytics" can never match it - belt and braces against
                    // a /federation/loans/{id} collision. Multi-segment path,
                    // so the locked single-segment /{slug} catch-all never
                    // intercepts it. The .json surface is CORS-open inside the
                    // controller (it stays admin-gated here on purpose).
                    $router->get('/analytics.json', [LoanAnalyticsController::class, 'json'])
                        ->name('federation.loans.analytics.json');
                    $router->get('/analytics', [LoanAnalyticsController::class, 'index'])
                        ->name('federation.loans.analytics');

                    $router->get('/{id}', [LoanRequestController::class, 'show'])
                        ->whereNumber('id')
                        ->name('federation.loans.show');
                    $router->post('/{id}/transition', [LoanRequestController::class, 'transition'])
                        ->whereNumber('id')
                        ->name('federation.loans.transition');
                });
        });
    }

    public function boot(): void
    {
        // Views are registered under the ahg-federation namespace by
        // AhgFederationServiceProvider; our loans.* views live in the same
        // resources/views tree, so no second loadViewsFrom is needed.

        // Auto-install the loan table on first boot. Single outer try/catch
        // wrapping the hasTable probe + the install run so a DB-less CI boot
        // stays green.
        try {
            if (! Schema::hasTable('federation_loan_request')) {
                $sqlPath = __DIR__.'/../../database/install_loan.sql';
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
