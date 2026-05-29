<?php

namespace AhgZ3950\Tests;

use AhgZ3950\Services\BerEncoder;
use PHPUnit\Framework\TestCase;

class BerEncoderTest extends TestCase
{
    private BerEncoder $enc;

    protected function setUp(): void
    {
        $this->enc = new BerEncoder();
    }

    // ──── encodeInteger ───────────────────────────────────────────────────

    public function testEncodeIntegerZero(): void
    {
        // Zero encodes as single 0x00 byte (no leading zero needed)
        $result = $this->enc->encodeInteger(0);
        // tag 0x02, len 1, body 0x00
        $this->assertEquals("\x02\x01\x00", $result);
    }

    public function testEncodeIntegerPositiveSmall(): void
    {
        // 127 = 0x7f (MSB=0, no leading zero needed)
        $result = $this->enc->encodeInteger(127);
        // tag 0x02, len 1, body 0x7f
        $this->assertEquals("\x02\x01\x7f", $result);
    }

    public function testEncodeIntegerPositiveLeadingZero(): void
    {
        // 255 = 0xff (MSB=1 → BER requires leading 0x00 to keep positive)
        $result = $this->enc->encodeInteger(255);
        // tag 0x02, len 2, body 0x00 0xff
        $this->assertEquals("\x02\x02\x00\xff", $result);
    }

    public function testEncodeIntegerLarge(): void
    {
        // 1000 = 0x03e8 (MSB=0x03, bit7=0 → no leading zero)
        $result = $this->enc->encodeInteger(1000);
        // tag 0x02, len 2, body 0x03 0xe8
        $this->assertEquals("\x02\x02\x03\xe8", $result);
    }

    public function testEncodeIntegerNegative(): void
    {
        // -1 = two's complement 0xff (single byte, bit7=1)
        $result = $this->enc->encodeInteger(-1);
        // tag 0x02, len 1, body 0xff
        $this->assertEquals("\x02\x01\xff", $result);
    }

    // ──── encodeOid ──────────────────────────────────────────────────────

    public function testEncodeOidRoot(): void
    {
        // Root OID 1.2.840 = 0x2a + [0x86, 0x48] = 4 bytes
        $result = $this->enc->encodeOid([1, 2, 840]);
        // tag 0x06, len 4, body
        $this->assertEquals("\x06\x04\x2a\x86\x48", $result);
    }

    public function testEncodeOidApdu(): void
    {
        // OID 1.2.840.10003.9.100 (6 arcs)
        // 0x2a + 840(0x86,0x48) + 10003(0x9a,0x16,0x73) + 9(0x09) = 9 bytes
        $result = $this->enc->encodeOid([1, 2, 840, 10003, 9, 100]);
        $this->assertEquals(
            "\x06\x09\x2a\x86\x48\x9a\x16\x73\x09",
            $result
        );
    }

    public function testEncodeOidInitRequest(): void
    {
        // OID 1.2.840.10003.9.100.1 (7 arcs)
        // 0x2a + 840(0x86,0x48) + 10003(0x9a,0x16,0x73) + 9(0x09) + 1(0x01) = 10 bytes
        $result = $this->enc->encodeOid([1, 2, 840, 10003, 9, 100, 1]);
        $this->assertEquals(
            "\x06\x0a\x2a\x86\x48\x9a\x16\x73\x09\x01",
            $result
        );
    }

    // ──── encodeSequence ─────────────────────────────────────────────────

    public function testEncodeSequence(): void
    {
        $result = $this->enc->encodeSequence('test');
        // tag 0x30, len 4, 'test'
        $this->assertEquals("\x30\x04test", $result);
    }

    public function testEncodeSequenceLong(): void
    {
        $content = str_repeat('x', 200);
        $result = $this->enc->encodeSequence($content);
        // tag 0x30, long form: 0x81 (1-byte length), len 200, content
        $this->assertEquals("\x30\x81\xc8" . $content, $result);
    }

    // ──── decodeInteger ──────────────────────────────────────────────────

    public function testDecodeIntegerPositive(): void
    {
        // 255 = 0x00 0xff (leading zero clears bit7)
        $result = $this->enc->decodeIntegerValue("\x00\xff");
        $this->assertEquals(255, $result);
    }

    public function testDecodeIntegerNegative(): void
    {
        // -1 in two's complement = 0xff (single byte, bit7=1)
        $result = $this->enc->decodeIntegerValue("\xff");
        $this->assertEquals(-1, $result);
    }

    public function testDecodeIntegerZero(): void
    {
        $result = $this->enc->decodeIntegerValue("\x00");
        $this->assertEquals(0, $result);
    }

    public function testDecodeIntegerLarge(): void
    {
        // 65535 = 0x00 0x00 0xff 0xff
        $result = $this->enc->decodeIntegerValue("\x00\x00\xff\xff");
        $this->assertEquals(65535, $result);
    }

    // ──── decodeOid ──────────────────────────────────────────────────────

    public function testDecodeOidRoot(): void
    {
        $result = $this->enc->decodeOidValue("\x2a\x86\x48");
        $this->assertEquals([1, 2, 840], $result);
    }

    public function testDecodeOidApdu(): void
    {
        $oidBytes = "\x2a\x86\x48\x9a\x16\x73\x09";
        $result = $this->enc->decodeOidValue($oidBytes);
        $this->assertEquals([1, 2, 840, 10003, 9], $result);
    }

    public function testDecodeOidInitRequest(): void
    {
        $oidBytes = "\x2a\x86\x48\x9a\x16\x73\x09\x01";
        $result = $this->enc->decodeOidValue($oidBytes);
        $this->assertEquals([1, 2, 840, 10003, 9, 100], $result);
    }

    // ──── Round-trip encode/decode ───────────────────────────────────────

    public function testEncodeOidRoundTrip(): void
    {
        $oid = [1, 2, 840, 10003, 9, 100];
        $encoded = $this->enc->encodeOid($oid);
        $decoded = $this->enc->decodeOidValue(substr($encoded, 2));
        $this->assertEquals($oid, $decoded);
    }

    public function testEncodeIntegerRoundTrip(): void
    {
        $values = [0, 1, 127, 255, 1000, 65535, -1, -128, -256];
        foreach ($values as $v) {
            $encoded = $this->enc->encodeInteger($v);
            $tagLen = 2;
            $bodyLen = ord($encoded[1]);
            $body = substr($encoded, $tagLen, $bodyLen);
            $decoded = $this->enc->decodeIntegerValue($body);
            $this->assertEquals($v, $decoded, "Round-trip failed for value: {$v}");
        }
    }

    // ──── Length encoding ─────────────────────────────────────────────────

    public function testEncodeTagLengthShort(): void
    {
        $result = $this->enc->encodeTagLength(0x30, 'abc');
        $this->assertEquals("\x30\x03", $result);
    }

    public function testEncodeTagLengthLong(): void
    {
        $content = str_repeat('x', 200);
        $result = $this->enc->encodeTagLength(0x30, $content);
        $this->assertEquals("\x30\x81\xc8" . $content, $result);
    }

    public function testDecodeLengthShortForm(): void
    {
        $result = $this->enc->decodeLength("\x02", 0, $consumed);
        $this->assertEquals(2, $result);
        $this->assertEquals(1, $consumed);
    }

    public function testDecodeLengthLongForm(): void
    {
        $result = $this->enc->decodeLength("\x81\xc8", 0, $consumed);
        $this->assertEquals(200, $result);
        $this->assertEquals(2, $consumed);
    }

    public function testDecodeLengthMultiByte(): void
    {
        $result = $this->enc->decodeLength("\x83\x01\x00\xc8", 0, $consumed);
        $this->assertEquals(200, $result);
        $this->assertEquals(4, $consumed);
    }

    // ──── Detect APDU type ─────────────────────────────────────────────────

    public function testDetectInitRequest(): void
    {
        // Build init request APDU using the fixed encoder
        $innerContent = $this->enc->encodeOctet('TEST01')
                      . "\x30\x05" . "\x06\x03\x2a\x86\x48"  // OID SEQUENCE
                      . $this->enc->encodeVisible('Z39.50')
                      . $this->enc->encodeVisible('Heratio')
                      . $this->enc->encodeVisible('1.0');

        $initApdu = $this->enc->encodeOidSequence([1, 2, 840, 10003, 9, 100, 1])
                   . $this->enc->encodeSequence($innerContent);

        $package = $this->enc->wrapInPackageHeader($initApdu);

        $type = $this->enc->detectApduType($this->enc->unwrapPackageHeader($package));
        $this->assertEquals('initRequest', $type);
    }

    public function testDetectSearchRequest(): void
    {
        $innerContent = $this->enc->encodeOctet('SREQ01')
                      . $this->enc->encodeSequence('')
                      . $this->enc->encodeInteger(10);

        $searchApdu = $this->enc->encodeOidSequence([1, 2, 840, 10003, 9, 100, 6])
                     . $this->enc->encodeSequence($innerContent);

        $package = $this->enc->wrapInPackageHeader($searchApdu);

        $type = $this->enc->detectApduType($this->enc->unwrapPackageHeader($package));
        $this->assertEquals('searchRequest', $type);
    }

    // ──── Package header ──────────────────────────────────────────────────

    public function testPackageHeader(): void
    {
        $apdu = 'test APDU content';
        $wrapped = $this->enc->wrapInPackageHeader($apdu);
        $this->assertEquals(5 + strlen($apdu), strlen($wrapped));
        $this->assertEquals(0x00, ord($wrapped[6])); // package id
        $this->assertEquals(0x00, ord($wrapped[7])); // flags

        $unwrapped = $this->enc->unwrapPackageHeader($wrapped);
        $this->assertEquals($apdu, $unwrapped);
    }

    // ──── InitResponse encoding ───────────────────────────────────────────

    public function testEncodeInitResponse(): void
    {
        $result = $this->enc->encodeInitResponse('HERATIO01');

        // Must have package header (5 bytes)
        $this->assertGreaterThan(5, strlen($result));

        $apdu = $this->enc->unwrapPackageHeader($result);

        // Outer SEQUENCE tag
        $this->assertEquals("\x30", $apdu[0]);

        // OID SEQUENCE at start of content
        $this->assertEquals("\x30", $apdu[2]);
    }

    public function testEncodeInitResponseOptions(): void
    {
        $result = $this->enc->encodeInitResponseOptions([
            'search' => true,
            'present' => true,
            'delSet' => true,
        ]);

        // BIT STRING: tag 0x03, len 5, body
        $this->assertEquals("\x03\x05", substr($result, 0, 2));

        // Flags byte: bits 0,1,2 set = 0x07
        $this->assertEquals("\x07", substr($result, 6, 1));
    }

    // ──── High-level encoding ─────────────────────────────────────────────

    public function testEncodeUtf8(): void
    {
        $result = $this->enc->encodeUtf8('Hello');
        $this->assertEquals("\x0c\x05Hello", $result);
    }

    public function testEncodeVisible(): void
    {
        $result = $this->enc->encodeVisible('TEST');
        $this->assertEquals("\x1a\x04TEST", $result);
    }

    public function testEncodeOctet(): void
    {
        $result = $this->enc->encodeOctet("\x00\xff\x01");
        $this->assertEquals("\x04\x03\x00\xff\x01", $result);
    }

    public function testEncodeNull(): void
    {
        $result = $this->enc->encodeNull();
        $this->assertEquals("\x05\x00", $result);
    }

    public function testEncodeOidSequence(): void
    {
        $result = $this->enc->encodeOidSequence([1, 2, 840]);
        // SEQUENCE { OID }: 0x30 + len + OID(0x06 + len + bytes)
        $this->assertStringStartsWith("\x30", $result);
        $this->assertStringContainsString("\x06\x04\x2a\x86\x48", $result);
    }
}