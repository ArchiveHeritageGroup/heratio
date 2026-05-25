<?php

/**
 * MetricsRegistry - PromPHP CollectorRegistry wrapper for Heratio.
 *
 * Centralises construction of the prometheus_client_php registry and picks
 * a storage adapter automatically:
 *
 *   - Redis     when cache.default = "redis" (multi-process safe; the right
 *               choice for php-fpm + worker queues sharing counters)
 *   - APCu      when the APCu extension is loaded (single-host fallback;
 *               accurate within one box, lost on php-fpm reload)
 *   - InMemory  otherwise (process-local; mostly useful for tests/CLI runs)
 *
 * Counters/Histograms/Gauges are registered lazily via getOrRegister* so
 * repeated calls on the same metric name are idempotent. Cardinality is
 * controlled by callers - see PrometheusHttpMiddleware for the route-name
 * fallback that prevents per-URL explosion.
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

namespace AhgObservability\Services;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;

class MetricsRegistry
{
    /**
     * Default HTTP latency histogram buckets (seconds). Tuned for a typical
     * Laravel web tier: most requests are well under 1s, the long tail
     * mostly matters at the 2.5-10s boundary (search, batch ingest).
     */
    public const HTTP_BUCKETS = [0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];

    /**
     * Default DB query histogram buckets (seconds). Single-query latencies
     * are typically sub-100ms; anything 500ms+ is interesting.
     */
    public const DB_BUCKETS = [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1];

    /**
     * Namespace used for all Heratio metrics. Becomes the metric prefix
     * in the rendered output (e.g. "heratio_http_requests_total").
     */
    public const NAMESPACE = 'heratio';

    private CollectorRegistry $registry;

    private Adapter $adapter;

    public function __construct(?Adapter $adapter = null)
    {
        $this->adapter = $adapter ?? self::resolveAdapter();
        $this->registry = new CollectorRegistry($this->adapter);
    }

    public function registry(): CollectorRegistry
    {
        return $this->registry;
    }

    public function adapter(): Adapter
    {
        return $this->adapter;
    }

    /**
     * Resolve the storage adapter based on the configured driver and
     * available extensions. Falls back gracefully to InMemory so a misconfig
     * never breaks request serving - at worst metrics are reset on reload.
     */
    public static function resolveAdapter(): Adapter
    {
        $driver = (string) config('observability.storage_driver', 'auto');

        if ($driver === 'auto') {
            $driver = self::autoDetectDriver();
        }

        return match ($driver) {
            'redis'    => self::buildRedisAdapter(),
            'apcu'     => extension_loaded('apcu') ? new APC : new InMemory,
            'inmemory' => new InMemory,
            default    => new InMemory,
        };
    }

    private static function autoDetectDriver(): string
    {
        if ((string) config('cache.default') === 'redis') {
            return 'redis';
        }
        if (extension_loaded('apcu') && function_exists('apcu_fetch')) {
            return 'apcu';
        }

        return 'inmemory';
    }

    private static function buildRedisAdapter(): Adapter
    {
        // Pull host/port from cache.stores.redis or database.redis.default so
        // operators don't have to duplicate the Redis URL. Fall back to a
        // sensible localhost default that matches a standard install.
        $redisCfg = (array) config('database.redis.default', []);
        $host = (string) ($redisCfg['host'] ?? '127.0.0.1');
        $port = (int) ($redisCfg['port'] ?? 6379);
        $password = $redisCfg['password'] ?? null;
        $database = (int) ($redisCfg['database'] ?? 0);

        try {
            return new Redis([
                'host'     => $host,
                'port'     => $port,
                'password' => $password === '' ? null : $password,
                'database' => $database,
                'timeout'  => 0.5,
                'read_timeout' => 1.0,
            ]);
        } catch (\Throwable $e) {
            // Redis configured but unreachable at boot - degrade rather
            // than 500 every request that touches the registry.
            return new InMemory;
        }
    }

    public function counter(string $name, string $help, array $labelNames = []): Counter
    {
        return $this->registry->getOrRegisterCounter(self::NAMESPACE, $name, $help, $labelNames);
    }

    public function histogram(string $name, string $help, array $labelNames = [], ?array $buckets = null): Histogram
    {
        return $this->registry->getOrRegisterHistogram(self::NAMESPACE, $name, $help, $labelNames, $buckets);
    }

    public function gauge(string $name, string $help, array $labelNames = []): Gauge
    {
        return $this->registry->getOrRegisterGauge(self::NAMESPACE, $name, $help, $labelNames);
    }
}
