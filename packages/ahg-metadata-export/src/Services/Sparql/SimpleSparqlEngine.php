<?php

/**
 * SimpleSparqlEngine - tiny pure-PHP SPARQL 1.1 SELECT evaluator scoped
 * to a single information_object's PROV-O graph. It is intentionally a
 * subset: enough to answer the typical PROV exploration queries
 *
 *     SELECT ?activity ?type
 *     WHERE { ?activity a prov:Activity ;
 *                       ahg:eventType ?type . }
 *     LIMIT 50
 *
 * without pulling easyrdf into the composer graph for Phase 4. Full
 * SPARQL 1.1 (OPTIONAL / UNION / FILTER expressions / property paths /
 * federated queries) is Phase 5 territory and would replace this
 * engine with easyrdf or rdfquery.
 *
 * Supported grammar:
 *   - PREFIX foo: <iri>
 *   - SELECT (DISTINCT)? *|?v ?v ...
 *   - WHERE { triple-pattern (. triple-pattern)* ;? }
 *   - triple-pattern: subj pred obj
 *     subj/pred/obj is: ?var | <iri> | prefix:local | "literal"
 *     pred can also be the bare keyword `a` (= rdf:type)
 *   - LIMIT N
 *
 * Output: SPARQL 1.1 Query Results JSON Format (W3C Rec).
 *   https://www.w3.org/TR/sparql11-results-json/
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMetadataExport\Services\Sparql;

class SimpleSparqlEngine
{
    public const RDF_TYPE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';

    /**
     * Built-in prefixes that callers can use without declaring.
     * Matches the PROV-O serializer namespaces.
     */
    public const DEFAULT_PREFIXES = [
        'rdf'    => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs'   => 'http://www.w3.org/2000/01/rdf-schema#',
        'xsd'    => 'http://www.w3.org/2001/XMLSchema#',
        'prov'   => 'http://www.w3.org/ns/prov#',
        'premis' => 'http://www.loc.gov/premis/rdf/v3/',
        'ahg'    => 'https://heratio.theahg.co.za/ns/ahg/',
    ];

    /** @var array<int, array{0:string,1:string,2:array{type:string,value:string}}> */
    private array $triples;

    /**
     * @param  array<int, array{0:string,1:string,2:array{type:string,value:string}}>  $triples
     */
    public function __construct(array $triples)
    {
        $this->triples = $triples;
    }

    /**
     * Run a SPARQL SELECT and return the SPARQL Results JSON document
     * as an associative array (caller json_encode()s).
     *
     * @return array{head: array{vars: array<int,string>}, results: array{bindings: array<int, array<string, array{type:string,value:string}>>}}
     */
    public function querySelect(string $query): array
    {
        [$prefixes, $remainder] = $this->extractPrefixes($query);
        $prefixes = array_merge(self::DEFAULT_PREFIXES, $prefixes);

        if (! preg_match('/SELECT\s+(DISTINCT\s+)?(.+?)\s+WHERE\s*\{(.+)\}\s*(LIMIT\s+(\d+))?\s*$/is', $remainder, $m)) {
            throw new \InvalidArgumentException('Could not parse SELECT query. Supported shape: SELECT (DISTINCT)? *|?v ... WHERE { triples } [LIMIT N]');
        }
        $distinct = ! empty($m[1]);
        $projection = trim($m[2]);
        $whereBody = trim($m[3]);
        $limit = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : null;

        $patterns = $this->parseTriplePatterns($whereBody, $prefixes);

        // Determine projection variables
        $allVars = [];
        foreach ($patterns as $p) {
            foreach ($p as $t) {
                if ($t['type'] === 'var' && ! in_array($t['value'], $allVars, true)) {
                    $allVars[] = $t['value'];
                }
            }
        }
        if ($projection === '*') {
            $vars = $allVars;
        } else {
            $vars = [];
            foreach (preg_split('/\s+/', $projection) as $tok) {
                if ($tok === '' || $tok[0] !== '?') {
                    continue;
                }
                $vars[] = substr($tok, 1);
            }
        }

        // Evaluate the basic graph pattern via nested-loop join
        $bindings = [[]];
        foreach ($patterns as $pattern) {
            $bindings = $this->joinPattern($bindings, $pattern);
            if (empty($bindings)) {
                break;
            }
        }

        // Project + DISTINCT
        $rows = [];
        $seen = [];
        foreach ($bindings as $b) {
            $row = [];
            foreach ($vars as $v) {
                if (isset($b[$v])) {
                    $row[$v] = $b[$v];
                }
            }
            if ($distinct) {
                $key = json_encode($row);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $rows[] = $row;
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        return [
            'head' => ['vars' => $vars],
            'results' => ['bindings' => $rows],
        ];
    }

    /**
     * Strip PREFIX / BASE declarations off the front and return the
     * remainder of the query.
     *
     * @return array{0: array<string,string>, 1: string}
     */
    private function extractPrefixes(string $query): array
    {
        $prefixes = [];
        $remainder = $query;
        while (preg_match('/^\s*PREFIX\s+([A-Za-z][A-Za-z0-9_-]*)\s*:\s*<([^>]+)>\s*/i', $remainder, $m)) {
            $prefixes[$m[1]] = $m[2];
            $remainder = substr($remainder, strlen($m[0]));
        }
        return [$prefixes, $remainder];
    }

    /**
     * Parse the WHERE body into a list of triple patterns. Each pattern
     * is a 3-element array of terms; each term is
     *   ['type' => 'var',     'value' => 'name']
     *   ['type' => 'uri',     'value' => 'http://...']
     *   ['type' => 'literal', 'value' => 'lex']
     *
     * Supports `.` separators between triples and `;` (predicate-object
     * list continuation reusing the previous subject). Trailing dot is
     * optional.
     *
     * @param  array<string,string>  $prefixes
     * @return array<int, array<int, array{type:string,value:string}>>
     */
    private function parseTriplePatterns(string $body, array $prefixes): array
    {
        $tokens = $this->tokenise($body);
        $patterns = [];
        $i = 0;
        $n = count($tokens);
        $lastSubject = null;
        while ($i < $n) {
            // Allow leading separators
            while ($i < $n && ($tokens[$i] === '.' || $tokens[$i] === ';')) {
                $i++;
            }
            if ($i >= $n) {
                break;
            }
            // If continuation with `;`, reuse $lastSubject; else read a fresh subject
            // Re-check: tokens[i] should be a term now. If a `;` happens to be at end
            // of a chain it's already consumed above. To support `s p1 o1 ; p2 o2`
            // we need to peek-back. Simplest: detect by tracking last separator.
            // Implementation: we set $continueSubject when we previously consumed `;`.

            $continueSubject = false;
            // Look backward through previously consumed tokens to see if there
            // was a `;` immediately before (meaning the next pattern shares the
            // previous subject). We tracked $i increments through separators
            // above, so peek at $tokens[$i-1] when applicable.
            if ($i > 0 && $tokens[$i - 1] === ';' && $lastSubject !== null) {
                $continueSubject = true;
            }

            if ($continueSubject) {
                $subj = $lastSubject;
            } else {
                $subj = $this->parseTerm($tokens[$i++], $prefixes, false);
                $lastSubject = $subj;
            }

            if ($i >= $n) {
                throw new \InvalidArgumentException('Unexpected end of WHERE body after subject');
            }
            $pred = $this->parseTerm($tokens[$i++], $prefixes, true);

            if ($i >= $n) {
                throw new \InvalidArgumentException('Unexpected end of WHERE body after predicate');
            }
            $obj = $this->parseTerm($tokens[$i++], $prefixes, false);

            $patterns[] = [$subj, $pred, $obj];
        }
        return $patterns;
    }

    /**
     * Lexer: split the WHERE body into tokens. Quoted literals stay
     * intact; `.` and `;` are separator tokens.
     *
     * @return array<int,string>
     */
    private function tokenise(string $body): array
    {
        $tokens = [];
        $len = strlen($body);
        $i = 0;
        while ($i < $len) {
            $c = $body[$i];
            if (ctype_space($c)) {
                $i++;
                continue;
            }
            if ($c === '.' || $c === ';' || $c === ',') {
                $tokens[] = $c;
                $i++;
                continue;
            }
            if ($c === '<') {
                $end = strpos($body, '>', $i + 1);
                if ($end === false) {
                    throw new \InvalidArgumentException('Unterminated IRI in WHERE body');
                }
                $tokens[] = substr($body, $i, $end - $i + 1);
                $i = $end + 1;
                continue;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c;
                $j = $i + 1;
                $buf = $quote;
                while ($j < $len) {
                    if ($body[$j] === '\\' && $j + 1 < $len) {
                        $buf .= $body[$j].$body[$j + 1];
                        $j += 2;
                        continue;
                    }
                    $buf .= $body[$j];
                    if ($body[$j] === $quote) {
                        $j++;
                        break;
                    }
                    $j++;
                }
                $tokens[] = $buf;
                $i = $j;
                continue;
            }
            // Bare word: variable (?x), prefix:local, or keyword (a)
            $j = $i;
            while ($j < $len && ! ctype_space($body[$j]) && ! in_array($body[$j], ['.', ';', ',', '<', '>', '"', "'"], true)) {
                $j++;
            }
            $tokens[] = substr($body, $i, $j - $i);
            $i = $j;
        }
        return $tokens;
    }

    /**
     * Resolve a single token into a triple term.
     *
     * @param  array<string,string>  $prefixes
     */
    private function parseTerm(string $tok, array $prefixes, bool $isPredicate): array
    {
        if ($tok === '') {
            throw new \InvalidArgumentException('Empty term');
        }
        if ($tok[0] === '?') {
            return ['type' => 'var', 'value' => substr($tok, 1)];
        }
        if ($tok[0] === '<' && substr($tok, -1) === '>') {
            return ['type' => 'uri', 'value' => substr($tok, 1, -1)];
        }
        if ($tok[0] === '"' || $tok[0] === "'") {
            $quote = $tok[0];
            $end = strrpos($tok, $quote);
            $raw = substr($tok, 1, $end - 1);
            $raw = stripcslashes($raw);
            return ['type' => 'literal', 'value' => $raw];
        }
        if ($isPredicate && $tok === 'a') {
            return ['type' => 'uri', 'value' => self::RDF_TYPE];
        }
        if (strpos($tok, ':') !== false) {
            [$pfx, $local] = explode(':', $tok, 2);
            if (! isset($prefixes[$pfx])) {
                throw new \InvalidArgumentException("Unknown prefix '$pfx:'");
            }
            return ['type' => 'uri', 'value' => $prefixes[$pfx].$local];
        }
        throw new \InvalidArgumentException("Cannot parse term '$tok'");
    }

    /**
     * Join the running bindings list against one triple pattern.
     *
     * @param  array<int, array<string, array{type:string,value:string}>>  $bindings
     * @param  array<int, array{type:string,value:string}>  $pattern
     * @return array<int, array<string, array{type:string,value:string}>>
     */
    private function joinPattern(array $bindings, array $pattern): array
    {
        $out = [];
        foreach ($bindings as $b) {
            foreach ($this->triples as $triple) {
                $delta = $this->match($pattern, $triple, $b);
                if ($delta === null) {
                    continue;
                }
                $out[] = $delta;
            }
        }
        return $out;
    }

    /**
     * Match a pattern against a triple given current bindings. Returns
     * the extended bindings on success, null on failure.
     *
     * @param  array<int, array{type:string,value:string}>  $pattern
     * @param  array{0:string,1:string,2:array{type:string,value:string}}  $triple
     * @param  array<string, array{type:string,value:string}>  $bindings
     */
    private function match(array $pattern, array $triple, array $bindings): ?array
    {
        $tripleTerms = [
            ['type' => 'uri', 'value' => $triple[0]],
            ['type' => 'uri', 'value' => $triple[1]],
            $triple[2],
        ];
        $new = $bindings;
        for ($i = 0; $i < 3; $i++) {
            $p = $pattern[$i];
            $t = $tripleTerms[$i];
            if ($p['type'] === 'var') {
                $v = $p['value'];
                if (isset($new[$v])) {
                    if ($new[$v]['type'] !== $t['type'] || $new[$v]['value'] !== $t['value']) {
                        return null;
                    }
                } else {
                    $new[$v] = $t;
                }
            } else {
                if ($p['type'] !== $t['type'] || $p['value'] !== $t['value']) {
                    return null;
                }
            }
        }
        return $new;
    }
}
