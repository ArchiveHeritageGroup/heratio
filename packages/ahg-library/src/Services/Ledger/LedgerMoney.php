<?php

namespace AhgLibrary\Services\Ledger;

/**
 * Exact money and rate arithmetic on integers. Floats never touch a ledger
 * amount. Rates are held in millionths of the whole (15.0000% = 150000).
 */
final class LedgerMoney
{
    public const RATE_SCALE = 1000000;

    /** Largest single line, keeps net * rate inside a 64-bit integer. */
    public const MAX_LINE_CENTS = 99999999999;

    public static function toCents(string|int|null $value): int
    {
        $s = trim((string) $value);
        if (!preg_match('/^(\d{1,13})(?:\.(\d{1,2}))?$/', $s, $m)) {
            throw new LedgerException("Invalid amount: {$s}");
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return $sign . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function rateToInt(string|int|null $percent): int
    {
        $s = trim((string) $percent);
        if (!preg_match('/^(\d{1,3})(?:\.(\d{1,4}))?$/', $s, $m)) {
            throw new LedgerException("Invalid tax rate: {$s}");
        }
        $rate = (int) $m[1] * 10000 + (int) str_pad($m[2] ?? '0', 4, '0');
        if ($rate > self::RATE_SCALE) {
            throw new LedgerException('A tax rate cannot exceed 100%.');
        }

        return $rate;
    }

    public static function rateToString(int $rate): string
    {
        return intdiv($rate, 10000) . '.' . str_pad((string) ($rate % 10000), 4, '0', STR_PAD_LEFT);
    }

    /** Tax on a net amount, rounded half up. */
    public static function tax(int $netCents, int $rate): int
    {
        return intdiv($netCents * $rate + intdiv(self::RATE_SCALE, 2), self::RATE_SCALE);
    }
}
