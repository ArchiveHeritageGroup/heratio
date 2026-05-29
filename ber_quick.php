<?php
require __DIR__.'/vendor/autoload.php';
use AhgZ3950\Services\BerEncoder;

$b = new BerEncoder();
$failed = 0;

$tests = [
    [0,"\x00"],[1,"\x01"],[127,"\x7f"],[255,"\x00\xff"],[128,"\x00\x80"],[1000,"\x03\xe8"],[-1,"\xff"],[-128,"\x80"]
];
foreach ($tests as [$v,$e]) {
    $g = $b->encodeIntegerValue($v);
    if ($g !== $e) { echo "FAIL encodeInt($v): ".bin2hex($e)." got ".bin2hex($g)."\n"; $failed++; }
    else { echo "OK encodeInt($v)\n"; }
    $d = $b->decodeIntegerValue($e);
    if ($d !== $v) { echo "FAIL decodeInt(".bin2hex($e)."): $v got $d\n"; $failed++; }
}

$oids = [
    [[1,2,840],               "\x2a\x86\x48"],
    [[1,2,840,10003,9,100],   "\x2a\x86\x48\x9a\x16\x73\x09"],
    [[1,2,840,10003,9,100,1], "\x2a\x86\x48\x9a\x16\x73\x09\x01"],
];
foreach ($oids as [$o,$e]) {
    $g = $b->encodeOidValue($o);
    if ($g !== $e) { echo "FAIL encodeOid: ".bin2hex($e)." got ".bin2hex($g)."\n"; $failed++; }
    else { echo "OK encodeOid\n"; }
    $d = $b->decodeOidValue($e);
    if ($d !== $o) { echo "FAIL decodeOid: [".implode(",",$o)."] got [".implode(",",$d)."]\n"; $failed++; }
}

$lens = [["\x7f",127,1],["\x81\xff",255,2],["\x82\x04\x00",1024,3]];
foreach ($lens as [$bytes,$exp,$expc]) {
    $c = 99;
    $r = $b->decodeLength($bytes, 0, $c);
    if ($r !== $exp || $c !== $expc) { echo "FAIL decodeLen: ($exp,$expc) got ($r,$c)\n"; $failed++; }
    else { echo "OK decodeLen\n"; }
}

echo $failed === 0 ? "ALL PASS\n" : "$failed FAILURES\n";