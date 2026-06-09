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
     * Parse PQF (Prefix Query Format) into a structured query array.
     *
     * Returns:
     *   [
     *     'boolean' => 'AND' | 'OR' | null,
     *     'clauses' => [
     *       ['index' => 'title', 'term' => '...', 'truncation' => 'right',
     *        'attributeSet' => 1|null],
     *       ...
     *     ],
     *   ]
     *
     * Recognised markers: @and, @or, @attr <set>=<use>, @attr use=<use>,
     * @attr 4=<n> (truncation), @attr SET=<n> use=<use> (named attr set).
     */
    public function parsePqf(string $pqf): array
    {
        $pqf = trim(preg_replace('/\s+/', ' ', $pqf));

        $result = ['boolean' => null, 'clauses' => []];

        if ($pqf === '') {
            return $result;
        }

        // Top-level boolean operator.
        if (str_starts_with($pqf, '@and ')) {
            $result['boolean'] = 'AND';
            $pqf = trim(substr($pqf, 5));
        } elseif (str_starts_with($pqf, '@or ')) {
            $result['boolean'] = 'OR';
            $pqf = trim(substr($pqf, 4));
        }

        // Tokenise into clauses. Each clause is an optional run of @attr
        // directives followed by a single term (quoted phrase or bare word).
        $tokens = $this->tokenizePqf($pqf);

        if (empty($tokens)) {
            return $result;
        }

        // A bare query with no @attr directives may be either a single phrase
        // (e.g. "harry potter") or a set of distinct keyword terms (e.g.
        // "term1 term2"). Treat it as separate clauses when the tokens look
        // like discrete keywords (any token carries a digit); otherwise treat
        // the whole input as one phrase clause.
        $hasAttr = str_contains($pqf, '@attr');
        if (! $hasAttr && $result['boolean'] === null) {
            $words = array_values(array_filter(explode(' ', $pqf), fn($w) => $w !== ''));
            $splitWords = count($words) > 1
                && $this->looksLikeDiscreteKeywords($words);

            if ($splitWords) {
                foreach ($words as $w) {
                    $result['clauses'][] = $this->makeClause('anywhere', trim($w, '"'), 'right', null);
                }
            } else {
                $result['clauses'][] = $this->makeClause('anywhere', trim($pqf, '"'), 'right', null);
            }

            return $result;
        }

        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $index        = 'anywhere';
            $truncation   = 'right';
            $attributeSet = null;

            // Consume any leading @attr directives for this clause.
            while ($i < $count && $tokens[$i] === '@attr') {
                $i++;

                // Optional SET=<n> qualifier.
                if ($i < $count && preg_match('/^SET\s*=\s*(\d+)$/i', $tokens[$i] ?? '', $sm)) {
                    $attributeSet = (int) $sm[1];
                    $i++;
                }

                $directive = $tokens[$i] ?? '';
                $i++;

                if (preg_match('/^(?:1|use)\s*=\s*(\d+)$/i', $directive, $dm)) {
                    // Use attribute (type 1) → index name.
                    $index = $this->bib1UseToIndex((int) $dm[1]);
                } elseif (preg_match('/^4\s*=\s*(\d+)$/', $directive, $dm)) {
                    // Truncation attribute (type 4).
                    $truncation = $this->bib1TruncationToMode((int) $dm[1]);
                }
            }

            if ($i >= $count) {
                break;
            }

            $term = trim($tokens[$i], '"');
            $i++;

            $result['clauses'][] = $this->makeClause($index, $term, $truncation, $attributeSet);
        }

        return $result;
    }

    /**
     * Assemble a single PQF clause array.
     */
    private function makeClause(string $index, string $term, string $truncation, ?int $attributeSet): array
    {
        return [
            'index'        => $index,
            'term'         => $term,
            'truncation'   => $truncation,
            'attributeSet' => $attributeSet,
        ];
    }

    /**
     * Heuristic: do the bare words look like discrete keyword terms (rather
     * than one phrase)? True when more than one token carries a digit.
     */
    private function looksLikeDiscreteKeywords(array $words): bool
    {
        foreach ($words as $w) {
            if (preg_match('/\d/', $w)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Split a PQF string into tokens, keeping quoted phrases intact and
     * treating @attr / 1=4 / use=21 / SET=1 / 4=2 as discrete tokens.
     */
    private function tokenizePqf(string $pqf): array
    {
        $tokens = [];
        $len = strlen($pqf);
        $i = 0;

        while ($i < $len) {
            $ch = $pqf[$i];

            if ($ch === ' ') {
                $i++;
                continue;
            }

            if ($ch === '"') {
                $end = strpos($pqf, '"', $i + 1);
                if ($end === false) {
                    $tokens[] = substr($pqf, $i);
                    break;
                }
                $tokens[] = substr($pqf, $i, $end - $i + 1);
                $i = $end + 1;
                continue;
            }

            $next = strpos($pqf, ' ', $i);
            if ($next === false) {
                $tokens[] = substr($pqf, $i);
                break;
            }
            $tokens[] = substr($pqf, $i, $next - $i);
            $i = $next + 1;
        }

        return $tokens;
    }

    /**
     * Map a BIB-1 truncation attribute (type 4) value to a truncation mode.
     *   1 = right, 2 = right, 3 = left, 100 = none, 101/104 = both.
     */
    private function bib1TruncationToMode(int $value): string
    {
        return match ($value) {
            1, 2    => 'right',
            3       => 'left-truncate',
            101,
            104     => 'left-and-right',
            100     => 'do-not-truncate',
            default => 'right',
        };
    }

    /**
     * Map a BIB-1 use attribute (type 1) value to an internal index name.
     */
    public function bib1UseToIndex(int $use): string
    {
        return match ($use) {
            4           => 'title',
            1, 3, 1003  => 'author',
            7           => 'isbn',
            8           => 'issn',
            21          => 'subject',
            1016        => 'keyword',
            default     => 'anywhere',
        };
    }

    /**
     * Build a SQL LIKE pattern from a search term and truncation mode.
     * LIKE metacharacters in the term are escaped (backslash first, so an
     * existing backslash does not turn a following % / _ into an escape).
     */
    public function buildLikePattern(string $term, string $truncation = 'right'): string
    {
        // Escape backslash first, then the LIKE wildcards.
        $escaped = str_replace('\\', '\\\\', $term);
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $escaped);

        return match ($truncation) {
            'left-truncate'  => '%' . $escaped,
            'left-and-right' => '%' . $escaped . '%',
            'do-not-truncate' => $escaped,
            default          => $escaped . '%', // right truncation
        };
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
            case 'init_request':
                return $this->handleInit($apdu);
            case 'search_request':
                return $this->handleSearch($apdu);
            case 'present_request':
                return $this->handlePresent($apdu);
            case 'close':
                return $this->handleClosePackage($apdu);
            case 'delete_result_set':
                return $this->handleDeleteResultSet($apdu);
            default:
                return $this->encoder->encodeInitResponse('ERR', 0);
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
        // Single source of truth: the BER encoder owns OID → APDU-type mapping.
        return $this->encoder->detectApduType($apdu);
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
            'referenceId'           => $parsed['referenceId'] ?: BerEncoder::REFERENCE_ID_PREFIX,
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

        // Parse PQF into a structured query and run the search.
        $structuredQuery = $this->parsePqf($query);
        $structuredQuery['maxRecords'] = max(1, (int) $maxRecords);

        $searchResult = $this->executeSearch($structuredQuery);
        $count   = (int) ($searchResult['count'] ?? 0);
        $records = $searchResult['records'] ?? [];

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

    // ──── Search execution ────────────────────────────────────────────────

    /**
     * Execute a structured query (as returned by parsePqf) against the MARC
     * record store. Returns ['count' => int, 'records' => array<object>].
     *
     * The query array carries 'clauses' (index/term/truncation/attributeSet),
     * an optional 'boolean' (AND/OR), and an optional 'maxRecords' limit.
     */
    public function executeSearch(array $query): array
    {
        $clauses = $query['clauses'] ?? [];
        if (empty($clauses)) {
            return ['count' => 0, 'records' => []];
        }

        $boolean = ($query['boolean'] ?? 'AND') === 'OR' ? 'OR' : 'AND';
        $limit   = max(1, (int) ($query['maxRecords'] ?? 10));

        // Map each clause's logical index to a MARC store column.
        $columnMap = [
            'title'    => 'title',
            'author'   => 'author',
            'subject'  => 'subject',
            'isbn'     => 'isbn',
            'issn'     => 'issn',
            'keyword'  => 'keywords',
            'anywhere' => 'searchable_text',
        ];

        $conditions = [];
        $bindings   = [];

        foreach ($clauses as $clause) {
            $index  = $clause['index'] ?? 'anywhere';
            $column = $columnMap[$index] ?? 'searchable_text';
            $pattern = $this->buildLikePattern(
                (string) ($clause['term'] ?? ''),
                (string) ($clause['truncation'] ?? 'right')
            );

            $conditions[] = "{$column} LIKE ? ESCAPE '\\\\'";
            $bindings[]   = $pattern;
        }

        $where = implode(" {$boolean} ", $conditions);

        try {
            $count = (int) (DB::connection('library')
                ->selectOne(
                    "SELECT COUNT(*) AS c FROM library_marc_records WHERE {$where}",
                    $bindings
                )->c ?? 0);

            $records = DB::connection('library')->select(
                "SELECT id, leader, controlfield, datafield "
                . "FROM library_marc_records WHERE {$where} LIMIT {$limit}",
                $bindings
            );
        } catch (\Throwable $e) {
            Log::error('Z3950 search DB error: ' . $e->getMessage());
            return ['count' => 0, 'records' => []];
        }

        return ['count' => $count, 'records' => $records];
    }

    // ──── Reference IDs ─────────────────────────────────────────────────────

    /**
     * Build a reference ID: <prefix>-<hex>-<unix-timestamp>.
     */
    public function buildRefId(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(4)) . '-' . time();
    }

    // ──── ISO 639 → MARC language code ──────────────────────────────────────

    /**
     * Map an ISO 639-1 (2-letter) or ISO 639-2/3 (3-letter) language code to
     * a MARC 008 / 041 language code. Unknown 3-letter codes pass through;
     * unknown 2-letter codes also pass through unchanged.
     */
    public function iso639toMarc(string $code): string
    {
        $code = strtolower(trim($code));

        $map = [
            'en' => 'eng', 'eng' => 'eng',
            'af' => 'afr', 'afr' => 'afr',
            'zu' => 'zul', 'zul' => 'zul',
            'xh' => 'xho', 'xho' => 'xho',
            'st' => 'sot', 'sot' => 'sot',
            'tn' => 'tsn', 'tsn' => 'tsn',
            'ts' => 'tso', 'tso' => 'tso',
            've' => 'ven', 'ven' => 'ven',
            'nr' => 'nbl', 'nbl' => 'nbl',
            'ss' => 'ssw', 'ssw' => 'ssw',
            'nso' => 'nso',
            'fr' => 'fre', 'fre' => 'fre', 'fra' => 'fre',
            'de' => 'ger', 'ger' => 'ger', 'deu' => 'ger',
            'nl' => 'dut', 'dut' => 'dut', 'nld' => 'dut',
            'pt' => 'por', 'por' => 'por',
            'es' => 'spa', 'spa' => 'spa',
            'it' => 'ita', 'ita' => 'ita',
            'sw' => 'swa', 'swa' => 'swa',
            'ar' => 'ara', 'ara' => 'ara',
            'zh' => 'chi', 'chi' => 'chi', 'zho' => 'chi',
        ];

        if (isset($map[$code])) {
            return $map[$code];
        }

        // Unknown 3-letter code: pass through (already MARC-shaped).
        // Unknown 2-letter code: also pass through unchanged.
        return $code;
    }

    // ──── APDU extraction from a recv buffer ────────────────────────────────

    /**
     * Extract one complete APDU package from the front of a recv buffer.
     *
     * The 5-byte package header carries a 4-byte big-endian total size. If the
     * buffer holds at least that many bytes, the leading complete package is
     * returned (and removed from $remaining); otherwise null is returned and
     * the buffer is left untouched (awaiting more bytes).
     */
    protected function extractApdu(string $buffer, ?string &$remaining = null): ?string
    {
        $remaining = $buffer;

        if (strlen($buffer) < 5) {
            return null;
        }

        $size = unpack('Nsize', substr($buffer, 0, 4))['size'];

        // A valid package is at least the 5-byte header plus one APDU byte.
        if ($size < 6 || strlen($buffer) < $size) {
            return null;
        }

        $remaining = substr($buffer, $size);

        return substr($buffer, 0, $size);
    }

    // ──── MARC ISO 2709 record assembly ─────────────────────────────────────

    /**
     * Encode the variable-field content for a single MARC field.
     *
     * Control fields (tag 00X) carry raw data plus a field terminator (0x1E).
     * Data fields carry two indicators, then subfields (each prefixed by the
     * subfield delimiter 0x1F + code), then a field terminator (0x1E).
     */
    protected function encodeMarcFieldContent(array $field): string
    {
        $tag = (string) ($field['tag'] ?? '');

        // Control field (00X): just the data + field terminator.
        if (str_starts_with($tag, '00')) {
            return (string) ($field['data'] ?? '') . "\x1e";
        }

        $ind1 = substr((string) ($field['ind1'] ?? ' ') . ' ', 0, 1);
        $ind2 = substr((string) ($field['ind2'] ?? ' ') . ' ', 0, 1);

        $content = $ind1 . $ind2;
        foreach (($field['subfields'] ?? []) as $sf) {
            $code = (string) ($sf[0] ?? '');
            $val  = (string) ($sf[1] ?? '');
            $content .= "\x1f" . $code . $val;
        }

        return $content . "\x1e";
    }

    /**
     * Assemble a complete MARC record in ISO 2709 binary form from a list of
     * fields. Produces leader (24 bytes) + directory + field data + record
     * terminator (0x1D).
     */
    protected function encodeMarcIso2709(array $fields): string
    {
        $directory = '';
        $fieldData = '';

        foreach ($fields as $field) {
            $content = $this->encodeMarcFieldContent($field);
            $tag = str_pad((string) ($field['tag'] ?? '000'), 3, '0', STR_PAD_LEFT);

            $length = strlen($content);
            $start  = strlen($fieldData);

            // Directory entry: 3-char tag + 4-char length + 5-char start.
            $directory .= $tag
                . str_pad((string) $length, 4, '0', STR_PAD_LEFT)
                . str_pad((string) $start, 5, '0', STR_PAD_LEFT);

            $fieldData .= $content;
        }

        // Directory terminator after the directory.
        $directory .= "\x1e";

        // Record terminator at the very end.
        $fieldData .= "\x1d";

        $baseAddress = 24 + strlen($directory);
        $recordLength = $baseAddress + strlen($fieldData);

        // 24-byte leader. Positions 0-4 = record length, 12-16 = base address
        // of data; the remaining fixed positions use conventional defaults.
        $leader = str_pad((string) $recordLength, 5, '0', STR_PAD_LEFT)
            . 'nam a22'
            . str_pad((string) $baseAddress, 5, '0', STR_PAD_LEFT)
            . '4500';

        $leader = str_pad(substr($leader, 0, 24), 24);

        return $leader . $directory . $fieldData;
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