<?php

namespace AhgRic\Providers;

use AhgRic\Services\RelationshipService;
use Illuminate\Support\ServiceProvider;

class AhgRicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RelationshipService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-ric');
    }
}
