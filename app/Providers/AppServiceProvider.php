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
            $router->get('/z3950', [\AhgZ3950\Controllers\Z3950Controller::class, 'index'])
                ->name('z3950.index');
            $router->middleware(['web', 'auth'])->group(function () use ($router) {
                $router->get('/z3950/search', [\AhgZ3950\Controllers\Z3950Controller::class, 'search'])->name('z3950.search');
                $router->post('/z3950/search', [\AhgZ3950\Controllers\Z3950Controller::class, 'searchRun'])->name('z3950.search-run');
                $router->get('/z3950/result/{resultSet}', [\AhgZ3950\Controllers\Z3950Controller::class, 'result'])->name('z3950.result');
                $router->get('/z3950/import/{resultSet}/{recordNumber}', [\AhgZ3950\Controllers\Z3950Controller::class, 'import'])->name('z3950.import');
                $router->post('/z3950/import', [\AhgZ3950\Controllers\Z3950Controller::class, 'importBatch'])->name('z3950.import-batch');
                $router->get('/z3950/admin', [\AhgZ3950\Controllers\Z3950Controller::class, 'admin'])->name('z3950.admin');
                $router->get('/z3950/target/create', [\AhgZ3950\Controllers\Z3950Controller::class, 'createTarget'])->name('z3950.target.create');
                $router->post('/z3950/target', [\AhgZ3950\Controllers\Z3950Controller::class, 'storeTarget'])->name('z3950.target.store');
                $router->delete('/z3950/target/{id}', [\AhgZ3950\Controllers\Z3950Controller::class, 'deleteTarget'])->name('z3950.target.delete');
            });

            // Demo-site blog / articles. `/articles` is a single top-level
            // segment, so it must be pre-registered here to beat the locked
            // `/{slug}` catch-all. Admin routes are grouped here for cohesion
            // (the `admin` prefix is already outside the catch-all).
            $router->middleware(['web'])->group(function () use ($router) {
                $router->get('/articles', [\App\Http\Controllers\BlogController::class, 'index'])->name('articles.index');
                $router->get('/articles/{slug}', [\App\Http\Controllers\BlogController::class, 'show'])
                    ->where('slug', '[a-z0-9][a-z0-9-]*')->name('articles.show');
            });
            $router->middleware(['web', 'auth'])->prefix('admin/articles')->name('admin.articles.')->group(function () use ($router) {
                $router->get('/', [\App\Http\Controllers\Admin\BlogAdminController::class, 'index'])->name('index');
                $router->get('/create', [\App\Http\Controllers\Admin\BlogAdminController::class, 'create'])->name('create');
                $router->post('/', [\App\Http\Controllers\Admin\BlogAdminController::class, 'store'])->name('store');
                $router->post('/upload-image', [\App\Http\Controllers\Admin\BlogAdminController::class, 'uploadImage'])->name('upload-image');
                $router->get('/{id}/edit', [\App\Http\Controllers\Admin\BlogAdminController::class, 'edit'])->where('id', '[0-9]+')->name('edit');
                $router->put('/{id}', [\App\Http\Controllers\Admin\BlogAdminController::class, 'update'])->where('id', '[0-9]+')->name('update');
                $router->delete('/{id}', [\App\Http\Controllers\Admin\BlogAdminController::class, 'destroy'])->where('id', '[0-9]+')->name('destroy');
            });
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
            $username = (string) $request->input('username', '');

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
