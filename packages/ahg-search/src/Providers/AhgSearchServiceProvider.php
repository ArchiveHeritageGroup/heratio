<?php

namespace AhgSearch\Providers;

use AhgSearch\Services\ElasticsearchService;
use Illuminate\Support\ServiceProvider;

class AhgSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ElasticsearchService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-search');
    }
}
