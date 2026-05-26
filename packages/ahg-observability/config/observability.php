<?php

/**
 * observability.php - Configuration for the Heratio Prometheus exporter.
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

return [

    /*
    |--------------------------------------------------------------------------
    | Bearer token for the /metrics endpoint
    |--------------------------------------------------------------------------
    |
    | When non-empty, the request must present `Authorization: Bearer <token>`
    | to reach the renderer. Combined with `allowed_ips` using OR semantics:
    | a request that satisfies EITHER the token check OR the IP allow-list
    | will be accepted. When both are empty / wide-open the endpoint will
    | refuse all traffic - this is intentional fail-closed behaviour.
    |
    */
    'token' => env('OBSERVABILITY_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | IP allow-list for the /metrics endpoint
    |--------------------------------------------------------------------------
    |
    | List of client IPs (or CIDRs in the future) that may scrape /metrics
    | without presenting a bearer token. Default = loopback only, which is
    | the typical Prometheus-on-same-host posture.
    |
    */
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('OBSERVABILITY_ALLOWED_IPS', '127.0.0.1,::1')
    )))),

    /*
    |--------------------------------------------------------------------------
    | Storage driver
    |--------------------------------------------------------------------------
    |
    | "auto"     - choose Redis if cache.default=redis, else APCu if loaded,
    |              else InMemory (process-local, drops on reload).
    | "redis"    - force the Redis adapter (requires phpredis + cache.stores.redis).
    | "apcu"     - force the APCu adapter (requires ext-apcu).
    | "inmemory" - process-local; useful for tests and CLI runs.
    |
    */
    'storage_driver' => env('OBSERVABILITY_STORAGE_DRIVER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Queue connections / queues to sample for queue_depth gauge
    |--------------------------------------------------------------------------
    |
    | Each entry is ['connection' => '<name>', 'queue' => '<queue>'].
    | The connection defaults to config('queue.default') when null/empty.
    | The queue name defaults to 'default'.
    |
    */
    'queues' => [
        ['connection' => null, 'queue' => 'default'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prometheus textfile collector directory
    |--------------------------------------------------------------------------
    |
    | Directory that node_exporter is configured to scan for *.prom files via
    | its `--collector.textfile.directory=<dir>` flag. The
    | `ai-compliance:emit-metrics` command writes
    | `heratio_ai_compliance.prom` here so the synthetic
    | `ai_compliance_verify_status` gauge (1=PASS, 0=FAIL) can drive the
    | InferenceChainBroken alert (see config/alerts/heratio.rules.yml).
    |
    | Default `/var/lib/node_exporter/textfile_collector` matches the
    | upstream Debian package; override via env on hosts that put it
    | elsewhere.
    |
    */
    'textfile_dir' => env('OBSERVABILITY_TEXTFILE_DIR', '/var/lib/node_exporter/textfile_collector'),
];
