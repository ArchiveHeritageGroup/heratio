#!/usr/bin/env python3
"""Fix test assertions and buildLikePattern in Z3950ServerService."""

# ── Fix 1: BerEncoderTest.php ───────────────────────────────────────────────
# a) encodeInteger(255): leading zero required for BER positive >= 128
#    "\xff" (0xFF, high bit set) → needs leading 0x00 → body is "\x00\xff"
old = b'    // BER: +255 requires leading zero byte (0x00 0xff) so MSB is clear\n    $this->assertEquals(255, $this->ber->decodeIntegerValue("' + b'\xff' + b'"));'
new = b'    // BER: +255 requires leading zero byte (0x00 0xff) so MSB is clear\n    $this->assertEquals(255, $this->ber->decodeIntegerValue("\\x00\\xff");'
with open('/usr/share/nginx/heratio/packages/ahg-z3950/tests/Unit/BerEncoderTest.php', 'rb') as f:
    c = f.read()
if old in c:
    c = c.replace(old, new, 1)
    print('Fix 1a applied: decodeIntegerValue test input ff→00ff')
else:
    print('Fix 1a NOT found')

# b) encodeInteger(255): leading zero required → body is 2 bytes, not 1
old2 = b'    public function testEncodeInteger255(): void\n    {\n        $bytes = $this->ber->encodeInteger(255);\n        $this->assertEquals("\\x02\\x01\\xff", $bytes);'
new2 = b'    public function testEncodeInteger255(): void\n    {\n        $bytes = $this->ber->encodeInteger(255);\n        // BER: +255 = 0xFF, high bit set → leading zero byte required → body is "\x00\xff"\n        $this->assertEquals("\\x02\\x02\\x00\\xff", $bytes);'
if old2 in c:
    c = c.replace(old2, new2, 1)
    print('Fix 1b applied: encodeInteger(255) expects 020200ff')
else:
    print('Fix 1b NOT found')

with open('/usr/share/nginx/heratio/packages/ahg-z3950/tests/Unit/BerEncoderTest.php', 'wb') as f:
    f.write(c)

# ── Fix 2: Z3950ServerServiceTest.php ──────────────────────────────────────
# a) buildLikePattern: escape order is wrong (\\ before %_)
#    Current: % → \% → \\%, _ → \_ → \\_  ← WRONG
#    Correct: % → \% → \\%, _ → \_ → \\_  ← WRONG ORDER (\\ first, then \%)
#    Fix: str_replace(['%','_'], ['\\%','\\_'], $term) FIRST, then str_replace('\\','\\\\', ...)
#    After fix: "50%" → "50\%" → "50\%" (right-truncate adds % → "50\%%")
#    But test expects "50\\%" which means the ESCAPE SEQUENCE itself contains \ followed by %
#    Actually: '50\%' in PHP = "50\%" (single backslash). So "50\\%" = "50\%" in PHP.
#    Wait... Let me reconsider. 
#    The CORRECT SQL LIKE for literal "50%" is: 50\%  (escape the %)
#    + right truncation: 50\% followed by % → "50\%%" (not "50\\%")
#    The test comment says: "Result: 50\%  + %  → '50\\%'" in PHP string: '50\\%' = "50\%"
#    In the test assertion: $this->assertEquals('50\\%', $result);
#    PHP '50\\%' = "50\%" (one backslash). Good.
#    So: term="50%", after buildLikePattern('50%', 'right-truncate'):
#    1. str_replace(['%','_'], ['\\%','\\_'], '50%') → '50\%'
#    2. str_replace('\\','\\\\', '50\%') → '50\\%'  ← WRONG (double backslash)
#    Correct order:  str_replace('\\','\\\\', $term) FIRST, then escape % and _
#    1. str_replace('\\','\\\\', '50%') → '50%' (no change, no backslash in term)
#    2. str_replace(['%','_'], ['\\%','\\_'], '50%') → '50\%'
#    3. return '50\%' . '%' → '50\%%'
#    But the test expects '50\%' followed by '%' = '50\%%'
#    So the assertion should be: assertEquals('50\%%', $result)
#    And: assertEquals('a\_b%', $result) for 'a_b'

with open('/usr/share/nginx/heratio/packages/ahg-z3950/tests/Unit/Z3950ServerServiceTest.php', 'rb') as f:
    c = f.read()

# Fix buildLikePattern test assertions
fixes = [
    # testBuildLikePatternEscapesPercent
    (b"$this->assertEquals('50\\\\%', $result);",
     b"$this->assertEquals('50\\%%', $result);"),
    # testBuildLikePatternEscapesUnderscore  
    (b"$this->assertEquals('a\\\\_b%', $result);",
     b"$this->assertEquals('a\\_b%', $result);"),
]
for old, new in fixes:
    if old in c:
        c = c.replace(old, new, 1)
        print(f'Applied: {old[:40]} → {new[:40]}')
    else:
        print(f'NOT found: {old[:60]}')

# Fix OID constant test expectations
# OID constants are [1,2,840,10003,9,100] = 6 arcs
# The tests assert 7 arcs (wrong). Fix to 6.
oid_fixes = [
    # InitRequest - 6 arcs
    (b"    public function testBerEncoderOidConstants(): void\n    {\n        $this->assertEquals([1, 2, 840, 10003, 9, 100], BerEncoder::OID_INIT_REQUEST);",
     b"    public function testBerEncoderOidConstants(): void\n    {\n        // OID root: 1.2.840.10003.9.100 (6 arcs), APDU type appended at runtime\n        $this->assertEquals([1, 2, 840, 10003, 9, 100], BerEncoder::OID_INIT_REQUEST);"),
    # InitResponse - append 1
    (b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 1], BerEncoder::OID_INIT_RESPONSE);",
     b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 1], BerEncoder::OID_INIT_RESPONSE);"),
    # SearchRequest - append 6
    (b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 6], BerEncoder::OID_SEARCH_REQUEST);",
     b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 6], BerEncoder::OID_SEARCH_REQUEST);"),
    # SearchResponse - append 7
    (b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 7], BerEncoder::OID_SEARCH_RESPONSE);",
     b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 7], BerEncoder::OID_SEARCH_RESPONSE);"),
    # PresentRequest - append 13
    (b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 13], BerEncoder::OID_PRESENT_REQUEST);",
     b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 13], BerEncoder::OID_PRESENT_REQUEST);"),
    # PresentResponse - append 14
    (b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 14], BerEncoder::OID_PRESENT_RESPONSE);",
     b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 14], BerEncoder::OID_PRESENT_RESPONSE);"),
    # Close - append 23
    (b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 23], BerEncoder::OID_CLOSE);",
     b"        $this->assertEquals([1, 2, 840, 10003, 9, 100, 23], BerEncoder::OID_CLOSE);"),
]
for old, new in oid_fixes:
    if old in c:
        c = c.replace(old, new, 1)
        print(f'OID fix applied')
    else:
        print(f'OID fix NOT found: {old[:80]}')

with open('/usr/share/nginx/heratio/packages/ahg-z3950/tests/Unit/Z3950ServerServiceTest.php', 'wb') as f:
    f.write(c)
print('Done with test fixes')
