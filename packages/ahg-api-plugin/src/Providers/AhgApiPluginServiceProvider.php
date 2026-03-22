<?php

namespace AhgApiPlugin\Providers;

use Illuminate\Support\ServiceProvider;

class AhgApiPluginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'api-plugin');
    }
}
