<?php

namespace AhgNaz\Providers;

use Illuminate\Support\ServiceProvider;

class AhgNazServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'naz');
    }
}
