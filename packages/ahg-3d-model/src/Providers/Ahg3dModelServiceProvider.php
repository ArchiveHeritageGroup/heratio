<?php

namespace Ahg3dModel\Providers;

use Ahg3dModel\Services\ThreeDThumbnailService;
use Illuminate\Support\ServiceProvider;

class Ahg3dModelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThreeDThumbnailService::class, function () {
            return new ThreeDThumbnailService();
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-3d-model');

        // Commands are registered unconditionally so they can be invoked via
        // Artisan::call(...) from web requests (e.g. user-triggered TripoSR
        // generation), not only from the CLI.
        $this->commands([
            \Ahg3dModel\Commands\ThreeDDerivativesCommand::class,
            \Ahg3dModel\Commands\ThreeDMultiangleCommand::class,
            \Ahg3dModel\Commands\TriposrGenerateCommand::class,
            \Ahg3dModel\Commands\TriposrHealthCommand::class,
            \Ahg3dModel\Commands\TriposrPreloadCommand::class,
        ]);
    }
}
