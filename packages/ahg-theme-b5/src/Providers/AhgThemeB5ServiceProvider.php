<?php

namespace AhgThemeB5\Providers;

use AhgThemeB5\Services\ThemeService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AhgThemeB5ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeService::class);
    }

    public function boot(): void
    {
        // Load views with 'theme' namespace
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'theme');

        // Share theme data with all views
        View::composer('theme::layouts.*', function ($view) {
            $themeService = app(ThemeService::class);
            $view->with('themeData', $themeService->getLayoutData());
        });
    }
}
