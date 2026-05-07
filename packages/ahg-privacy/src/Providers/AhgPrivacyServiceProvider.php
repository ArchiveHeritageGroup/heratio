<?php

namespace AhgPrivacy\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AhgPrivacyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'privacy');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgPrivacy\Console\Commands\CheckOverdueDsarsCommand::class,
            ]);

            // Daily 09:00 sweep — the command itself short-circuits when
            // dp_notify_overdue=false or dp_notify_email is empty, so this
            // schedule entry is safe to enable unconditionally.
            $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('privacy:check-overdue-dsars')
                    ->dailyAt('09:00')
                    ->withoutOverlapping();
            });
        }
    }
}
