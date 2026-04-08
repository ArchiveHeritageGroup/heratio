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

        // Auto-seed research dropdowns on first boot if missing
        $this->app->booted(function () {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('ahg_dropdown')) {
                    $hasSeatTypes = \Illuminate\Support\Facades\DB::table('ahg_dropdown')
                        ->where('taxonomy', 'seat_type')->exists();
                    if (!$hasSeatTypes) {
                        \Illuminate\Support\Facades\Artisan::call('ahg:seed-research-dropdowns');
                    }
                }
            } catch (\Exception $e) {
                // Silently skip if DB not ready (e.g. during migrations)
            }
        });

        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'research');
    }

    public function register(): void
    {
        $this->app->singleton(\AhgResearch\Services\OdrlService::class);
    }
}
