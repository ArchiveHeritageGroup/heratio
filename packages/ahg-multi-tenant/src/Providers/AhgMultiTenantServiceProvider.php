<?php

/**
 * AhgMultiTenantServiceProvider.
 *
 * Boots routes / views, registers the tenant.resolve middleware alias,
 * and auto-applies install.sql on first boot (sentinel: ahg_tenant).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMultiTenant\Providers;

use AhgMultiTenant\Http\Middleware\ResolveTenantMiddleware;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgMultiTenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(Router $router): void
    {
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-multi-tenant');

        // Alias for use in route groups / controllers. The middleware is
        // also wired onto the web stack in bootstrap/app.php so every web
        // request resolves a tenant; the alias keeps targeted use possible.
        $router->aliasMiddleware('tenant.resolve', ResolveTenantMiddleware::class);

        // Auto-install schema on first boot. One outer try/catch so a
        // Schema::hasTable() failure never breaks boot (matches CI pattern
        // documented in reference_ci_schema_hastable.md).
        try {
            if (!Schema::hasTable('ahg_tenant')) {
                $sql = @file_get_contents(__DIR__ . '/../../database/install.sql');
                if ($sql !== false && trim($sql) !== '') {
                    DB::unprepared($sql);
                    Log::info('ahg-multi-tenant: install.sql applied (first-boot)');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ahg-multi-tenant boot install skipped: ' . $e->getMessage());
        }
    }
}
