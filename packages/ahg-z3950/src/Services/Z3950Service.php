<?php

/**
 * Z3950Service — Z39.50 client logic for Heratio
 *
 * Wraps the yaz PECL extension to:
 * - Connect to remote Z39.50 targets (yaz_connect)
 * - Execute CQL/PQF queries (yaz_search / yaz_present)
 * - Retrieve records in MARC21/USMARC or other syntaxes (yaz_record)
 * - Import retrieved records into the library_biblio_* tables
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
     * Import a MARC record (USmarc/MARC21) into the Heratio catalogue.
     *
     * Parses the raw MARC record and upserts into:
     *   library_biblio_work     (title, author, language, year)
     *   library_biblio_instance (carrier, pub_place, publisher, pub_date, isbn)
     *   library_biblio_agent    (authors associated via library_biblio_work_agent)
     *
     * @param string $marcContent Raw MARC record bytes
     * @param string $syntax      Syntax hint: USmarc | MARC21 | XML
     * @return array{works:int, instances:int, items:int, warnings:int, errors:int}
     */
    public function importMarc(string $marcContent, string $syntax = 'USmarc'): array
    {
        $stats = ['works' => 0, 'instances' => 0, 'items' => 0, 'warnings' => 0, 'errors' => 0];

        $fields = $this->parseMarcRecord($marcContent);

        if (empty($fields)) {
            $stats['errors']++;
            return $stats;
        }

        try {
            // Extract Work fields
            $title = trim(preg_replace('/[\/|:]$/', '', $fields['245']['a'] ?? $fields['245']['b'] ?? 'Untitled'));
            $author = $fields['100']['a'] ?? $fields['110']['a'] ?? $fields['700']['a'] ?? '';

            $yearField = $fields['260']['c'] ?? $fields['008'] ?? '';
            $yearRaw = preg_replace('/[^0-9]/', '', substr($yearField, 0, 4));
            $year = (is_numeric($yearRaw) && strlen($yearRaw) === 4) ? (int) $yearRaw : null;

            $language = $this->parseLanguageCode($fields['008'] ?? '');

            $workId = $this->upsertWork($title, $author, $language, $year);

            // Extract Instance fields
            $publisher = $fields['260']['b'] ?? '';
            $pubPlace  = $fields['260']['a'] ?? '';
            $pubDate   = $fields['260']['c'] ?? '';
            $isbn      = $fields['020']['a'] ?? '';
            $carrier   = $this->marcLeaderToCarrier($marcContent);

            $this->upsertInstance($workId, $title, $publisher, $pubPlace, $pubDate, $isbn, $carrier);

            $stats['works']     = 1;
            $stats['instances'] = 1;

            // Upsert Agent (personal author)
            $agentName = $fields['100']['a'] ?? '';
            if ($agentName) {
                $agentId = $this->upsertAgent($agentName, $fields['100'] ?? []);
                $this->linkWorkAgent($workId, $agentId, 'aut');
            }

            // Corporate author (110 field)
            $corpAuthor = $fields['110']['a'] ?? '';
            if ($corpAuthor && $corpAuthor !== $author) {
                $agentId = $this->upsertAgent($corpAuthor, $fields['110'] ?? []);
                $this->linkWorkAgent($workId, $agentId, 'ctb');
            }

            // Proxy to OpenRiC for RiC-O canonical record management
            $this->proxyToOpenric($marcContent, 'import');

        } catch (\Throwable $e) {
            $stats['errors']++;
            Log::error("[Z39.50] MARC import error: {$e->getMessage()}");
        }

        return $stats;
    }

    /**
     * Parse a raw MARC record into an associative array keyed by field tag.
     *
     * MARC record structure (ISO 2709):
     *   Directory: 3-char tag + 4-char length + 5-char start = 12 bytes per entry
     *   After directory: field terminator (0x1D) then variable fields
     *
     * @param string $raw Raw MARC record bytes
     * @return array Field tag => subfield array (e.g. ['245' => ['a' => 'Title', 'b' => 'subtitle']])
     */
    public function parseMarcRecord(string $raw): array
    {
        if (strlen($raw) < 24) {
            return [];
        }

        $leader = substr($raw, 0, 24);
        $dirLen = (int) substr($leader, 10, 1) + 1; // incl. RTF byte

        $dirStart = 24;
        $dirEnd   = $dirStart + $dirLen - 1;

        $fields = [];
        $pos    = $dirStart;

        while ($pos + 12 <= $dirEnd) {
            $tag   = substr($raw, $pos, 3);
            $len   = (int) substr($raw, $pos + 3, 4);
            $start = (int) substr($raw, $pos + 7, 5);
            $pos  += 12;

            if (! ctype_digit($tag)) {
                continue;
            }

            $dataStart = $dirStart + $dirLen + $start;
            $dataEnd   = $dataStart + $len - 1;

            if ($dataEnd > strlen($raw)) {
                break;
            }

            $data = substr($raw, $dataStart, $len - 1);

            // Indicators for bibliographic fields (001-999 except 000)
            if ($tag !== '000' && $tag !== '00l') {
                $subfieldData = substr($data, 2);
            } else {
                $subfieldData = $data;
            }

            // Split on subfield delimiter 0x1F
            $subfields = [];
            foreach (explode("\x1F", $subfieldData) as $part) {
                if (strlen($part) < 1) {
                    continue;
                }
                $subfields[$part[0]] = substr($part, 1);
            }

            $fields[$tag] = $subfields;
        }

        return $fields;
    }

    /**
     * Map a MARC leader record type / Bib level to a BF carrier code.
     *
     * @param string $marcContent Raw MARC record
     * @return string carrier code (nc, cr, nbc, sd, cf, etc.)
     */
    protected function marcLeaderToCarrier(string $marcContent): string
    {
        if (strlen($marcContent) < 24) {
            return 'nc';
        }

        $recType  = $marcContent[6] ?? ' ';
        $bibLevel = $marcContent[7] ?? ' ';

        return match ($recType) {
            'a', 't' => match ($bibLevel) {
                'm' => 'cr',   // monograph component
                's' => 'nc',   // serial
                default => 'nc',
            },
            'c', 'd' => 'nnc', // notated music score
            'e', 'f' => 'nnc', // cartographic
            'i', 'j' => 'sd',  // moving image
            'k'      => 'nnc', // 2D graphic
            'm'      => 'cf',  // computer file
            'p'      => 'nc',  // mixed material
            'r'      => 'nnc', // 3D artifact
            default  => 'nc',
        };
    }

    /**
     * Parse language code from MARC 008 positions 35-37.
     *
     * @param string $field008 MARC 008 field content
     * @return string ISO 639-1 language code
     */
    protected function parseLanguageCode(string $field008): string
    {
        if (strlen($field008) < 38) {
            return 'en';
        }
        $code = strtolower(substr($field008, 35, 3));

        return $this->langCodeMap[$code] ?? $code;
    }

    /**
     * Upsert a bibliographic work.
     *
     * @return int work id
     */
    protected function upsertWork(string $title, string $author, string $language, ?int $year): int
    {
        $existing = DB::connection('heratio')
            ->table('library_biblio_work')
            ->where('title', $title)
            ->where('author', $author)
            ->first();

        if ($existing) {
            DB::connection('heratio')
                ->table('library_biblio_work')
                ->where('id', $existing->id)
                ->update([
                    'language'   => $language,
                    'updated_at' => now(),
                ]);
            return (int) $existing->id;
        }

        return DB::connection('heratio')
            ->table('library_biblio_work')
            ->insertGetId([
                'title'     => $title,
                'author'   => $author,
                'language' => $language,
                'year'     => $year,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Upsert a bibliographic instance.
     *
     * @return int instance id
     */
    protected function upsertInstance(
        int $workId,
        string $title,
        string $publisher,
        string $pubPlace,
        string $pubDate,
        string $isbn,
        string $carrier
    ): int {
        if ($isbn) {
            $existing = DB::connection('heratio')
                ->table('library_biblio_instance')
                ->where('work_id', $workId)
                ->where('isbn', $isbn)
                ->first();
        } else {
            $existing = null;
        }

        if ($existing) {
            DB::connection('heratio')
                ->table('library_biblio_instance')
                ->where('id', $existing->id)
                ->update([
                    'publisher'  => $publisher,
                    'pub_place' => $pubPlace,
                    'pub_date'  => $pubDate,
                    'updated_at' => now(),
                ]);
            return (int) $existing->id;
        }

        return DB::connection('heratio')
            ->table('library_biblio_instance')
            ->insertGetId([
                'work_id'   => $workId,
                'title'     => $title,
                'publisher' => $publisher,
                'pub_place' => $pubPlace,
                'pub_date'  => $pubDate,
                'isbn'      => $isbn,
                'carrier'   => $carrier,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Upsert a bibliographic agent (person or corporate body).
     *
     * @return int agent id
     */
    protected function upsertAgent(string $name, array $fields): int
    {
        $type = match ($fields['t'] ?? null) {
            'p' => 'per',
            'c' => 'org',
            default => 'per',
        };

        $existing = DB::connection('heratio')
            ->table('library_biblio_agent')
            ->where('name', $name)
            ->where('type', $type)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return DB::connection('heratio')
            ->table('library_biblio_agent')
            ->insertGetId([
                'name'       => $name,
                'type'       => $type,
                'created_at' => now(),
            ]);
    }

    /**
     * Link a work to an agent via the work_agent pivot table.
     */
    protected function linkWorkAgent(int $workId, int $agentId, string $role): void
    {
        $exists = DB::connection('heratio')
            ->table('library_biblio_work_agent')
            ->where('work_id', $workId)
            ->where('agent_id', $agentId)
            ->exists();

        if (! $exists) {
            DB::connection('heratio')
                ->table('library_biblio_work_agent')
                ->insert([
                    'work_id'  => $workId,
                    'agent_id' => $agentId,
                    'role'     => $role,
                ]);
        }
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

    /** Language code normaliser for MARC 008 positions 35-37 */
    protected array $langCodeMap = [
        'eng' => 'en',
        'afr' => 'af',
        'dut' => 'nl',
        'fre' => 'fr',
        'ger' => 'de',
        'ita' => 'it',
        'spa' => 'es',
        'por' => 'pt',
        'rus' => 'ru',
        'chi' => 'zh',
        'jpn' => 'ja',
        'kor' => 'ko',
        'ara' => 'ar',
        'heb' => 'he',
        'pol' => 'pl',
        'swe' => 'sv',
        'nor' => 'no',
        'dan' => 'da',
        'fin' => 'fi',
        'ces' => 'cs',
    ];
}