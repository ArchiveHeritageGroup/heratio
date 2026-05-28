<?php

/**
 * Z3950Service for PSIS (Symfony 1.4 / ahgLibraryPlugin)
 *
 * Ported from: Heratio packages/ahg-z3950/src/Services/Z3950Service.php
 * Issue: atom-ahg-plugins#92
 *
 * Wraps the php-yaz PECL extension to:
 * - Connect to remote Z39.50 targets (yaz_connect)
 * - Execute CQL/PQF queries (yaz_search / yaz_present)
 * - Retrieve MARC21 records (yaz_record)
 * - Import records into the PSIS library_* tables
 *
 * Requires:
 *   php-yaz PECL extension  — install with `pecl install yaz`
 *   Symfony 1.4 database connection (via sfProjectDatabase)
 *
 * bib-1 attribute set:
 *   Use (1):  title=4, author=1003, subject=21, isbn=7, issn=8, lccn=9
 *   Relation (2): exact=1, less=2, greater=3, within=5
 *   Truncation (4): none=1, right=2, left=3, both=4
 *
 *   Copyright (C) 2026 Johan Pieterse — The Archive Heritage Group (Pty) Ltd
 *   AGPL-3.0 — same licence as Heratio/ahg-z3950
 */

class Z3950Service
{
    // Map: CQL index → bib-1 use attribute
    protected array $cqlIndexMap = [
        'title'   => 4,
        'author'  => 1003,
        'subject' => 21,
        'isbn'    => 7,
        'issn'    => 8,
        'lccn'    => 9,
        'local'   => 12,
        'name'    => 1002,
        'any'     => 1016,
    ];

    protected ?sfDoctrineDatabase $db = null;

    public function __construct()
    {
        $this->db = Doctrine_Manager::connection();
    }

    /**
     * Execute a Z39.50 search against a remote target.
     *
     * @param string $host        Target hostname
     * @param int    $port        Target port (default 210)
     * @param string $database   Database name on the target
     * @param string $query       CQL or PQF query string
     * @param string $syntax      Wire syntax: USmarc | SUTRS | XML (default USmarc)
     * @param string $elementSet  Element set name: F (full) | B (brief)
     * @param int    $maxRecords  Maximum records to return (default 100)
     * @return array{count:int, records:string[], error:?string}
     */
    public function search(
        string $host,
        int    $port,
        string $database,
        string $query,
        string $syntax = 'USmarc',
        string $elementSet = 'F',
        int    $maxRecords = 100
    ): array {
        $result = ['count' => 0, 'records' => [], 'error' => null];

        // yaz_connect() — returns connection ID or false
        $connectionId = @yaz_connect("{$host}:{$port}/{$database}");

        if ($connectionId === false) {
            return array_merge($result, ['error' => "Failed to connect to {$host}:{$port}"]);
        }

        yaz_set_option($connectionId, 'charset', 'UTF-8');
        yaz_set_option($connectionId, 'elementSetName', $elementSet);
        yaz_set_option($connectionId, 'implementation', '1');

        // Convert CQL → PQF before sending
        $pqf = $this->cqlToPqf($query);
        yaz_search($connectionId, 'rpn', $pqf);
        yaz_wait();

        $errno = yaz_errno($connectionId);
        $error = yaz_error($connectionId);

        if ($errno !== 0) {
            $this->log('warning', "[Z39.50] target={$host} errno={$errno} {$error}");
            yaz_close($connectionId);
            return array_merge($result, ['error' => "Target returned error {$errno}: {$error}"]);
        }

        $hits = yaz_hits($connectionId);
        $result['count'] = min((int) $hits, $maxRecords);

        for ($i = 1; $i <= $result['count']; $i++) {
            $record = yaz_record($connectionId, $i, $syntax);
            if ($record !== false && $record !== '') {
                $result['records'][] = str_replace("\r\n", "\n", $record);
            }
        }

        yaz_close($connectionId);

        $this->log('info', "[Z39.50] {$host} returned {$result['count']} records");
        return $result;
    }

    /**
     * Import a MARC21 record into the PSIS catalogue.
     *
     * Creates: library_biblio_work, library_biblio_instance,
     *          library_biblio_agent (authors), library_biblio_work_agent.
     *
     * @param string $marcContent Raw MARC record bytes or MARCXML
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
            // --- Work ---
            $title   = trim(preg_replace('/[\/|:]$/', '', $fields['245']['a'] ?? $fields['245']['b'] ?? 'Untitled'));
            $author  = $fields['100']['a'] ?? $fields['110']['a'] ?? '';

            $yearField = $fields['260']['c'] ?? $fields['008'] ?? '';
            $yearRaw   = preg_replace('/[^0-9]/', '', substr($yearField, 0, 4));
            $year      = (is_numeric($yearRaw) && strlen($yearRaw) === 4) ? (int) $yearRaw : null;

            $language = $this->parseLanguageCode($fields['008'] ?? '');
            $workId   = $this->upsertWork($title, $author, $language, $year);

            // --- Instance ---
            $publisher = $fields['260']['b'] ?? '';
            $pubPlace  = $fields['260']['a'] ?? '';
            $pubDate   = $fields['260']['c'] ?? '';
            $isbn      = $fields['020']['a'] ?? '';
            $carrier   = $this->marcLeaderToCarrier($marcContent);

            $this->upsertInstance($workId, $title, $publisher, $pubPlace, $pubDate, $isbn, $carrier);
            $stats['works'] = 1;
            $stats['instances'] = 1;

            // --- Agents ---
            $agentName = $fields['100']['a'] ?? '';
            if ($agentName) {
                $agentId = $this->upsertAgent($agentName, 'per', $fields['100'] ?? []);
                $this->linkWorkAgent($workId, $agentId, 'aut');
            }

            $corpAuthor = $fields['110']['a'] ?? '';
            if ($corpAuthor && $corpAuthor !== $agentName) {
                $agentId = $this->upsertAgent($corpAuthor, 'org', $fields['110'] ?? []);
                $this->linkWorkAgent($workId, $agentId, 'ctb');
            }

        } catch (Exception $e) {
            $stats['errors']++;
            $this->log('error', "[Z39.50] MARC import error: {$e->getMessage()}");
        }

        return $stats;
    }

    /**
     * Parse a raw MARC record (ISO 2709) into per-field subfield arrays.
     *
     * MIRRORS: AhgZ3950\Services\Z3950Service::parseMarcRecord()
     * Any bugfix there should be mirrored here.
     *
     * @param string $raw Raw MARC record bytes
     * @return array Field tag => ['subfield_code' => 'value', ...]
     */
    public function parseMarcRecord(string $raw): array
    {
        if (strlen($raw) < 24) {
            return [];
        }

        $leader   = substr($raw, 0, 24);
        $dirLen   = (int) substr($leader, 10, 1) + 1;
        $dirStart = 24;
        $dirEnd   = $dirStart + $dirLen - 1;
        $fields  = [];
        $pos     = $dirStart;

        while ($pos + 12 <= $dirEnd) {
            $tag    = substr($raw, $pos, 3);
            $len    = (int) substr($raw, $pos + 3, 4);
            $start  = (int) substr($raw, $pos + 7, 5);
            $pos   += 12;

            if (! ctype_digit($tag)) { continue; }

            $dataStart = $dirStart + $dirLen + $start;
            $dataEnd   = $dataStart + $len - 1;

            if ($dataEnd > strlen($raw)) { break; }

            $data = substr($raw, $dataStart, $len - 1);

            // Skip leader / control fields (001-00x)
            if (substr($tag, 0, 1) !== '0' || $tag === '008') {
                $subfieldData = substr($data, 2);
            } else {
                $subfieldData = $data;
            }

            $subfields = [];
            foreach (explode("\x1F", $subfieldData) as $part) {
                if (strlen($part) < 1) { continue; }
                $subfields[$part[0]] = substr($part, 1);
            }

            $fields[$tag] = $subfields;
        }

        return $fields;
    }

    /**
     * Map MARC leader record-type + bibliographic-level to BF carrier code.
     *
     * @param string $marcContent Raw MARC record
     * @return string Carrier code: nc | cr | sd | cf | nbc | nnc
     */
    public function marcLeaderToCarrier(string $marcContent): string
    {
        if (strlen($marcContent) < 24) { return 'nc'; }

        $recType  = $marcContent[6] ?? ' ';
        $bibLevel = $marcContent[7] ?? ' ';

        return match ($recType) {
            'a', 't' => match ($bibLevel) { 'm' => 'cr', 's' => 'nc', default => 'nc' },
            'c', 'd' => 'nnc',   // notated music score
            'e', 'f' => 'nnc',   // cartographic
            'i', 'j' => 'sd',    // moving image
            'k'      => 'nnc',   // 2D graphic
            'm'      => 'cf',    // computer file
            'p'      => 'nc',    // mixed material
            'r'      => 'nnc',   // 3D artifact
            default  => 'nc',
        };
    }

    /**
     * Parse MARC 008 positions 35-37 → ISO 639-1 language code.
     */
    protected function parseLanguageCode(string $field008): string
    {
        if (strlen($field008) < 38) { return 'en'; }

        $code = strtolower(substr($field008, 35, 3));
        $map  = [
            'eng' => 'en', 'afr' => 'af', 'dut' => 'nl', 'fre' => 'fr', 'ger' => 'de',
            'ita' => 'it', 'spa' => 'es', 'por' => 'pt', 'rus' => 'ru', 'dan' => 'da',
            'nor' => 'no', 'swe' => 'sv', 'fin' => 'fi', 'ces' => 'cs', 'pol' => 'pl',
        ];

        return $map[$code] ?? $code;
    }

    // ─── Protected database helpers ────────────────────────────────────────

    protected function upsertWork(string $title, string $author, string $language, ?int $year): int
    {
        $conn = $this->db;

        $existing = Doctrine_Query::create()
            ->from('LibraryBiblioWork w')
            ->where('w.title = ?', $title)
            ->andWhere('w.author = ?', $author)
            ->fetchOne();

        if ($existing) {
            $existing->language = $language;
            $existing->updated_at = date('Y-m-d H:i:s');
            $existing->save();
            return $existing->id;
        }

        $work = new LibraryBiblioWork();
        $work->title    = $title;
        $work->author   = $author;
        $work->language = $language;
        $work->year     = $year;
        $work->created_at = date('Y-m-d H:i:s');
        $work->updated_at = date('Y-m-d H:i:s');
        $work->save();

        return $work->id;
    }

    protected function upsertInstance(
        int    $workId,
        string $title,
        string $publisher,
        string $pubPlace,
        string $pubDate,
        string $isbn,
        string $carrier
    ): int {
        $q = Doctrine_Query::create()
            ->from('LibraryBiblioInstance i')
            ->where('i.work_id = ?', $workId);

        if ($isbn) {
            $q->andWhere('i.isbn = ?', $isbn);
        }

        $existing = $q->fetchOne();

        if ($existing) {
            $existing->publisher = $publisher;
            $existing->pub_place = $pubPlace;
            $existing->pub_date  = $pubDate;
            $existing->updated_at = date('Y-m-d H:i:s');
            $existing->save();
            return $existing->id;
        }

        $instance = new LibraryBiblioInstance();
        $instance->work_id    = $workId;
        $instance->title     = $title;
        $instance->publisher  = $publisher;
        $instance->pub_place  = $pubPlace;
        $instance->pub_date   = $pubDate;
        $instance->isbn       = $isbn;
        $instance->carrier    = $carrier;
        $instance->created_at = date('Y-m-d H:i:s');
        $instance->updated_at = date('Y-m-d H:i:s');
        $instance->save();

        return $instance->id;
    }

    protected function upsertAgent(string $name, string $type, array $fields = []): int
    {
        $existing = Doctrine_Query::create()
            ->from('LibraryBiblioAgent a')
            ->where('a.name = ?', $name)
            ->andWhere('a.type = ?', $type)
            ->fetchOne();

        if ($existing) {
            return $existing->id;
        }

        $agent = new LibraryBiblioAgent();
        $agent->name       = $name;
        $agent->type       = $type;
        $agent->created_at = date('Y-m-d H:i:s');
        $agent->save();

        return $agent->id;
    }

    protected function linkWorkAgent(int $workId, int $agentId, string $role): void
    {
        $exists = Doctrine_Query::create()
            ->from('LibraryBiblioWorkAgent wa')
            ->where('wa.work_id = ?', $workId)
            ->andWhere('wa.agent_id = ?', $agentId)
            ->count() > 0;

        if (! $exists) {
            $link = new LibraryBiblioWorkAgent();
            $link->work_id  = $workId;
            $link->agent_id = $agentId;
            $link->role     = $role;
            $link->save();
        }
    }

    // ─── Query conversion ───────────────────────────────────────────────────

    /**
     * Convert a CQL query to Z39.50 PQF (Prefix Query Format).
     *
     * @param string $cql CQL query string
     * @return string PQF query string
     */
    protected function cqlToPqf(string $cql): string
    {
        $pqf = $cql;

        foreach ($this->cqlIndexMap as $index => $attr) {
            $pqf = preg_replace("/\\b{$index}=/i", "@attr 1={$attr} ", $pqf);
        }

        $pqf = str_ireplace([' AND ', ' OR '], [' @and ', ' @or '], $pqf);

        // Truncation: strip trailing * and add right-truncation bib-1 attribute
        if (str_contains($pqf, '*')) {
            $pqf = str_replace('*', '', $pqf);
            $pqf = preg_replace('/(@attr \d+=\d+ )([^ ]+)/', '$1@attr 4=2 $2', $pqf);
        }

        return $pqf ?: $cql;
    }

    // ─── Logging proxy ───────────────────────────────────────────────────────

    protected function log(string $level, string $message): void
    {
        sfContext::getInstance()->getLogger()->{$level}($message);
    }
}
