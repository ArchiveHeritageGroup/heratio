<?php

/**
 * Code128 - encodes a value as a Code 128 symbol and draws it as SVG.
 *
 * Uses code set C for all-digit values of even length (half the width) and
 * code set B otherwise.
 * ponytail: no mid-symbol set switching, so mixed values are slightly wider
 * than optimal; add it if real barcodes stop fitting small labels.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Support;

class Code128
{
    public const QUIET_MODULES = 10;

    private const START_B = 104;

    private const START_C = 105;

    private const STOP = 106;

    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    public static function encodable(string $value): bool
    {
        return $value !== '' && preg_match('/^[\x20-\x7E]+$/', $value) === 1;
    }

    /** Symbol values: start, data, check digit, stop. */
    public static function symbols(string $value): array
    {
        if (! self::encodable($value)) {
            throw new \InvalidArgumentException('Value cannot be encoded as Code 128');
        }

        if (strlen($value) % 2 === 0 && ctype_digit($value)) {
            $codes = [self::START_C];
            foreach (str_split($value, 2) as $pair) {
                $codes[] = (int) $pair;
            }
        } else {
            $codes = [self::START_B];
            foreach (str_split($value) as $char) {
                $codes[] = ord($char) - 32;
            }
        }

        $sum = $codes[0];
        foreach (array_slice($codes, 1) as $i => $code) {
            $sum += ($i + 1) * $code;
        }
        $codes[] = $sum % 103;
        $codes[] = self::STOP;

        return $codes;
    }

    /** Symbol width in modules, including both quiet zones. */
    public static function modules(string $value): int
    {
        return (count(self::symbols($value)) - 1) * 11 + 13 + 2 * self::QUIET_MODULES;
    }

    /** SVG sized in millimetres, one viewBox unit per module. */
    public static function svg(string $value, float $moduleMm, float $heightMm): string
    {
        $modules = self::modules($value);
        $x = self::QUIET_MODULES;
        $bars = '';
        foreach (self::symbols($value) as $symbol) {
            foreach (str_split(self::PATTERNS[$symbol]) as $i => $width) {
                if ($i % 2 === 0) {
                    $bars .= '<rect x="'.$x.'" y="0" width="'.$width.'" height="10"/>';
                }
                $x += (int) $width;
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%.3Fmm" height="%.3Fmm" viewBox="0 0 %d 10" preserveAspectRatio="none" shape-rendering="crispEdges" fill="#000" role="img" aria-label="%s">%s</svg>',
            $modules * $moduleMm, $heightMm, $modules, e($value), $bars
        );
    }
}
