<?php

/**
 * SparqlUpdateService - SPARQL UPDATE writer for Heratio
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

namespace AhgRic\Services;

use AhgCore\Services\SecretCrypto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Write-side sibling of SparqlQueryService.
 *
 * Issue #61 / ADR-0002: every AI inference is mirrored from MySQL into
 * Fuseki as an RDF-Star annotation. Reviewer overrides are written as
 * reified PROV-O Activities. This service is the only place in the
 * codebase that calls the Fuseki /update endpoint.
 *
 * Apache Jena Fuseki >= 4.x is required for RDF-Star support; the
 * insertRdfStar() helper assumes turtle-star syntax (<<s p o>>) is
 * accepted by the configured update endpoint. A startup health check
 * (verifyRdfStarSupport) probes this on first use and logs a warning
 * if it appears unsupported.
 */
class SparqlUpdateService
{
    private string $updateEndpoint;
    private ?string $username;
    private ?string $password;
    private int $timeoutSeconds;

    public function __construct()
    {
        // Resolution order, most specific to least: ahg_settings (DB-driven,
        // edited via /admin/settings), then ahg-ric config, then localhost
        // default. The DB-first order matches SparqlQueryService and the
        // rest of the Heratio Fuseki integration so credentials surface in
        // one place.
        $base = rtrim(
            $this->setting('fuseki_endpoint',
                config('ahg-ric.fuseki_endpoint',
                    config('heratio.fuseki_endpoint', 'http://localhost:3030/heratio')
                )
            ),
            '/'
        );

        $derived = $base . '/update';

        $this->updateEndpoint = $this->setting(
            'fuseki_update_endpoint',
            config('heratio.fuseki_update_endpoint', $derived)
        );

        // #1487. The write endpoint MUST target the same Fuseki dataset as the
        // read endpoint. A stored absolute `fuseki_update_endpoint` silently
        // overrides the value derived from `fuseki_endpoint`, so the two drift
        // apart and NOTHING fails: Fuseki happily accepts an UPDATE against any
        // dataset it serves.
        //
        // Found on dev reading `/ric` (648 rico:Record) while writing to
        // `/openric-model` - which is the RiC-O ONTOLOGY, 19,992 triples and
        // zero Records. Every write had been landing in the ontology dataset.
        //
        // The read endpoint is treated as authoritative for which dataset this
        // instance uses, because that is the one the application demonstrably
        // reads back from. A cross-dataset write is never intentional here, so
        // this corrects rather than merely warns - but it warns loudly too,
        // since the stored setting is still wrong and will mislead the next
        // person to read the admin page.
        if (self::datasetOf($this->updateEndpoint) !== self::datasetOf($base)) {
            Log::warning('Fuseki update endpoint targets a different dataset than the query endpoint; using the query dataset instead', [
                'configured_update_endpoint' => $this->updateEndpoint,
                'query_endpoint'             => $base,
                'using'                      => $derived,
                'fix'                        => "set ahg_settings.fuseki_update_endpoint to {$derived} or delete the row so it derives",
            ]);
            $this->updateEndpoint = $derived;
        }
        $this->username = $this->setting('fuseki_username', config('heratio.fuseki_update_username'));
        $this->password = SecretCrypto::reveal($this->setting('fuseki_password', config('heratio.fuseki_update_password'))); // #1395(D) decrypt-at-rest
        $this->timeoutSeconds = (int) $this->setting('fuseki_update_timeout', config('heratio.fuseki_update_timeout', 30));
    }

    /**
     * The resolved write target and credentials.
     *
     * #1487. FusekiSyncService used to resolve all of this a SECOND time for
     * its queued path, which meant the dataset guard above protected only the
     * synchronous path while `fuseki_queue_enabled=1` - the actual default on
     * every instance - bypassed it entirely. Two copies of one lookup is how
     * they drift; this session has now seen that shape four times in the
     * loan-rule query alone. One resolution, exposed.
     */
    public function updateEndpoint(): string
    {
        return $this->updateEndpoint;
    }

    public function username(): ?string
    {
        return $this->username;
    }

    /** Already decrypted via SecretCrypto::reveal in the constructor. */
    public function password(): ?string
    {
        return $this->password;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    private function setting(string $key, $default = null)
    {
        try {
            $row = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
            return ($row !== null && $row !== '') ? $row : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * The Fuseki dataset name a URL addresses - the FIRST path segment.
     *
     * http://host:3030/ric            -> ric
     * http://host:3030/ric/update     -> ric
     * http://host:3030/openric-model/ -> openric-model
     *
     * Assumes Fuseki is served at the root of its host, which is how it is
     * deployed here. Behind a path prefix this would need the prefix stripped
     * first; it returns '' for a URL with no path, which compares equal only
     * to another path-less URL and so cannot produce a false mismatch.
     */
    private static function datasetOf(?string $url): string
    {
        $path = parse_url((string) $url, PHP_URL_PATH) ?: '';
        $segments = array_values(array_filter(explode('/', $path), 'strlen'));

        return $segments[0] ?? '';
    }

    /**
     * Insert turtle-star data into the named graph.
     *
     * Build the turtle WITHOUT the wrapping INSERT DATA / GRAPH clauses;
     * this method handles the SPARQL UPDATE wrapping and the HTTP POST.
     *
     * @param string $graphUri Named graph URI to write into
     * @param string $turtleBody Turtle / turtle-star body (one or many triples)
     * @return array{ok:bool, status:int, error:?string}
     */
    public function insertRdfStar(string $graphUri, string $turtleBody): array
    {
        $update = "INSERT DATA { GRAPH <{$graphUri}> {\n{$turtleBody}\n} }";
        return $this->postUpdate($update);
    }

    /**
     * Insert plain (non-RDF-Star) turtle data into a named graph.
     * Use this for reviewer-override PROV-O activities (Shape B in ADR-0002).
     */
    public function insertData(string $graphUri, string $turtleBody): array
    {
        return $this->insertRdfStar($graphUri, $turtleBody);
    }

    /**
     * Run an arbitrary SPARQL UPDATE statement. Reserved for the replay job
     * and for migrations; AI services should use insertRdfStar() above.
     */
    public function executeUpdate(string $sparqlUpdate): array
    {
        return $this->postUpdate($sparqlUpdate);
    }

    /**
     * One-shot probe: try inserting and then deleting a sentinel RDF-Star
     * triple in a throwaway graph. Returns true if both succeed (Fuseki
     * supports RDF-Star); false otherwise. Intended to be called from a
     * boot-time health check or `php artisan` command.
     */
    public function verifyRdfStarSupport(): bool
    {
        $graph = 'urn:ahg:provenance:health-check';
        $turtle = '<urn:ahg:test> <urn:ahg:hasMeta> <<<urn:ahg:s> <urn:ahg:p> <urn:ahg:o>>> .';
        $insert = $this->insertRdfStar($graph, $turtle);
        if (!$insert['ok']) {
            return false;
        }
        // Best-effort cleanup; ignore errors on the drop.
        $this->postUpdate("DROP GRAPH <{$graph}>");
        return true;
    }

    /**
     * POST a SPARQL UPDATE statement to the configured endpoint.
     */
    private function postUpdate(string $sparqlUpdate): array
    {
        $ch = curl_init($this->updateEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/sparql-update; charset=utf-8',
                'Accept: */*',
            ],
            CURLOPT_POSTFIELDS     => $sparqlUpdate,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
        ]);

        if ($this->username !== null && $this->password !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        $body   = curl_exec($ch);
        $errno  = curl_errno($ch);
        $errstr = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            Log::warning('[ahg-ric] sparql update curl failed', ['errno' => $errno, 'err' => $errstr]);
            return ['ok' => false, 'status' => 0, 'error' => "curl: {$errstr}"];
        }
        if ($status < 200 || $status >= 300) {
            Log::warning('[ahg-ric] sparql update non-2xx', ['status' => $status, 'body' => substr((string) $body, 0, 500)]);
            return ['ok' => false, 'status' => $status, 'error' => 'HTTP ' . $status . ': ' . substr((string) $body, 0, 500)];
        }
        return ['ok' => true, 'status' => $status, 'error' => null];
    }
}
