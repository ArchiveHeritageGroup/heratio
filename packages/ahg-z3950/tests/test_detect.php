<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

$ber = new AhgZ3950\Services\BerEncoder();

echo "Testing detectApduType...\n";

$close = $ber->encodeClose('ref3', 0);
echo "encodeClose len=" . strlen($close) . "\n";
echo "first8: " . bin2hex(substr($close,0,8)) . "\n";

$type = $ber->detectApduType($close);
echo "detectApduType(encodeClose) = '$type'\n";

$ir = $ber->encodeInitResponse('client1', 1);
echo "encodeInitResponse len=" . strlen($ir) . "\n";
$type2 = $ber->detectApduType($ir);
echo "detectApduType(encodeInitResponse) = '$type2'\n";

echo "Done.\n";