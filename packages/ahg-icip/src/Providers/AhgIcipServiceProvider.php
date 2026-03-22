<?php

namespace AhgIcip\Providers;

use Illuminate\Support\ServiceProvider;

class AhgIcipServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'icip');
    }
}
