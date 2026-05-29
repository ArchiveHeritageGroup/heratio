#!/usr/bin/env python3
import re

with open('/usr/share/nginx/heratio/packages/ahg-z3950/src/Services/BerEncoder.php', 'rb') as f:
    content = f.read()

# Fix 1: decodeLength parameter types - int → ?int, add defaults
old1_sig = b'    public function decodeLength(string $bytes, int $offset, int &$length, int &$consumed): int'
new1_sig = b'    public function decodeLength(string $bytes, int $offset, ?int &$length = null, ?int &$consumed = null): int'
if old1_sig in content:
    content = content.replace(old1_sig, new1_sig, 1)
    print('Fix 1 applied: decodeLength int to ?int with defaults')
else:
    print('Fix 1 NOT found (may already be applied or different)')

# Fix 2: encodeOidValue continuation bit - loop from j=0 to j<count-1 → j=1 to j<count
old2_loop = b'            for ($j = 0; $j < $count - 1; $j++) {\n                $arcBytes[$j] = chr(ord($arcBytes[$j]) | 0x80);\n            }'
new2_loop = b'            for ($j = 1; $j < $count; $j++) {\n                $arcBytes[$j] = chr(ord($arcBytes[$j]) | 0x80);\n            }'
if old2_loop in content:
    content = content.replace(old2_loop, new2_loop, 1)
    print('Fix 2 applied: encodeOidValue j=1 to count (continuation on first N-1 of LSB-first)')
else:
    print('Fix 2 NOT found (may already be applied or different)')

with open('/usr/share/nginx/heratio/packages/ahg-z3950/src/Services/BerEncoder.php', 'wb') as f:
    f.write(content)
print('Done writing BerEncoder.php')
