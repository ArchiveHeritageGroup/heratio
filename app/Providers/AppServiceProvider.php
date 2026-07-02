<?php

namespace App\Providers;

use App\Auth\AtomUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The locked information-object slug catch-all `/{slug}` (in
        // ahg-information-object-manage) intercepts any top-level word not
        // in its exclusion regex. Routes whose package sorts alphabetically
        // AFTER `ahg-information-object-manage` therefore lose to the
        // catch-all even when they are literal. Pre-register those routes
        // here in register() so they win on registration order.
        $this->callAfterResolving('router', function ($router) {
            $router->get('/sru', [\AhgZ3950\Controllers\SruController::class, 'handle'])
                ->name('sru.handle');
            // #1379: the z3950 dashboard lists configured remote-target host/port/db —
            // staff-only, was anon-public. Gate under web+auth (was bare).
            $router->get('/z3950', [\AhgZ3950\Controllers\Z3950Controller::class, 'index'])
                ->middleware(['web', 'auth'])->name('z3950.index');
            // /metrics (ahg-observability Prometheus scrape) — bare, NO 'web'
            // middleware (controller auths via bearer token / allow-listed IP).
            // Pre-registered here so it beats the slug catch-all, which would
            // otherwise eat /metrics and 404 it (#1137). Unnamed to avoid a
            // route-name collision with the package's own 'observability.metrics'.
            $router->get('/metrics', [\AhgObservability\Http\Controllers\MetricsController::class, 'show']);
            // Open-source credits / licenses page - the AGPL "Appropriate Legal
            // Notices" surface (sec 5d) crediting AtoM / Artefactual, the ICA,
            // and the libraries Heratio builds on, plus the network-use source
            // offer (sec 13). Single top-level segment, so pre-registered here
            // to beat the slug catch-all. Public (web middleware for the view).
            $router->get('/credits', [\App\Http\Controllers\CreditsController::class, 'show'])
                ->middleware('web')->name('credits');
            $router->middleware(['web', 'auth'])->group(function () use ($router) {
                $router->get('/z3950/search', [\AhgZ3950\Controllers\Z3950Controller::class, 'search'])->name('z3950.search');
                $router->get('/z3950/result/{resultSet}', [\AhgZ3950\Controllers\Z3950Controller::class, 'result'])->name('z3950.result');
                // #1379: remote search execution, MARC import, target CRUD + the admin
                // dashboard are admin operations (help claims admin-only; was any authed
                // user). Add 'admin' (RequireAdmin) on top of the group's web+auth.
                $router->post('/z3950/search', [\AhgZ3950\Controllers\Z3950Controller::class, 'searchRun'])->middleware('admin')->name('z3950.search-run');
                $router->get('/z3950/import/{resultSet}/{recordNumber}', [\AhgZ3950\Controllers\Z3950Controller::class, 'import'])->middleware('admin')->name('z3950.import');
                $router->post('/z3950/import', [\AhgZ3950\Controllers\Z3950Controller::class, 'importBatch'])->middleware('admin')->name('z3950.import-batch');
                $router->get('/z3950/admin', [\AhgZ3950\Controllers\Z3950Controller::class, 'admin'])->middleware('admin')->name('z3950.admin');
                $router->get('/z3950/target/create', [\AhgZ3950\Controllers\Z3950Controller::class, 'createTarget'])->middleware('admin')->name('z3950.target.create');
                $router->post('/z3950/target', [\AhgZ3950\Controllers\Z3950Controller::class, 'storeTarget'])->middleware('admin')->name('z3950.target.store');
                $router->delete('/z3950/target/{id}', [\AhgZ3950\Controllers\Z3950Controller::class, 'deleteTarget'])->middleware('admin')->name('z3950.target.delete');
            });

            // Articles / news routes moved to packages/ahg-articles
            // (AhgArticlesServiceProvider), which registers them via the same
            // callAfterResolving('router') mechanism for /{slug} catch-all precedence.
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Bootstrap-5 pagination markup for Laravel paginator ->links()
        // (the blog/articles index uses it; nothing else in the app does).
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        // Behind a port-mapped container (Docker test stack: host :8088 -> nginx
        // :80) Laravel builds absolute URLs/redirects from the *internal* port,
        // dropping :8088 and breaking redirects. When FORCE_ROOT_URL is set we
        // pin URL generation to APP_URL. Inert everywhere the flag is unset
        // (metal installs / production never set it), so this is Docker-only.
        if (filter_var(env('FORCE_ROOT_URL', false), FILTER_VALIDATE_BOOLEAN) && config('app.url')) {
            \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }

        // Register custom Heratio authentication provider
        Auth::provider('atom', function ($app, array $config) {
            return new AtomUserProvider;
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
            // #1395(E) — the login form posts 'email' (LoginController reads
            // input('email')); the previous input('username') was always empty,
            // collapsing the per-account limiter into one shared bucket.
            $username = (string) $request->input('email', '');

            return [
                Limit::perMinute(5)->by('login-ip:'.$ip),
                Limit::perMinute(5)->by('login-user:'.strtolower($username)),
            ];
        });
        RateLimiter::for('passwordReset', function (Request $request) {
            // Same shape but tighter - password reset emails cost real
            // money on transactional providers + reset tokens are a
            // credential-stuffing target.
            return Limit::perMinute(3)->by('pwreset:'.$request->ip());
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
