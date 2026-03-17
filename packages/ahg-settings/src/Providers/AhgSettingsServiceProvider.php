<?php

namespace AhgSettings\Providers;

use AhgSettings\Services\SettingsService;
use Illuminate\Support\ServiceProvider;

class AhgSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-settings');
    }
}
