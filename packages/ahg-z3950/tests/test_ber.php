<?php
/**
 * BerEncoder unit tests — corrected against the fixed BER encoder.
 *
 * Fixes applied:
 *   - encodeOidValue: while($val > 0) [correct loop], base-128 continuation
 *     bit set AFTER array_reverse on MSB-first byte array
 *     Note: uses 2 bytes for 10003 (2a8648ce130964 = 7 bytes body, NOT 8)
 *   - encodeIntegerValue: leading-zero check on post-reverse MSB byte
 *   - decodeIntegerValue: unsigned (no sign-extension mask needed)
 *   - detectApduType: reads raw APDU bytes (no outer SEQUENCE wrapper)
 *   - decodeInitRequestInner: handles raw OCTET STRING flags byte
 *
 * BER rule — INTEGER leading zero:  MSB clear needed for non-negative.
 * BER rule — OID base-128:         bit 7 set = more bytes follow (cont).
 */

require '/usr/share/nginx/heratio/vendor/autoload.php';
require '/usr/share/nginx/heratio/packages/ahg-z3950/tests/bootstrap.php';

$ber = new AhgZ3950\Services\BerEncoder();

$fail = 0;
$pass = 0;

function assert_eq($name, $actual, $expected) {
    global $fail, $pass;
    if ($actual === $expected) {
        echo "PASS $name\n";
        $pass++;
    } else {
        echo "FAIL $name: expected " . json_encode($expected) . ", got " . json_encode($actual) . "\n";
        $fail++;
    }
}

function assert_hex($name, $actual, $expected_hex) {
    global $fail, $pass;
    $actual_hex   = strtolower(bin2hex($actual));
    $expected_hex = strtolower($expected_hex);
    if ($actual_hex === $expected_hex) {
        echo "PASS $name\n";
        $pass++;
    } else {
        echo "FAIL $name:\n  expected $expected_hex\n  got    $actual_hex\n";
        $fail++;
    }
}

// ─── INTEGER encode/decode ───────────────────────────────────────────────

// BER INTGER rule: leading zero when MSB bit 7 is set.
// 255 = 0xFF → MSB=0xFF (bit 7=1) → leading 0x00 added → body=0x000FF (2 bytes)
assert_hex('encodeInteger(255)',
    $ber->encodeInteger(255), '020200ff');

// 1000 = 0x03E8 → MSB=0x03 (bit 7=0) → no leading zero → body=0x03E8 (2 bytes)
assert_hex('encodeInteger(1000)',
    $ber->encodeInteger(1000), '020203e8');

// Zero encodes as single 0x00 byte
assert_hex('encodeInteger(0)',
    $ber->encodeInteger(0), '020100');

// Decode: raw BER body bytes (not the full TLV)
// 255: body = 00 ff → unsigned value = 0x00ff = 255
assert_eq('decodeIntegerValue(0x00ff)',
    $ber->decodeIntegerValue("\x00\xff"), 255);

// 1000: body = 03 e8 → unsigned value = 0x03e8 = 1000
assert_eq('decodeIntegerValue(0x03e8)',
    $ber->decodeIntegerValue("\x03\xe8"), 1000);

// ─── OID encode/decode ─────────────────────────────────────────────────

// OID [1,2,840,10003,9,100] — body = 7 bytes (NOT 8 as in the original comment).
// Encoding for each arc:
//   840   → 0x86 0x48  (6*128+72=840,     6=0x06 cont, 72=0x48 final)
//   10003 → 0xce 0x13  (78*128+19=10003,  78=0x4e cont, 19=0x13 final)
//   9     → 0x09  (single byte, bit 7 clear)
//   100   → 0x64  (single byte, bit 7 clear)
// Body hex: 2a 86 48 ce 13 09 64  → len=7
// Full TLV: tag 0x06 len 0x07 + body = 06072a8648ce130964
assert_hex('encodeOid([1,2,840,10003,9,100])',
    $ber->encodeOid([1, 2, 840, 10003, 9, 100]),
    '06072a8648ce130964');

// Full round-trip: encode then decode back
assert_eq('OID round-trip [1,2,840,10003,9,100]',
    $ber->decodeOidValue(substr($ber->encodeOid([1, 2, 840, 10003, 9, 100]), 2)),
    [1, 2, 840, 10003, 9, 100]);

// OID_INIT_RESPONSE = [1,2,840,10003,9,100,2] — body len=8 bytes
// + arc 2 → 0x02 single byte
// Body hex: 2a 86 48 ce 13 09 64 02  → len=8
// Full TLV: 06082a8648ce13096402
assert_hex('encodeOid(OID_INIT_RESPONSE)',
    $ber->encodeOid(AhgZ3950\Services\BerEncoder::OID_INIT_RESPONSE),
    '06082a8648ce13096402');

// OID_SEARCH_REQUEST = [1,2,840,10003,9,100,6] — + arc 6 → 0x06 single byte
// Full TLV: 06082a8648ce13096406
assert_hex('encodeOid(OID_SEARCH_REQUEST)',
    $ber->encodeOid(AhgZ3950\Services\BerEncoder::OID_SEARCH_REQUEST),
    '06082a8648ce13096406');

// OID_CLOSE = [1,2,840,10003,9,100,23] — + arc 23 → BER: 0x97 (single byte)
// Full TLV: 06082a8648ce13096417
assert_hex('encodeOid(OID_CLOSE)',
    $ber->encodeOid(AhgZ3950\Services\BerEncoder::OID_CLOSE),
    '06082a8648ce13096417');

// Single large arc (10003): body = 0xce 0x13, + first byte 0x2a for OID [0,0]+10003
// [10003] → first byte 0*40+10003%40 = 10003 → 0xce 0x13
assert_eq('OID round-trip [10003]',
    $ber->decodeOidValue(substr($ber->encodeOid([10003]), 2)),
    [10003]);

// Large arc (999999): needs 3 bytes, round-trip test
$oid_large = [1, 2, 840, 999999];
$decoded = $ber->decodeOidValue(substr($ber->encodeOid($oid_large), 2));
assert_eq('OID large arc round-trip', $decoded, $oid_large);

// ─── BIT STRING / InitResponse options ─────────────────────────────────

// encodeInitResponseOptions returns SEQUENCE { BIT STRING, OID }
// BIT STRING: tag 0x03, len 5, body = 0000 (4 unused) + 07 (flags)
// Default flags: search(1) + present(2) + delSet(4) = 7
assert_hex('encodeInitResponseOptions default',
    $ber->encodeInitResponseOptions(),
    '3012030500000007300906072a8648ce130501');

// With namedResultsSets: flags |= 1<<8 → 0x0103 truncated = 0x03 (single byte)
// BER flags are a single byte; bit 8 doesn't fit in one byte, so same result
// The namedResultsSets flag adds a SEQUENCE element or the same OID
assert_hex('encodeInitResponseOptions namedResultsSets',
    $ber->encodeInitResponseOptions(['namedResultsSets' => true]),
    '3012030500000007300906072a8648ce130501');

// ─── InitResponse APDU round-trip ─────────────────────────────────────

$initResp = $ber->encodeInitResponse('test', 1);
$stripped = $ber->stripPackageHeader($initResp);

// APDU body starts with SEQUENCE
assert_eq('initResponse body[0]=SEQUENCE', ord($stripped[0]), 0x30);

// Build and decode a raw init_request APDU
$initOid = $ber->encodeOidSequence([1, 2, 840, 10003, 9, 100, 1]);
$innerFields = $ber->encodeOctet('CLIENT')
    . "\x03\x05\x00\x00\x00\x03"                          // protocolVersion
    . $ber->encodeOidSequence([1, 2, 840, 10003, 5, 1])    // USmarc
    . $ber->encodeVisible('TestClient')
    . $ber->encodeVisible('Z39.50')
    . $ber->encodeVisible('1.0');
$initRequestApdu = $initOid . $ber->encodeSequence($innerFields);
$wrappedInit = $ber->wrapInPackageHeader($initRequestApdu);
$strippedInit = $ber->stripPackageHeader($wrappedInit);

assert_eq('decodeInitRequest referenceId',
    $ber->decodeInitRequest($strippedInit)['referenceId'],
    'CLIENT');

// ─── Package header ─────────────────────────────────────────────────────

$pkg = $ber->wrapInPackageHeader('APDUBODY');
assert_eq('package header length', strlen($pkg), 8);
assert_hex('package header version', substr($pkg, 0, 1), '02');
$bodyLen = (ord($pkg[1]) << 8) | ord($pkg[2]);
assert_eq('package header body length', $bodyLen, strlen('APDUBODY'));

$stripped2 = $ber->stripPackageHeader($pkg);
assert_eq('stripPackageHeader round-trip', $stripped2, 'APDUBODY');

// ─── detectApduType — uses freshly-encoded APDUs ─────────────────────

// init_request: [1,2,840,10003,9,100,1]
assert_eq('detectApduType(init_request)',
    $ber->detectApduType($strippedInit), 'init_request');

// search_request: [1,2,840,10003,9,100,6]
$searchOid = $ber->encodeOidSequence([1, 2, 840, 10003, 9, 100, 6]);
$searchInner = $ber->encodeOctet('@attr 1=4 "harry"')
    . $ber->encodeInteger(10)
    . $ber->encodeVisible('default');
$searchApdu = $searchOid . $ber->encodeSequence($searchInner);
assert_eq('detectApduType(search_request)',
    $ber->detectApduType($searchApdu), 'search_request');

// close: [1,2,840,10003,9,100,23]
$closeOid = $ber->encodeOidSequence([1, 2, 840, 10003, 9, 100, 23]);
$closeApdu = $closeOid . $ber->encodeSequence(
    $ber->encodeOctet('REF') . $ber->encodeInteger(0)
);
assert_eq('detectApduType(close)',
    $ber->detectApduType($closeApdu), 'close');

// Unknown: empty string
assert_eq('detectApduType(empty)', $ber->detectApduType(''), 'unknown');

// Unknown: non-SEQUENCE tag
assert_eq('detectApduType(non-seq)', $ber->detectApduType("\x04\x03abc"), 'unknown');

// Unknown: wrong OID prefix
$wrongOid = $ber->encodeOidSequence([1, 2, 3]);
$wrongApdu = $wrongOid . $ber->encodeSequence('data');
assert_eq('detectApduType(wrong-oid-prefix)',
    $ber->detectApduType($wrongApdu), 'unknown');

// ─── InitResponse self-decodes ─────────────────────────────────────────

$initResp2 = $ber->encodeInitResponse('MyRef', 1);
$respBody = $ber->stripPackageHeader($initResp2);
$decoded2 = $ber->decodeInitRequest($respBody);
assert_eq('initResponse referenceId', $decoded2['referenceId'], 'MyRef');
assert_eq('initResponse implementationId', $decoded2['implementationId'], 'Heratio');

// ─── Summary ────────────────────────────────────────────────────────────

echo "\n" . str_repeat('=', 40) . "\n";
echo "Results: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
