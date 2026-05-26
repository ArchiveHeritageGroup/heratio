<?php

/**
 * TraceDbQuery - emit a child span for each slow DB query.
 *
 * Phase 5 of issue #677.
 *
 * Listens for Illuminate\Database\Events\QueryExecuted and, when the
 * query exceeded `observability.otel_db_slow_query_ms`, opens and
 * immediately closes a `db.query` span under whatever span is currently
 * active (normally the request-level http.server.request span).
 *
 * Attributes follow the OTel semantic conventions for databases:
 *   - db.system       = "mysql"
 *   - db.name         = connection database
 *   - db.connection   = connection name
 *   - db.statement    = first 200 chars of the SQL
 *   - db.statement.sha256 = sha256 of the full statement (so identical
 *                          queries fingerprint the same in the trace UI
 *                          without ballooning the statement text)
 *
 * Defensive: a tracer failure must not break the query path - we already
 * fired the QueryExecuted event AFTER the query returned, but a thrown
 * exception here would still bubble into the request. Swallow + drop.
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

namespace AhgObservability\Tracing\Listeners;

use AhgObservability\Tracing\Trace;
use Illuminate\Database\Events\QueryExecuted;

class TraceDbQuery
{
    public function __construct(private readonly int $slowQueryMs = 50) {}

    public function handle(QueryExecuted $event): void
    {
        try {
            // Filter out cheap queries to keep span volume reasonable.
            // Setting slow_query_ms=0 records every query.
            if ($this->slowQueryMs > 0 && (float) $event->time < $this->slowQueryMs) {
                return;
            }

            $sql = (string) $event->sql;
            $truncated = mb_strlen($sql) > 200 ? mb_substr($sql, 0, 200).'...' : $sql;

            $attrs = [
                'db.system'            => 'mysql',
                'db.connection'        => (string) ($event->connectionName ?? 'default'),
                'db.statement'         => $truncated,
                'db.statement.sha256'  => hash('sha256', $sql),
                'db.duration_ms'       => (float) $event->time,
            ];

            // db.name from the connection's database name (when available).
            try {
                $dbName = $event->connection->getDatabaseName();
                if (! empty($dbName)) {
                    $attrs['db.name'] = (string) $dbName;
                }
            } catch (\Throwable) {
                // some test connections don't expose getDatabaseName()
            }

            // The query has already completed; we open + immediately close
            // a span with the correct duration backdated via attributes.
            // OTel doesn't let us back-date the span start time portably,
            // so we use a zero-duration span carrying the real duration as
            // an attribute. This is the same trade-off used by the official
            // PHP DB instrumentation packages.
            $span = Trace::start('db.query', $attrs);
            Trace::end($span);
        } catch (\Throwable) {
            // Tracing MUST NOT break query execution.
        }
    }
}
