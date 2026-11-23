<?php

/**
 * TripleStore - store-neutral view of the RiC SPARQL endpoint (heratio#1518).
 *
 * Fuseki is the default and its URLs and queries pass through unchanged.
 * The 'qlever' driver only changes how URLs are built; selecting it does not
 * by itself make every integration work (see the notes on heratio#1518).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
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

namespace AhgRic\Support;

use InvalidArgumentException;

class TripleStore
{
    private const TOKENS = '~\#[^\n]*'
        .'|\"\"\"[^\"\\\\]*+(?:(?:\\\\.|\"(?!\"\"))[^\"\\\\]*+)*+\"\"\"'
        ."|'''[^'\\\\]*+(?:(?:\\\\.|'(?!''))[^'\\\\]*+)*+'''"
        .'|\"[^\"\\\\\n]*+(?:\\\\.[^\"\\\\\n]*+)*+\"'
        ."|'[^'\\\\\n]*+(?:\\\\.[^'\\\\\n]*+)*+'"
        .'|<[^\s<>\"{}|\\\\^`]*>'
        .'|[?$]\w+'
        .'|[\w.-]*:[\w.%-]*'
        .'|[A-Za-z_]\w*'
        .'|[{}]~su';

    public static function driver(): string
    {
        return config('ahg-ric.triplestore.driver', 'fuseki') === 'qlever' ? 'qlever' : 'fuseki';
    }

    /** Fuseki keeps the caller's service path; QLever serves SPARQL at its root. */
    public static function queryUrl(string $base, string $fusekiPath = '/query'): string
    {
        return self::driver() === 'qlever' ? rtrim($base, '/') : $base.$fusekiPath;
    }

    public static function updateUrl(string $base): string
    {
        return self::driver() === 'qlever' ? rtrim($base, '/') : $base.'/update';
    }

    /** QLever has no Graph Store Protocol endpoint, which the /data loaders need. */
    public static function supportsGraphStore(): bool
    {
        return self::driver() === 'fuseki';
    }

    /**
     * Limit a read query to the instance graph when one is configured.
     *
     * A store whose default graph is the union of all graphs would otherwise
     * also match the vocabulary and other named graphs. With no graph
     * configured the query is returned byte for byte. Queries that name their
     * own dataset or use GRAPH are left alone, because adding FROM would empty
     * their named-graph set. Updates are never changed.
     */
    public static function scope(string $sparql): string
    {
        $graph = trim((string) config('ahg-ric.triplestore.instance_graph', ''));
        if ($graph === '') {
            return $sparql;
        }
        if (! preg_match('/^[a-z][a-z0-9+.-]*:[^\x00-\x20<>"{}|\\\\^`]*$/i', $graph)) {
            throw new InvalidArgumentException('ahg-ric.triplestore.instance_graph is not a valid IRI.');
        }

        $at = self::datasetClauseOffset($sparql);

        return $at === null
            ? $sparql
            : substr($sparql, 0, $at)."\nFROM <{$graph}>\n".substr($sparql, $at);
    }

    /** Byte offset for a FROM clause, or null when the query must stay as it is. */
    private static function datasetClauseOffset(string $sparql): ?int
    {
        // A failed tokenise must not pass as a query with no GRAPH or FROM.
        if (preg_match_all(self::TOKENS, $sparql, $matches, PREG_OFFSET_CAPTURE) === false) {
            throw new InvalidArgumentException('SPARQL query could not be tokenised: '.preg_last_error_msg());
        }

        $form = null;
        $at = null;
        $depth = 0;
        $groups = 0;

        foreach ($matches[0] as [$token, $offset]) {
            if ($token === '{') {
                if ($depth === 0 && $form !== null && $at === null) {
                    $groups++;
                    // The first group of a CONSTRUCT is its template.
                    if ($form !== 'CONSTRUCT' || $groups === 2) {
                        $at = $offset;
                    }
                }
                $depth++;

                continue;
            }
            if ($token === '}') {
                $depth = max(0, $depth - 1);

                continue;
            }

            $word = strtoupper($token);
            if ($word === 'GRAPH' || $word === 'FROM') {
                return null;
            }
            if ($depth !== 0) {
                continue;
            }
            if ($form === null) {
                if (in_array($word, ['SELECT', 'ASK', 'CONSTRUCT', 'DESCRIBE'], true)) {
                    $form = $word;
                } elseif (in_array($word, ['INSERT', 'DELETE', 'LOAD', 'CLEAR', 'CREATE', 'DROP', 'COPY', 'MOVE', 'ADD', 'WITH'], true)) {
                    return null;
                }
            } elseif ($at === null && in_array($word, ['WHERE', 'ORDER', 'GROUP', 'HAVING', 'LIMIT', 'OFFSET', 'VALUES'], true)) {
                $at = $offset;
            }
        }

        if ($form === null) {
            return null;
        }

        // A bare DESCRIBE <iri> has no group to anchor on.
        return $at ?? ($form === 'DESCRIBE' ? strlen($sparql) : null);
    }
}
