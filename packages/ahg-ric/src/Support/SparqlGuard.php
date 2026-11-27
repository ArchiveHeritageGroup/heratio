<?php

/**
 * SparqlGuard - what the RiC SPARQL endpoints refuse, judged on the query's
 * syntax rather than its raw text.
 *
 * Strings, IRIs and # comments are blanked first in one left-to-right pass, so
 * a "#" inside an IRI (rico:...#Record) cannot hide the rest of a line and a
 * keyword inside a literal is not mistaken for an operation. Then:
 * - SERVICE is refused: it makes the triple store fetch an address chosen by
 *   the caller (federated query, 26 Nov 2026).
 * - Update operations are refused; only SELECT, ASK, CONSTRUCT and DESCRIBE run.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgRic\Support;

class SparqlGuard
{
    private const BLANK = '/"""(?:[^"\\\\]|\\\\.|"(?!""))*"""'
        ."|'''(?:[^'\\\\]|\\\\.|'(?!''))*'''"
        .'|"(?:[^"\\\\\n]|\\\\.)*"'
        ."|'(?:[^'\\\\\n]|\\\\.)*'"
        .'|<[^<>"{}|^`\\\\\s]*>'
        .'|#[^\n]*/s';

    /** The query with strings, IRIs and comments replaced by a space. */
    public static function syntax(string $query): string
    {
        return (string) preg_replace(self::BLANK, ' ', $query);
    }

    /**
     * Why the query is refused, or null when it may run.
     *
     * @return array{0:int,1:string}|null [HTTP status, message]
     */
    public static function refusal(string $query): ?array
    {
        $s = self::syntax($query);
        $kw = fn (string $words) => (bool) preg_match('/(?<![\w:?$])(?:'.$words.')(?![\w:-])/i', $s);
        if ($kw('SERVICE')) {
            return [403, 'service-not-permitted: federated SERVICE queries are not allowed on this endpoint.'];
        }
        if ($kw('INSERT|DELETE|LOAD|DROP|CLEAR|CREATE|MOVE|COPY|ADD')) {
            return [403, 'Update operations are not permitted on this endpoint.'];
        }
        if (! $kw('SELECT|ASK|CONSTRUCT|DESCRIBE')) {
            return [400, 'Only SELECT / ASK / CONSTRUCT / DESCRIBE queries are accepted.'];
        }

        return null;
    }
}
