<?php
require __DIR__ . '/vendor/autoload.php';
$b = new AhgZ3950\Services\BerEncoder;

echo 'encodeInteger(1): ' . bin2hex($b->encodeInteger(1)) . PHP_EOL;
echo 'encodeInteger(255): ' . bin2hex($b->encodeInteger(255)) . PHP_EOL;
echo 'encodeInteger(1000): ' . bin2hex($b->encodeInteger(1000)) . PHP_EOL;
echo 'encodeSequence: ' . bin2hex($b->encodeSequence(chr(0x02).chr(0x01).chr(0x2a))) . PHP_EOL;

$v = $b->decodeIntegerValue(chr(0xff));
echo 'decodeIntegerValue(0xff): ' . $v . PHP_EOL;
$v = $b->decodeIntegerValue(chr(0x00).chr(0xff));
echo 'decodeIntegerValue(00ff): ' . $v . PHP_EOL;

echo 'encodeOidSequence: ' . bin2hex($b->encodeOidSequence($b::OID_INIT_REQUEST)) . PHP_EOL;

$oidSeq = $b->encodeOidSequence($b::OID_INIT_REQUEST);
$inner = $b->encodeSequence($b->encodeOctet('test'));
$apdu = $b->wrapInPackageHeader($oidSeq . $inner);
echo 'detectApduType: ' . $b->detectApduType($apdu) . PHP_EOL;
echo 'Expected detectApduType: init_request' . PHP_EOL;

echo 'encodeUtf8: ' . bin2hex($b->encodeUtf8('café')) . PHP_EOL;
echo 'encodeVisible: ' . bin2hex($b->encodeVisible('Heratio')) . PHP_EOL;
echo 'encodeOctet: ' . bin2hex($b->encodeOctet('test')) . PHP_EOL;
echo 'encodeNull: ' . bin2hex($b->encodeNull()) . PHP_EOL;