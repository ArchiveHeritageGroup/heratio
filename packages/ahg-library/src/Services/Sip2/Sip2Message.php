<?php

/**
 * Sip2Message - parses and frames SIP2 protocol messages (#1475).
 *
 * Copyright (C) 2026 Johan Pieterse
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Services\Sip2;

final class Sip2Message
{
    public const MAX_LENGTH = 4096;

    /** Fixed-field length after the two-character command code. */
    private const FIXED = [
        '93' => 2, '99' => 8, '97' => 0, '23' => 21, '63' => 31,
        '11' => 38, '09' => 37, '37' => 25, '01' => 19,
    ];

    private function __construct(
        public readonly string $code,
        public readonly string $fixed,
        public readonly array $fields,
        public readonly ?int $sequence,
    ) {
    }

    /** Null when the message is malformed or its checksum is wrong. */
    public static function parse(string $raw): ?self
    {
        $raw = rtrim($raw, "\r\n");
        if ($raw === '' || strlen($raw) > self::MAX_LENGTH || ! mb_check_encoding($raw, 'UTF-8')) {
            return null;
        }

        $sequence = null;
        if (preg_match('/AY(\d)AZ([0-9A-Fa-f]{4})$/', $raw, $m, PREG_OFFSET_CAPTURE)) {
            $signed = substr($raw, 0, $m[2][1]);
            if (strcasecmp(self::checksum($signed), $m[2][0]) !== 0) {
                return null;
            }
            $sequence = (int) $m[1][0];
            $raw = substr($raw, 0, $m[0][1]);
        }

        $code = substr($raw, 0, 2);
        if (! isset(self::FIXED[$code]) || strlen($raw) < 2 + self::FIXED[$code]) {
            return null;
        }

        $fields = [];
        foreach (explode('|', substr($raw, 2 + self::FIXED[$code])) as $part) {
            if (strlen($part) >= 2 && ! isset($fields[substr($part, 0, 2)])) {
                $fields[substr($part, 0, 2)] = substr($part, 2);
            }
        }

        return new self($code, substr($raw, 2, self::FIXED[$code]), $fields, $sequence);
    }

    /** Block patron has no language field; give it a blank one for the shared patron reply. */
    public function withLanguage(): self
    {
        return new self($this->code, '000', $this->fields, $this->sequence);
    }

    public function field(string $id): ?string
    {
        return $this->fields[$id] ?? null;
    }

    public static function checksum(string $text): string
    {
        return sprintf('%04X', (-array_sum(unpack('C*', $text))) & 0xFFFF);
    }

    /** A variable field, with the protocol's delimiters removed from the value. */
    public static function f(string $id, ?string $value): string
    {
        return $id . str_replace(['|', "\r", "\n"], ' ', (string) $value) . '|';
    }

    public static function frame(string $body, ?int $sequence): string
    {
        if ($sequence !== null) {
            $body .= 'AY' . $sequence . 'AZ';
            $body .= self::checksum($body);
        }

        return $body . "\r";
    }

    public static function timestamp(): string
    {
        return date('Ymd') . '    ' . date('His');
    }
}
