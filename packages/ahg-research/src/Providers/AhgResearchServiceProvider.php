<?php

namespace AhgResearch\Providers;

use Illuminate\Support\ServiceProvider;

class AhgResearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register ODRL policy middleware alias
        $router = $this->app['router'];
        $router->aliasMiddleware('odrl', \AhgResearch\Middleware\OdrlPolicyMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgResearch\Commands\SeedDropdownsCommand::class,
            ]);
        }

        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'research');
    }

    public function register(): void
    {
        $this->app->singleton(\AhgResearch\Services\OdrlService::class);
    }
}
