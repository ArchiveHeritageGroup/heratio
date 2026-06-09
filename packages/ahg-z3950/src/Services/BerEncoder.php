<?php

/**
 * BerEncoder — BER (Basic Encoding Rules) encode/decode for Z39.50 APDUs.
 *
 * Implements ITU-T X.690 (BER) and ISO 23950 Z39.50 APDU binary encoding.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd — AGPL-3.0
 */

namespace AhgZ3950\Services;

class BerEncoder
{
    // Universal tag numbers
    public const TAG_BOOLEAN     = 0x01;
    public const TAG_INTEGER    = 0x02;
    public const TAG_OCTET     = 0x04;
    public const TAG_NULL      = 0x05;
    public const TAG_OID       = 0x06;
    public const TAG_SEQUENCE  = 0x30;
    public const TAG_SET       = 0x31;
    public const TAG_UTF8STR   = 0x0c;
    public const TAG_NUMERICSTR = 0x12;
    public const TAG_PRINTABLESTR = 0x13;
    public const TAG_IA5STR    = 0x16;
    public const TAG_VISIBLE   = 0x1a;

    // Z39.50 APDU OID: 1.2.840.10003.9.100 (6 arcs)
    public const OID_ROOT   = [1, 2, 840, 10003, 9];
    public const OID_APDU95 = [1, 2, 840, 10003, 9, 100];

    // APDU type OIDs (appended to OID_APDU95 = 7 arcs total)
    public const OID_INIT_REQUEST     = [1, 2, 840, 10003, 9, 100, 1];
    public const OID_INIT_RESPONSE    = [1, 2, 840, 10003, 9, 100, 2];
    public const OID_SEARCH_REQUEST   = [1, 2, 840, 10003, 9, 100, 6];
    public const OID_SEARCH_RESPONSE  = [1, 2, 840, 10003, 9, 100, 7];
    public const OID_PRESENT_REQUEST  = [1, 2, 840, 10003, 9, 100, 13];
    public const OID_PRESENT_RESPONSE = [1, 2, 840, 10003, 9, 100, 14];
    public const OID_CLOSE             = [1, 2, 840, 10003, 9, 100, 23];
    public const OID_DELETE_RESULT_SET = [1, 2, 840, 10003, 9, 100, 19];

    public const REFERENCE_ID_PREFIX = 'HERATIO';

    private string $ber = '';

    // ──── High-level encoding ─────────────────────────────────────────────

    public function encodeInteger(int $value): string
    {
        $body = $this->encodeIntegerValue($value);
        return $this->encodeTagLength(self::TAG_INTEGER, $body) . $body;
    }

    public function encodeOid(array $oid): string
    {
        $body = $this->encodeOidValue($oid);
        return $this->encodeTagLength(self::TAG_OID, $body) . $body;
    }

    public function encodeUtf8(string $value): string
    {
        return $this->encodeTagLength(self::TAG_UTF8STR, $value) . $value;
    }

    public function encodeVisible(string $value): string
    {
        return $this->encodeTagLength(self::TAG_VISIBLE, $value) . $value;
    }

    public function encodeOctet(string $bytes): string
    {
        return $this->encodeTagLength(self::TAG_OCTET, $bytes) . $bytes;
    }

    public function encodeSequence(string $content): string
    {
        return $this->encodeTagLength(self::TAG_SEQUENCE, $content) . $content;
    }

    public function encodeOidSequence(array $oid): string
    {
        return $this->encodeSequence($this->encodeOid($oid));
    }

    public function encodeIntegerSequence(int $value): string
    {
        return $this->encodeSequence($this->encodeInteger($value));
    }

    public function encodeNull(): string
    {
        return "\x05\x00";
    }

    // ──── Z39.50 APDU encoding ─────────────────────────────────────────────

    /**
     * Encode options BIT STRING for InitResponse.
     * BIT STRING body: 4 unused bits (0x00 each) + 1 flags byte.
     */
    public function encodeInitResponseOptions(array $options = []): string
    {
        $defaults = [
            'search'    => true,
            'present'   => true,
            'delSet'    => true,
            'namedResultsSets' => false,
        ];
        $opts = array_merge($defaults, $options);

        $bits = 0;
        if (! empty($opts['search']))           $bits |= 1 << 0;
        if (! empty($opts['present']))          $bits |= 1 << 1;
        if (! empty($opts['delSet']))           $bits |= 1 << 2;
        if (! empty($opts['namedResultsSets'])) $bits |= 1 << 8;

        // Z39.50 InitResponse `options` is an ASN.1 BIT STRING (tag 0x03, len 5:
        // 1 unused-bits octet + 4 flag octets) - NOT a SEQUENCE. The preferred-
        // record-syntax OID is a Search/Present request field, not part of the
        // InitResponse options, so it does not belong here.
        $bitStringBody = "\x00\x00\x00\x00" . chr($bits);

        return "\x03\x05" . $bitStringBody;
    }

    public function encodeInitResponse(string $referenceId, int $result = 1): string
    {
        $content = '';
        $content .= $this->encodeOctet($referenceId);
        if (! $result) {
            $content .= "\x01\x01\x00";
        }
        // protocolVersion BIT STRING: tag 0x03, len 5, 4 unused + flags 0x03
        $content .= "\x03\x05\x00\x00\x00\x03";
        $content .= $this->encodeInitResponseOptions();
        $content .= $this->encodeOidSequence([1, 2, 840, 10003, 5, 1]);
        $content .= $this->encodeVisible('Heratio');
        $content .= $this->encodeVisible('Heratio Z39.50 Server');
        $content .= $this->encodeVisible('1.0');

        $apdu = $this->encodeOidSequence(self::OID_INIT_RESPONSE)
              . $this->encodeSequence($content);

        return $this->wrapInPackageHeader($apdu);
    }

    public function encodeSearchResponse(
        string $referenceId,
        int    $resultCount,
        string $resultSetId = 'default',
        string $records = '',
        int    $nextResultSetPosition = 0
    ): string {
        $content = '';
        $content .= $this->encodeOctet($referenceId);
        $content .= "\x01\x01\xff";
        $content .= $this->encodeInteger($resultCount);
        $content .= $this->encodeInteger(substr_count($records, "\x1d") ?: 0);
        $content .= $this->encodeInteger($nextResultSetPosition ?: 0);
        $content .= $this->encodeVisible($resultSetId ?: 'default');
        $content .= $this->encodeSequence('');

        $apdu = $this->encodeOidSequence(self::OID_SEARCH_RESPONSE)
              . $this->encodeSequence($content);

        if ($records !== '') {
            $apdu .= $records;
        }

        return $this->wrapInPackageHeader($apdu);
    }

    public function encodePresentResponse(
        string $referenceId,
        int    $nextPosition,
        int    $numberReturned,
        string $records = '',
        int    $resultSetStatus = 0
    ): string {
        $content = '';
        $content .= $this->encodeOctet($referenceId);
        $content .= "\x01\x01\xff";
        $content .= $this->encodeInteger($nextPosition);
        $content .= $this->encodeInteger($numberReturned);
        $content .= $this->encodeInteger($resultSetStatus);
        $content .= $this->encodeSequence('');

        $recordContent = '';
        if (strlen($records) > 0) {
            $recordContent .= $this->encodeOctet($records);
        }
        $content .= $this->encodeSequence($recordContent);

        $apdu = $this->encodeOidSequence(self::OID_PRESENT_RESPONSE)
              . $this->encodeSequence($content);

        return $this->wrapInPackageHeader($apdu);
    }

    public function encodeClose(string $referenceId, int $closeStatus = 0): string
    {
        $content = '';
        $content .= $this->encodeOctet($referenceId);
        $content .= $this->encodeInteger($closeStatus);

        $apdu = $this->encodeOidSequence(self::OID_CLOSE)
              . $this->encodeSequence($content);

        return $this->wrapInPackageHeader($apdu);
    }

    public function encodeDeleteResultSetResponse(string $referenceId, int $deleteStatus = 0): string
    {
        $content = '';
        $content .= $this->encodeOctet($referenceId);
        $content .= $this->encodeInteger($deleteStatus);

        $apdu = $this->encodeOidSequence(self::OID_DELETE_RESULT_SET)
              . $this->encodeSequence($content);

        return $this->wrapInPackageHeader($apdu);
    }

    // ──── BER decoding ─────────────────────────────────────────────────────

    public function decodeSearchRequest(string $body): array
    {
        $result = [
            'referenceId'    => '',
            'query'          => '',
            'resultSetName'  => 'default',
            'maxRecords'     => 0,
            'recordSyntax'   => '',
            'elementSetName' => '',
            'smallSetElementSetNames' => '',
            'largeSetNavigation' => false,
        ];

        $pos = 0;
        $len = strlen($body);

        while ($pos < $len) {
            $tag = ord($body[$pos]);
            [$lenBytes, $length, $consumed] = $this->decodeLengthRet($body, $pos + 1);
            if ($lenBytes === 0) break;
            $valStart = $pos + 1 + $lenBytes;
            $value = substr($body, $valStart, $length);

            if ($tag === self::TAG_OCTET) {
                if ($result['referenceId'] === '') {
                    $result['referenceId'] = $value;
                } else {
                    $result['query'] = $value;
                }
            } elseif ($tag === self::TAG_SEQUENCE) {
                $this->decodeSearchRequestInner($value, $result);
            } elseif ($tag === self::TAG_INTEGER) {
                $result['maxRecords'] = $this->decodeIntegerValue($value);
            } elseif (in_array($tag, [self::TAG_VISIBLE, self::TAG_IA5STR, self::TAG_UTF8STR], true)) {
                if ($result['elementSetName'] === '' && strlen($value) <= 10) {
                    $result['elementSetName'] = $value;
                }
            } elseif ($tag === self::TAG_OID) {
                $result['recordSyntax'] = implode('.', $this->decodeOidValue($value));
            }

            $pos = $valStart + $length;
        }

        return $result;
    }

    private function decodeSearchRequestInner(string $content, array &$result): void
    {
        $pos = 0;
        $len = strlen($content);

        while ($pos < $len) {
            $tag = ord($content[$pos]);
            [$lenBytes, $length] = $this->decodeLengthRet($content, $pos + 1);
            if ($lenBytes === 0) break;
            $valStart = $pos + 1 + $lenBytes;
            $value = substr($content, $valStart, $length);

            if ($tag === self::TAG_OCTET) {
                $result['query'] = $value;
            } elseif ($tag === self::TAG_VISIBLE) {
                if ($result['resultSetName'] === 'default') {
                    $result['resultSetName'] = $value;
                }
            } elseif ($tag === self::TAG_INTEGER) {
                if ($result['maxRecords'] === 0) {
                    $result['maxRecords'] = $this->decodeIntegerValue($value);
                }
            }

            $pos = $valStart + $length;
        }
    }

    public function decodeInitRequest(string $body): array
    {
        $result = [
            'referenceId'           => '',
            'options'               => 0,
            'preferredRecordSyntax' => '',
            'implementationId'     => '',
            'implementationName'   => '',
            'implementationVersion' => '',
        ];

        $pos = 0;
        $len = strlen($body);

        while ($pos < $len) {
            $tag = ord($body[$pos]);
            [$lenBytes, $length] = $this->decodeLengthRet($body, $pos + 1);
            if ($lenBytes === 0) break;
            $valStart = $pos + 1 + $lenBytes;
            $value = substr($body, $valStart, $length);

            if ($tag === self::TAG_OCTET) {
                if ($result['referenceId'] === '') {
                    $result['referenceId'] = $value;
                }
            } elseif ($tag === self::TAG_SEQUENCE) {
                $this->decodeInitRequestInner($value, $result);
            }

            $pos = $valStart + $length;
        }

        return $result;
    }

    private function decodeInitRequestInner(string $content, array &$result): void
    {
        $pos = 0;
        $len = strlen($content);

        while ($pos < $len) {
            $tag = ord($content[$pos]);
            [$lenBytes, $length] = $this->decodeLengthRet($content, $pos + 1);
            if ($lenBytes === 0) break;
            $valStart = $pos + 1 + $lenBytes;
            $value = substr($content, $valStart, $length);

            if ($tag === self::TAG_OCTET) {
                if (strlen($value) >= 1) {
                    $result['options'] = ord($value[strlen($value) - 1]);
                }
            } elseif ($tag === self::TAG_OID) {
                $result['preferredRecordSyntax'] = implode('.', $this->decodeOidValue($value));
            } elseif (in_array($tag, [self::TAG_VISIBLE, self::TAG_UTF8STR], true)) {
                if ($result['implementationId'] === '') {
                    $result['implementationId'] = $value;
                } elseif ($result['implementationName'] === '') {
                    $result['implementationName'] = $value;
                } else {
                    $result['implementationVersion'] = $value;
                }
            }

            $pos = $valStart + $length;
        }
    }

    public function decodePresentRequest(string $body): array
    {
        $result = [
            'referenceId'         => '',
            'resultSetId'         => 'default',
            'resultSetStartPoint' => 1,
            'maxRecords'          => 0,
            'recordSyntax'        => '',
            'elementSetNames'    => '',
        ];

        $pos = 0;
        $len = strlen($body);

        while ($pos < $len) {
            $tag = ord($body[$pos]);
            [$lenBytes, $length] = $this->decodeLengthRet($body, $pos + 1);
            if ($lenBytes === 0) break;
            $valStart = $pos + 1 + $lenBytes;
            $value = substr($body, $valStart, $length);

            if ($tag === self::TAG_OCTET) {
                if ($result['referenceId'] === '') {
                    $result['referenceId'] = $value;
                }
            } elseif ($tag === self::TAG_SEQUENCE) {
                $this->decodePresentRequestInner($value, $result);
            } elseif ($tag === self::TAG_INTEGER) {
                $result['resultSetStartPoint'] = $this->decodeIntegerValue($value);
            }

            $pos = $valStart + $length;
        }

        return $result;
    }

    private function decodePresentRequestInner(string $content, array &$result): void
    {
        $pos = 0;
        $len = strlen($content);

        while ($pos < $len) {
            $tag = ord($content[$pos]);
            [$lenBytes, $length] = $this->decodeLengthRet($content, $pos + 1);
            if ($lenBytes === 0) break;
            $valStart = $pos + 1 + $lenBytes;
            $value = substr($content, $valStart, $length);

            if ($tag === self::TAG_VISIBLE || $tag === self::TAG_UTF8STR) {
                if ($result['resultSetId'] === 'default') {
                    $result['resultSetId'] = $value;
                } elseif ($result['elementSetNames'] === '') {
                    $result['elementSetNames'] = $value;
                }
            } elseif ($tag === self::TAG_INTEGER) {
                if ($result['maxRecords'] === 0) {
                    $result['maxRecords'] = $this->decodeIntegerValue($value);
                }
            } elseif ($tag === self::TAG_OCTET) {
                // MARC record
            }

            $pos = $valStart + $length;
        }
    }

    // ──── Core encoding helpers ────────────────────────────────────────────

    /**
     * Encode an INTEGER body as BER DER bytes.
     *
     * DER rule: two's complement. Positive: MSB must not have high bit set
     * (add leading 0x00 if MSB has bit 7 set). Negative: minimal encoding
     * (minimum bytes to represent the value with high bit 1).
     */
    public function encodeIntegerValue(int $value): string
    {
        if ($value === 0) {
            return "\x00";
        }

        $bytes = [];

        if ($value > 0) {
            $v = $value;
            while ($v !== 0) {
                $bytes[] = chr($v & 0xff);
                $v >>= 8;
            }
            $bytes = array_reverse($bytes);

            // DER: if MSB has bit 7 set, prepend 0x00 to keep the value positive.
            if (ord($bytes[0]) & 0x80) {
                array_unshift($bytes, "\x00");
            }
        } else {
            // Negative: two's complement. Arithmetic >> floors a negative value
            // at -1 (all-ones), so terminate on -1 once the remaining bits are
            // all sign bits — otherwise the loop never ends and blows up memory.
            // do/while so value -1 still emits its single 0xff byte (the loop
            // condition is already true on entry for -1).
            $v = $value;
            do {
                $bytes[] = chr($v & 0xff);
                $v >>= 8;
            } while ($v !== -1);
            $bytes = array_reverse($bytes);

            // Minimal encoding: the MSB must already have bit 7 set (it is a
            // negative number). If it is clear, a leading 0xff is needed so the
            // sign survives. Drop a redundant leading 0xff when the next byte
            // already carries the sign bit.
            if (count($bytes) > 1 && ord($bytes[0]) === 0xff && (ord($bytes[1]) & 0x80)) {
                array_shift($bytes);
            } elseif (! (ord($bytes[0]) & 0x80)) {
                array_unshift($bytes, "\xff");
            }
        }

        return implode('', $bytes);
    }

    /**
     * Encode an OID as BER DER bytes (base-128, ITU-T X.690).
     *
     * First byte: (arc[0] * 40) + arc[1].
     * Subsequent arcs: base-128, LSB-first, all bytes except last get 0x80.
     */
    public function encodeOidValue(array $oid): string
    {
        if (count($oid) < 2) {
            return '';
        }

        $bytes = [];

        // First arc pair: (a * 40) + b
        $first = ($oid[0] * 40) + ($oid[1] ?? 0);
        $bytes[] = chr($first);

        // Remaining arcs: base-128, LSB-first, continuation bit on all but last
        $count = count($oid);
        for ($i = 2; $i < $count; $i++) {
            $val = (int) $oid[$i];
            $chunk = [];

            while ($val > 0) {
                $chunk[] = chr($val & 0x7f);
                $val >>= 7;
            }

            if (empty($chunk)) {
                $chunk[] = "\x00";
            }

            // $chunk is LSB-first; in transmission order (MSB-first) every byte
            // except the LAST (which is $chunk[0], the low 7 bits) carries the
            // 0x80 continuation bit. So set it on every element except index 0.
            $chunkCount = count($chunk);
            for ($j = 1; $j < $chunkCount; $j++) {
                $chunk[$j] = chr(ord($chunk[$j]) | 0x80);
            }

            $bytes = array_merge($bytes, array_reverse($chunk));
        }

        return implode('', $bytes);
    }

    /**
     * Encode tag + length header bytes.
     */
    public function encodeTagLength(int $tag, string $body): string
    {
        $len = strlen($body);
        if ($len < 128) {
            return chr($tag) . chr($len);
        }

        $lenBytes = [];
        while ($len > 0) {
            array_unshift($lenBytes, $len & 0xff);
            $len >>= 8;
        }

        $first = 0x80 | count($lenBytes);
        return chr($tag) . chr($first) . implode('', array_map('chr', $lenBytes));
    }

    // ──── Core decoding helpers ────────────────────────────────────────────

    /**
     * Decode BER length field. Returns [lenBytes, length, totalConsumed].
     */
    public function decodeLength(string $s, int $offset, int &$consumed = 0): int
    {
        if ($offset >= strlen($s)) {
            return 0;
        }

        $first = ord($s[$offset]);

        if (($first & 0x80) === 0) {
            $consumed = 1;
            return $first;
        }

        $numBytes = $first & 0x7f;
        if ($numBytes === 0 || $offset + $numBytes >= strlen($s)) {
            $consumed = 1;
            return 0;
        }

        $length = 0;
        for ($i = 1; $i <= $numBytes; $i++) {
            $length = ($length << 8) | ord($s[$offset + $i]);
        }

        $consumed = 1 + $numBytes;
        return $length;
    }

    /**
     * Return-style version of decodeLength (avoids reference params).
     */
    public function decodeLengthRet(string $s, int $offset): array
    {
        if ($offset >= strlen($s)) {
            return [0, 0];
        }

        $first = ord($s[$offset]);

        if (($first & 0x80) === 0) {
            return [1, $first];
        }

        $numBytes = $first & 0x7f;
        if ($numBytes === 0 || $offset + $numBytes >= strlen($s)) {
            return [1, 0];
        }

        $length = 0;
        for ($i = 1; $i <= $numBytes; $i++) {
            $length = ($length << 8) | ord($s[$offset + $i]);
        }

        return [1 + $numBytes, $length];
    }

    /**
     * Decode BER INTEGER body to PHP int.
     *
     * Single byte 0x80..0xff with bit 7 set = negative two's complement.
     * Multi-byte: high bit set → negative.
     */
    public function decodeIntegerValue(string $body): int
    {
        if ($body === '' || strlen($body) === 0) {
            return 0;
        }

        $value = 0;
        $len = strlen($body);

        for ($i = 0; $i < $len; $i++) {
            $value = ($value << 8) | ord($body[$i]);
        }

        // If MSB (bit 7) of last byte is set → negative two's complement
        if (ord($body[0]) & 0x80) {
            $bitLen = $len * 8;
            $mask = ~0 << $bitLen;
            $value = $mask | $value;
        }

        return $value;
    }

    /**
     * Decode BER OID body to array of integers.
     */
    public function decodeOidValue(string $body): array
    {
        if ($body === '' || strlen($body) === 0) {
            return [];
        }

        $arcs = [];
        $value = 0;
        $len = strlen($body);
        $first = true;

        for ($i = 0; $i < $len; $i++) {
            $byte = ord($body[$i]);

            if ($byte & 0x80) {
                // Continuation byte: accumulate 7 bits
                $value = ($value << 7) | ($byte & 0x7f);
            } else {
                // Final byte: complete this arc
                $value = ($value << 7) | $byte;
                if ($first) {
                    // X.690: the first sub-identifier encodes two arcs as
                    // (arc0 * 40) + arc1. arc0 is 0/1/2; for arc0=2 the second
                    // arc may exceed 39, so derive arc0 by range, not just /40.
                    $arc0 = intdiv($value, 40);
                    if ($arc0 > 2) {
                        $arc0 = 2;
                    }
                    $arcs[] = $arc0;
                    $arcs[] = $value - ($arc0 * 40);
                    $first = false;
                } else {
                    $arcs[] = $value;
                }
                $value = 0;
            }
        }

        return $arcs;
    }

    // ──── Package header (Z39.50 connection layer) ─────────────────────────

    /**
     * Wrap APDU in Z39.50 package header:
     *   uint16  size  (0..65535, payload length + 5)
     *   uint8   id    (0x00)
     *   uint8   flags (0x00)
     *   uint16  reserved (0x0000)
     */
    public function wrapInPackageHeader(string $apdu): string
    {
        // 5-byte package header: 4-byte big-endian total size + 1 reserved byte.
        // (The top two size bytes double as protocol-family / reserved = 0x00 for
        // any APDU under 64 KiB.) unwrap/strip remove exactly these 5 bytes.
        return pack('N', strlen($apdu) + 5) . "\x00" . $apdu;
    }

    /**
     * Strip Z39.50 package header to recover APDU bytes.
     */
    public function unwrapPackageHeader(string $packet): string
    {
        if (strlen($packet) < 5) {
            return '';
        }

        return substr($packet, 5);
    }

    /** Alias for {@see unwrapPackageHeader()} (used across the test suite). */
    public function stripPackageHeader(string $packet): string
    {
        return $this->unwrapPackageHeader($packet);
    }
}