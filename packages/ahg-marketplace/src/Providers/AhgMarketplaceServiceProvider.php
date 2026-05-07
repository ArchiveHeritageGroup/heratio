<?php

namespace AhgMarketplace\Providers;

use Illuminate\Console\Scheduling\Schedule;
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
                // #84 featured-listing expiry
                \AhgMarketplace\Console\Commands\MarketplaceFeatureExpireCommand::class,
            ]);

            // #84 daily demote of expired featured listings. Cheap when the
            // table has no expired featured rows (one indexed UPDATE that
            // matches zero rows).
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('ahg:marketplace-feature-expire')
                    ->dailyAt('02:15')
                    ->withoutOverlapping(60);
            });
        }
    }
}
