<?php

declare(strict_types=1);

/**
 * SruService - Heratio ahg-library (heratio#1281, PSIS parity)
 *
 * SRU (Search/Retrieve via URL) server: an HTTP discovery endpoint exposing the
 * Heratio library catalogue (information_object + library_item, source_standard
 * 'library') to other libraries / discovery layers. Ported from the PSIS
 * ahgLibraryPlugin SruService with heratio adaptations:
 *   - Laravel DB facade (not the Capsule manager) and request-derived host/port.
 *   - Primary creator resolved via library_item_creator.sort_order (heratio has no
 *     is_primary column) using a correlated subquery, so multi-creator items do not
 *     multiply result rows; CQL creator/free-text filters use whereExists.
 *   - library_item.description is selected only when present (the #1281 backbone
 *     migration adds it) so the endpoint never 500s if the schema lags.
 *
 * Operations: searchRetrieve (CQL -> SRU XML) and explain (capability document).
 * SRU 1.1 / 1.2. See https://www.loc.gov/standards/sru/
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SruService
{
    public const SUPPORTED_VERSIONS = ['1.1', '1.2'];
    public const DEFAULT_VERSION = '1.1';
    public const DEFAULT_RECORDS = 20;
    public const MAX_RECORDS = 500;

    public const XML_NS = 'http://www.loc.gov/zing/srw/';

    /** Result count + CQL of the last searchRetrieve, for request logging. */
    public int $lastResultCount = 0;
    public string $lastCql = '';

    // ── XML helpers ──────────────────────────────────────────────────────────

    protected function xmlHeader(string $version): string
    {
        $schema = $version >= '1.2'
            ? 'http://www.loc.gov/zing/srw/sru.xsd'
            : 'http://www.loc.gov/zing/srw/';

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <srw:searchRetrieveResponse xmlns:srw="{$this->XML_NS}"
              xmlns:dc="http://purl.org/dc/elements/1.1/"
              xmlns:diag="http://www.loc.gov/zing/srw/diagnostic/"
              xmlns:xcql="http://www.loc.gov/zing/cql/xcql/"
              xmlns:xsd="http://www.w3.org/2001/XMLSchema"
              xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
              xsi:schemaLocation="{$schema}">

            XML;
    }

    protected function xmlFooter(): string
    {
        return "</srw:searchRetrieveResponse>\n";
    }

    /** @param array<int,array<string,mixed>> $diagnostics */
    protected function xmlDiagnostics(array $diagnostics): string
    {
        $out = "  <srw:diagnostics>\n";
        foreach ($diagnostics as $diag) {
            $id = (int) ($diag['id'] ?? 1);
            $uri = htmlspecialchars($diag['uri'] ?? "http://www.loc.gov/zing/srw/diagnostic/#{$id}");
            $detail = htmlspecialchars($diag['message'] ?? 'Unknown error');
            $out .= "    <diag:diagnostic>\n"
                . "      <diag:uri>{$uri}</diag:uri>\n"
                . "      <diag:message>{$detail}</diag:message>\n"
                . "    </diag:diagnostic>\n";
        }
        $out .= "  </srw:diagnostics>\n";

        return $out;
    }

    /** Render one SRU record (MARCXML when recordPacking=marcxml, else Dublin Core). */
    protected function xmlRecord(array $row, string $recordSchema, int $position): string
    {
        $ioId = (int) ($row['io_id'] ?? 0);
        $id = (int) ($row['id'] ?? $ioId);
        $title = htmlspecialchars((string) ($row['title'] ?? ''));
        $creator = htmlspecialchars((string) ($row['creator'] ?? ''));
        $pub = htmlspecialchars((string) ($row['publisher'] ?? ''));
        $date = htmlspecialchars((string) ($row['publication_date'] ?? ''));
        $isbn = htmlspecialchars((string) ($row['isbn'] ?? ''));
        $issn = htmlspecialchars((string) ($row['issn'] ?? ''));
        $mat = htmlspecialchars((string) ($row['material_type'] ?? ''));
        $call = htmlspecialchars((string) ($row['call_number'] ?? ''));
        $lang = htmlspecialchars((string) ($row['language'] ?? ''));
        $desc = htmlspecialchars((string) ($row['description'] ?? ''));
        $slug = htmlspecialchars((string) ($row['slug'] ?? ''));

        if ($recordSchema === 'marcxml') {
            $f008 = date('ymd') . 's' . str_pad($lang, 3) . str_repeat(' ', 28) . 'd';

            return <<<XML
                  <srw:record>
                    <srw:recordSchema>info:srw/cql-context-set/1/marcxml-v1.1</srw:recordSchema>
                    <srw:recordPacking>xml</srw:recordPacking>
                    <srw:recordPosition>{$position}</srw:recordPosition>
                    <srw:recordData>
                      <record xmlns="http://www.loc.gov/MARC21/slim">
                        <leader>00000cam a2200000 a 4500</leader>
                        <controlfield tag="001">{$id}</controlfield>
                        <controlfield tag="008">{$f008}</controlfield>
                        <datafield tag="245" ind1="0" ind2="0"><subfield code="a">{$title}</subfield></datafield>
                        <datafield tag="100" ind1="1" ind2="0"><subfield code="a">{$creator}</subfield></datafield>
                        <datafield tag="260" ind1=" " ind2=" "><subfield code="b">{$pub}</subfield><subfield code="c">{$date}</subfield></datafield>
                        <datafield tag="020" ind1=" " ind2=" "><subfield code="a">{$isbn}</subfield></datafield>
                        <datafield tag="022" ind1=" " ind2=" "><subfield code="a">{$issn}</subfield></datafield>
                        <datafield tag="050" ind1="0" ind2="0"><subfield code="a">{$call}</subfield></datafield>
                        <datafield tag="300" ind1=" " ind2=" "><subfield code="a">{$mat}</subfield></datafield>
                        <datafield tag="520" ind1=" " ind2=" "><subfield code="a">{$desc}</subfield></datafield>
                      </record>
                    </srw:recordData>
                  </srw:record>

                XML;
        }

        return <<<XML
              <srw:record>
                <srw:recordSchema>info:srw/cql-context-set/1/dc-v1.1</srw:recordSchema>
                <srw:recordPacking>xml</srw:recordPacking>
                <srw:recordPosition>{$position}</srw:recordPosition>
                <srw:recordData>
                  <dc:record xmlns:dc="http://purl.org/dc/elements/1.1/">
                    <dc:title>{$title}</dc:title>
                    <dc:creator>{$creator}</dc:creator>
                    <dc:publisher>{$pub}</dc:publisher>
                    <dc:date>{$date}</dc:date>
                    <dc:identifier>urn:uuid:{$ioId}</dc:identifier>
                    <dc:identifier>ISBN:{$isbn}</dc:identifier>
                    <dc:identifier>ISSN:{$issn}</dc:identifier>
                    <dc:language>{$lang}</dc:language>
                    <dc:subject>{$mat}</dc:subject>
                    <dc:description>{$desc}</dc:description>
                    <dc:relation>slug:{$slug}</dc:relation>
                  </dc:record>
                </srw:recordData>
              </srw:record>

            XML;
    }

    // ── searchRetrieve ─────────────────────────────────────────────────────────

    /**
     * Execute a CQL search and return the SRU XML response.
     *
     * @param array<string,mixed> $params normalised SRU query parameters
     */
    public function searchRetrieve(array $params): string
    {
        $version = (string) ($params['version'] ?? self::DEFAULT_VERSION);
        $query = trim((string) ($params['query'] ?? ''));
        $recordSchema = ((string) ($params['recordSchema'] ?? '')) === 'marcxml'
            || ((string) ($params['recordPacking'] ?? '')) === 'marcxml' ? 'marcxml' : 'dc';
        $recordsPerPage = min((int) ($params['maximumRecords'] ?? self::DEFAULT_RECORDS), self::MAX_RECORDS);
        $recordsPerPage = max(0, $recordsPerPage);
        $startRecord = max(1, (int) ($params['startRecord'] ?? 1));
        $sortKeys = trim((string) ($params['sortKeys'] ?? ''));

        $this->lastCql = $query;
        $this->lastResultCount = 0;

        if (! in_array($version, self::SUPPORTED_VERSIONS, true)) {
            return $this->xmlHeader($version)
                . $this->xmlDiagnostics([['id' => 8, 'message' => "SRU version '{$version}' not supported. Use one of: " . implode(', ', self::SUPPORTED_VERSIONS)]])
                . $this->xmlFooter();
        }
        if ($query === '') {
            return $this->xmlHeader($version)
                . $this->xmlDiagnostics([['id' => 7, 'message' => 'CQL query is required (parameter "query")']])
                . $this->xmlFooter();
        }

        $hasDescription = Schema::hasColumn('library_item', 'description');
        $orderBy = $sortKeys !== '' ? $this->resolveSortKeys($sortKeys) : '';

        try {
            $base = fn () => DB::table('information_object as io')
                ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
                ->join('library_item as li', 'io.id', '=', 'li.information_object_id')
                ->where('ioi.culture', 'en')
                ->where('io.source_standard', 'library')
                ->when($query !== 'cql.allRecords', fn ($q) => $this->applyCqlFilter($q, $query));

            $total = (int) $base()->count();
            $this->lastResultCount = $total;

            // Primary creator: lowest sort_order (heratio has no is_primary). Correlated
            // subquery keeps one row per item even when an item has several creators.
            $creatorSub = DB::table('library_item_creator as lic')
                ->whereColumn('lic.library_item_id', 'li.id')
                ->orderBy('lic.sort_order')->limit(1)->select('lic.name');

            $select = [
                'io.id as io_id', 'ioi.title', 's.slug', 'li.id', 'li.isbn', 'li.issn',
                'li.publisher', 'li.publication_date', 'li.material_type', 'li.call_number', 'li.language',
            ];
            if ($hasDescription) {
                $select[] = 'li.description';
            }

            $rows = $base()
                ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                ->select($select)
                ->selectSub($creatorSub, 'creator')
                ->when($orderBy !== '', fn ($q) => $q->orderByRaw($orderBy), fn ($q) => $q->orderBy('ioi.title'))
                ->offset($startRecord - 1)
                ->limit($recordsPerPage)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            return $this->xmlHeader($version)
                . $this->xmlDiagnostics([['id' => 2, 'message' => 'System error processing the query']])
                . $this->xmlFooter();
        }

        $recordsXml = '';
        $pos = $startRecord;
        foreach ($rows as $row) {
            $recordsXml .= $this->xmlRecord($row, $recordSchema, $pos);
            $pos++;
        }

        $nextPos = ($startRecord + $recordsPerPage - 1) < $total ? (string) ($startRecord + $recordsPerPage) : '';
        $nextXml = $nextPos !== '' ? "  <srw:nextRecordPosition>{$nextPos}</srw:nextRecordPosition>\n" : '';
        $escQuery = htmlspecialchars($query);

        return $this->xmlHeader($version)
            . "  <srw:version>{$version}</srw:version>\n"
            . "  <srw:numberOfRecords>{$total}</srw:numberOfRecords>\n"
            . "  <srw:records>\n{$recordsXml}  </srw:records>\n"
            . $nextXml
            . "  <srw:echoedSearchRetrieveRequest>\n"
            . "    <srw:version>{$version}</srw:version>\n"
            . "    <srw:query>{$escQuery}</srw:query>\n"
            . "    <srw:startRecord>{$startRecord}</srw:startRecord>\n"
            . "    <srw:maximumRecords>{$recordsPerPage}</srw:maximumRecords>\n"
            . "  </srw:echoedSearchRetrieveRequest>\n"
            . $this->xmlFooter();
    }

    /** SRU explain (server capability document). */
    public function explain(string $host = 'localhost', string $port = '443'): string
    {
        $host = htmlspecialchars($host);
        $port = htmlspecialchars($port);

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <srw:explainResponse xmlns:srw="http://www.loc.gov/zing/srw/">
              <srw:version>1.1</srw:version>
              <srw:record>
                <srw:recordSchema>http://explain.z3950.org/dtd/2.0/</srw:recordSchema>
                <srw:recordPacking>xml</srw:recordPacking>
                <srw:recordData>
                  <explain xmlns="http://explain.z3950.org/dtd/2.0/">
                    <serverInfo protocol="SRU" version="1.1">
                      <host>{$host}</host>
                      <port>{$port}</port>
                      <database>library</database>
                    </serverInfo>
                    <databaseInfo>
                      <title>Heratio Library Catalogue</title>
                      <description lang="en" primary="true">SRU access to the Heratio library catalogue.</description>
                    </databaseInfo>
                    <schemaInfo>
                      <schema identifier="info:srw/cql-context-set/1/dc-v1.1" name="dc" location="http://purl.org/dc/elements/1.1/"><title>Dublin Core</title></schema>
                      <schema identifier="info:srw/cql-context-set/1/marcxml-v1.1" name="marcxml"><title>MARC 21 XML</title></schema>
                    </schemaInfo>
                    <indexInfo>
                      <set identifier="info:srw/cql-context-set/1/dc-v1.1" name="dc"/>
                      <index><title>Title</title><map><name set="dc">title</name></map></index>
                      <index><title>Creator</title><map><name set="dc">creator</name></map></index>
                      <index><title>Subject</title><map><name set="dc">subject</name></map></index>
                      <index><title>Identifier (ISBN)</title><map><name set="dc">identifier</name></map></index>
                      <index><title>Publisher</title><map><name set="dc">publisher</name></map></index>
                      <index><title>Date</title><map><name set="dc">date</name></map></index>
                    </indexInfo>
                    <configInfo>
                      <default type="numberOfRecords">20</default>
                      <setting type="maximumRecords">500</setting>
                    </configInfo>
                  </explain>
                </srw:recordData>
              </srw:record>
            </srw:explainResponse>
            XML;
    }

    // ── CQL filter ─────────────────────────────────────────────────────────────

    /**
     * Apply a (subset) CQL query to the query builder.
     * Indexes: dc.title|creator|subject|identifier|publisher|date.
     * Operators: =, <>/!=, <, <=, >, >=. Boolean: top-level AND/OR.
     */
    protected function applyCqlFilter($q, string $cql): void
    {
        $cql = trim($cql);
        while (str_starts_with($cql, '(') && str_ends_with($cql, ')')) {
            $cql = trim(substr($cql, 1, -1));
        }

        foreach ($this->splitCqlTokens($cql) as $part) {
            $part = trim($part);
            if ($part === '' || in_array(mb_strtoupper($part), ['AND', 'OR'], true)) {
                continue;
            }

            $field = null;
            $op = 'LIKE';
            $val = $part;

            if (preg_match('/^(dc\.[a-z]+)\s*(<>|!=|<=|>=|<|>|=)\s*(["\'](.*?)["\']|(.*))$/i', $part, $m)) {
                $field = strtolower($m[1]);
                $rel = $m[2];
                $val = trim($m[4] !== '' ? $m[4] : $m[5]);
                $op = match (true) {
                    in_array($rel, ['<>', '!='], true) => 'NOT LIKE',
                    in_array($rel, ['<', '<='], true) => '<',
                    in_array($rel, ['>', '>='], true) => '>',
                    default => 'LIKE',
                };
            } elseif (preg_match('/^(dc\.[a-z]+)\s+(.*)$/i', $part, $m)) {
                $field = strtolower($m[1]);
                $val = trim($m[2], " \t\"'");
            }

            // Free-text (no dc.* qualifier): title OR creator.
            if ($field === null) {
                $v = '%' . $val . '%';
                $q->where(function ($inner) use ($v, $val) {
                    $inner->where('ioi.title', 'LIKE', $v)
                        ->orWhereExists($this->creatorMatch($val));
                });

                continue;
            }

            // dc.creator filters against library_item_creator via whereExists.
            if ($field === 'dc.creator') {
                if ($op === 'NOT LIKE') {
                    $q->whereNotExists($this->creatorMatch($val));
                } else {
                    $q->whereExists($this->creatorMatch($val));
                }

                continue;
            }

            $col = match ($field) {
                'dc.title' => 'ioi.title',
                'dc.subject' => 'li.material_type',
                'dc.identifier' => 'li.isbn',
                'dc.publisher' => 'li.publisher',
                'dc.date' => 'li.publication_date',
                default => 'ioi.title',
            };

            if ($op === 'LIKE') {
                $q->where($col, 'LIKE', '%' . $val . '%');
            } elseif ($op === 'NOT LIKE') {
                $q->where(fn ($inner) => $inner->where($col, 'NOT LIKE', '%' . $val . '%')->orWhereNull($col));
            } else {
                $q->where($col, $op, $val);
            }
        }
    }

    /** A correlated "creator name matches" subquery for whereExists. */
    protected function creatorMatch(string $val): \Closure
    {
        return function ($sub) use ($val) {
            $sub->from('library_item_creator as licx')
                ->whereColumn('licx.library_item_id', 'li.id')
                ->where('licx.name', 'LIKE', '%' . $val . '%')
                ->select(DB::raw(1));
        };
    }

    /** @return array<int,string> */
    protected function splitCqlTokens(string $cql): array
    {
        $tokens = preg_split('/\s+(AND|OR)\s+/i', $cql, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        return array_values(array_filter(array_map('trim', $tokens), fn ($t) => $t !== ''));
    }

    protected function resolveSortKeys(string $sortKeys): string
    {
        if (preg_match('/^(dc\.[a-z]+)\s*(ascending|descending|asc|desc)?/i', trim($sortKeys), $m)) {
            $dir = str_starts_with(strtolower($m[2] ?? 'asc'), 'desc') ? 'DESC' : 'ASC';
            $col = match (strtolower($m[1])) {
                'dc.date' => 'li.publication_date',
                'dc.creator' => 'ioi.title',   // creator is a subquery; fall back to title for ordering
                default => 'ioi.title',
            };

            return "{$col} {$dir}";
        }

        return '';
    }
}
