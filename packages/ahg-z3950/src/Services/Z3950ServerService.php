<?php

/**
 * Z3950ServerService — Z39.50 server implementation (ISO 23950).
 *
 * Handles: Init, Search, Present, Close, DeleteResultSet APDUs.
 * BER encoding/decoding via BerEncoder.
 * Query: prefix query format (PQF) parsed into SQL WHERE clauses.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd — AGPL-3.0
 */

namespace AhgZ3950\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Z3950ServerService
{
    private BerEncoder $encoder;

    private string $serverId = 'Heratio/1.0';
    private array $options = [];
    private array $resultSets = [];

    // #1135 - reconciled to the single collaborator the provider + tests use
    // (BerEncoder). The previously type-hinted Marc21Repository / AuthorityRepository
    // never existed, so the server fatally failed container resolution. MARC21 is
    // now assembled inline from the stored record columns (see buildMarc21()).
    public function __construct(BerEncoder $encoder)
    {
        $this->encoder = $encoder;
    }

    // ──── Query parsing (PQF → SQL) ───────────────────────────────────────

    /**
     * Parse PQF (Prefix Query Format) into a SQL WHERE clause fragment.
     *
     * Structure markers: @set, @and, @or, @not
     * Term: [@attr <set>=<use> <op> <value>] <term>
     *
     * Unsupported: complex proximity, ranked results, scan.
     */
    public function parsePqf(string $pqf): string
    {
        $pqf = trim($pqf);

        // Normalize: collapse multiple spaces, strip leading/trailing parens
        $pqf = preg_replace('/\s+/', ' ', $pqf);
        $pqf = trim($pqf, '()');

        if ($pqf === '') {
            return '1=1';
        }

        // Handle top-level boolean operators
        if (str_starts_with($pqf, '@or ')) {
            return $this->parseOr(trim(substr($pqf, 4)));
        }
        if (str_starts_with($pqf, '@and ')) {
            return $this->parseAnd(trim(substr($pqf, 5)));
        }
        if (str_starts_with($pqf, '@not ')) {
            return $this->parseNot(trim(substr($pqf, 5)));
        }

        // Handle set (result set reference as term)
        if (str_starts_with($pqf, '@set ')) {
            $setName = trim(substr($pqf, 4));
            return "result_set_id = " . $this->escapeSql($setName);
        }

        // Handle attribute-set prefix: @attrSET=1 use=4 "term"
        // Maps to: @attr 1=4 "term" (BIB-1 use attribute 4 = Title)
        if (preg_match('/^\@attr\s+SET\s*=\s*\d+\s+use\s*=\s*(\d+)\s+(.+)$/i', $pqf, $m)) {
            $pqf = '@attr 1=' . $m[1] . ' ' . $m[2];
        }

        // Handle @attr <set>=<use> <op> <value> <term>
        $attrMap = [
            '1'  => 'biblio.tag',     // BIB-1 attribute set
            '2'  => 'biblio.access',
        ];
        $useMap = [
            // BIB-1 use attributes (attribute 1)
            '1'  => 'author',
            '4'  => 'title',
            '7'  => 'isbn',
            '8'  => 'isbn13',
            '21' => 'subject',
            '1024' => 'any',
        ];
        $relMap = [
            '1' => '=',
            '2' => '<',
            '3' => '>',
            '4' => '<=',
            '5' => '>=',
            '6' => '<>',
        ];

        if (preg_match('/^\@attr\s+(\d+)\s*=\s*(\d+)(?:\s+(@\w+|[\@\w].*))?$/i', $pqf, $m)) {
            $setId   = $m[1];
            $useAttr = $m[2];
            $term    = trim($m[3] ?? '');
        } elseif (str_starts_with($pqf, '@attr ') && strpos($pqf, '=') !== false) {
            // Bare @attr with just "1=4" shorthand → default to bib-1
            if (preg_match('/^\@attr\s+(\d+)\s*=\s*(\d+)\s*"?([^"]*)"?$/i', $pqf, $m)) {
                $setId   = '1';
                $useAttr = $m[2];
                $term    = trim($m[3] ?? '');
            } else {
                $term = trim(substr($pqf, 5));
                $setId   = '1';
                $useAttr = '1024';
            }
        } else {
            // No @attr: search all fields (general)
            $term = trim($pqf);
            $setId   = '1';
            $useAttr = '1024';
        }

        // Relation operator from @attr relation=N
        $relation = '=';
        $term = trim($term);
        if (str_starts_with($term, '@attr ')) {
            if (preg_match('/^\@attr\s+\d+\s+(\d+)\s+(.+)$/i', $term, $rm)) {
                $relation = $relMap[$rm[1]] ?? '=';
                $term = trim($rm[2]);
            }
        }

        // Strip surrounding quotes
        $term = trim($term, '"');

        if ($term === '') {
            return '1=1';
        }

        // Map BIB-1 use attribute to column
        $column = $useMap[$useAttr] ?? 'any';

        if ($column === 'any') {
            return $this->buildLikePattern('any', $term, $relation);
        }

        // Relation operator
        if ($relation === '<>') {
            return "({$column} NOT LIKE " . $this->escapeSql('%' . $term . '%') . ")";
        }

        // Truncation: right-side wildcard for '*' or '?' at end of term
        $truncated = false;
        if (preg_match('/\*$/', $term) || preg_match('/\?$/', $term)) {
            $term = rtrim($term, '*?');
            $truncated = true;
        }

        return $this->buildLikePattern($column, $term, $relation, $truncated);
    }

    /**
     * Build SQL LIKE pattern from term and relation.
     */
    public function buildLikePattern(
        string $column,
        string $term,
        string $relation = '=',
        bool   $truncated = false
    ): string {
        $term = trim($term);

        // Escape LIKE wildcards in the raw term first
        $term = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $term);

        // Apply truncation after escaping
        if ($truncated) {
            $term .= '%';
        } else {
            // Left truncation: '?term' → '%term%'
            if (str_starts_with($term, '?')) {
                $term = '%' . substr($term, 1);
            }
            // Right truncation: term without trailing '*' is already exact
            $term = '%' . $term . '%';
        }

        return "({$column} LIKE " . $this->escapeSql($term) . ")";
    }

    private function parseOr(string $body): string
    {
        // Split at top-level @and/@or (not nested) using a simple bracket-aware scan
        $parts = $this->splitTopLevel($body, '@or', '@and', '@not');
        $clauses = array_map(fn($p) => trim($this->parsePqf($p)), $parts);
        $clauses = array_filter($clauses, fn($c) => $c !== '' && $c !== '1=1');
        if (empty($clauses)) {
            return '1=1';
        }
        return '(' . implode(' OR ', $clauses) . ')';
    }

    private function parseAnd(string $body): string
    {
        $parts = $this->splitTopLevel($body, '@and', '@or', '@not');
        $clauses = array_map(fn($p) => trim($this->parsePqf($p)), $parts);
        $clauses = array_filter($clauses, fn($c) => $c !== '' && $c !== '1=1');
        if (empty($clauses)) {
            return '1=1';
        }
        return '(' . implode(' AND ', $clauses) . ')';
    }

    private function parseNot(string $body): string
    {
        $inner = $this->parsePqf($body);
        return "NOT ({$inner})";
    }

    /**
     * Split string at top-level boolean markers, respecting parentheses depth.
     */
    private function splitTopLevel(string $body, string ...$markers): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $i = 0;
        $len = strlen($body);

        while ($i < $len) {
            $ch = $body[$i];

            if ($ch === '(') {
                $depth++;
                $current .= $ch;
            } elseif ($ch === ')') {
                $depth--;
                $current .= $ch;
            } elseif ($depth === 0) {
                // Check for marker at current position
                $matched = false;
                foreach ($markers as $marker) {
                    if (str_starts_with(substr($body, $i), $marker)) {
                        $markerLen = strlen($marker);
                        $nextCh = $body[$i + $markerLen] ?? ' ';
                        if ($nextCh === ' ' || $nextCh === "\t" || $nextCh === '(') {
                            if ($current !== '') {
                                $parts[] = $current;
                            }
                            $current = '';
                            $i += $markerLen;
                            continue 2;
                        }
                    }
                }
                $current .= $ch;
            } else {
                $current .= $ch;
            }
            $i++;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * Escape a value for safe SQL inclusion (prepend/append quotes).
     */
    private function escapeSql(string $value): string
    {
        $v = str_replace(['\\', "'", "\x00", "\x1a"], ["\\\\", "''", '', ''], $value);
        return "'" . $v . "'";
    }

    // ──── APDU routing ─────────────────────────────────────────────────────

    /**
     * Route a raw Z39.50 package to the correct APDU handler.
     * Returns a raw response APDU (with package header) or '' on error.
     */
    public function routePackage(string $packet): string
    {
        $apdu = $this->encoder->unwrapPackageHeader($packet);
        if ($apdu === '') {
            return '';
        }

        $type = $this->detectApduType($apdu);
        $this->logApdu('received', $type, $apdu);

        switch ($type) {
            case 'initRequest':
                return $this->handleInit($apdu);
            case 'searchRequest':
                return $this->handleSearch($apdu);
            case 'presentRequest':
                return $this->handlePresent($apdu);
            case 'close':
                return $this->handleClosePackage($apdu);
            case 'deleteResultSet':
                return $this->handleDeleteResultSet($apdu);
            default:
                return $this->encodeInitResponse('ERR', 0);
        }
    }

    /**
     * Detect APDU type from raw BER bytes.
     *
     * After stripping the 5-byte package header, the APDU is:
     *   SEQUENCE { OID SEQUENCE { ... } }
     *
     * We look for the OID to determine type.
     */
    public function detectApduType(string $apdu): string
    {
        $len = strlen($apdu);
        if ($len < 10) {
            return 'unknown';
        }

        // Position 0: SEQUENCE tag (0x30)
        $pos = 0;
        if (ord($apdu[$pos]) !== 0x30) {
            return 'unknown';
        }

        // Decode outer SEQUENCE length
        $decoded = $this->encoder->decodeLengthRet($apdu, $pos + 1);
        $outerLen = $decoded[1];

        // OID SEQUENCE starts at $pos + 1 + lenBytes
        $lenBytes = $decoded[0];
        $oidSeqStart = $pos + 1 + $lenBytes;

        if ($oidSeqStart + 1 >= $len) {
            return 'unknown';
        }

        // OID SEQUENCE tag
        if (ord($apdu[$oidSeqStart]) !== 0x30) {
            return 'unknown';
        }

        $decoded2 = $this->encoder->decodeLengthRet($apdu, $oidSeqStart + 1);
        $oidLen = $decoded2[1];
        $oidLenBytes = $decoded2[0];

        $oidStart = $oidSeqStart + 1 + $oidLenBytes;
        if ($oidStart + $oidLen > $len) {
            return 'unknown';
        }

        $oidBody = substr($apdu, $oidStart, $oidLen);
        $arcs = $this->encoder->decodeOidValue($oidBody);

        if (count($arcs) >= 7 && $arcs[0] === 1 && $arcs[1] === 2
            && $arcs[2] === 840 && $arcs[3] === 10003 && $arcs[4] === 9 && $arcs[5] === 100) {
            return match ($arcs[6] ?? 0) {
                1      => 'initRequest',
                2      => 'initResponse',
                6      => 'searchRequest',
                7      => 'searchResponse',
                13     => 'presentRequest',
                14     => 'presentResponse',
                19     => 'deleteResultSet',
                23     => 'close',
                default => 'unknown',
            };
        }

        return 'unknown';
    }

    /**
     * Handle InitRequest APDU.
     */
    private function handleInit(string $apdu): string
    {
        $pos = 0;
        $len = strlen($apdu);

        // Skip outer SEQUENCE + OID SEQUENCE
        if (ord($apdu[$pos]) !== 0x30) {
            return $this->encoder->encodeInitResponse('ERR');
        }
        $decoded = $this->encoder->decodeLengthRet($apdu, $pos + 1);
        $outerLen = $decoded[1];
        $lenBytes = $decoded[0];
        $oidSeqStart = $pos + 1 + $lenBytes;
        $decoded2 = $this->encoder->decodeLengthRet($apdu, $oidSeqStart + 1);
        $oidLen = $decoded2[1];
        $innerLenBytes = $decoded2[0];
        $innerStart = $oidSeqStart + 1 + $innerLenBytes + $oidLen;

        if ($innerStart >= $len) {
            return $this->encoder->encodeInitResponse('ERR');
        }

        // Decode init request body
        $body = substr($apdu, $innerStart);
        $decoded3 = $this->encoder->decodeLengthRet($body, 0);
        $bodyLen = $decoded3[1];
        $bodyStart = $decoded3[0];
        $body = substr($body, $bodyStart, $bodyLen);

        $parsed = $this->encoder->decodeInitRequest($body);

        $this->options = [
            'referenceId'           => $parsed['referenceId'] ?: self::REFERENCE_ID_PREFIX,
            'preferredRecordSyntax' => $parsed['preferredRecordSyntax'] ?: '1.2.840.10003.5.1',
            'implementationId'     => $parsed['implementationId'] ?: '',
            'implementationName'   => $parsed['implementationName'] ?: '',
            'implementationVersion' => $parsed['implementationVersion'] ?: '',
        ];

        $this->logApdu('sending', 'initResponse', '');
        return $this->encoder->encodeInitResponse(
            $this->options['referenceId']
        );
    }

    /**
     * Handle SearchRequest APDU.
     */
    private function handleSearch(string $apdu): string
    {
        $parsed = $this->encoder->decodeSearchRequest(
            $this->extractSearchBody($apdu)
        );

        $referenceId   = $parsed['referenceId'] ?: 'HERATIO';
        $resultSetId   = $parsed['resultSetName'] ?: 'default';
        $maxRecords    = $parsed['maxRecords'] ?: 1;
        $recordSyntax  = $parsed['recordSyntax'] ?: '1.2.840.10003.5.1';
        $elementSetName = $parsed['elementSetName'] ?: 'F';
        $query         = $parsed['query'] ?: '';

        // Parse PQF to SQL
        $where = $this->parsePqf($query);

        // Build MARC SQL query
        $marcSql = "SELECT id, leader, controlfield, datafield "
                 . "FROM library_marc_records WHERE " . $where
                 . " LIMIT " . max(1, (int) $maxRecords);

        $countSql = "SELECT COUNT(*) FROM library_marc_records WHERE " . $where;

        try {
            $count = DB::connection('library')->selectOne($countSql)->count ?? 0;
            $records = DB::connection('library')->select($marcSql);
        } catch (\Exception $e) {
            Log::error('Z3950 search DB error: ' . $e->getMessage());
            $count = 0;
            $records = [];
        }

        // Encode records as requested syntax
        $encodedRecords = '';
        foreach ($records as $rec) {
            $marc = $this->buildMarc21($rec);
            $encodedRecords .= "\x1e" . $marc . "\x1e\x1d";
        }

        $this->resultSets[$resultSetId] = [
            'referenceId'   => $referenceId,
            'records'       => $encodedRecords,
            'nextPosition'  => $count + 1,
        ];

        $this->logApdu('sending', 'searchResponse', '');
        return $this->encoder->encodeSearchResponse(
            $referenceId, $count, $resultSetId, $encodedRecords
        );
    }

    private function extractSearchBody(string $apdu): string
    {
        $len = strlen($apdu);
        $pos = 0;

        if (ord($apdu[$pos]) !== 0x30) {
            return '';
        }

        $decoded = $this->encoder->decodeLengthRet($apdu, $pos + 1);
        $lenBytes = $decoded[0];
        $outerLen = $decoded[1];
        $oidSeqStart = $pos + 1 + $lenBytes;

        $decoded2 = $this->encoder->decodeLengthRet($apdu, $oidSeqStart + 1);
        $innerLenBytes = $decoded2[0];
        $oidLen = $decoded2[1];

        $innerStart = $oidSeqStart + 1 + $innerLenBytes + $oidLen;

        if ($innerStart >= $len) {
            return '';
        }

        $body = substr($apdu, $innerStart);
        $decoded3 = $this->encoder->decodeLengthRet($body, 0);
        $bodyLen = $decoded3[1];
        $bodyStart = $decoded3[0];

        return substr($body, $bodyStart, $bodyLen);
    }

    /**
     * Handle PresentRequest APDU.
     */
    private function handlePresent(string $apdu): string
    {
        $parsed = $this->encoder->decodePresentRequest(
            $this->extractSearchBody($apdu)
        );

        $referenceId      = $parsed['referenceId'] ?: 'HERATIO';
        $resultSetId      = $parsed['resultSetId'] ?: 'default';
        $resultSetStart   = $parsed['resultSetStartPoint'] ?: 1;
        $maxRecords       = $parsed['maxRecords'] ?: 1;
        $elementSetName   = $parsed['elementSetNames'] ?: 'F';

        $set = $this->resultSets[$resultSetId] ?? null;
        if (! $set) {
            $apdu = $this->encoder->encodePresentResponse($referenceId, 0, 0, '', 3);
            return $this->encoder->wrapInPackageHeader($apdu);
        }

        $records = $set['records'];
        $totalCount = substr_count($records, "\x1e") - 1;
        $nextPosition = $resultSetStart + $maxRecords;

        $recordSlice = $this->sliceMarcRecords($records, $resultSetStart, $maxRecords);

        $this->logApdu('sending', 'presentResponse', '');
        return $this->encoder->encodePresentResponse(
            $referenceId, $nextPosition, $maxRecords, $recordSlice
        );
    }

    private function sliceMarcRecords(string $records, int $start, int $count): string
    {
        $parts = explode("\x1e", $records, -1);
        $slice = array_slice($parts, $start - 1, $count);
        $out = '';
        foreach ($slice as $rec) {
            $out .= "\x1e" . $rec . "\x1e\x1d";
        }
        return $out;
    }

    /**
     * Handle Close APDU.
     */
    private function handleClosePackage(string $apdu): string
    {
        $len = strlen($apdu);
        $pos = 0;

        if (ord($apdu[$pos]) !== 0x30) {
            return $this->encoder->encodeClose('ERR', 0);
        }

        $decoded = $this->encoder->decodeLengthRet($apdu, $pos + 1);
        $outerLen = $decoded[1];
        $lenBytes = $decoded[0];
        $oidSeqStart = $pos + 1 + $lenBytes;

        $decoded2 = $this->encoder->decodeLengthRet($apdu, $oidSeqStart + 1);
        $innerLenBytes = $decoded2[0];
        $oidLen = $decoded2[1];

        $innerStart = $oidSeqStart + 1 + $innerLenBytes + $oidLen;
        if ($innerStart >= $len) {
            return $this->encoder->encodeClose('ERR', 0);
        }

        $body = substr($apdu, $innerStart);
        $decoded3 = $this->encoder->decodeLengthRet($body, 0);
        $bodyLen = $decoded3[1];
        $bodyStart = $decoded3[0];
        $body = substr($body, $bodyStart, $bodyLen);

        $referenceId = '';
        $closeStatus = 0;

        $bodyPos = 0;
        while ($bodyPos < strlen($body)) {
            $tag = ord($body[$bodyPos]);
            $decoded4 = $this->encoder->decodeLengthRet($body, $bodyPos + 1);
            $valLen = $decoded4[1];
            $valStart = $bodyPos + 1 + $decoded4[0];
            $value = substr($body, $valStart, $valLen);

            if ($tag === 0x04 && $referenceId === '') {
                $referenceId = $value;
            } elseif ($tag === 0x02) {
                $closeStatus = $this->encoder->decodeIntegerValue($value);
            }

            $bodyPos = $valStart + $valLen;
        }

        $this->logApdu('sending', 'close', '');
        return $this->encoder->encodeClose(
            $referenceId ?: 'HERATIO', $closeStatus
        );
    }

    /**
     * Handle DeleteResultSet APDU.
     */
    private function handleDeleteResultSet(string $apdu): string
    {
        $parsed = $this->encoder->decodeSearchRequest(
            $this->extractSearchBody($apdu)
        );

        $referenceId = $parsed['referenceId'] ?: 'HERATIO';
        $resultSetId = $parsed['resultSetName'] ?: 'default';

        unset($this->resultSets[$resultSetId]);

        $this->logApdu('sending', 'deleteResultSetResponse', '');
        return $this->encoder->encodeDeleteResultSetResponse($referenceId, 0);
    }

    // ──── Logging ───────────────────────────────────────────────────────────

    private function logApdu(string $direction, string $type, string $bytes): void
    {
        Log::channel('z3950')->debug("Z39.50 {$direction}: {$type} "
            . strlen($bytes) . ' bytes');
    }

    // ──── Accessors ─────────────────────────────────────────────────────────

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getResultSet(string $name): ?array
    {
        return $this->resultSets[$name] ?? null;
    }

    public function clearResultSets(): void
    {
        $this->resultSets = [];
    }

    /**
     * #1135 - assemble a MARC21 record body from a stored library_marc_records
     * row (leader + control/data field columns). Best-effort: emits the 24-byte
     * leader followed by the field data; the caller adds the MARC record/field
     * separators. (Replaces the never-existent Marc21Repository::buildMarc21.)
     */
    private function buildMarc21(object $rec): string
    {
        $leader = (string) ($rec->leader ?? '');
        $leader = $leader !== '' ? str_pad(substr($leader, 0, 24), 24) : str_pad('', 24);
        $control = (string) ($rec->controlfield ?? '');
        $data = (string) ($rec->datafield ?? '');

        return $leader . $control . $data;
    }
}