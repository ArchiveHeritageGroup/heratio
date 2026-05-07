<?php

namespace AhgPrivacy\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AhgPrivacyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'privacy');

        // #72: middleware alias used by routes/web.php to gate /admin/privacy/*
        // on the dp_enabled master toggle.
        $router = $this->app['router'];
        if ($router instanceof Router) {
            $router->aliasMiddleware('dp.enabled', \AhgPrivacy\Middleware\EnsureDataProtectionEnabled::class);
        }

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
