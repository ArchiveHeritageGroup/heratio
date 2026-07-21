<?php

/**
 * Z3950Service — Z39.50 client logic for Heratio
 *
 * Wraps the yaz PECL extension to:
 * - Connect to remote Z39.50 targets (yaz_connect)
 * - Execute CQL/PQF queries (yaz_search / yaz_present)
 * - Retrieve records in MARC21/USMARC or other syntaxes (yaz_record)
 * - Import retrieved records into the library_item catalogue (via ahg-library)
 *
 * Requires: `yaz` PECL extension (pecl install yaz or apt-get install php-yaz)
 *
 * bib-1 attribute set:
 *   Use (1): title=4, author=1003, subject=21, ISBN=7, ISSN=8, LCCN=9, local=12
 *   Relation (2): exact=1, less=2, greater=3, within=5
 *   Position (3): first=1, any=2
 *   Truncation (4): none=1, right=2, left=3, both=4
 *   Completeness (5): incomplete=1, partial=2, complete=3
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 * Email: johan@theahg.co.za
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

namespace AhgZ3950\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Z3950Service
{
    /**
     * Execute a Z39.50 search against a remote target.
     *
     * @param string  $host        Target hostname
     * @param int     $port        Target port (default 210)
     * @param string  $database    Database name on the target
     * @param string  $query      Query string (CQL or PQF)
     * @param string  $syntax     Record syntax: USmarc | SUTRS | XML | MARCXML
     * @param string  $elementSet Element set: F (full) | B (brief) | S (suggested)
     * @param int     $maxRecords Maximum number of records to return
     * @return array{count:int, records:string[], error:string|null}
     */
    public function search(
        string $host,
        int $port,
        string $database,
        string $query,
        string $syntax = 'USmarc',
        string $elementSet = 'F',
        int $maxRecords = 100
    ): array {
        $result = ['count' => 0, 'records' => [], 'error' => null];

        // Build Z39.50 connection string (host:port/database)
        $connStr = "{$host}:{$port}/{$database}";

        // yaz_connect returns a connection ID (or false on failure)
        $connectionId = @yaz_connect($connStr);

        if ($connectionId === false) {
            return array_merge($result, ['error' => "Failed to connect to {$host}:{$port}"]);
        }

        // Set connection options before search
        yaz_set_option($connectionId, 'charset', 'UTF-8');
        yaz_set_option($connectionId, 'elementSetName', $elementSet);
        yaz_set_option($connectionId, 'implementation', '1');

        // Build PQF from CQL query
        $pqf = $this->cqlToPqf($query);

        // Execute search asynchronously
        yaz_search($connectionId, 'rpn', $pqf);

        // Wait for results (timeout from config)
        yaz_wait();

        // Check for errors
        $errno = yaz_errno($connectionId);
        $error = yaz_error($connectionId);

        if ($errno !== 0) {
            Log::warning("[Z39.50] yaz_errno={$errno} {$error}");
            yaz_close($connectionId);
            return array_merge($result, [
                'error' => "Target returned error {$errno}: {$error}",
            ]);
        }

        // Retrieve record count
        $hits = yaz_hits($connectionId);
        $result['count'] = min((int) $hits, $maxRecords);

        // Retrieve records — yaz_record returns string|false|null
        $syntax = match (strtolower($syntax)) {
            'usmarc', 'marc21', 'marc' => 'xml',   // yaz returns MARC-in-XML for USmarc
            default => $syntax,
        };

        for ($i = 1; $i <= $result['count']; $i++) {
            $raw = yaz_record($connectionId, $i, $syntax);

            if ($raw !== false && $raw !== null && (string) $raw !== '') {
                $result['records'][] = str_replace("\r\n", "\n", (string) $raw);
            }
        }

        yaz_close($connectionId);

        Log::info("[Z39.50] Search {$host} returned {$result['count']} records");

        return $result;
    }

    /**
     * Import a MARC record (USmarc/MARC21 binary or MARCXML) into the catalogue.
     *
     * Records are created as library_item rows through ahg-library's copy
     * cataloguing path - the same route the Library module uses - so Z39.50
     * imports land in the one bibliographic model Heratio actually reads
     * (#1413). There is no separate library_biblio_* store.
     *
     * The counts stay keyed works/instances/items because one MARC record maps
     * to one Work + one Instance + one Item, and z3950_import_log records them.
     *
     * @param string $marcContent Raw MARC record bytes or MARCXML
     * @param string $syntax      Syntax hint: USmarc | MARC21 | XML (advisory;
     *                            the decoder sniffs the payload itself)
     * @return array{works:int, instances:int, items:int, warnings:int, errors:int, information_object_id:int|null}
     */
    public function importMarc(string $marcContent, string $syntax = 'USmarc'): array
    {
        $stats = [
            'works'                 => 0,
            'instances'             => 0,
            'items'                 => 0,
            'warnings'              => 0,
            'errors'                => 0,
            'information_object_id' => null,
        ];

        if (trim($marcContent) === '') {
            $stats['errors']++;
            return $stats;
        }

        // ahg-library owns the catalogue. Resolved at call time so ahg-z3950
        // carries no composer dependency on it (ahg-library already depends on
        // this package for its Z39.50 client, so a hard require would cycle).
        if (! class_exists(\AhgLibrary\Services\CopyCataloguingService::class)) {
            Log::error('[Z39.50] Import unavailable: ahg-library is not installed on this instance.');
            $stats['errors']++;
            return $stats;
        }

        try {
            $objectId = app(\AhgLibrary\Services\CopyCataloguingService::class)->import($marcContent);

            $stats['works']                 = 1;
            $stats['instances']             = 1;
            $stats['items']                 = 1;
            $stats['information_object_id'] = (int) $objectId;

            // Proxy to OpenRiC for RiC-O canonical record management
            $this->proxyToOpenric($marcContent, 'import');
        } catch (\Throwable $e) {
            $stats['errors']++;
            Log::error("[Z39.50] MARC import error: {$e->getMessage()}");
        }

        return $stats;
    }

    /**
     * Parse a retrieved MARC record into an associative array keyed by field tag.
     *
     * Both syntaxes a target can return are handled, because search() asks yaz
     * for 'xml' when the target syntax is USmarc/MARC21 - so result sets are
     * usually MARCXML rather than ISO 2709.
     *
     * Reading is delegated to ahg-library, which owns the MARC readers the
     * Library module already uses. The hand-rolled ISO 2709 reader that used to
     * live here mistook leader position 10 (the indicator length, always '2')
     * for the directory length, so its directory loop never ran and it returned
     * an empty array for every valid binary record.
     *
     * Only the first occurrence of a repeated tag is kept, which is what the
     * result-set browser displays.
     *
     * @param string $raw Raw MARC record bytes or MARCXML
     * @return array Field tag => subfield array (e.g. ['245' => ['a' => 'Title', 'b' => 'subtitle']])
     */
    public function parseMarcRecord(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        if (! class_exists(\AhgLibrary\Services\Marc21DecoderService::class)) {
            Log::warning('[Z39.50] Cannot read MARC: ahg-library is not installed on this instance.');
            return [];
        }

        $decoder = new \AhgLibrary\Services\Marc21DecoderService();

        try {
            $parsed = $decoder->detectSyntax($raw) === 'marcxml'
                ? (new \AhgLibrary\Services\MarcEditService())->parseMarcxml($raw)
                : $decoder->decode($raw);
        } catch (\Throwable $e) {
            Log::warning("[Z39.50] MARC parse failed: {$e->getMessage()}");
            return [];
        }

        $fields = [];

        foreach ($parsed['data'] ?? [] as $field) {
            $tag = $field['tag'] ?? '';
            if ($tag === '' || isset($fields[$tag])) {
                continue;
            }
            $fields[$tag] = $field['subfields'] ?? [];
        }

        return $fields;
    }

    /**
     * Convert a CQL query string to Z39.50 PQF (Prefix Query Format).
     *
     * @param string $cql CQL query string
     * @return string PQF query
     */
    protected function cqlToPqf(string $cql): string
    {
        // bib-1 use attributes: title=4, author=1003, subject=21, ISBN=7, ISSN=8, LCCN=9
        $map = [
            'title'   => '@attr 1=4 ',
            'author'  => '@attr 1=1003 ',
            'subject' => '@attr 1=21 ',
            'isbn'    => '@attr 1=7 ',
            'issn'    => '@attr 1=8 ',
            'lccn'    => '@attr 1=9 ',
        ];

        $pqf = $cql;

        // Replace CQL field prefixes with PQF attribute prefixes
        foreach ($map as $cqlField => $pqfPrefix) {
            $pqf = preg_replace("/\\b{$cqlField}=/i", $pqfPrefix, $pqf);
        }

        // Map AND / OR
        $pqf = str_ireplace([' AND ', ' OR '], [' @and ', ' @or '], $pqf);

        // Right truncation: * in CQL → @attr 4=2 (right-truncation)
        if (str_contains($pqf, '*')) {
            $pqf = str_replace('*', '', $pqf);
            $pqf = preg_replace('/(@attr \d+=\d+ )([^ ]+)/', '$1@attr 4=2 $2', $pqf);
        }

        return $pqf ?: $cql;
    }

    /**
     * Proxy MARC import to OpenRiC for canonical RiC-O record management.
     *
     * @param string $marcContent Raw MARC bytes
     * @param string $action     import | export
     * @return array
     */
    protected function proxyToOpenric(string $marcContent, string $action): array
    {
        $openricUrl = rtrim(config('services.openric.url', 'http://localhost:3030'), '/');

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json'])
                ->post("{$openricUrl}/api/ric/z3950/{$action}", [
                    'marc' => base64_encode($marcContent),
                ]);

            if ($response->successful()) {
                Log::info("[Z39.50] OpenRiC {$action} proxy successful");
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning("[Z39.50] OpenRiC not available: {$e->getMessage()}");
        }

        return ['proxied' => false, 'via' => 'openric', 'url' => $openricUrl];
    }
}