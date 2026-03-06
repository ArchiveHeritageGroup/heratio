<?php

namespace App\Providers;

use App\Auth\AtomUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom Heratio authentication provider
        Auth::provider('atom', function ($app, array $config) {
            return new AtomUserProvider();
        });
    }
}
