<?php

declare(strict_types=1);

namespace AhgInferenceReceipts;

use InvalidArgumentException;

/**
 * RFC 8785 JSON Canonicalization Scheme (JCS) encoder.
 *
 * Produces a deterministic UTF-8 byte sequence for any JSON-serializable
 * PHP value, so that two implementations always agree on the bytes to
 * hash or sign.
 *
 * Rules implemented:
 *   - Object keys sorted lexicographically by UTF-16 code units (RFC 8785 §3.2.3)
 *   - No insignificant whitespace
 *   - Numbers serialized per ECMA-262 7.1.12.1 (shortest round-trip)
 *   - Strings escaped per JCS minimal rules (only " \ and U+0000..U+001F)
 *   - Arrays: element order preserved
 *
 * Limits:
 *   - Resources, closures, and non-stdClass objects are rejected.
 *   - Recursive structures are rejected.
 *   - NaN / +/-Infinity are rejected (per JSON spec).
 */
final class JcsEncoder
{
    public static function encode(mixed $value): string
    {
        return self::serialize($value, 0);
    }

    private static function serialize(mixed $value, int $depth): string
    {
        if ($depth > 512) {
            throw new InvalidArgumentException('JCS encoder: structure too deep (max 512)');
        }

        if ($value === null) {
            return 'null';
        }

        if ($value === true) {
            return 'true';
        }

        if ($value === false) {
            return 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return self::serializeFloat($value);
        }

        if (is_string($value)) {
            return self::serializeString($value);
        }

        if (is_array($value)) {
            return self::serializeArrayOrObject($value, $depth);
        }

        if (is_object($value)) {
            if ($value instanceof \stdClass && get_object_vars($value) === []) {
                return '{}';
            }
            $arr = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            return self::serialize($arr, $depth);
        }

        throw new InvalidArgumentException('JCS encoder: unsupported type ' . get_debug_type($value));
    }

    private static function serializeFloat(float $value): string
    {
        if (is_nan($value) || is_infinite($value)) {
            throw new InvalidArgumentException('JCS encoder: NaN / Infinity not permitted');
        }

        if ($value == 0.0) {
            return '0';
        }

        if ($value == floor($value) && abs($value) < 1e21) {
            $intVal = (int) $value;
            if ((float) $intVal === $value) {
                return (string) $intVal;
            }
        }

        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');
        $str = json_encode($value);
        ini_set('serialize_precision', (string) $previous);

        if ($str === false) {
            throw new InvalidArgumentException('JCS encoder: cannot serialize float');
        }

        if (str_contains($str, 'E') || str_contains($str, 'e')) {
            $str = self::normalizeExponent($str);
        }

        return $str;
    }

    private static function normalizeExponent(string $str): string
    {
        [$mantissa, $exp] = preg_split('/[eE]/', $str, 2);

        $sign = '';
        if ($exp !== '' && ($exp[0] === '+' || $exp[0] === '-')) {
            $sign = $exp[0] === '-' ? '-' : '+';
            $exp = substr($exp, 1);
        } else {
            $sign = '+';
        }
        $exp = ltrim($exp, '0');
        if ($exp === '') {
            $exp = '0';
        }

        $expInt = (int) ($sign === '-' ? "-{$exp}" : $exp);

        if ($expInt >= -6 && $expInt < 21) {
            return rtrim(rtrim(sprintf('%.20F', (float) $str), '0'), '.');
        }

        return $mantissa . 'e' . ($expInt < 0 ? '-' : '+') . abs($expInt);
    }

    private static function serializeString(string $value): string
    {
        $out = '"';
        $len = strlen($value);

        for ($i = 0; $i < $len; $i++) {
            $c = $value[$i];
            $o = ord($c);

            if ($c === '"') {
                $out .= '\\"';
            } elseif ($c === '\\') {
                $out .= '\\\\';
            } elseif ($o === 0x08) {
                $out .= '\\b';
            } elseif ($o === 0x09) {
                $out .= '\\t';
            } elseif ($o === 0x0A) {
                $out .= '\\n';
            } elseif ($o === 0x0C) {
                $out .= '\\f';
            } elseif ($o === 0x0D) {
                $out .= '\\r';
            } elseif ($o < 0x20) {
                $out .= sprintf('\\u%04x', $o);
            } else {
                $out .= $c;
            }
        }

        return $out . '"';
    }

    private static function serializeArrayOrObject(array $value, int $depth): string
    {
        if ($value === []) {
            return '[]';
        }

        if (array_is_list($value)) {
            $parts = [];
            foreach ($value as $item) {
                $parts[] = self::serialize($item, $depth + 1);
            }
            return '[' . implode(',', $parts) . ']';
        }

        $keys = array_keys($value);
        foreach ($keys as $k) {
            if (!is_string($k)) {
                throw new InvalidArgumentException('JCS encoder: object keys must be strings');
            }
        }

        usort($keys, [self::class, 'compareUtf16']);

        $parts = [];
        foreach ($keys as $k) {
            $parts[] = self::serializeString($k) . ':' . self::serialize($value[$k], $depth + 1);
        }

        return '{' . implode(',', $parts) . '}';
    }

    private static function compareUtf16(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }

        $aU = mb_convert_encoding($a, 'UTF-16BE', 'UTF-8');
        $bU = mb_convert_encoding($b, 'UTF-16BE', 'UTF-8');

        if ($aU === false || $bU === false) {
            return strcmp($a, $b);
        }

        return strcmp($aU, $bU);
    }
}
