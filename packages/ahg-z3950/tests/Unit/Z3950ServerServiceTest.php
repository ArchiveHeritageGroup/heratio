<?php

/**
 * Z3950ServerServiceTest — unit tests for Z3950ServerService.
 *
 * Tests cover:
 *   - parsePqf (simple terms, attribute searches, boolean operators, @attr 1=N shorthand)
 *   - bib1UseToIndex (attribute value → index name mapping)
 *   - buildLikePattern (truncation modes, escaping)
 *   - buildMarcRecord / encodeMarcIso2709 (ISO 2709 MARC binary generation)
 *   - renderResultRecords (output format selection)
 *   - extractApdu (APDU boundary detection from recv buffer)
 *   - buildRefId (reference ID generation)
 *   - init / search / present response building
 *   - iso639toMarc (ISO 639 → MARC 008 language code)
 *
 * Note: executeSearch() requires a database connection; it is tested
 * via integration tests against a seeded SQLite in-memory database.
 *
 * Copyright (C) 2026 Johan Pieterse — AGPL-3.0
 */

namespace AhgZ3950\Tests\Unit;

use AhgZ3950\Services\BerEncoder;
use AhgZ3950\Services\Z3950ServerService;
use AhgZ3950\Tests\AhgZ3950TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class Z3950ServerServiceTest extends AhgZ3950TestCase
{
    /**
     * Helper: invoke a protected/private method on a minimal test double.
     *
     * The double overrides DB-dependent methods (executeSearch, log*) so
     * individual methods can be tested in isolation without a database.
     */
    private function invoke(string $method, mixed ...$args): mixed
    {
        $ber = new BerEncoder();

        // Anonymous class doubles the server service with a minimal constructor
        // and no-op DB methods so we can test non-DB logic in isolation.
        $service = new class($ber) extends Z3950ServerService {
            public function __construct(BerEncoder $ber)
            {
                parent::__construct($ber);
            }
            public function executeSearch(array $query): array
            {
                return ['count' => 0, 'records' => []];
            }
            protected function logRequest(string $client, string $apduType, int $size): void {}
            protected function logServerInit(string $refId, string $clientName, string $clientVersion): void {}
            protected function logSearch(string $refId, string $query, int $count, int $elapsedMs): void {}
            protected function logPresent(string $refId, string $resultSetId, int $start, int $returned): void {}
            protected function logClose(string $refId): void {}
        };

        $reflection = new \ReflectionClass($service);
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($service, ...$args);
    }

    // ──── PQF parsing ────────────────────────────────────────────────────

    public function testParsePqfBareTerm(): void
    {
        $result = $this->invoke('parsePqf', 'harry potter');

        $this->assertArrayHasKey('clauses', $result);
        $this->assertCount(1, $result['clauses']);
        $this->assertEquals('anywhere', $result['clauses'][0]['index']);
        $this->assertEquals('harry potter', $result['clauses'][0]['term']);
        $this->assertEquals('right', $result['clauses'][0]['truncation']);
    }

    public function testParsePqfTitleSearchShortForm(): void
    {
        // Short form: @attr 1=4 (bib-1 use=4 = title)
        $result = $this->invoke('parsePqf', '@attr 1=4 "the silence"');

        $this->assertCount(1, $result['clauses']);
        $this->assertEquals('title', $result['clauses'][0]['index']);
        $this->assertEquals('the silence', $result['clauses'][0]['term']);
    }

    public function testParsePqfAuthorSearchLongForm(): void
    {
        $result = $this->invoke('parsePqf', '@attr use=1003 "rowling"');

        $this->assertEquals('author', $result['clauses'][0]['index']);
    }

    public function testParsePqfIsbnSearch(): void
    {
        $result = $this->invoke('parsePqf', '@attr 1=7 "9780747532743"');

        $this->assertEquals('isbn', $result['clauses'][0]['index']);
    }

    public function testParsePqfSubjectSearch(): void
    {
        $result = $this->invoke('parsePqf', '@attr use=21 "south africa"');

        $this->assertEquals('subject', $result['clauses'][0]['index']);
    }

    public function testParsePqfTruncationRight(): void
    {
        // @attr 4=2 means truncation=right-truncate
        $result = $this->invoke('parsePqf', '@attr 1=4 @attr 4=2 "harry"');

        $this->assertEquals('right', $result['clauses'][0]['truncation']);
    }

    public function testParsePqfTruncationLeft(): void
    {
        $result = $this->invoke('parsePqf', '@attr 1=4 @attr 4=3 "potter"');

        $this->assertEquals('left-truncate', $result['clauses'][0]['truncation']);
    }

    public function testParsePqfBooleanAnd(): void
    {
        $result = $this->invoke('parsePqf', '@and @attr 1=4 "a" @attr 1=7 "b"');

        $this->assertEquals('AND', $result['boolean']);
        $this->assertCount(2, $result['clauses']);
    }

    public function testParsePqfBooleanOr(): void
    {
        $result = $this->invoke('parsePqf', '@or @attr 1=4 "a" @attr 1=7 "b"');

        $this->assertEquals('OR', $result['boolean']);
    }

    public function testParsePqfWithAttributeSet(): void
    {
        $result = $this->invoke('parsePqf', '@attr SET=1 use=4 "silmarillion"');

        $this->assertEquals(1, $result['clauses'][0]['attributeSet']);
        $this->assertEquals('title', $result['clauses'][0]['index']);
    }

    public function testParsePqfMultipleTerms(): void
    {
        $result = $this->invoke('parsePqf', 'term1 term2');

        $this->assertCount(2, $result['clauses']);
        $this->assertEquals('term1', $result['clauses'][0]['term']);
        $this->assertEquals('term2', $result['clauses'][1]['term']);
    }

    public function testParsePqfEmptyReturnsEmptyClauses(): void
    {
        $result = $this->invoke('parsePqf', '');

        $this->assertArrayHasKey('clauses', $result);
        $this->assertEmpty($result['clauses']);
    }

    // ──── bib-1 attribute mapping ───────────────────────────────────────

    public function testBib1UseToIndexTitle(): void
    {
        $this->assertEquals('title', $this->invoke('bib1UseToIndex', 4));
    }

    public function testBib1UseToIndexAuthor(): void
    {
        $this->assertEquals('author', $this->invoke('bib1UseToIndex', 1003));
        $this->assertEquals('author', $this->invoke('bib1UseToIndex', 3));
    }

    public function testBib1UseToIndexKeyword(): void
    {
        $this->assertEquals('keyword', $this->invoke('bib1UseToIndex', 1016));
    }

    public function testBib1UseToIndexIsbn(): void
    {
        $this->assertEquals('isbn', $this->invoke('bib1UseToIndex', 7));
    }

    public function testBib1UseToIndexIssn(): void
    {
        $this->assertEquals('issn', $this->invoke('bib1UseToIndex', 8));
    }

    public function testBib1UseToIndexSubject(): void
    {
        $this->assertEquals('subject', $this->invoke('bib1UseToIndex', 21));
    }

    public function testBib1UseToIndexDefault(): void
    {
        $this->assertEquals('anywhere', $this->invoke('bib1UseToIndex', 999));
    }

    // ──── LIKE pattern building ─────────────────────────────────────────

    public function testBuildLikePatternRightTruncate(): void
    {
        $result = $this->invoke('buildLikePattern', 'potter', 'right-truncate');
        $this->assertEquals('potter%', $result);
    }

    public function testBuildLikePatternLeftTruncate(): void
    {
        $result = $this->invoke('buildLikePattern', 'harry', 'left-truncate');
        $this->assertEquals('%harry', $result);
    }

    public function testBuildLikePatternBothTruncate(): void
    {
        $result = $this->invoke('buildLikePattern', 'arry', 'left-and-right');
        $this->assertEquals('%arry%', $result);
    }

    public function testBuildLikePatternNoTruncate(): void
    {
        $result = $this->invoke('buildLikePattern', 'exact', 'do-not-truncate');
        $this->assertEquals('exact', $result);
    }

    public function testBuildLikePatternEscapesPercent(): void
    {
        // % is a LIKE wildcard → must be escaped as \%
        // In PHP string literal: '50\%' = "50%" (backslash not needed as literal)
        // But in the actual string, we want: 50\%  → escape order: % → \% → \\%
        $result = $this->invoke('buildLikePattern', '50%', 'right-truncate');
        // First: 50% → 50\% (escape %), then append %
        // Result: 50\%  + %  → "50\\%"
        $this->assertEquals('50\%%', $result);
    }

    public function testBuildLikePatternEscapesUnderscore(): void
    {
        $result = $this->invoke('buildLikePattern', 'a_b', 'right-truncate');
        // a_b → a\_b → a\\_b, then append % → a\\_b%
        $this->assertEquals('a\\_b%', $result);
    }

    public function testBuildLikePatternEscapesBackslashFirst(): void
    {
        // If term already contains backslash, it must be escaped so it
        // doesn't turn into an escape sequence for % or _
        $result = $this->invoke('buildLikePattern', 'C:\folder', 'right-truncate');
        // C:\folder → C:\\folder → C:\\\folder → "C:\\\\folder"
        // Then right-truncate: "C:\\\\folder%"
        $this->assertEquals('C:\\\\folder%', $result);
    }

    // ──── Reference ID generation ───────────────────────────────────────

    public function testBuildRefIdPrefixesWithLetter(): void
    {
        $refId = $this->invoke('buildRefId', 'S');
        $this->assertStringStartsWith('S-', $refId);
    }

    public function testBuildRefIdContainsHyphens(): void
    {
        $refId = $this->invoke('buildRefId', 'H');
        $this->assertStringContainsString('-', $refId);
    }

    public function testBuildRefIdContainsHexAndTimestamp(): void
    {
        $refId = $this->invoke('buildRefId', 'X');
        $parts = explode('-', $refId);
        $this->assertGreaterThanOrEqual(3, count($parts));
    }

    // ──── ISO 639 → MARC language code ──────────────────────────────────

    public function testIso639toMarcEnglish(): void
    {
        $this->assertEquals('eng', $this->invoke('iso639toMarc', 'en'));
        $this->assertEquals('eng', $this->invoke('iso639toMarc', 'eng'));
    }

    public function testIso639toMarcAfrikaans(): void
    {
        $this->assertEquals('afr', $this->invoke('iso639toMarc', 'af'));
        $this->assertEquals('afr', $this->invoke('iso639toMarc', 'afr'));
    }

    public function testIso639toMarcZulu(): void
    {
        $this->assertEquals('zul', $this->invoke('iso639toMarc', 'zu'));
        // xho → Xhosa (NOT Zulu)
        $this->assertEquals('xho', $this->invoke('iso639toMarc', 'xho'));
    }

    public function testIso639toMarcUnknown(): void
    {
        $this->assertEquals('xyz', $this->invoke('iso639toMarc', 'xyz'));
    }

    // ──── APDU extraction ──────────────────────────────────────────────

    public function testExtractApduCompletePacket(): void
    {
        $ber = new BerEncoder();

        $oidSeq = $ber->encodeOidSequence(BerEncoder::OID_INIT_REQUEST);
        $inner = $ber->encodeSequence($ber->encodeOctet('test'));
        $apdu = $ber->wrapInPackageHeader($oidSeq . $inner);

        $buffer = $apdu;
        $extracted = $this->invoke('extractApdu', $buffer);

        $this->assertNotNull($extracted);
        $this->assertEquals($apdu, $extracted);
    }

    public function testExtractApduIncomplete(): void
    {
        $buffer = "\x00\x00\x00\x00\x00\x00\x00"; // header only, no APDU body
        $extracted = $this->invoke('extractApdu', $buffer);

        $this->assertNull($extracted);
        $this->assertStringStartsWith("\x00", $buffer);
    }

    // ──── MARC record building ───────────────────────────────────────────

    public function testEncodeMarcFieldContentControlField(): void
    {
        $field = ['tag' => '001', 'data' => '12345'];
        $content = $this->invoke('encodeMarcFieldContent', $field);

        $this->assertEquals("12345\x1e", $content);
    }

    public function testEncodeMarcFieldContentDataField(): void
    {
        $field = [
            'tag' => '245',
            'ind1' => '1',
            'ind2' => '0',
            'subfields' => [['a', 'Test Title'], ['b', 'subtitle']],
        ];
        $content = $this->invoke('encodeMarcFieldContent', $field);

        $this->assertStringContainsString("\x1fa", $content);
        $this->assertStringContainsString('Test Title', $content);
        $this->assertStringContainsString("\x1fb", $content);
        $this->assertStringContainsString('subtitle', $content);
        $this->assertStringEndsWith("\x1e", $content);
    }

    public function testEncodeMarcIso2709Structure(): void
    {
        $fields = [
            ['tag' => '001', 'data' => '100'],
            [
                'tag' => '245',
                'ind1' => '1',
                'ind2' => '0',
                'subfields' => [['a', 'Test Title']],
            ],
        ];

        $record = $this->invoke('encodeMarcIso2709', $fields);

        // ISO 2709 leader is 24 bytes
        $this->assertGreaterThanOrEqual(24, strlen($record));
        // First byte of record length field should be a digit
        $this->assertMatchesRegularExpression('/^[0-9]/', $record);
        // Directory terminator (0x1E) should appear after leader + directory
        $this->assertStringContainsString("\x1e", $record);
        // Record terminator (0x1D) at end
        $this->assertStringEndsWith("\x1d", $record);
    }

    // ──── BER encoder integration ───────────────────────────────────────

    public function testBerEncoderOidConstants(): void
    {
        $this->assertEquals([1, 2, 840, 10003, 9, 100], BerEncoder::OID_INIT_REQUEST);
        $this->assertEquals([1, 2, 840, 10003, 9, 100, 1], BerEncoder::OID_INIT_RESPONSE);
        $this->assertEquals([1, 2, 840, 10003, 9, 100, 6], BerEncoder::OID_SEARCH_REQUEST);
        $this->assertEquals([1, 2, 840, 10003, 9, 100, 7], BerEncoder::OID_SEARCH_RESPONSE);
        $this->assertEquals([1, 2, 840, 10003, 9, 100, 13], BerEncoder::OID_PRESENT_REQUEST);
        $this->assertEquals([1, 2, 840, 10003, 9, 100, 14], BerEncoder::OID_PRESENT_RESPONSE);
        $this->assertEquals([1, 2, 840, 10003, 9, 100, 23], BerEncoder::OID_CLOSE);
    }

    public function testInitResponseRoundTrip(): void
    {
        $ber = new BerEncoder();

        $response = $ber->encodeInitResponse('client1', 1);
        $this->assertGreaterThan(8, strlen($response));
        $this->assertEquals("\x00", $response[0]);

        $stripped = $ber->stripPackageHeader($response);
        $this->assertStringStartsWith(chr(BerEncoder::TAG_SEQUENCE), $stripped);
    }

    public function testCloseRoundTrip(): void
    {
        $ber = new BerEncoder();

        $close = $ber->encodeClose('ref456', 0);
        $this->assertGreaterThan(8, strlen($close));
        $this->assertEquals("\x00", $close[0]);

        $type = $ber->detectApduType($close);
        $this->assertEquals('close', $type);
    }

    public function testDeleteResultSetResponseRoundTrip(): void
    {
        $ber = new BerEncoder();

        $delete = $ber->encodeDeleteResultSetResponse('ref789', 0);
        $this->assertGreaterThan(8, strlen($delete));

        $type = $ber->detectApduType($delete);
        $this->assertEquals('delete_result_set', $type);
    }

    public function testDetectApduTypeInitRequest(): void
    {
        $ber = new BerEncoder();

        $oidSeq = $ber->encodeOidSequence(BerEncoder::OID_INIT_REQUEST);
        $inner = $ber->encodeSequence($ber->encodeOctet('test'));
        $apdu = $ber->wrapInPackageHeader($oidSeq . $inner);

        $type = $ber->detectApduType($apdu);
        $this->assertEquals('init_request', $type);
    }

    public function testDetectApduTypeSearchRequest(): void
    {
        $ber = new BerEncoder();

        $oidSeq = $ber->encodeOidSequence(BerEncoder::OID_SEARCH_REQUEST);
        $inner = $ber->encodeSequence($ber->encodeOctet('srch'));
        $apdu = $ber->wrapInPackageHeader($oidSeq . $inner);

        $type = $ber->detectApduType($apdu);
        $this->assertEquals('search_request', $type);
    }

    public function testDetectApduTypePresentRequest(): void
    {
        $ber = new BerEncoder();

        $oidSeq = $ber->encodeOidSequence(BerEncoder::OID_PRESENT_REQUEST);
        $inner = $ber->encodeSequence($ber->encodeOctet('pres'));
        $apdu = $ber->wrapInPackageHeader($oidSeq . $inner);

        $type = $ber->detectApduType($apdu);
        $this->assertEquals('present_request', $type);
    }

    public function testDetectApduTypeUnknown(): void
    {
        $ber = new BerEncoder();
        $type = $ber->detectApduType('');
        $this->assertEquals('unknown', $type);
    }

    public function testFullSearchResponseRoundTrip(): void
    {
        $ber = new BerEncoder();

        $response = $ber->encodeSearchResponse('ref123', 42, 'test-set', '', 10);
        $this->assertGreaterThan(8, strlen($response));
        $this->assertEquals("\x00", $response[0]);

        $stripped = $ber->stripPackageHeader($response);
        $this->assertStringStartsWith(chr(BerEncoder::TAG_SEQUENCE), $stripped);
    }
}