<?php

namespace AhgApi\Providers;

use AhgApi\Console\GenerateOpenApiCommand;
use AhgApi\Console\PruneIdempotencyCommand;
use AhgApi\Middleware\ApiAuthenticate;
use AhgApi\Middleware\ApiCors;
use AhgApi\Middleware\ApiLogger;
use AhgApi\Middleware\ApiRateLimit;
use AhgApi\Middleware\ETagMiddleware;
use AhgApi\Middleware\IdempotencyKeyMiddleware;
use AhgApi\Services\ApiKeyService;
use AhgApi\Services\GraphExplorerService;
use AhgApi\Services\GraphSerializerService;
use AhgApi\Services\OpenApiGenerator;
use AhgApi\Services\WebhookService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApiKeyService::class);
        $this->app->singleton(WebhookService::class);
        $this->app->singleton(OpenApiGenerator::class);
        $this->app->singleton(GraphSerializerService::class);
        $this->app->singleton(GraphExplorerService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-api');

        // Register API middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('api.auth', ApiAuthenticate::class);
        $router->aliasMiddleware('api.ratelimit', ApiRateLimit::class);
        $router->aliasMiddleware('api.log', ApiLogger::class);
        $router->aliasMiddleware('api.cors', ApiCors::class);
        $router->aliasMiddleware('api.idempotency', IdempotencyKeyMiddleware::class);
        $router->aliasMiddleware('api.etag', ETagMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateOpenApiCommand::class,
                PruneIdempotencyCommand::class,
            ]);

            // Auto-install Phase 1 schema on first boot (idempotency-key table)
            try {
                if (! Schema::hasTable('ahg_api_idempotency_key')) {
                    $sql = @file_get_contents(__DIR__.'/../../database/install.sql');
                    if ($sql !== false) {
                        \Illuminate\Support\Facades\DB::unprepared($sql);
                    }
                }
            } catch (\Throwable $e) {
                // best-effort; never break boot
            }
        }
    }
}
