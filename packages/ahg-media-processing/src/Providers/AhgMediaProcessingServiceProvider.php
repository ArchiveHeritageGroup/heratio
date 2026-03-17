<?php

namespace AhgMediaProcessing\Providers;

use AhgMediaProcessing\Services\DerivativeService;
use AhgMediaProcessing\Services\WatermarkService;
use Illuminate\Support\ServiceProvider;

class AhgMediaProcessingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DerivativeService::class);
        $this->app->singleton(WatermarkService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-media-processing');
    }
}
