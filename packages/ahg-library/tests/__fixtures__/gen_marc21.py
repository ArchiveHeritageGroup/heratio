#!/usr/bin/env python3
"""
Generate tests/__fixtures__/sample.marc21 — a valid ISO 2709 binary MARC21 record.

ISO 2709 layout:
  Bytes 0-23   : Leader (24 bytes)
  Bytes 24..N  : Directory (12 bytes per entry + 1×0x1E terminator)
  Bytes N+1..  : Data area (fields + RTF 0x1E + RD 0x1D)

Directory entry: tag(3) + length(4) + start(5) where start is the offset
from the base address (the first byte of the data area).
"""

def build_marc21():
    # (tag, raw field content — RTF 0x1E is appended by this script)
    # 008 field MUST be exactly 40 bytes (ISO 2709 §5.2.1):
    #   pos  0-5  : date entered on file (YYMMdd = 6)
    #   pos  6    : type of date (s = single, r = reprint, etc.)
    #   pos  7-10 : publication year (4)
    #   pos 11-14 : various codes
    #   pos 15-34 : 20 bytes reserved
    #   pos 35-37 : language code (3)
    #   pos 38-39 : 2 more chars
    e008 = '250601s2025    xxu    ' + ' ' * 20 + 'eng '  # exactly 40 bytes
    assert len(e008.encode('utf-8')) == 40, f"008 is {len(e008)} bytes, need 40"

    fields = [
        ('001', '9780123456789'),
        ('005', '20250615000000.0'),
        ('008', e008),
        ('020', '\x1Fa9780123456789\x1F\x1FcR450.00'),
        ('100', '\x1FaPieterse, Johan'),
        ('245', '10\x1FaArchival description\x1Fbusing RiC-O\x1Fh[electronic resource]'),
        ('260', '\x1FaPretoria\x1FbAHG Press\x1Fc2025'),
    ]

    # Build data area first so we know field lengths + offsets
    data_parts = []
    offsets    = []

    current_offset = 0
    for _tag, content in fields:
        field_bytes = content.encode('utf-8') + b'\x1E'
        offsets.append(current_offset)
        data_parts.append(field_bytes)
        current_offset += len(field_bytes)

    # Data area: all fields + record terminator (RD 0x1D)
    data_area = b''.join(data_parts) + b'\x1D'

    # Directory
    dir_size = len(fields) * 12 + 1
    base_address = 24 + dir_size

    dir_bytes = bytearray()
    for (tag, _content), start in zip(fields, offsets):
        field_bytes = _content.encode('utf-8') + b'\x1E'
        length = len(field_bytes)
        dir_bytes += tag.encode() + str(length).zfill(4).encode() + str(start).zfill(5).encode()

    dir_bytes += b'\x1E'

    # Leader
    record_len = 24 + len(dir_bytes) + len(data_area)

    leader = bytearray(24)
    for i, c in enumerate(str(record_len).zfill(5).encode()):
        leader[i] = c
    leader[5]  = ord('n')
    leader[6]  = ord('a')
    leader[7]  = ord('m')
    leader[9]  = ord(' ')
    leader[10] = ord('2')
    leader[11] = ord('2')
    for i, c in enumerate(str(base_address).zfill(5).encode()):
        leader[12 + i] = c
    leader[17] = ord(' ')
    leader[18] = ord(' ')

    record = bytes(leader) + bytes(dir_bytes) + data_area
    return record


record = build_marc21()
with open('tests/__fixtures__/sample.marc21', 'wb') as f:
    f.write(record)

print(f'Record: {len(record)} bytes')
leader = record[:24]
print(f'Leader: {leader.decode()}')
print(f'Base address field (bytes 12-16): {leader[12:17].decode()}')
ba = int(leader[12:17].decode())
print(f'Data area starts at byte {ba}\n')

dir_end = ba - 1
print(f'Directory: bytes 24-{dir_end}  ({dir_end-24} bytes, {(dir_end-24)//12} entries)')
pos = 24
while pos + 12 <= dir_end:
    tag   = record[pos:pos+3].decode()
    flen  = int(record[pos+3:pos+7].decode())
    start = int(record[pos+7:pos+12].decode())
    data_start = ba + start
    data_end   = data_start + flen
    field_data = record[data_start:data_end].decode('utf-8', errors='replace')
    print(f'  [{tag}] flen={flen:2d} start={start:3d}  →  {repr(field_data)}')
    pos += 12

print(f'\nRecord terminator: {repr(record[-1:])}')
print(f'008[{35}:{38}] (language code) = {repr(record.decode("utf-8", "replace")[35:38])}')
print('\n✓ sample.marc21 written successfully')