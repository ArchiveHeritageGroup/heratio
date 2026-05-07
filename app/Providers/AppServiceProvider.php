<?php

namespace App\Providers;

use App\Auth\AtomUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

        // #47 login brute-force rate-limit. Per-IP throttle: 5 attempts in
        // any 60-second window, then 429 for the rest of the minute. The
        // limit is identity-keyed by remote IP so a single attacker can't
        // burn through guesses; legitimate users at the same IP (e.g.
        // shared NAT) collectively share the budget but 5/min is generous
        // for human typing speed.
        // Defence-in-depth: nginx limit_req_zone is the recommended host-
        // side companion (per the issue body) - this provides the
        // application-layer throttle that survives nginx-bypass and works
        // across multi-frontend deployments. Both layers honour different
        // identity keys (nginx by source IP, Laravel by IP+username here)
        // so an attacker who rotates IPs still hits the username throttle.
        RateLimiter::for('login', function (Request $request) {
            $ip = (string) $request->ip();
            $username = (string) $request->input('username', '');
            return [
                Limit::perMinute(5)->by('login-ip:' . $ip),
                Limit::perMinute(5)->by('login-user:' . strtolower($username)),
            ];
        });
        RateLimiter::for('passwordReset', function (Request $request) {
            // Same shape but tighter - password reset emails cost real
            // money on transactional providers + reset tokens are a
            // credential-stuffing target.
            return Limit::perMinute(3)->by('pwreset:' . $request->ip());
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
