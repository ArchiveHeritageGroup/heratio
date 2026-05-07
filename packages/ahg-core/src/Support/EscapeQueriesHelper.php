<?php

/**
 * EscapeQueriesHelper - Heratio
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
 * Escape user-typed search input before it reaches Lucene/ES query_string or
 * MySQL LIKE clauses. Gated on GlobalSettings::escapeQueries() (operator
 * setting on /admin/settings/global, default on).
 *
 * When the setting is on (default), Lucene/ES reserved characters and MySQL
 * LIKE wildcards are backslash-escaped so user input is treated as literal
 * text - the setting protects against query-syntax errors crashing the
 * search and against accidental wildcard-injection from a stray `*` in a
 * pasted query.
 *
 * When the setting is off, the input is returned verbatim so power users
 * can use Lucene operators (`+term -term "phrase" field:value AND OR`).
 *
 * Closes #111.
 */
class EscapeQueriesHelper
{
    /**
     * Lucene / Elasticsearch query_string reserved characters. The order
     * matters - backslash MUST be escaped first or the subsequent escapes
     * would double-escape the leading backslash.
     *
     * Reference: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html#_reserved_characters
     */
    private const LUCENE_SPECIAL = [
        '\\', '+', '-', '&&', '||', '!', '(', ')', '{', '}',
        '[', ']', '^', '"', '~', '*', '?', ':', '/',
    ];

    public static function escapeForElasticsearch(string $raw): string
    {
        if (!GlobalSettings::escapeQueries()) {
            return $raw;
        }

        $replace = array_map(static fn (string $c): string => '\\' . $c, self::LUCENE_SPECIAL);

        return str_replace(self::LUCENE_SPECIAL, $replace, $raw);
    }

    /**
     * Escape MySQL LIKE wildcards (% and _) so user input is matched
     * literally. The caller is responsible for wrapping the result in
     * `%...%` for substring search.
     */
    public static function escapeForLike(string $raw): string
    {
        if (!GlobalSettings::escapeQueries()) {
            return $raw;
        }

        return addcslashes($raw, '\\%_');
    }
}
