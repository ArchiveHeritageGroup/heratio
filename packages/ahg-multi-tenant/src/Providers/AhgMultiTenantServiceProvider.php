<?php

/**
 * AhgMultiTenantServiceProvider.
 *
 * Boots routes / views, registers the tenant.resolve middleware alias,
 * binds the TenantContext singleton + facade accessor, registers Phase 1
 * artisan commands (multi-tenant:assign-rows), and auto-applies
 * install.sql on first boot (sentinel: ahg_tenant). Also auto-seeds the
 * 'default' tenant row on a brand-new install so existing single-tenant
 * deployments keep working without operator action.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMultiTenant\Providers;

use AhgMultiTenant\Console\Commands\AssignRowsCommand;
use AhgMultiTenant\Http\Middleware\ResolveTenantMiddleware;
use AhgMultiTenant\Services\TenantContext;
use AhgMultiTenant\Services\TenantFileService;
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
        // Phase 1 - resolver singleton. Bound under both the class name
        // and the 'tenant.context' alias so the facade and direct
        // type-hint resolution both work.
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext();
        });
        $this->app->alias(TenantContext::class, 'tenant.context');

        // TenantFileService - thin path helper, constructed each time so
        // tests can swap the context underneath it freely.
        $this->app->bind(TenantFileService::class, function ($app) {
            return new TenantFileService($app->make(TenantContext::class));
        });
    }

    public function boot(Router $router): void
    {
        Route::middleware('web')->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-multi-tenant');

        // Alias for use in route groups / controllers. The middleware is
        // also wired onto the web stack in bootstrap/app.php so every web
        // request resolves a tenant; the alias keeps targeted use possible.
        $router->aliasMiddleware('tenant.resolve', ResolveTenantMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AssignRowsCommand::class,
            ]);
        }

        // Auto-install schema on first boot. One outer try/catch so a
        // Schema::hasTable() failure never breaks boot (matches CI pattern
        // documented in reference_ci_schema_hastable.md).
        try {
            if (! Schema::hasTable('ahg_tenant')) {
                $sql = @file_get_contents(__DIR__.'/../../database/install.sql');
                if ($sql !== false && trim($sql) !== '') {
                    DB::unprepared($sql);
                    Log::info('ahg-multi-tenant: install.sql applied (first-boot)');
                }
            }

            // Phase 1 - auto-seed the 'default' tenant row when ahg_tenant
            // is empty. Keeps existing single-tenant installs working: the
            // resolver returns this row when no host / session match.
            if (Schema::hasTable('ahg_tenant')) {
                $hasAny = DB::table('ahg_tenant')->limit(1)->exists();
                if (! $hasAny) {
                    DB::table('ahg_tenant')->insert([
                        'code' => 'default',
                        'name' => 'Default',
                        'description' => 'Auto-seeded default tenant. Rename or replace as needed.',
                        'is_active' => 1,
                        'is_default' => 1,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    Log::info('ahg-multi-tenant: seeded default tenant row (first-boot)');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ahg-multi-tenant boot install skipped: '.$e->getMessage());
        }
    }
}
