<?php

/**
 * LogRedactor - Service for Heratio
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

namespace AhgCore\Support;

/**
 * #1395(E) — strip credential-bearing query-string values before a URL is
 * persisted to a log/telemetry sink. Some clients pass an API key as `?api=…`
 * (a documented shape for the public + OAI APIs); left raw, that key lands in
 * the request-trace span and `ahg_error_log.url`, recoverable by anyone with
 * log access. This redacts the VALUE of sensitive params while preserving the
 * rest of the URL for debugging.
 *
 * (Application logs only. The web server's own access log still records the
 * raw request line — configure nginx to drop the query string on the OAI/API
 * locations if that surface also matters.)
 */
class LogRedactor
{
    /** Query-parameter names whose values must never be logged. */
    private const SENSITIVE = 'api|api_key|apikey|key|token|access_token|auth|password|passwd|secret|signature|sig|bearer';

    public static function url(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/([?&])('.self::SENSITIVE.')=([^&#]*)/i',
            static fn (array $m): string => $m[1].$m[2].'=[REDACTED]',
            $url
        );
    }
}
