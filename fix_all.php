<?php
/**
 * Fix all remaining test + code bugs in ahg-z3950.
 * Run: php fix_all.php
 */

$ber_file = __DIR__ . '/packages/ahg-z3950/src/Services/BerEncoder.php';
$ber_test = __DIR__ . '/packages/ahg-z3950/tests/Unit/BerEncoderTest.php';
$server_test = __DIR__ . '/packages/ahg-z3950/tests/Unit/Z3950ServerServiceTest.php';
$server_svc = __DIR__ . '/packages/ahg-z3950/src/Services/Z3950ServerService.php';

// ── Fix 1: BerEncoder.php ────────────────────────────────────────────────────

$ber = file_get_contents($ber_file);

// Fix 1a: decodeLength parameter types (int& → ?int& with defaults)
$ber = str_replace(
    'public function decodeLength(string $bytes, int $offset, int &$length, int &$consumed): int',
    'public function decodeLength(string $bytes, int $offset, ?int &$length = null, ?int &$consumed = null): int',
    $ber
);
echo "Fix 1a: decodeLength params updated\n";

// Fix 1b: encodeOidValue continuation bit loop
// OLD: for ($j = 0; $j < $count - 1; $j++) { ... }  (sets bit on [0..count-2] = wrong)
// NEW: for ($j = 1; $j < $count; $j++) { ... }      (sets bit on [1..count-1] = right)
$ber = str_replace(
    "            for (\$j = 0; \$j < \$count - 1; \$j++) {\n                \$arcBytes[\$j] = chr(ord(\$arcBytes[\$j]) | 0x80);\n            }",
    "            for (\$j = 1; \$j < \$count; \$j++) {\n                \$arcBytes[\$j] = chr(ord(\$arcBytes[\$j]) | 0x80);\n            }",
    $ber
);
echo "Fix 1b: encodeOidValue continuation bit loop fixed (j=1 to count)\n";

// Fix 1c: decodeIntegerValue test input "\xff" → "\x00\xff" (ff with high bit = BER negative)
// The assertion expects 255 but \xff = -1 per two's complement. Fix test input.
$ber = str_replace(
    "\$this->assertEquals(255, \$this->ber->decodeIntegerValue(\"\\xff\"));",
    "\$this->assertEquals(255, \$this->ber->decodeIntegerValue(\"\\x00\\xff\"));",
    $ber
);
echo "Fix 1c: decodeIntegerValue test input fixed (\\xff → \\x00\\xff)\n";

// Fix 1d: encodeInteger(255) test assertion: leading zero required per BER rules
// 255 = 0xFF, high bit set → BER body is "\x00\xff" (2 bytes), not "\xff" (1 byte)
$ber = str_replace(
    '// BER: +255 requires leading zero byte (0x00 0xff) so MSB is clear' . "\n" .
    '        $this->assertEquals("\\x02\\x01\\xff", $bytes);',
    '// BER: +255 = 0xFF, high bit set → leading zero byte required → body "\x00\xff" (2 bytes)' . "\n" .
    '        $this->assertEquals("\\x02\\x02\\x00\\xff", $bytes);',
    $ber
);
echo "Fix 1d: encodeInteger(255) test assertion fixed (expects 020200ff)\n";

file_put_contents($ber_file, $ber);
echo "BerEncoder.php written\n";

// ── Fix 2: Z3950ServerServiceTest.php ───────────────────────────────────────

$stest = file_get_contents($server_test);

// Fix 2a: buildLikePattern escaping - wrong order
// Current: escape % and _ first, then escape backslashes → double-escapes \ before %
// Fix: escape backslashes FIRST, then escape % and _
// testBuildLikePatternEscapesPercent: expects '50\\%' but correct is '50\%%'
$stest = str_replace(
    "\$this->assertEquals('50\\\\%', \$result);",
    "\$this->assertEquals('50\\%%', \$result);",
    $stest
);
echo "Fix 2a: buildLikePattern percent assertion updated\n";

// testBuildLikePatternEscapesUnderscore: expects 'a\\\\_b%' but correct is 'a\\_b%'
$stest = str_replace(
    "\$this->assertEquals('a\\\\\\_b%', \$result);",
    "\$this->assertEquals('a\\_b%', \$result);",
    $stest
);
echo "Fix 2b: buildLikePattern underscore assertion updated\n";

// Fix 2c: OID constant test - constants have 6 arcs, tests assert 7
// The OID root [1,2,840,10003,9,100] is 6 arcs; tests wrongly expect 7
// All the assertions in testBerEncoderOidConstants are already correct (6 arcs)
// No fix needed for OID constant assertions - they're right.

file_put_contents($server_test, $stest);
echo "Z3950ServerServiceTest.php written\n";

// ── Fix 3: Z3950ServerService.php buildLikePattern ──────────────────────────

$svc = file_get_contents($server_svc);

// Fix: escape order - escape existing backslashes FIRST, then escape SQL wildcards
// Current (WRONG): str_replace(['%','_'], ['\\%','\\_'], $term) then str_replace('\\','\\\\', ...)
// Correct: str_replace('\\','\\\\', $term) then str_replace(['%','_'], ['\\%','\\_'], ...)
$svc = str_replace(
    "        // Escape LIKE wildcards first, then escape backslash itself\n" .
    "        \$escaped = str_replace(['%', '_'], ['\\\\%', '\\\\_'], \$term);\n" .
    "        \$escaped = str_replace('\\\\', '\\\\\\\\', \$escaped);",
    "        // Escape existing backslashes FIRST so they aren't consumed by %/_ escaping\n" .
    "        \$escaped = str_replace('\\\\', '\\\\\\\\', \$term);\n" .
    "        \$escaped = str_replace(['%', '_'], ['\\\\%', '\\\\_'], \$escaped);",
    $svc
);
echo "Fix 3: buildLikePattern escape order corrected\n";

file_put_contents($server_svc, $svc);
echo "Z3950ServerService.php written\n";

echo "\nAll fixes applied.\n";
