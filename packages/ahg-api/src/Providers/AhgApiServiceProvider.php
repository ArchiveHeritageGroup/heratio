<?php

namespace AhgApi\Providers;

use AhgApi\Middleware\ApiAuthenticate;
use AhgApi\Middleware\ApiCors;
use AhgApi\Middleware\ApiLogger;
use AhgApi\Middleware\ApiRateLimit;
use AhgApi\Services\ApiKeyService;
use AhgApi\Services\WebhookService;
use Illuminate\Support\ServiceProvider;

class AhgApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApiKeyService::class);
        $this->app->singleton(WebhookService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        // Register API middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('api.auth', ApiAuthenticate::class);
        $router->aliasMiddleware('api.ratelimit', ApiRateLimit::class);
        $router->aliasMiddleware('api.log', ApiLogger::class);
        $router->aliasMiddleware('api.cors', ApiCors::class);
    }
}
