<?php

namespace AhgCart\Providers;

use AhgCart\Services\CartService;
use AhgCart\Services\EcommerceService;
use Illuminate\Support\ServiceProvider;

class AhgCartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CartService::class);
        $this->app->singleton(EcommerceService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-cart');
    }
}
