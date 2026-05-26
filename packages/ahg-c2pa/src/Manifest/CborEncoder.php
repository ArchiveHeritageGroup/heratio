<?php
/**
 * Heratio - minimal deterministic CBOR encoder for C2PA manifests.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Manifest;

use InvalidArgumentException;

/**
 * Tiny deterministic CBOR encoder.
 *
 * Used to produce the binary form of a C2PA manifest when we need to embed
 * it in JUMBF. Pure PHP, no extension, no third-party dep. Supports the
 * subset of CBOR that C2PA manifests actually use:
 *
 *   - unsigned + negative integers
 *   - byte strings (CBOR major 2)
 *   - text strings (UTF-8) (major 3)
 *   - arrays of CBOR items (major 4)
 *   - maps of text-key -> CBOR item (major 5)
 *   - booleans + null (major 7)
 *
 * Determinism rules (CTAP2 canonical, the same profile C2PA's reference
 * implementation uses):
 *   - definite-length encoding for everything
 *   - shortest integer encoding for length headers
 *   - map keys sorted by their canonical CBOR byte sequence
 *
 * Floats are NOT supported: C2PA manifests are integer/string/structural;
 * if a caller smuggles in a float we fail loud rather than silently quantise.
 */
final class CborEncoder
{
    public static function encode(mixed $value): string
    {
        return self::encodeValue($value);
    }

    private static function encodeValue(mixed $value): string
    {
        if ($value === null) {
            return chr(0xF6);
        }
        if ($value === true) {
            return chr(0xF5);
        }
        if ($value === false) {
            return chr(0xF4);
        }
        if (is_int($value)) {
            return self::encodeInt($value);
        }
        if (is_string($value)) {
            return self::encodeTextString($value);
        }
        if (is_array($value)) {
            if ($value === []) {
                return self::encodeHeader(4, 0);
            }
            if (array_is_list($value)) {
                $out = self::encodeHeader(4, count($value));
                foreach ($value as $item) {
                    $out .= self::encodeValue($item);
                }
                return $out;
            }
            return self::encodeMap($value);
        }
        if (is_float($value)) {
            throw new InvalidArgumentException('CborEncoder: floats not supported in C2PA manifest payloads');
        }

        throw new InvalidArgumentException('CborEncoder: unsupported type ' . get_debug_type($value));
    }

    /**
     * @param array<string,mixed> $map
     */
    private static function encodeMap(array $map): string
    {
        $entries = [];
        foreach ($map as $k => $v) {
            if (!is_string($k)) {
                throw new InvalidArgumentException('CborEncoder: map keys must be strings');
            }
            $kBytes = self::encodeTextString($k);
            $entries[] = [$kBytes, self::encodeValue($v)];
        }

        usort($entries, function (array $a, array $b): int {
            $la = strlen($a[0]);
            $lb = strlen($b[0]);
            if ($la !== $lb) {
                return $la <=> $lb;
            }
            return strcmp($a[0], $b[0]);
        });

        $out = self::encodeHeader(5, count($entries));
        foreach ($entries as [$k, $v]) {
            $out .= $k . $v;
        }
        return $out;
    }

    private static function encodeInt(int $n): string
    {
        if ($n >= 0) {
            return self::encodeHeader(0, $n);
        }
        return self::encodeHeader(1, -1 - $n);
    }

    private static function encodeTextString(string $s): string
    {
        return self::encodeHeader(3, strlen($s)) . $s;
    }

    private static function encodeHeader(int $major, int $value): string
    {
        $tag = ($major & 0x07) << 5;
        if ($value < 0) {
            throw new InvalidArgumentException('CborEncoder: header value must be non-negative');
        }
        if ($value < 24) {
            return chr($tag | $value);
        }
        if ($value < 0x100) {
            return chr($tag | 24) . chr($value);
        }
        if ($value < 0x10000) {
            return chr($tag | 25) . pack('n', $value);
        }
        if ($value < 0x100000000) {
            return chr($tag | 26) . pack('N', $value);
        }
        return chr($tag | 27) . pack('J', $value);
    }
}
