<?php

namespace App\Providers;

use App\Auth\AtomUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom Heratio authentication provider
        Auth::provider('atom', function ($app, array $config) {
            return new AtomUserProvider();
        });

        // Schedule the security housekeeping commands (closes audit issue #90).
        // auth:gc-attempts trims login_attempt rows older than the configured
        // retention; auth:warn-password-expiry emails users whose password is
        // about to expire. Both honour their respective ahg_settings keys
        // and short-circuit when disabled.
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\AuthGcAttemptsCommand::class,
                \App\Console\Commands\AuthWarnPasswordExpiryCommand::class,
            ]);

            $this->app->afterResolving(\Illuminate\Console\Scheduling\Schedule::class, function (\Illuminate\Console\Scheduling\Schedule $schedule) {
                $schedule->command('auth:gc-attempts')->hourly()->withoutOverlapping();
                $schedule->command('auth:warn-password-expiry')->dailyAt('06:00')->withoutOverlapping();
            });
        }
    }
}
