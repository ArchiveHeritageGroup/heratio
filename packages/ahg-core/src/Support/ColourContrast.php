<?php

/**
 * ColourContrast - WCAG 2.x contrast for configurable theme colours
 * (heratio#1532).
 *
 * The theme lets an administrator choose the background and text colour of
 * the header, description bar, footer, card headers, buttons and sidebar.
 * Nothing checked the pairs, and white text on the default description-bar
 * green (#619e8f) measured 3.09:1 against the 4.5:1 that WCAG 2.2 AA asks of
 * normal text. ensure() keeps the chosen text colour and shades the background
 * (darker under light text, lighter under dark text) just until the pair
 * passes, so the hue the administrator picked survives.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Support;

final class ColourContrast
{
    /** WCAG 2.x minimum for normal-size text at level AA. */
    public const AA = 4.5;

    /** #rgb or #rrggbb as [r, g, b] 0-255, or null for anything else (var(), names). */
    public static function rgb(string $hex): ?array
    {
        $h = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-f]{3}$/i', $h)) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }
        if (! preg_match('/^[0-9a-f]{6}$/i', $h)) {
            return null;
        }

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    /** Relative luminance, WCAG 2.x definition. */
    public static function luminance(array $rgb): float
    {
        $c = array_map(function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /** Contrast ratio of two hex colours, or null when either is not a hex colour. */
    public static function ratio(string $a, string $b): ?float
    {
        $x = self::rgb($a);
        $y = self::rgb($b);
        if ($x === null || $y === null) {
            return null;
        }
        [$hi, $lo] = [max(self::luminance($x), self::luminance($y)), min(self::luminance($x), self::luminance($y))];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    /**
     * $background shaded just enough that $text on it reaches $min, as #rrggbb.
     * Returned unchanged when it already passes, or when either value is not a
     * plain hex colour.
     */
    public static function ensure(string $background, string $text, float $min = self::AA): string
    {
        $bg = self::rgb($background);
        $fg = self::rgb($text);
        if ($bg === null || $fg === null || self::ratio($background, $text) >= $min) {
            return $background;
        }
        // Light text needs a darker ground, dark text a lighter one.
        $towards = self::luminance($fg) > self::luminance($bg) ? 0 : 255;
        for ($step = 1; $step <= 100; $step++) {
            $mix = array_map(fn ($v) => (int) round($v + ($towards - $v) * $step / 100), $bg);
            $hex = sprintf('#%02x%02x%02x', ...$mix);
            if (self::ratio($hex, $text) >= $min) {
                return $hex;
            }
        }

        return $towards === 0 ? '#000000' : '#ffffff';
    }
}
