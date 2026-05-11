<?php

/**
 * AhgShareLinkServiceProvider — service provider for the AHG Share Link package.
 *
 * Phase A — schema only. Migrations are loaded; routes, middleware and services
 * register in subsequent build phases.
 *
 * Copyright (C) 2026 The Archive and Heritage Group (Pty) Ltd
 * AGPL-3.0.
 */

namespace AhgShareLink\Providers;

use AhgShareLink\Console\PruneCommand;
use AhgShareLink\Services\AccessService;
use AhgShareLink\Services\AclCheck;
use AhgShareLink\Services\ClearanceCheck;
use AhgShareLink\Services\IssueService;
use AhgShareLink\Services\PruneService;
use AhgShareLink\Services\RevokeService;
use AhgShareLink\Services\TokenService;
use Illuminate\Support\ServiceProvider;

class AhgShareLinkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TokenService::class, fn () => new TokenService());
        $this->app->singleton(AclCheck::class, fn () => new AclCheck());
        $this->app->singleton(ClearanceCheck::class, fn () => new ClearanceCheck());
        $this->app->singleton(IssueService::class, fn ($app) => new IssueService(
            $app->make(TokenService::class),
            $app->make(AclCheck::class),
            $app->make(ClearanceCheck::class),
        ));
        $this->app->singleton(AccessService::class, fn ($app) => new AccessService(
            $app->make(TokenService::class),
        ));
        $this->app->singleton(RevokeService::class, fn () => new RevokeService());
        $this->app->singleton(PruneService::class, fn () => new PruneService());
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-share-link');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneCommand::class,
            ]);
        }
    }
}
