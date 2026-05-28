<?php

/**
 * SruService for PSIS (Symfony 1.4 / ahgLibraryPlugin)
 *
 * Ported from: Heratio packages/ahg-z3950/src/Services/SruService.php
 * Issue: atom-ahg-plugins#92
 *
 * Implements the SRU 2.0 (Search/Retrieve via URL) HTTP server for the
 * PSIS library catalogue.  SRU is the HTTP successor to native Z39.50.
 *
 * Accepted operations:
 *   GET /z3950/sru?operation=explain
 *   GET /z3950/sru?operation=searchRetrieve&query=...&startRecord=&maximumRecords=&recordSchema=
 *
 * Supported CQL indexes:
 *   dc.title, dc.creator, dc.subject, dc.publisher, dc.date, dc.identifier
 *   bath.isbn, bath.issn
 *   cql.anywhere, cql.serverChoice
 *
 * Supported record schemas:
 *   info:srw/schema/1/marcxml-v1.1  (MARCXML)
 *   info:srw/schema/1/dc-v1.1      (Dublin Core)
 *
 * CQL is anonymous-access.  No auth required for SRU reads.
 *
 * Copyright (C) 2026 Johan Pieterse — The Archive Heritage Group (Pty) Ltd
 * AGPL-3.0 — same licence as Heratio/ahg-z3950
 */

class SruService
{
    const SRU_VERSION = '2.0';

    /**
     * CQL index → PDO column expression used in WHERE clauses.
     * Maps loose CQL index names to library_item / join column selectors.
     */
    private array $cqlIndexMap = [
        'cql.anywhere'       => 'anywhere',
        'cql.serverChoice'   => 'anywhere',
        'dc.title'           => 'title',
        'title'              => 'title',
        'dc.creator'         => 'creator',
        'creator'            => 'creator',
        'author'             => 'creator',
        'dc.subject'         => 'subject',
        'subject'            => 'subject',
        'dc.identifier'      => 'identifier',
        'identifier'         => 'identifier',
        'bath.isbn'          => 'isbn',
        'isbn'               => 'isbn',
        'bath.issn'          => 'issn',
        'issn'               => 'issn',
        'dc.publisher'       => 'publisher',
        'publisher'          => 'publisher',
        'dc.date'            => 'date',
        'date'               => 'date',
    ];

    /**
     * SRU searchRetrieve operation.
     *
     * @param string $cqlQuery       CQL query string
     * @param int    $startRecord    1-based result offset (default 1)
     * @param int    $maximumRecords Max records to return (default 10, max 100)
     * @param string $recordSchema   Output format (marcxml | dc)
     * @return array{count:int, records:string[], diagnostic:?string}
     */
    public function searchRetrieve(
        string $cqlQuery,
        int    $startRecord = 1,
        int    $maximumRecords = 10,
        string $recordSchema = 'marcxml'
    ): array {
        try {
            $clauses = $this->parseCql($cqlQuery);
        } catch (Exception $e) {
            return ['count' => 0, 'records' => [], 'diagnostic' => 'CQL parse error: ' . $e->getMessage()];
        }

        $maximumRecords = max(1, min($maximumRecords, 100));
        $startRecord    = max(1, $startRecord);
        $offset         = $startRecord - 1;

        // Build query using PSIS library_item + information_object join
        $conn = Doctrine_Manager::connection();

        $select = <<<SQL
SELECT
  li.id,
  li.information_object_id,
  li.subtitle,
  li.publisher,
  li.publication_date,
  li.isbn,
  li.issn,
  li.pagination,
  li.dimensions,
  oi18n.title
FROM library_item li
LEFT JOIN information_object io ON li.information_object_id = io.id
LEFT JOIN information_object_i18n oi18n ON oi18n.id = io.id AND oi18n.culture = 'en'
SQL;

        $where = [];
        $params = [];

        foreach ($clauses as $clause) {
            $term    = $clause['term'];
            $param   = ':w_' . count($params);
            $params[$param] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
            $exact   = in_array($clause['relation'], ['==', 'exact'], true);
            $op      = $exact ? '= ' . $param : 'LIKE ' . $param;

            switch ($clause['index']) {
                case 'title':
                    $where[] = "oi18n.title {$op}";
                    break;
                case 'isbn':
                    $where[] = "li.isbn {$op}";
                    break;
                case 'issn':
                    $where[] = "li.issn {$op}";
                    break;
                case 'publisher':
                    $where[] = "li.publisher {$op}";
                    break;
                case 'date':
                    $where[] = "li.publication_date {$op}";
                    break;
                case 'creator':
                    // EXISTS subquery against library_item_creator
                    $p2 = str_replace(':', ':cr_', $param);
                    $where[] = "EXISTS (
                      SELECT 1 FROM library_item_creator lic
                      WHERE lic.library_item_id = li.id
                        AND lic.name {$op}
                    )";
                    $params[$p2] = $params[$param];
                    break;
                case 'subject':
                    $p2 = str_replace(':', ':sb_', $param);
                    $where[] = "EXISTS (
                      SELECT 1 FROM object_term_relation otr
                      JOIN term_i18n ti ON ti.id = otr.term_id
                      WHERE otr.object_id = li.information_object_id
                        AND ti.name {$op}
                    )";
                    $params[$p2] = $params[$param];
                    break;
                case 'identifier':
                    $where[] = "(li.isbn {$op} OR li.issn {$op})";
                    break;
                default:
                    $where[] = "(oi18n.title {$op} OR li.isbn {$op} OR li.issn {$op} OR li.publisher {$op})";
            }
        }

        $whereClause = ! empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order       = 'ORDER BY li.id';

        // Total count
        $countSql = "SELECT COUNT(*) FROM library_item li
                     LEFT JOIN information_object io ON li.information_object_id = io.id
                     LEFT JOIN information_object_i18n oi18n ON oi18n.id = io.id AND oi18n.culture = 'en'
                     {$whereClause}";
        $stmt = $conn->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Page of results
        $limit = (int) $maximumRecords;
        $sql = "{$select} {$whereClause} {$order} LIMIT {$limit} OFFSET {$offset}";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(Doctrine_Core::FETCH_ASSOC);

        $records = [];
        foreach ($rows as $row) {
            $records[] = $this->renderRecord($row, $recordSchema);
        }

        return ['count' => $total, 'records' => $records, 'diagnostic' => null];
    }

    /**
     * Parse a CQL query into an array of clause descriptors.
     *
     * Supported syntax:
     *   name = "value"          (index relation quoted-term)
     *   name "value"            (index term — relation defaults to =)
     *   "value"                 (anywhere term)
     *   name = "a" AND name2 = "b"   (AND / OR / NOT booleans)
     *   name = "a" NOT name2 = "b"
     *
     * @param string $cql Raw CQL string
     * @return array List of {index, relation, term, boolean} clauses
     * @throws Exception if the CQL is malformed
     */
    public function parseCql(string $cql): array
    {
        $cql = trim($cql);
        if ($cql === '') {
            throw new InvalidArgumentException('Empty CQL query');
        }

        $tokens = $this->tokenise($cql);
        $clauses = [];
        $boolean = 'AND';
        $i = 0;

        while ($i < count($tokens)) {
            $token  = $tokens[$i];
            $upper  = strtoupper($token);

            if (in_array($upper, ['AND', 'OR', 'NOT'], true)) {
                $boolean = $upper;
                $i++;
                continue;
            }

            if (isset($tokens[$i + 1]) && in_array($tokens[$i + 1], ['=', '==', '<>', '<', '>'], true)) {
                $clauses[] = [
                    'index'    => $this->normaliseIndex($token),
                    'relation' => $tokens[$i + 1],
                    'term'     => $this->stripQuotes($tokens[$i + 2] ?? ''),
                    'boolean'  => $boolean,
                ];
                $i += 3;
            } else {
                $clauses[] = [
                    'index'    => 'anywhere',
                    'relation' => '=',
                    'term'     => $this->stripQuotes($token),
                    'boolean'  => $boolean,
                ];
                $i++;
            }

            $boolean = 'AND';
        }

        return $clauses;
    }

    // ─── Tokeniser ─────────────────────────────────────────────────────────

    private function tokenise(string $cql): array
    {
        $tokens = [];
        $buf    = '';
        $inQuote = false;
        $len    = strlen($cql);

        for ($i = 0; $i < $len; $i++) {
            $c = $cql[$i];

            if ($c === '"') {
                $inQuote = ! $inQuote;
                $buf .= $c;
                continue;
            }

            if (! $inQuote && $c === ' ' || $c === "\t") {
                if ($buf !== '') {
                    $tokens[] = $buf;
                    $buf = '';
                }
                continue;
            }

            if (! $inQuote && ($c === '=' || $c === '<' || $c === '>')) {
                if ($buf !== '') {
                    $tokens[] = $buf;
                    $buf = '';
                }
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

        if ($buf !== '') {
            $tokens[] = $buf;
        }

        return $tokens;
    }

    private function stripQuotes(string $term): string
    {
        $len = strlen($term);
        if ($len >= 2 && $term[0] === '"' && $term[$len - 1] === '"') {
            return substr($term, 1, -1);
        }
        return $term;
    }

    private function normaliseIndex(string $raw): string
    {
        $lower = strtolower($raw);
        return $this->cqlIndexMap[$lower] ?? 'anywhere';
    }

    // ─── MARCXML / Dublin Core renderers ───────────────────────────────────

    /**
     * Render one library_item row as the requested SRU record schema.
     */
    private function renderRecord(array $row, string $schema): string
    {
        return match (strtolower($schema)) {
            'marcxml', 'info:srw/schema/1/marcxml-v1.1', 'marc21' => $this->renderMarcXml($row),
            'dc', 'info:srw/schema/1/dc-v1.1', 'dublincore'           => $this->renderDublinCore($row),
            default => $this->renderMarcXml($row),
        };
    }

    private function renderMarcXml(array $row): string
    {
        $conn = Doctrine_Manager::connection();

        $authors = $conn->prepare(
            "SELECT name FROM library_item_creator
             WHERE library_item_id = :id
             ORDER BY sort_order
             LIMIT 5"
        )->execute(['id' => $row['id']])->fetchAll(Doctrine_Core::FETCH_COLUMN);

        $subjectsStmt = $conn->prepare(
            "SELECT ti.name FROM object_term_relation otr
             JOIN term_i18n ti ON ti.id = otr.term_id
             WHERE otr.object_id = :oid AND ti.culture = 'en'
             LIMIT 20"
        );
        $subjectsStmt->execute(['oid' => $row['information_object_id']]);
        $subjects = $subjectsStmt->fetchAll(Doctrine_Core::FETCH_COLUMN);

        $escape = fn(string $s): string => htmlspecialchars((string) $s, ENT_XML1);
        $title  = $escape($row['title'] ?? '');
        $isbn   = $escape($row['isbn'] ?? '');
        $issn   = $escape($row['issn'] ?? '');
        $pub    = $escape($row['publisher'] ?? '');
        $year   = $escape($row['publication_date'] ?? '');
        $phys   = trim($escape($row['pagination'] ?? '') . ' ' . $escape($row['dimensions'] ?? ''));

        $m = '<record xmlns="http://www.loc.gov/MARC21/slim">';
        $m .= '<leader>     cam a22     2a 4500</leader>';
        $m .= "<controlfield tag=\"001\">{$escape((string)$row['id'])}</controlfield>";

        if ($isbn) { $m .= "<datafield tag=\"020\" ind1=\" \" ind2=\" \"><subfield code=\"a\">{$isbn}</subfield></datafield>"; }
        if ($issn) { $m .= "<datafield tag=\"022\" ind1=\" \" ind2=\" \"><subfield code=\"a\">{$issn}</subfield></datafield>"; }
        if (!empty($authors)) { $m .= "<datafield tag=\"100\" ind1=\"1\" ind2=\" \"><subfield code=\"a\">{$escape($authors[0])}</subfield></datafield>"; }
        if ($title) { $m .= "<datafield tag=\"245\" ind1=\"1\" ind2=\"0\"><subfield code=\"a\">{$title}</subfield></datafield>"; }
        if ($pub || $year) {
            $m .= "<datafield tag=\"264\" ind1=\" \" ind2=\"1\">";
            if ($pub) { $m .= "<subfield code=\"b\">{$pub}</subfield>"; }
            if ($year) { $m .= "<subfield code=\"c\">{$year}</subfield>"; }
            $m .= '</datafield>';
        }
        if ($phys) { $m .= "<datafield tag=\"300\" ind1=\" \" ind2=\" \"><subfield code=\"a\">{$phys}</subfield></datafield>"; }
        foreach (array_slice($authors, 1) as $a) { $m .= "<datafield tag=\"700\" ind1=\"1\" ind2=\" \"><subfield code=\"a\">{$escape($a)}</subfield></datafield>"; }
        foreach ($subjects as $s) { $m .= "<datafield tag=\"650\" ind1=\" \" ind2=\"0\"><subfield code=\"a\">{$escape($s)}</subfield></datafield>"; }
        $m .= '</record>';

        return $m;
    }

    private function renderDublinCore(array $row): string
    {
        $conn = Doctrine_Manager::connection();

        $authorsStmt = $conn->prepare(
            "SELECT name FROM library_item_creator
             WHERE library_item_id = :id
             ORDER BY sort_order
             LIMIT 5"
        );
        $authorsStmt->execute(['id' => $row['id']]);
        $authors = $authorsStmt->fetchAll(Doctrine_Core::FETCH_COLUMN);

        $escape = fn(string $s): string => htmlspecialchars((string) $s, ENT_XML1);

        $xml = '<srw_dc:dc xmlns:srw_dc="info:srw/schema/1/dc-v1.1" xmlns:dc="http://purl.org/dc/elements/1.1/">';
        if (!empty($row['title'])) {
            $xml .= '<dc:title>' . $escape($row['title']) . '</dc:title>';
        }
        foreach ($authors as $a) {
            $xml .= '<dc:creator>' . $escape($a) . '</dc:creator>';
        }
        if (!empty($row['publisher'])) {
            $xml .= '<dc:publisher>' . $escape($row['publisher']) . '</dc:publisher>';
        }
        if (!empty($row['publication_date'])) {
            $xml .= '<dc:date>' . $escape($row['publication_date']) . '</dc:date>';
        }
        if (!empty($row['isbn'])) {
            $xml .= '<dc:identifier>urn:isbn:' . $escape($row['isbn']) . '</dc:identifier>';
        }
        if (!empty($row['issn'])) {
            $xml .= '<dc:identifier>urn:issn:' . $escape($row['issn']) . '</dc:identifier>';
        }
        $xml .= '</srw_dc:dc>';

        return $xml;
    }

    /**
     * SRU 2.0 explain response — endpoint capability advertisement.
     */
    public function explain(): string
    {
        $project = sfProjectConfiguration::getActive();

        $xml = '<explain xmlns="http://explain.z3950.org/dtd/2.0/">';
        $xml .= '<serverInfo>';
        $xml .= '<protocol>SRU</protocol>';
        $xml .= '<version>2.0</version>';
        $xml .= '<transport>http</transport>';
        $xml .= '</serverInfo>';
        $xml .= '<databaseInfo>';
        $xml .= '<title>PSIS Library Catalogue</title>';
        $xml .= '<description>SRU 2.0 endpoint for the PSIS library catalogue.</description>';
        $xml .= '</databaseInfo>';
        $xml .= '<indexInfo>';
        $xml .= '<set name="cql" identifier="info:srw/cql-context-set/1/cql-v1.2"/>';
        $xml .= '<set name="dc" identifier="info:srw/cql-context-set/1/dc-v1.1"/>';
        $xml .= '<set name="bath" identifier="http://zing.z3950.org/cql/bath/2.0/"/>';

        $indexes = [
            ['title', 'dc', 'title', 'Title'],
            ['creator', 'dc', 'creator', 'Creator / Author'],
            ['subject', 'dc', 'subject', 'Subject'],
            ['isbn', 'bath', 'isbn', 'ISBN'],
            ['issn', 'bath', 'issn', 'ISSN'],
        ];

        foreach ($indexes as [$name, $set, $map, $title]) {
            $xml .= '<index><title>' . htmlspecialchars($title) . '</title>';
            $xml .= '<map><name set="' . htmlspecialchars($set) . '">' . htmlspecialchars($map) . '</name></map>';
            $xml .= '</index>';
        }

        $xml .= '</indexInfo>';
        $xml .= '<schemaInfo>';
        $xml .= '<schema identifier="info:srw/schema/1/marcxml-v1.1" name="marcxml"><title>MARC21 XML</title></schema>';
        $xml .= '<schema identifier="info:srw/schema/1/dc-v1.1" name="dc"><title>Dublin Core</title></schema>';
        $xml .= '</schemaInfo>';
        $xml .= '<configInfo>';
        $xml .= '<default type="numberOfRecords">10</default>';
        $xml .= '<setting type="maximumRecords">100</setting>';
        $xml .= '</configInfo>';
        $xml .= '</explain>';

        return $xml;
    }
}
