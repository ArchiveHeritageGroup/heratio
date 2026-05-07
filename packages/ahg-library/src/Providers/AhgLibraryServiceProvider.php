<?php

namespace AhgLibrary\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AhgLibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-library');

        // Alias for the OPAC gate so route files can use ['opac.enabled']
        $this->app['router']->aliasMiddleware('opac.enabled', \AhgLibrary\Middleware\EnsureOpacEnabled::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgLibrary\Console\Commands\ImportLibraryCsvCommand::class,
                \AhgLibrary\Console\Commands\AutoExpireHoldsCommand::class,
                \AhgLibrary\Console\Commands\AutoExpirePatronsCommand::class,
                \AhgLibrary\Console\Commands\CalculateFinesCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                // Daily housekeeping for the circulation surface. Each command
                // guards itself on its own setting flag, so flipping a toggle in
                // /admin/ahgSettings/library is enough to silence the schedule.
                $schedule->command('ahg:library-auto-expire-holds')->dailyAt('02:30')->withoutOverlapping(60);
                $schedule->command('ahg:library-auto-expire-patrons')->dailyAt('02:45')->withoutOverlapping(60);
                $schedule->command('ahg:library-calculate-fines')->dailyAt('03:15')->withoutOverlapping(60);
            });
        }
    }
}
