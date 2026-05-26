<?php

/**
 * Heratio locale-aware formatting helpers (Issue #675 Phase 3).
 *
 * Six global helpers wrap ext-intl's IntlDateFormatter / NumberFormatter so
 * Blade templates and PHP code can format dates, numbers, and currency in
 * whatever locale App::getLocale() currently resolves to. Format strings
 * follow ICU's symbolic levels (short / medium / long / full) rather than
 * raw skeleton patterns so the output adapts cleanly across locales:
 *
 *   en + medium date     -> "Aug 2, 2026"
 *   af + medium date     -> "02 Aug. 2026"
 *   en + ZAR currency    -> "ZAR 1,234.50"
 *   af + ZAR currency    -> "R 1 234,50"
 *
 * Graceful degradation: when ext-intl is missing (CI scaffolding, minimal
 * Docker stages, or a misconfigured host) every helper falls back to the
 * raw input as a string so the page still renders.
 *
 * Companion Blade directives (@ahgDate, @ahgDateTime, @ahgTime, @ahgNumber,
 * @ahgCurrency, @ahgPercent) are registered by I18nFormattingServiceProvider.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

if (! function_exists('ahg_intl_available')) {
    /**
     * Returns true when ext-intl is loaded. Cheap repeat-call check used by
     * every helper below so we only have to gate the extension test once.
     */
    function ahg_intl_available(): bool
    {
        static $available = null;
        if ($available === null) {
            $available = extension_loaded('intl');
        }

        return $available;
    }
}

if (! function_exists('ahg_intl_format_const')) {
    /**
     * Map the public symbolic format name to IntlDateFormatter's constants.
     * Anything we don't recognise falls back to MEDIUM (matches AtoM's
     * historic default and Laravel's `->format('d M Y')` look-and-feel).
     */
    function ahg_intl_format_const(string $format): int
    {
        switch (strtolower($format)) {
            case 'none':   return \IntlDateFormatter::NONE;
            case 'short':  return \IntlDateFormatter::SHORT;
            case 'long':   return \IntlDateFormatter::LONG;
            case 'full':   return \IntlDateFormatter::FULL;
            case 'medium':
            default:       return \IntlDateFormatter::MEDIUM;
        }
    }
}

if (! function_exists('ahg_to_datetime')) {
    /**
     * Coerce a value to a \DateTimeInterface, returning null when the input
     * is empty, malformed, or not a recognisable date.
     *
     *   ahg_to_datetime(new DateTime())  -> the same instance
     *   ahg_to_datetime('2026-08-02')    -> DateTimeImmutable(2026-08-02 00:00:00)
     *   ahg_to_datetime(1722594840)      -> DateTimeImmutable(timestamp)
     *   ahg_to_datetime('')              -> null
     *   ahg_to_datetime('not a date')    -> null
     */
    function ahg_to_datetime($value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            try {
                return (new \DateTimeImmutable('@'.(int) $value))
                    ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            } catch (\Throwable $e) {
                return null;
            }
        }
        if (is_string($value)) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}

if (! function_exists('ahg_locale')) {
    /**
     * The active locale to format against. Wraps app()->getLocale() so unit
     * tests can stub it; falls back to config('app.locale') when the app
     * container hasn't been booted (rare - mostly relevant in CLI helpers).
     */
    function ahg_locale(): string
    {
        if (function_exists('app') && app()->bound('translator')) {
            return (string) app()->getLocale();
        }

        return (string) (config('app.locale') ?? 'en');
    }
}

if (! function_exists('ahg_date')) {
    /**
     * Locale-aware date (no time component).
     *
     *   ahg_date('2026-08-02')           // en -> "Aug 2, 2026"
     *   ahg_date('2026-08-02', 'short')  // en -> "8/2/26"
     *   ahg_date('2026-08-02', 'long')   // en -> "August 2, 2026"
     */
    function ahg_date($value, string $format = 'medium'): string
    {
        $dt = ahg_to_datetime($value);
        if ($dt === null) {
            return is_scalar($value) ? (string) $value : '';
        }
        if (! ahg_intl_available()) {
            return $dt->format('Y-m-d');
        }

        $fmt = new \IntlDateFormatter(
            ahg_locale(),
            ahg_intl_format_const($format),
            \IntlDateFormatter::NONE
        );
        $out = $fmt->format($dt);

        return $out === false ? $dt->format('Y-m-d') : $out;
    }
}

if (! function_exists('ahg_datetime')) {
    /**
     * Locale-aware date + time. The same format keyword applies to both the
     * date and the time component (ICU keeps them tied to a single level so
     * "medium" produces a coherent pair across locales).
     *
     *   ahg_datetime('2026-08-02 12:34')          // en -> "Aug 2, 2026, 12:34 PM"
     *   ahg_datetime('2026-08-02 12:34', 'short') // en -> "8/2/26, 12:34 PM"
     */
    function ahg_datetime($value, string $format = 'medium'): string
    {
        $dt = ahg_to_datetime($value);
        if ($dt === null) {
            return is_scalar($value) ? (string) $value : '';
        }
        if (! ahg_intl_available()) {
            return $dt->format('Y-m-d H:i');
        }

        // The combined "date + time" surface prefers a compact SHORT time
        // ("12:34 PM" / "12:34") over the verbose MEDIUM/LONG variants
        // ("12:34:00 PM" / "12:34:00 SAST"): seconds-precision belongs in
        // audit logs, not user-facing timestamps. Callers who want full
        // precision can reach for ahg_time($value, 'medium') explicitly.
        $fmt = new \IntlDateFormatter(
            ahg_locale(),
            ahg_intl_format_const($format),
            \IntlDateFormatter::SHORT
        );
        $out = $fmt->format($dt);

        return $out === false ? $dt->format('Y-m-d H:i') : $out;
    }
}

if (! function_exists('ahg_time')) {
    /**
     * Locale-aware time of day (no date component).
     *
     *   ahg_time('2026-08-02 12:34')          // en -> "12:34 PM"
     *   ahg_time('2026-08-02 12:34', 'medium')// en -> "12:34:00 PM"
     */
    function ahg_time($value, string $format = 'short'): string
    {
        $dt = ahg_to_datetime($value);
        if ($dt === null) {
            return is_scalar($value) ? (string) $value : '';
        }
        if (! ahg_intl_available()) {
            return $dt->format('H:i');
        }

        $fmt = new \IntlDateFormatter(
            ahg_locale(),
            \IntlDateFormatter::NONE,
            ahg_intl_format_const($format)
        );
        $out = $fmt->format($dt);

        return $out === false ? $dt->format('H:i') : $out;
    }
}

if (! function_exists('ahg_to_number')) {
    /**
     * Coerce a value to float|int, returning null when the input is empty
     * or non-numeric. Locale-formatted strings ("1 234,50") aren't supported
     * here on purpose - inputs are expected to be raw numeric values from
     * the database / API layer; formatting is the OUTPUT step.
     */
    function ahg_to_number($value)
    {
        if ($value === null || $value === '' || is_bool($value)) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return strpos($value, '.') === false ? (int) $value : (float) $value;
        }

        return null;
    }
}

if (! function_exists('ahg_number')) {
    /**
     * Locale-aware decimal number.
     *
     *   ahg_number(1234567.89)        // en -> "1,234,567.89"
     *   ahg_number(1234567.89)        // af -> "1 234 567,89"
     *   ahg_number(1234.5, 0)         // en -> "1,235"
     */
    function ahg_number($value, ?int $decimals = null): string
    {
        $n = ahg_to_number($value);
        if ($n === null) {
            return is_scalar($value) ? (string) $value : '';
        }
        if (! ahg_intl_available()) {
            return $decimals === null ? (string) $n : number_format((float) $n, $decimals);
        }

        $fmt = new \NumberFormatter(ahg_locale(), \NumberFormatter::DECIMAL);
        if ($decimals !== null) {
            $fmt->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $fmt->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        }
        $out = $fmt->format($n);

        return $out === false ? (string) $n : $out;
    }
}

if (! function_exists('ahg_currency')) {
    /**
     * Locale-aware currency. The ISO code is picked up from $currency, falling
     * back to config('app.currency', 'ZAR') for instances that haven't set a
     * tenant currency. ICU chooses the right symbol vs. code presentation per
     * locale (en+ZAR -> "ZAR 1,234.50"; af+ZAR -> "R 1 234,50").
     */
    function ahg_currency($value, ?string $currency = null): string
    {
        $n = ahg_to_number($value);
        if ($n === null) {
            return is_scalar($value) ? (string) $value : '';
        }
        $code = $currency ?: (string) config('app.currency', 'ZAR');
        if (! ahg_intl_available()) {
            return $code.' '.number_format((float) $n, 2);
        }

        $fmt = new \NumberFormatter(ahg_locale(), \NumberFormatter::CURRENCY);
        $out = $fmt->formatCurrency((float) $n, $code);

        return $out === false ? $code.' '.number_format((float) $n, 2) : $out;
    }
}

if (! function_exists('ahg_percent')) {
    /**
     * Locale-aware percentage. Pass a fractional value (0.125 -> "12.5%").
     * Multiply at the call site if the source data is already a 0-100 percent
     * (e.g. ahg_percent($row->score / 100)).
     */
    function ahg_percent($value, int $decimals = 1): string
    {
        $n = ahg_to_number($value);
        if ($n === null) {
            return is_scalar($value) ? (string) $value : '';
        }
        if (! ahg_intl_available()) {
            return number_format((float) $n * 100, $decimals).'%';
        }

        $fmt = new \NumberFormatter(ahg_locale(), \NumberFormatter::PERCENT);
        $fmt->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $fmt->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        $out = $fmt->format($n);

        return $out === false ? number_format((float) $n * 100, $decimals).'%' : $out;
    }
}
