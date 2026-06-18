<?php

/**
 * SruService - Search/Retrieve via URL (SRU) 2.0 server for Heratio.
 *
 * Implements the LoC SRU 2.0 spec subset that NLSA tender §7.1 requires:
 * - searchRetrieve operation
 * - explain operation
 * - CQL query parsing (simple bib-1-equivalent index set)
 * - MARC21/MARCXML record schema
 * - Dublin Core record schema
 *
 * SRU is the HTTP-friendly successor to native Z39.50. Most modern library
 * federations consume SRU rather than the binary Z39.50 wire protocol.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * AGPL-3.0
 */

namespace AhgZ3950\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SruService
{
    public const SRU_VERSION = '2.0';

    /** Result count + CQL string of the last searchRetrieve, for request logging. */
    public int $lastResultCount = 0;
    public string $lastCql = '';

    /**
     * CQL index aliases mapped to library_item / actor / term columns.
     * Keys are CQL indexes a client may use (per LoC dc + bib indices).
     */
    private const CQL_INDEX_MAP = [
        'cql.anywhere'   => 'anywhere',
        'cql.serverChoice' => 'anywhere',
        'dc.title'       => 'title',
        'title'          => 'title',
        'dc.creator'     => 'author',
        'author'         => 'author',
        'creator'        => 'author',
        'dc.subject'     => 'subject',
        'subject'        => 'subject',
        'dc.identifier'  => 'identifier',
        'identifier'     => 'identifier',
        'bath.isbn'      => 'isbn',
        'isbn'           => 'isbn',
        'bath.issn'      => 'issn',
        'issn'           => 'issn',
        'dc.publisher'   => 'publisher',
        'publisher'      => 'publisher',
        'dc.date'        => 'date',
    ];

    /**
     * Run a CQL query against the local library catalogue.
     *
     * @return array{count:int, records:array, diagnostic:?string}
     */
    public function searchRetrieve(
        string $cqlQuery,
        int $startRecord = 1,
        int $maximumRecords = 10,
        string $recordSchema = 'marcxml'
    ): array {
        $this->lastCql = $cqlQuery;
        $this->lastResultCount = 0;

        try {
            $parsed = $this->parseCql($cqlQuery);
        } catch (Throwable $e) {
            return [
                'count' => 0,
                'records' => [],
                'diagnostic' => 'CQL parse error: ' . $e->getMessage(),
            ];
        }

        $maximumRecords = max(1, min($maximumRecords, 100));
        $startRecord = max(1, $startRecord);
        $offset = $startRecord - 1;

        // library_item.description was added by the #1281 backbone migration;
        // select it only when present so the endpoint never 500s if schema lags.
        $hasDescription = Schema::hasColumn('library_item', 'description');

        $select = [
            'library_item.id',
            'library_item.information_object_id',
            'library_item.subtitle',
            'library_item.publisher',
            'library_item.publication_date',
            'library_item.isbn',
            'library_item.issn',
            'library_item.pagination',
            'library_item.dimensions',
            'information_object_i18n.title',
            'library_item.created_at',
            'library_item.updated_at',
        ];
        if ($hasDescription) {
            $select[] = 'library_item.description';
        }

        $query = DB::table('library_item')
            ->join('information_object', 'library_item.information_object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object_i18n.id', '=', 'information_object.id')
                     ->where('information_object_i18n.culture', '=', 'en');
            })
            // Catalogue scoping: SRU exposes the public library catalogue only.
            ->where('information_object.source_standard', 'library')
            ->select($select);

        foreach ($parsed as $clause) {
            $this->applyClause($query, $clause);
        }

        $count = (clone $query)->count('library_item.id');
        $rows = $query->offset($offset)->limit($maximumRecords)->get();
        $this->lastResultCount = (int) $count;

        $records = [];
        foreach ($rows as $row) {
            $records[] = $this->renderRecord($row, $recordSchema);
        }

        return [
            'count' => $count,
            'records' => $records,
            'diagnostic' => null,
        ];
    }

    /**
     * Parse a CQL query into a list of {index, relation, term, boolean} clauses.
     * Supports: AND / OR / NOT booleans, "exact" + "=" relations, quoted strings.
     */
    public function parseCql(string $cql): array
    {
        $cql = trim($cql);
        if ($cql === '') {
            throw new \InvalidArgumentException('Empty CQL query');
        }

        $tokens = $this->tokenise($cql);
        $clauses = [];
        $boolean = 'AND';

        $i = 0;
        while ($i < count($tokens)) {
            $token = $tokens[$i];
            $upper = strtoupper($token);

            if (in_array($upper, ['AND', 'OR', 'NOT'], true)) {
                $boolean = $upper;
                $i++;
                continue;
            }

            // Three possible forms:
            //   index relation term       e.g. dc.title = "cry beloved"
            //   index term                e.g. title "cry beloved" (= default)
            //   term                      e.g. "cry beloved" (cql.anywhere implicit)
            if (isset($tokens[$i + 1]) && in_array($tokens[$i + 1], ['=', '==', '<>', '<', '>'], true)) {
                $clauses[] = [
                    'index' => $this->normaliseIndex($token),
                    'relation' => $tokens[$i + 1],
                    'term' => $this->stripQuotes($tokens[$i + 2] ?? ''),
                    'boolean' => $boolean,
                ];
                $i += 3;
            } else {
                $clauses[] = [
                    'index' => 'anywhere',
                    'relation' => '=',
                    'term' => $this->stripQuotes($token),
                    'boolean' => $boolean,
                ];
                $i++;
            }
            $boolean = 'AND';
        }

        return $clauses;
    }

    private function tokenise(string $cql): array
    {
        $tokens = [];
        $buf = '';
        $inQuote = false;
        $len = strlen($cql);

        for ($i = 0; $i < $len; $i++) {
            $c = $cql[$i];
            if ($c === '"') {
                $inQuote = !$inQuote;
                $buf .= $c;
                continue;
            }
            if (!$inQuote && ($c === ' ' || $c === "\t")) {
                if ($buf !== '') { $tokens[] = $buf; $buf = ''; }
                continue;
            }
            if (!$inQuote && ($c === '=' || $c === '<' || $c === '>')) {
                if ($buf !== '') { $tokens[] = $buf; $buf = ''; }
                // double-char operators
                $next = $cql[$i + 1] ?? '';
                if (($c === '=' && $next === '=') || ($c === '<' && $next === '>')) {
                    $tokens[] = $c . $next;
                    $i++;
                } else {
                    $tokens[] = $c;
                }
                continue;
            }
            $buf .= $c;
        }
        if ($buf !== '') $tokens[] = $buf;
        return $tokens;
    }

    private function stripQuotes(string $term): string
    {
        if (strlen($term) >= 2 && $term[0] === '"' && substr($term, -1) === '"') {
            return substr($term, 1, -1);
        }
        return $term;
    }

    private function normaliseIndex(string $rawIndex): string
    {
        $lower = strtolower($rawIndex);
        return self::CQL_INDEX_MAP[$lower] ?? 'anywhere';
    }

    private function applyClause($query, array $clause): void
    {
        $term = $clause['term'];
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        $relation = $clause['relation'];
        $exact = in_array($relation, ['==', 'exact'], true);

        $method = match ($clause['boolean']) {
            'OR' => 'orWhere',
            'NOT' => 'whereNot',
            default => 'where',
        };

        $query->$method(function ($q) use ($clause, $term, $like, $exact) {
            $matchVal = fn(string $col) => $exact
                ? $q->where($col, '=', $term)
                : $q->where($col, 'LIKE', $like);

            match ($clause['index']) {
                'title' => $matchVal('information_object_i18n.title'),
                'isbn' => $matchVal('library_item.isbn'),
                'issn' => $matchVal('library_item.issn'),
                'publisher' => $matchVal('library_item.publisher'),
                'date' => $matchVal('library_item.publication_date'),
                'author' => $q->whereExists(function ($sub) use ($term, $like, $exact) {
                    $sub->select(DB::raw(1))
                        ->from('library_item_creator')
                        ->whereColumn('library_item_creator.library_item_id', 'library_item.id');
                    if ($exact) {
                        $sub->where('library_item_creator.name', '=', $term);
                    } else {
                        $sub->where('library_item_creator.name', 'LIKE', $like);
                    }
                }),
                'subject' => $q->whereExists(function ($sub) use ($term, $like, $exact) {
                    $sub->select(DB::raw(1))
                        ->from('object_term_relation')
                        ->join('term_i18n', 'term_i18n.id', '=', 'object_term_relation.term_id')
                        ->whereColumn('object_term_relation.object_id', 'library_item.information_object_id');
                    if ($exact) {
                        $sub->where('term_i18n.name', '=', $term);
                    } else {
                        $sub->where('term_i18n.name', 'LIKE', $like);
                    }
                }),
                'identifier' => $q->where(function ($q2) use ($like) {
                    $q2->where('library_item.isbn', 'LIKE', $like)
                       ->orWhere('library_item.issn', 'LIKE', $like);
                }),
                default => $q->where(function ($q2) use ($like) {
                    $q2->where('information_object_i18n.title', 'LIKE', $like)
                       ->orWhere('library_item.isbn', 'LIKE', $like)
                       ->orWhere('library_item.issn', 'LIKE', $like)
                       ->orWhere('library_item.publisher', 'LIKE', $like);
                }),
            };
        });
    }

    /**
     * Render one record according to the requested schema.
     */
    private function renderRecord(object $row, string $schema): string
    {
        return match (strtolower($schema)) {
            'marcxml', 'info:srw/schema/1/marcxml-v1.1', 'marc21' => $this->renderMarcXml($row),
            'dc', 'info:srw/schema/1/dc-v1.1', 'dublincore' => $this->renderDublinCore($row),
            default => $this->renderMarcXml($row),
        };
    }

    private function renderMarcXml(object $row): string
    {
        $authors = DB::table('library_item_creator')
            ->where('library_item_id', $row->id)
            ->orderBy('sort_order')
            ->limit(5)
            ->pluck('name')
            ->all();

        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'term_i18n.id', '=', 'object_term_relation.term_id')
            ->where('object_term_relation.object_id', $row->information_object_id)
            ->where('term_i18n.culture', 'en')
            ->limit(20)
            ->pluck('term_i18n.name')
            ->all();

        $title = (string) ($row->title ?? '');
        $isbn = (string) ($row->isbn ?? '');
        $issn = (string) ($row->issn ?? '');
        $publisher = (string) ($row->publisher ?? '');
        $year = (string) ($row->publication_date ?? '');
        $physical = trim((string) ($row->pagination ?? '') . ' ' . (string) ($row->dimensions ?? ''));

        $marc = '<record xmlns="http://www.loc.gov/MARC21/slim">';
        $marc .= '<leader>     ' . 'cam a22     2a 4500</leader>';
        $marc .= '<controlfield tag="001">' . htmlspecialchars((string) $row->id, ENT_XML1) . '</controlfield>';

        if ($isbn !== '') {
            $marc .= '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">' . htmlspecialchars($isbn, ENT_XML1) . '</subfield></datafield>';
        }
        if ($issn !== '') {
            $marc .= '<datafield tag="022" ind1=" " ind2=" "><subfield code="a">' . htmlspecialchars($issn, ENT_XML1) . '</subfield></datafield>';
        }
        if (!empty($authors)) {
            $marc .= '<datafield tag="100" ind1="1" ind2=" "><subfield code="a">' . htmlspecialchars($authors[0], ENT_XML1) . '</subfield></datafield>';
        }
        if ($title !== '') {
            $marc .= '<datafield tag="245" ind1="1" ind2="0"><subfield code="a">' . htmlspecialchars($title, ENT_XML1) . '</subfield></datafield>';
        }
        if ($publisher !== '' || $year !== '') {
            $marc .= '<datafield tag="264" ind1=" " ind2="1">';
            if ($publisher !== '') $marc .= '<subfield code="b">' . htmlspecialchars($publisher, ENT_XML1) . '</subfield>';
            if ($year !== '')      $marc .= '<subfield code="c">' . htmlspecialchars($year, ENT_XML1) . '</subfield>';
            $marc .= '</datafield>';
        }
        if ($physical !== '') {
            $marc .= '<datafield tag="300" ind1=" " ind2=" "><subfield code="a">' . htmlspecialchars($physical, ENT_XML1) . '</subfield></datafield>';
        }
        $description = (string) ($row->description ?? '');
        if ($description !== '') {
            $marc .= '<datafield tag="520" ind1=" " ind2=" "><subfield code="a">' . htmlspecialchars($description, ENT_XML1) . '</subfield></datafield>';
        }
        foreach (array_slice($authors, 1) as $extra) {
            $marc .= '<datafield tag="700" ind1="1" ind2=" "><subfield code="a">' . htmlspecialchars($extra, ENT_XML1) . '</subfield></datafield>';
        }
        foreach ($subjects as $subject) {
            $marc .= '<datafield tag="650" ind1=" " ind2="0"><subfield code="a">' . htmlspecialchars($subject, ENT_XML1) . '</subfield></datafield>';
        }

        $marc .= '</record>';
        return $marc;
    }

    private function renderDublinCore(object $row): string
    {
        $authors = DB::table('library_item_creator')
            ->where('library_item_id', $row->id)
            ->orderBy('sort_order')
            ->limit(5)
            ->pluck('name')
            ->all();

        $xml = '<srw_dc:dc xmlns:srw_dc="info:srw/schema/1/dc-v1.1" xmlns:dc="http://purl.org/dc/elements/1.1/">';
        if (!empty($row->title))   $xml .= '<dc:title>' . htmlspecialchars((string) $row->title, ENT_XML1) . '</dc:title>';
        foreach ($authors as $a)   $xml .= '<dc:creator>' . htmlspecialchars($a, ENT_XML1) . '</dc:creator>';
        if (!empty($row->publisher)) $xml .= '<dc:publisher>' . htmlspecialchars((string) $row->publisher, ENT_XML1) . '</dc:publisher>';
        if (!empty($row->publication_date)) $xml .= '<dc:date>' . htmlspecialchars((string) $row->publication_date, ENT_XML1) . '</dc:date>';
        if (!empty($row->isbn))    $xml .= '<dc:identifier>urn:isbn:' . htmlspecialchars((string) $row->isbn, ENT_XML1) . '</dc:identifier>';
        if (!empty($row->issn))    $xml .= '<dc:identifier>urn:issn:' . htmlspecialchars((string) $row->issn, ENT_XML1) . '</dc:identifier>';
        if (!empty($row->description)) $xml .= '<dc:description>' . htmlspecialchars((string) $row->description, ENT_XML1) . '</dc:description>';
        $xml .= '</srw_dc:dc>';
        return $xml;
    }

    /**
     * SRU 2.0 explain response (capabilities advertisement).
     * Called when client issues `?operation=explain` or hits the base URL.
     */
    public function explain(): string
    {
        $title = htmlspecialchars((string) (config('app.name') ?: 'Heratio'), ENT_XML1);
        return <<<XML
<explain xmlns="http://explain.z3950.org/dtd/2.0/">
  <serverInfo protocol="SRU" version="2.0" transport="http">
    <host>{$this->serverHost()}</host>
    <port>{$this->serverPort()}</port>
    <database>sru</database>
  </serverInfo>
  <databaseInfo>
    <title lang="en" primary="true">{$title} Bibliographic Catalogue</title>
    <description lang="en" primary="true">SRU 2.0 endpoint for the {$title} library catalogue.</description>
  </databaseInfo>
  <indexInfo>
    <set name="cql" identifier="info:srw/cql-context-set/1/cql-v1.2"/>
    <set name="dc" identifier="info:srw/cql-context-set/1/dc-v1.1"/>
    <set name="bath" identifier="http://zing.z3950.org/cql/bath/2.0/"/>
    <index>
      <title>Title</title>
      <map><name set="dc">title</name></map>
    </index>
    <index>
      <title>Creator</title>
      <map><name set="dc">creator</name></map>
    </index>
    <index>
      <title>Subject</title>
      <map><name set="dc">subject</name></map>
    </index>
    <index>
      <title>ISBN</title>
      <map><name set="bath">isbn</name></map>
    </index>
    <index>
      <title>ISSN</title>
      <map><name set="bath">issn</name></map>
    </index>
  </indexInfo>
  <schemaInfo>
    <schema identifier="info:srw/schema/1/marcxml-v1.1" name="marcxml" sort="false" retrieve="true">
      <title>MARC21 XML</title>
    </schema>
    <schema identifier="info:srw/schema/1/dc-v1.1" name="dc" sort="false" retrieve="true">
      <title>Dublin Core</title>
    </schema>
  </schemaInfo>
  <configInfo>
    <default type="numberOfRecords">10</default>
    <setting type="maximumRecords">100</setting>
  </configInfo>
</explain>
XML;
    }

    private function serverHost(): string
    {
        $url = parse_url((string) config('app.url'), PHP_URL_HOST);
        return htmlspecialchars((string) ($url ?: 'localhost'), ENT_XML1);
    }

    private function serverPort(): string
    {
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);
        return (string) ($port ?: 80);
    }
}
