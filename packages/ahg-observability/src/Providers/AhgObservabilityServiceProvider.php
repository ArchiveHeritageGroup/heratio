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

use AhgObservability\Console\Commands\EmitAiComplianceMetricsCommand;
use AhgObservability\Console\Commands\RecordQueueDepthCommand;
use AhgObservability\Listeners\RecordDbQuery;
use AhgObservability\Services\MetricsRegistry;
use AhgObservability\Tracing\Listeners\TraceDbQuery;
use AhgObservability\Tracing\Listeners\TraceHttpClient;
use AhgObservability\Tracing\TracerProvider as HeratioTracerProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\ConnectionFailed as HttpClientConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending as HttpClientRequestSending;
use Illuminate\Http\Client\Events\ResponseReceived as HttpClientResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;

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

        // ---- Phase 5: OpenTelemetry tracer (#677) ----
        //
        // One process-wide TracerProvider, lazily built. The Trace helper
        // and TraceMiddleware pull `otel.tracer` from the container.
        $this->app->singleton('otel.tracerprovider', function ($app) {
            $config = (array) $app['config']->get('observability', []);
            $version = $this->readAppVersion();

            return HeratioTracerProvider::build($config, $version);
        });

        $this->app->singleton(TracerProviderInterface::class, fn ($app) => $app->make('otel.tracerprovider'));

        $this->app->singleton('otel.tracer', function ($app) {
            $provider = $app->make('otel.tracerprovider');

            return $provider->getTracer('ahg/observability', $this->readAppVersion());
        });

        $this->app->singleton(TracerInterface::class, fn ($app) => $app->make('otel.tracer'));
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

        // ---- Phase 5 tracing wiring (#677) ----
        //
        // We only actually subscribe the tracing listeners when the
        // exporter is configured to do something; otherwise the listener
        // would be invoked on every query/HTTP call and do a noop trip
        // through the helper. Cheap, but pointless.
        $exporter = strtolower((string) config('observability.otel_exporter', 'null'));
        $tracingEnabled = ! in_array($exporter, ['', 'null', 'noop', 'off'], true);

        if ($tracingEnabled) {
            $slowMs = (int) config('observability.otel_db_slow_query_ms', 50);
            $this->app->singleton(TraceDbQuery::class, fn () => new TraceDbQuery($slowMs));
            Event::listen(QueryExecuted::class, [TraceDbQuery::class, 'handle']);

            if ((bool) config('observability.otel_http_client_enabled', true)
                && class_exists(HttpClientRequestSending::class)) {
                $this->app->singleton(TraceHttpClient::class);
                Event::listen(HttpClientRequestSending::class, [TraceHttpClient::class, 'handleSending']);
                Event::listen(HttpClientResponseReceived::class, [TraceHttpClient::class, 'handleReceived']);
                Event::listen(HttpClientConnectionFailed::class, [TraceHttpClient::class, 'handleFailed']);
            }

            // Flush spans on app shutdown so CLI / queue runs get their
            // traces exported even when there's no terminate() hook.
            $this->app->terminating(function () {
                try {
                    $provider = $this->app->make('otel.tracerprovider');
                    if (method_exists($provider, 'shutdown')) {
                        $provider->shutdown();
                    } elseif (method_exists($provider, 'forceFlush')) {
                        $provider->forceFlush();
                    }
                } catch (\Throwable) {
                    // never let exporter shutdown bubble
                }
            });
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                RecordQueueDepthCommand::class,
                EmitAiComplianceMetricsCommand::class,
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

    /**
     * Read the running Heratio version from version.json. Falls back to
     * "0.0.0-dev" when the file is missing or unparseable - traces will
     * still attribute on service.version, just with a sentinel value.
     */
    protected function readAppVersion(): string
    {
        try {
            $path = base_path('version.json');
            if (! is_file($path)) {
                return '0.0.0-dev';
            }
            $json = json_decode((string) file_get_contents($path), true);
            $v = is_array($json) ? ($json['version'] ?? null) : null;

            return is_string($v) && $v !== '' ? $v : '0.0.0-dev';
        } catch (\Throwable) {
            return '0.0.0-dev';
        }
    }
}
