<?php
// Test script: verify BerEncoder detectApduType logic step by step
// Write to heratio workspace so it can run

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

$ber = new AhgZ3950\Services\BerEncoder();

// === Test 1: Basic encode/decode ===
echo "=== BER Primitives ===\n";
echo "encodeUtf8(cafe): " . bin2hex($ber->encodeUtf8('cafe')) . " (want: 0c0463616665)\n";
echo "encodeOctet(test): " . bin2hex($ber->encodeOctet('test')) . " (want: 040474657374)\n";
echo "encodeVisible(Heratio): " . bin2hex($ber->encodeVisible('Heratio')) . " (want: 1a074865726174696f)\n";
echo "encodeInteger(255): " . bin2hex($ber->encodeInteger(255)) . " (want: 020200ff)\n";
echo "encodeInteger(0): " . bin2hex($ber->encodeInteger(0)) . " (want: 020100)\n";
echo "encodeInteger(1000): " . bin2hex($ber->encodeInteger(1000)) . " (want: 020300e803)\n";
echo "encodeSequence(02 01 2a): " . bin2hex($ber->encodeSequence("\x02\x01\x2a")) . " (want: 300302012a)\n";

// === Test 2: detectApduType with encoded APDUs ===
echo "\n=== APDU type detection ===\n";

$refId = $ber->encodeOctet('test');
echo "refId (OCTET): " . bin2hex($refId) . "\n";

$opts = $ber->encodeInitResponseOptions();
echo "initOpts: " . bin2hex($opts) . "\n";

$prefSyntax = $ber->encodeOidSequence([1, 2, 840, 10003, 5, 1]);
echo "prefSyntax: " . bin2hex($prefSyntax) . "\n";

$implName = $ber->encodeVisible('Heratio');
$implVer = $ber->encodeVisible('Server');
$implRev = $ber->encodeVisible('1.0');

// Manually build init response to trace the bytes
$optionsSeq = $ber->encodeSequence($opts . $prefSyntax . $implName . $implVer . $implRev);
$bodySeq = $refId . $optionsSeq;
$apduBody = $ber->encodeOidSequence([1,2,840,10003,9,100,2]) . $ber->encodeSequence($bodySeq);

echo "\nAPDU body:\n";
echo "  len=" . strlen($apduBody) . " hex=" . bin2hex($apduBody) . "\n";
echo "  OID_SEQ len=" . strlen($ber->encodeOidSequence([1,2,840,10003,9,100,2])) . "\n";
echo "  OID_SEQ hex=" . bin2hex($ber->encodeOidSequence([1,2,840,10003,9,100,2])) . "\n";
echo "  bodySeq hex=" . bin2hex($ber->encodeSequence($bodySeq)) . "\n";

$wrapped = $ber->wrapInPackageHeader($apduBody);
echo "\nWrapped:\n";
echo "  first8: " . bin2hex(substr($wrapped,0,8)) . "\n";

$stripped = $ber->stripPackageHeader($wrapped);
echo "\nStripped:\n";
echo "  len=" . strlen($stripped) . " hex=" . bin2hex($stripped) . "\n";

// Trace detectApduType manually
echo "\nManual trace of detectApduType on stripped:\n";
$raw = $stripped;
$len = strlen($raw);
echo "  len=$len\n";

$pos = 0;
if ($len >= 8 && ord($raw[0]) === 0x00 && ord($raw[2]) === 0x00) {
    echo "  [pkg header detected, pos=8]\n";
    $pos = 8;
}
echo "  pos=$pos byte=0x" . sprintf('%02x', ord($raw[$pos])) . " (want 0x30)\n";

$outerLen = 0; $consumed = 0;
$lb = $ber->decodeLength($raw, $pos + 1, $outerLen, $consumed);
echo "  outerLen=$outerLen consumed=$consumed lb=$lb\n";
$pos = $pos + 1 + $consumed;
echo "  pos after outer len=$pos byte=0x" . sprintf('%02x', ord($raw[$pos])) . " (want 0x30)\n";

$oidLen = 0; $c = 0;
$lb2 = $ber->decodeLength($raw, $pos + 1, $oidLen, $c);
echo "  oidLen=$oidLen consumed=$lb2\n";
$oidValStart = $pos + 1 + $lb2;
echo "  oidValStart=$oidValStart (need <= $len)\n";

$oidValue = substr($raw, $oidValStart, $oidLen);
echo "  oidValue hex: " . bin2hex($oidValue) . "\n";
$decodedOid = $ber->decodeOidValue($oidValue);
echo "  decoded OID: " . json_encode($decodedOid) . "\n";

$last = array_slice($decodedOid, -1, 1)[0] ?? 0;
$secondLast = array_slice($decodedOid, -2, 1)[0] ?? 0;
echo "  secondLast=$secondLast last=$last\n";

// Now call detectApduType
echo "\nFunction results:\n";
$type = $ber->detectApduType($wrapped);
echo "  detectApduType(wrapped) = '$type'\n";
$type2 = $ber->detectApduType($stripped);
echo "  detectApduType(stripped) = '$type2'\n";

// === Test 3: Build Close APDU ===
echo "\n=== Close APDU ===\n";
$close = $ber->encodeClose('ref3', 0);
echo "encodeClose first8: " . bin2hex(substr($close,0,8)) . "\n";
echo "encodeClose len=" . strlen($close) . "\n";
$type3 = $ber->detectApduType($close);
echo "detectApduType(encodeClose) = '$type3'\n";

// === Test 4: Test encodeInitResponse directly ===
echo "\n=== encodeInitResponse ===\n";
$ir = $ber->encodeInitResponse('client1', 1);
echo "encodeInitResponse first8: " . bin2hex(substr($ir,0,8)) . "\n";
echo "encodeInitResponse len=" . strlen($ir) . "\n";
$type4 = $ber->detectApduType($ir);
echo "detectApduType(encodeInitResponse) = '$type4'\n";