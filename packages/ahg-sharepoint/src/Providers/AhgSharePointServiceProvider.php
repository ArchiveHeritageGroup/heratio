<?php

namespace AhgSharePoint\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * AhgSharePointServiceProvider — Heratio package wiring.
 *
 * Mirrored in atom-ahg-plugins/ahgSharePointPlugin/config/ahgSharePointPluginConfiguration.class.php.
 * Both must register: routes, queue handlers, settings section, services.
 */
class AhgSharePointServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton bindings (Phase 1)
        $this->app->singleton(\AhgSharePoint\Services\GraphTokenCache::class);
        $this->app->singleton(\AhgSharePoint\Services\GraphClientService::class);
    }

    public function boot(): void
    {
        // Routes
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
        Route::prefix('api')->middleware('api')->group(__DIR__ . '/../../routes/api.php');

        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Views (Phase 1 admin UI templates)
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-sharepoint');

        // CLI commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgSharePoint\Console\Commands\SharePointInstallCommand::class,
                \AhgSharePoint\Console\Commands\SharePointTestConnectionCommand::class,
                \AhgSharePoint\Console\Commands\SharePointSyncCommand::class,
                \AhgSharePoint\Console\Commands\SharePointStatusCommand::class,
                // Phase 2 commands registered up-front; they fail loudly until implemented.
                \AhgSharePoint\Console\Commands\SharePointSubscribeCommand::class,
                \AhgSharePoint\Console\Commands\SharePointRenewSubscriptionsCommand::class,
                \AhgSharePoint\Console\Commands\SharePointIngestEventCommand::class,
            ]);
        }
    }
}
