<?php

namespace AhgApi\Providers;

use Illuminate\Support\ServiceProvider;

class AhgApiServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
    }
}
