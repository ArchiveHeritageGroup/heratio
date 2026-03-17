<?php

namespace AhgPreservation\Providers;

use AhgPreservation\Services\PreservationService;
use Illuminate\Support\ServiceProvider;

class AhgPreservationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PreservationService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-preservation');
    }
}
