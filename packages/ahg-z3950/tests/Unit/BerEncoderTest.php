<?php

/**
 * BerEncoderTest — unit tests for BER (ISO 8824 / X.690) encoding/decoding.
 *
 * Tests:
 *   - encodeIntegerValue (positive, zero, negative, leading-zero rule)
 *   - encodeOidValue (base-128, round-trip)
 *   - encodeTagLength (short/long form)
 *   - encodeSequence / encodeOidSequence / encodeIntegerSequence
 *   - encodeInitResponseOptions
 *   - encodeInitResponse / encodeSearchResponse / encodePresentResponse / encodeClose
 *   - encodeDeleteResultSetResponse
 *   - wrapInPackageHeader / unwrapPackageHeader
 *   - decodeLength (short form, long form)
 *   - decodeIntegerValue (positive, negative, two's complement)
 *   - decodeOidValue (round-trip)
 *   - round-trip encode/decode for integers and OIDs
 *
 * Copyright (C) 2026 Johan Pieterse — AGPL-3.0
 */

namespace AhgZ3950\Tests\Unit;

use AhgZ3950\Services\BerEncoder;
use AhgZ3950\Tests\AhgZ3950TestCase;

class BerEncoderTest extends AhgZ3950TestCase
{
    private BerEncoder $ber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ber = new BerEncoder();
    }

    // ──── encodeIntegerValue ─────────────────────────────────────────────

    /**
     * encodeIntegerValue body for zero.
     * BER: zero = single 0x00 byte (no leading zero needed).
     */
    public function testEncodeIntegerValueZero(): void
    {
        $body = $this->ber->encodeIntegerValue(0);
        $this->assertEquals("\x00", $body);
    }

    /**
     * encodeIntegerValue body for small positive (MSB has bit 7 clear).
     * 1 = 0x01, bit7=0, no leading zero needed.
     */
    public function testEncodeIntegerValueSmall(): void
    {
        $body = $this->ber->encodeIntegerValue(1);
        // 1 = 0x01, bit7=0 → single byte
        $this->assertEquals("\x01", $body);
    }

    /**
     * encodeIntegerValue body for 127 (largest single-byte positive).
     * 127 = 0x7f, bit7=0 → single byte.
     */
    public function testEncodeIntegerValue127(): void
    {
        $body = $this->ber->encodeIntegerValue(127);
        $this->assertEquals("\x7f", $body);
    }

    /**
     * encodeIntegerValue body for 255.
     * BER: 0xff has bit 7 set → must prepend leading 0x00 to keep positive.
     * Body = 0x00 0xff (2 bytes).
     */
    public function testEncodeIntegerValue255(): void
    {
        $body = $this->ber->encodeIntegerValue(255);
        $this->assertEquals("\x00\xff", $body);
    }

    /**
     * encodeIntegerValue body for 128.
     * 128 = 0x80, bit 7 set → leading zero required.
     * Body = 0x00 0x80 (2 bytes).
     */
    public function testEncodeIntegerValue128(): void
    {
        $body = $this->ber->encodeIntegerValue(128);
        $this->assertEquals("\x00\x80", $body);
    }

    /**
     * encodeIntegerValue body for 1000.
     * 1000 = 0x03e8, MSB = 0x03, bit7=0 → no leading zero.
     * Body = 0x03 0xe8 (2 bytes).
     */
    public function testEncodeIntegerValueLarge(): void
    {
        $body = $this->ber->encodeIntegerValue(1000);
        $this->assertEquals("\x03\xe8", $body);
    }

    /**
     * encodeIntegerValue body for negative -1.
     * BER two's complement: -1 = 0xff (single byte, bit7=1).
     */
    public function testEncodeIntegerValueNegative(): void
    {
        $body = $this->ber->encodeIntegerValue(-1);
        $this->assertEquals("\xff", $body);
    }

    /**
     * encodeIntegerValue body for -128.
     * BER two's complement: minimal encoding, single 0x80 (bit7=1).
     */
    public function testEncodeIntegerValueNegative128(): void
    {
        $body = $this->ber->encodeIntegerValue(-128);
        $this->assertEquals("\x80", $body);
    }

    // ──── encodeInteger (full TLV) ───────────────────────────────────────

    public function testEncodeIntegerTagLength(): void
    {
        // encodeInteger wraps body in tag+length
        $result = $this->ber->encodeInteger(42);
        // Tag 0x02, length 1, body 0x2a
        $this->assertEquals("\x02\x01\x2a", $result);
    }

    public function testEncodeInteger255(): void
    {
        $result = $this->ber->encodeInteger(255);
        // Tag 0x02, len 2, body 0x00 0xff
        $this->assertEquals("\x02\x02\x00\xff", $result);
    }

    public function testEncodeIntegerLarge(): void
    {
        $result = $this->ber->encodeInteger(1000);
        // Tag 0x02, len 2, body 0x03 0xe8
        $this->assertEquals("\x02\x02\x03\xe8", $result);
    }

    // ──── encodeOidValue ─────────────────────────────────────────────────

    /**
     * OID 1.2.840.10003.9.100:
     *   0x2a (42 = 1*40+2)
     *   0x86 0x48 (840 in base-128: 72|0x80, 6)
     *   0x9a 0x16 0x73 (10003 in base-128)
     *   0x09 (9)
     *   0x64 (100)
     * Total: 9 bytes.
     */
    public function testEncodeOidValueApdu(): void
    {
        $body = $this->ber->encodeOidValue([1, 2, 840, 10003, 9, 100]);
        $this->assertEquals(
            "\x2a\x86\x48\xce\x13\x09\x64",
            $body
        );

        // Decode round-trip must recover the OID
        $decoded = $this->ber->decodeOidValue($body);
        $this->assertEquals([1, 2, 840, 10003, 9, 100], $decoded);
    }

    /**
     * OID 1.2.840.10003.9.100.1 (InitRequest):
     *   0x2a + 840(0x86,0x48) + 10003(0xce,0x13) + 9(0x09) + 100(0x64) + 1(0x01) = 8 bytes.
     */
    public function testEncodeOidValueInitRequest(): void
    {
        $body = $this->ber->encodeOidValue([1, 2, 840, 10003, 9, 100, 1]);
        $this->assertEquals(
            "\x2a\x86\x48\xce\x13\x09\x64\x01",
            $body
        );

        $decoded = $this->ber->decodeOidValue($body);
        $this->assertEquals([1, 2, 840, 10003, 9, 100, 1], $decoded);
    }

    /**
     * OID 1.2.840.10003.9.100.6 (SearchRequest): 8 bytes.
     */
    public function testEncodeOidValueSearchRequest(): void
    {
        $body = $this->ber->encodeOidValue([1, 2, 840, 10003, 9, 100, 6]);
        $this->assertEquals(
            "\x2a\x86\x48\xce\x13\x09\x64\x06",
            $body
        );
    }

    /**
     * OID 1.2.840.10003.5.1 (USmarc): 7 bytes.
     *   0x2a + 840(0x86,0x48) + 10003(0xce,0x13) + 5(0x05) + 1(0x01)
     */
    public function testEncodeOidValueUSmarc(): void
    {
        $body = $this->ber->encodeOidValue([1, 2, 840, 10003, 5, 1]);
        $this->assertEquals(
            "\x2a\x86\x48\xce\x13\x05\x01",
            $body
        );
    }

    /**
     * OID 1.2.840.10003.9.100.7 (SearchResponse).
     */
    public function testEncodeOidValueSearchResponse(): void
    {
        $body = $this->ber->encodeOidValue([1, 2, 840, 10003, 9, 100, 7]);
        $this->assertEquals(
            "\x2a\x86\x48\xce\x13\x09\x64\x07",
            $body
        );
    }

    // ──── encodeOid (full TLV) ────────────────────────────────────────────

    public function testEncodeOidApdu(): void
    {
        $result = $this->ber->encodeOid([1, 2, 840, 10003, 9, 100]);
        // Tag 0x06, length 7, body 7 bytes
        $this->assertEquals(
            "\x06\x07\x2a\x86\x48\xce\x13\x09\x64",
            $result
        );
    }

    // ──── encodeTagLength ────────────────────────────────────────────────

    public function testEncodeTagLengthShortForm(): void
    {
        $result = $this->ber->encodeTagLength(0x30, 'abc');
        $this->assertEquals("\x30\x03", $result);
    }

    public function testEncodeTagLengthLongForm(): void
    {
        $content = str_repeat('x', 200);
        $result = $this->ber->encodeTagLength(0x30, $content);
        // Header only (callers append the body): tag + 0x81 (1 length byte) + 200.
        $this->assertEquals("\x30\x81\xc8", $result);
    }

    public function testEncodeTagLengthLongForm3Bytes(): void
    {
        $content = str_repeat('x', 65535);
        $result = $this->ber->encodeTagLength(0x30, $content);
        // Header only, minimal DER: 0x82 (2 length bytes) + 0xff 0xff (65535).
        $this->assertEquals("\x30\x82\xff\xff", $result);
    }

    // ──── decodeLength ───────────────────────────────────────────────────

    /**
     * Short form: single byte, bit7=0 → length = byte value.
     */
    public function testDecodeLengthShortForm(): void
    {
        $consumed = 99;
        $result = $this->ber->decodeLength("\x7f", 0, $consumed);
        $this->assertEquals(127, $result);
        $this->assertEquals(1, $consumed);
    }

    /**
     * Short form zero.
     */
    public function testDecodeLengthShortFormZero(): void
    {
        $consumed = 99;
        $result = $this->ber->decodeLength("\x00", 0, $consumed);
        $this->assertEquals(0, $result);
        $this->assertEquals(1, $consumed);
    }

    /**
     * Long form: 0x81 = 1 length byte following.
     */
    public function testDecodeLengthLongForm1Byte(): void
    {
        $consumed = 99;
        $result = $this->ber->decodeLength("\x81\xff", 0, $consumed);
        $this->assertEquals(255, $result);
        $this->assertEquals(2, $consumed);
    }

    /**
     * Long form: 0x82 = 2 length bytes following.
     */
    public function testDecodeLengthLongForm2Bytes(): void
    {
        $consumed = 99;
        $result = $this->ber->decodeLength("\x82\x04\x00", 0, $consumed);
        // 0x0400 = 1024
        $this->assertEquals(1024, $result);
        $this->assertEquals(3, $consumed);
    }

    /**
     * Long form: 0x83 = 3 length bytes following.
     */
    public function testDecodeLengthLongForm3Bytes(): void
    {
        $consumed = 99;
        $result = $this->ber->decodeLength("\x83\x00\xff\xff", 0, $consumed);
        // 0x00ffff = 65535
        $this->assertEquals(65535, $result);
        $this->assertEquals(4, $consumed);
    }

    // ──── decodeIntegerValue ─────────────────────────────────────────────

    /**
     * Positive integer: body has no leading zero, MSB bit7 clear.
     */
    public function testDecodeIntegerValuePositive(): void
    {
        $this->assertEquals(0,    $this->ber->decodeIntegerValue("\x00"));
        $this->assertEquals(1,    $this->ber->decodeIntegerValue("\x01"));
        $this->assertEquals(127,  $this->ber->decodeIntegerValue("\x7f"));
        $this->assertEquals(255,  $this->ber->decodeIntegerValue("\x00\xff"));
        $this->assertEquals(128,  $this->ber->decodeIntegerValue("\x00\x80"));
        $this->assertEquals(1000, $this->ber->decodeIntegerValue("\x03\xe8"));
        $this->assertEquals(65535, $this->ber->decodeIntegerValue("\x00\x00\xff\xff"));
    }

    /**
     * Negative integer: BER two's complement. MSB bit7 set → sign extend.
     *   -1: 0xff (single byte, bit7=1)
     *   -128: 0x80 (single byte, bit7=1)
     *   -256: 0x00 0xff (bit7 of first byte is 0, so treated as positive 255 by BER rules — minimal encoding uses just 0xff)
     */
    public function testDecodeIntegerValueNegative(): void
    {
        $this->assertEquals(-1,   $this->ber->decodeIntegerValue("\xff"));
        $this->assertEquals(-128, $this->ber->decodeIntegerValue("\x80"));
    }

    // ──── decodeOidValue ─────────────────────────────────────────────────

    public function testDecodeOidValueSimple(): void
    {
        $this->assertEquals(
            [1, 2, 840],
            $this->ber->decodeOidValue("\x2a\x86\x48")
        );
    }

    public function testDecodeOidValueApdu(): void
    {
        $this->assertEquals(
            [1, 2, 840, 10003, 9],
            $this->ber->decodeOidValue("\x2a\x86\x48\xce\x13\x09")
        );
    }

    public function testDecodeOidValueInitRequest(): void
    {
        $this->assertEquals(
            [1, 2, 840, 10003, 9, 100],
            $this->ber->decodeOidValue("\x2a\x86\x48\xce\x13\x09\x64")
        );
    }

    public function testDecodeOidValueUSmarc(): void
    {
        $this->assertEquals(
            [1, 2, 840, 10003, 5, 1],
            $this->ber->decodeOidValue("\x2a\x86\x48\xce\x13\x05\x01")
        );
    }

    // ──── High-level encoding ────────────────────────────────────────────

    public function testEncodeSequence(): void
    {
        $result = $this->ber->encodeSequence("\x02\x01\x2a");
        $this->assertEquals("\x30\x03\x02\x01\x2a", $result);
    }

    public function testEncodeSequenceLong(): void
    {
        $content = str_repeat('x', 200);
        $result = $this->ber->encodeSequence($content);
        $this->assertEquals("\x30\x81\xc8" . $content, $result);
    }

    public function testEncodeOidSequence(): void
    {
        $result = $this->ber->encodeOidSequence([1, 2, 840]);
        // SEQUENCE { OID }: 0x30 + len + OID(0x06 + len + bytes)
        $this->assertStringStartsWith("\x30", $result);
        $this->assertStringContainsString("\x06\x03\x2a\x86\x48", $result);
    }

    public function testEncodeIntegerSequence(): void
    {
        $result = $this->ber->encodeIntegerSequence(42);
        // SEQUENCE + len 3 + INTEGER 42
        $this->assertEquals("\x30\x03\x02\x01\x2a", $result);
    }

    public function testEncodeUtf8(): void
    {
        $result = $this->ber->encodeUtf8('café');
        $this->assertEquals("\x0c\x05café", $result);
    }

    public function testEncodeVisible(): void
    {
        $result = $this->ber->encodeVisible('Heratio');
        $this->assertEquals("\x1a\x07Heratio", $result);
    }

    public function testEncodeOctet(): void
    {
        $result = $this->ber->encodeOctet("\x00\xff\x01");
        $this->assertEquals("\x04\x03\x00\xff\x01", $result);
    }

    public function testEncodeNull(): void
    {
        $this->assertEquals("\x05\x00", $this->ber->encodeNull());
    }

    // ──── InitResponse ───────────────────────────────────────────────────

    public function testEncodeInitResponseOptions(): void
    {
        $result = $this->ber->encodeInitResponseOptions();
        // BIT STRING: tag 0x03, len 5, body (4 unused + flags)
        $this->assertEquals("\x03\x05", substr($result, 0, 2));
        // flags byte: bits 0,1,2 set (search+present+delSet) = 0x07
        $this->assertEquals("\x07", substr($result, 6, 1));
    }

    public function testEncodeInitResponse(): void
    {
        $result = $this->ber->encodeInitResponse('HERATIO01');
        $this->assertGreaterThan(5, strlen($result));

        $apdu = $this->ber->unwrapPackageHeader($result);
        $this->assertEquals("\x30", $apdu[0]); // outer SEQUENCE tag

        // OID SEQUENCE starts at bytes 2+
        $decoded = $this->ber->decodeLengthRet($apdu, 1);
        $lenBytes = $decoded[0];
        $oidSeqStart = 1 + $lenBytes;
        $this->assertEquals("\x30", $apdu[$oidSeqStart]);
    }

    public function testEncodeInitResponseFailure(): void
    {
        $result = $this->ber->encodeInitResponse('CLIENT1', 0);
        $this->assertGreaterThan(5, strlen($result));
    }

    // ──── SearchResponse ─────────────────────────────────────────────────

    public function testEncodeSearchResponse(): void
    {
        $result = $this->ber->encodeSearchResponse('REF001', 25, 'mySet', '', 26);
        $this->assertGreaterThan(5, strlen($result));

        $apdu = $this->ber->unwrapPackageHeader($result);
        $this->assertEquals("\x30", $apdu[0]);

        // Search response OID at bytes 2+
        $decoded = $this->ber->decodeLengthRet($apdu, 1);
        $lenBytes = $decoded[0];
        $oidSeqStart = 1 + $lenBytes;
        $this->assertEquals("\x30", $apdu[$oidSeqStart]);
    }

    // ──── PresentResponse ────────────────────────────────────────────────

    public function testEncodePresentResponse(): void
    {
        $result = $this->ber->encodePresentResponse('REF002', 11, 5, '', 0);
        $this->assertGreaterThan(5, strlen($result));
        $this->assertEquals("\x00", $result[5] ?? '');
    }

    // ──── Close ──────────────────────────────────────────────────────────

    public function testEncodeClose(): void
    {
        $result = $this->ber->encodeClose('REF003', 1);
        $this->assertGreaterThan(5, strlen($result));
    }

    public function testEncodeDeleteResultSetResponse(): void
    {
        $result = $this->ber->encodeDeleteResultSetResponse('REF004', 0);
        $this->assertGreaterThan(5, strlen($result));
    }

    // ──── Package header ─────────────────────────────────────────────────

    public function testWrapInPackageHeader(): void
    {
        $apdu = "\x30\x04\x02\x01\x0a"; // SEQUENCE + INTEGER 10
        $wrapped = $this->ber->wrapInPackageHeader($apdu);

        // 5-byte header + apdu
        $this->assertEquals(5 + strlen($apdu), strlen($wrapped));

        // Protocol family = 0x00
        $this->assertEquals("\x00", $wrapped[0]);
        // Reserved = 0x00
        $this->assertEquals("\x00", $wrapped[1]);
        // 4-byte big-endian size
        $size = unpack('Nsize', $wrapped);
        $this->assertEquals(strlen($apdu) + 5, $size['size']);
    }

    public function testUnwrapPackageHeader(): void
    {
        $apdu = "\x30\x04\x02\x01\x0a";
        $wrapped = $this->ber->wrapInPackageHeader($apdu);

        $unwrapped = $this->ber->unwrapPackageHeader($wrapped);
        $this->assertEquals($apdu, $unwrapped);
    }

    public function testUnwrapPackageHeaderEmpty(): void
    {
        $result = $this->ber->unwrapPackageHeader('');
        $this->assertEquals('', $result);
    }

    public function testUnwrapPackageHeaderShort(): void
    {
        $result = $this->ber->unwrapPackageHeader("\x00");
        $this->assertEquals('', $result);
    }

    // ──── Round-trip encode/decode ───────────────────────────────────────

    /**
     * encodeInteger → decodeIntegerValue round-trip for a range of values.
     * BER positive: leading zero added only when MSB has bit 7 set.
     */
    public function testRoundTripIntegers(): void
    {
        $values = [0, 1, 127, 128, 255, 256, 1000, 65535];
        foreach ($values as $v) {
            $body = $this->ber->encodeIntegerValue($v);
            $decoded = $this->ber->decodeIntegerValue($body);
            $this->assertEquals(
                $v, $decoded,
                "Integer round-trip failed for value: {$v}"
            );
        }
    }

    /**
     * encodeOidValue → decodeOidValue round-trip for all Z39.50 OIDs.
     * Each arc must survive the base-128 encode/decode cycle.
     */
    public function testRoundTripOids(): void
    {
        $oids = [
            [1, 2, 840],
            [1, 2, 840, 10003, 9],
            [1, 2, 840, 10003, 9, 100],
            [1, 2, 840, 10003, 9, 100, 1],
            [1, 2, 840, 10003, 9, 100, 2],
            [1, 2, 840, 10003, 9, 100, 6],
            [1, 2, 840, 10003, 9, 100, 7],
            [1, 2, 840, 10003, 9, 100, 13],
            [1, 2, 840, 10003, 9, 100, 14],
            [1, 2, 840, 10003, 9, 100, 19],
            [1, 2, 840, 10003, 9, 100, 23],
            [1, 2, 840, 10003, 5, 1],
            [1, 2, 840, 10003, 5, 2],
        ];

        foreach ($oids as $oid) {
            $body = $this->ber->encodeOidValue($oid);
            $decoded = $this->ber->decodeOidValue($body);
            $this->assertEquals(
                $oid, $decoded,
                'OID round-trip failed: ' . implode('.', $oid)
            );
        }
    }

    /**
     * encodeSequence → decodeLength round-trip.
     */
    public function testRoundTripSequence(): void
    {
        $content = $this->ber->encodeInteger(42) . $this->ber->encodeVisible('test');
        $seq = $this->ber->encodeSequence($content);

        $this->assertEquals(chr(BerEncoder::TAG_SEQUENCE), $seq[0]);
        $decoded = $this->ber->decodeLengthRet($seq, 1);
        $this->assertEquals(strlen($content), $decoded[1]);
    }
}