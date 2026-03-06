<?php

namespace AhgDisplay;

use Illuminate\Support\ServiceProvider;

class AhgDisplayServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ahg-display');
    }

    public function register(): void
    {
        //
    }
}
