<?php

/**
 * AhgObservabilityServiceProvider - Service provider for AHG Observability.
 *
 * Wires the Prometheus exporter into Heratio:
 *   - registers MetricsRegistry as a singleton (so all metric pushes hit
 *     the same in-memory collector during a request)
 *   - merges the observability config so env() overrides work
 *   - subscribes RecordDbQuery to QueryExecuted
 *   - loads the /metrics route (no middleware - controller handles auth)
 *   - registers the observability:record-queue-depth artisan command
 *   - schedules the queue-depth sampler every minute
 *
 * Note: the request-level PrometheusHttpMiddleware is NOT auto-registered
 * here. The host application registers it in bootstrap/app.php so it can
 * be placed at the right point in the global middleware stack (after the
 * request_id middleware, before auth).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgObservability\Providers;

use AhgObservability\Console\Commands\RecordQueueDepthCommand;
use AhgObservability\Listeners\RecordDbQuery;
use AhgObservability\Services\MetricsRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AhgObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/observability.php', 'observability');

        // Singleton: one CollectorRegistry per request. The Redis/APCu
        // adapters are themselves multi-process safe, so reusing one
        // registry instance inside a request is what we want.
        $this->app->singleton(MetricsRegistry::class, function () {
            return new MetricsRegistry;
        });
    }

    public function boot(): void
    {
        // /metrics route is registered WITHOUT the 'web' middleware group
        // so it doesn't carry session cookies / CSRF tokens / locale
        // resolution. The controller authenticates by bearer token or IP.
        Route::group([], __DIR__.'/../../routes/web.php');

        // QueryExecuted fires on every DB query (including raw selects) -
        // subscribing it once at boot is cheaper than per-request wiring.
        Event::listen(QueryExecuted::class, [RecordDbQuery::class, 'handle']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RecordQueueDepthCommand::class,
            ]);

            // Sample queue depth every minute. withoutOverlapping() keeps
            // a long-running ->size() call (rare, but possible on a slow
            // database driver) from doubling up at the next tick.
            $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('observability:record-queue-depth')
                    ->everyMinute()
                    ->withoutOverlapping();
            });

            // Make the config publishable for operators who want to tune
            // buckets / IP allow-list outside env().
            $this->publishes([
                __DIR__.'/../../config/observability.php' => config_path('observability.php'),
            ], 'observability-config');
        }
    }
}
