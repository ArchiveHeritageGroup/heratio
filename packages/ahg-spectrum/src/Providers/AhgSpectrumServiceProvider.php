<?php

namespace AhgSpectrum\Providers;

use Illuminate\Support\ServiceProvider;

class AhgSpectrumServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'spectrum');
    }
}
