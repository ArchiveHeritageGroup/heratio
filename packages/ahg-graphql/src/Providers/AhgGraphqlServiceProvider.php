<?php

namespace AhgGraphql\Providers;

use Illuminate\Support\ServiceProvider;

class AhgGraphqlServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-graphql');
    }
}
