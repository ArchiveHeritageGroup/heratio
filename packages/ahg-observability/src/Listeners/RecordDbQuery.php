<?php

/**
 * RecordDbQuery - subscribe to QueryExecuted and record DB metrics.
 *
 * Increments:
 *   heratio_db_queries_total{connection}
 *
 * Observes:
 *   heratio_db_query_duration_seconds_bucket{connection, le}
 *
 * The QueryExecuted event reports time in milliseconds; we divide by 1000
 * to express seconds (Prometheus convention).
 *
 * Cardinality control: we label only by connection name, NOT by SQL text
 * or table name. Per-statement labels would blow up cardinality and are
 * the wrong tool - high-cardinality query inspection belongs in tracing
 * (Phase 4), not metrics.
 *
 * Defensive: a registry failure must not propagate out of the handler -
 * we'd be aborting the request over a metrics blip. Swallow + carry on.
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

namespace AhgObservability\Listeners;

use AhgObservability\Services\MetricsRegistry;
use Illuminate\Database\Events\QueryExecuted;

class RecordDbQuery
{
    public function __construct(private readonly MetricsRegistry $metrics) {}

    public function handle(QueryExecuted $event): void
    {
        try {
            $connection = (string) ($event->connectionName ?? 'default');
            $seconds = ((float) $event->time) / 1000.0;

            $this->metrics
                ->counter('db_queries_total', 'Total database queries executed', ['connection'])
                ->inc([$connection]);

            $this->metrics
                ->histogram(
                    'db_query_duration_seconds',
                    'Database query duration in seconds',
                    ['connection'],
                    MetricsRegistry::DB_BUCKETS
                )
                ->observe($seconds, [$connection]);
        } catch (\Throwable $e) {
            // Metrics MUST NOT break query execution. The listener fires
            // after the query has already returned data; the request
            // continues regardless.
        }
    }
}
