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
        $viewPath = __DIR__ . '/../../resources/views';

        // Load views with 'theme' namespace (primary)
        $this->loadViewsFrom($viewPath, 'theme');

        // Register 'ahg-theme-b5' as an alias namespace so views using
        // @extends('ahg-theme-b5::...') resolve correctly
        $this->loadViewsFrom($viewPath, 'ahg-theme-b5');

        // Share theme data with all views
        View::composer('theme::layouts.*', function ($view) {
            $themeService = app(ThemeService::class);
            $view->with('themeData', $themeService->getLayoutData());
        });

        View::composer('ahg-theme-b5::layouts.*', function ($view) {
            $themeService = app(ThemeService::class);
            $view->with('themeData', $themeService->getLayoutData());
        });
    }
}
