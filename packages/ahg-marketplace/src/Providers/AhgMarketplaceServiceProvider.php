<?php

namespace AhgMarketplace\Providers;

use Illuminate\Support\ServiceProvider;

class AhgMarketplaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'marketplace');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgMarketplace\Console\Commands\AssignGalleryItemsCommand::class,
                \AhgMarketplace\Console\Commands\ReservationNotifyCommand::class,
            ]);
        }
    }
}
