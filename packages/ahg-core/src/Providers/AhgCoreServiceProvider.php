<?php

namespace AhgCore\Providers;

use Illuminate\Support\ServiceProvider;

class AhgCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register core services
    }

    public function boot(): void
    {
        // Load routes
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-core');
    }
}
