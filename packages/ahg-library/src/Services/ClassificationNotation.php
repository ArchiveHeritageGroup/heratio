<?php

namespace AhgLibrary\Services;

/**
 * Pure notation rules for classification schemes (#1475): normalising a keyed
 * number, finding its class portion, walking to broader numbers and deriving a
 * byte-comparable shelf-order key. No database access.
 */
class ClassificationNotation
{
    public const LC = 'LC';
    public const DEWEY = 'Dewey';
    public const UDC = 'UDC';

    private const KEY_LENGTH = 191;

    /** Spellings found in stored data and imports, mapped to the dropdown code. */
    private const ALIASES = [
        'lc' => self::LC, 'lcc' => self::LC,
        'dewey' => self::DEWEY, 'ddc' => self::DEWEY,
        'udc' => self::UDC,
        'local' => 'Local', 'other' => 'Other',
    ];

    public static function canonicalScheme(?string $scheme): ?string
    {
        $scheme = trim((string) $scheme);

        return $scheme === '' ? null : (self::ALIASES[strtolower($scheme)] ?? $scheme);
    }

    public static function normalise(string $scheme, string $number): string
    {
        $number = trim(preg_replace('/\s+/u', ' ', $number));

        return match (self::canonicalScheme($scheme)) {
            self::LC => preg_replace('/^([A-Z]{1,3}) (?=\d)/', '$1', mb_strtoupper($number)),
            self::DEWEY => str_replace(['/', "'"], '', $number),
            default => $number,
        };
    }

    /** The class number without cutters, dates or auxiliaries; null if it is malformed. */
    public static function classPortion(string $scheme, string $number): ?string
    {
        $number = self::normalise($scheme, $number);
        $pattern = match (self::canonicalScheme($scheme)) {
            self::LC => '/^[A-Z]{1,3}(?![A-Z])(?:\d+(?:\.\d+)?)?/',
            self::DEWEY => '/^\d{3}(?:\.\d+)?(?![\d.])/',
            self::UDC => '/^\d+(?:\.\d+)*/',
            default => null,
        };
        if ($pattern === null) {
            return $number === '' ? null : $number;
        }

        return preg_match($pattern, $number, $m) ? $m[0] : null;
    }

    /**
     * Scheme of a call number recorded without one, for filing only: a blank
     * scheme is never validated, since a local shelf mark can look like LC.
     */
    public static function detectScheme(string $number): ?string
    {
        $number = mb_strtoupper(trim($number));
        if (preg_match('/^[A-Z]{1,3} ?\d+(?:\.\d+)?(?=$|\s|\.[A-Z])/', $number)) {
            return self::LC;
        }

        return preg_match('/^\d{3}(?:\.\d+)?(?=$|\s)/', $number) ? self::DEWEY : null;
    }

    /** Broader notations, nearest first, excluding the number itself. */
    public static function broaderCandidates(string $scheme, string $number): array
    {
        $class = self::classPortion($scheme, $number);
        if ($class === null) {
            return [];
        }
        $out = [];
        switch (self::canonicalScheme($scheme)) {
            case self::LC:
                preg_match('/^([A-Z]+)(\d+)?(?:\.(\d+))?/', $class, $m);
                for ($i = strlen($m[3] ?? '') - 1; $i >= 1; $i--) {
                    $out[] = $m[1].$m[2].'.'.substr($m[3], 0, $i);
                }
                if (($m[3] ?? '') !== '') {
                    $out[] = $m[1].$m[2];
                }
                // Integer parts of an LC number are not hierarchical: QA7 is not above QA76.
                if (($m[2] ?? '') !== '') {
                    $out[] = $m[1];
                }
                for ($i = strlen($m[1]) - 1; $i >= 1; $i--) {
                    $out[] = substr($m[1], 0, $i);
                }
                break;
            case self::DEWEY:
                [$int, $frac] = array_pad(explode('.', $class, 2), 2, '');
                for ($i = strlen($frac) - 1; $i >= 1; $i--) {
                    $out[] = $int.'.'.substr($frac, 0, $i);
                }
                if ($frac !== '') {
                    $out[] = $int;
                }
                // 655 -> 650 -> 600 (the hundreds and tens carry the hierarchy).
                $out[] = substr($int, 0, 2).'0';
                $out[] = $int[0].'00';
                break;
            case self::UDC:
                for ($i = strlen($class) - 1; $i >= 1; $i--) {
                    $out[] = rtrim(substr($class, 0, $i), '.');
                }
                break;
            default:
                $parts = preg_split('/(?=[.\-\/ ])/', $class);
                for ($i = count($parts) - 1; $i >= 1; $i--) {
                    $out[] = implode('', array_slice($parts, 0, $i));
                }
        }
        $self = self::normalise($scheme, $number);

        return array_values(array_filter(array_unique($out), fn ($n) => $n !== '' && $n !== $self && $n !== $class));
    }

    public static function sortKey(string $scheme, string $number): string
    {
        $number = self::normalise($scheme, $number);
        $key = match (self::canonicalScheme($scheme)) {
            self::LC => self::lcKey($number),
            self::DEWEY => self::deweyKey($number),
            self::UDC => self::udcKey($number),
            default => self::naturalKey($number),
        };

        return mb_strcut($key, 0, self::KEY_LENGTH, 'UTF-8'); // Never splits a multibyte character.
    }

    private static function lcKey(string $n): string
    {
        if (! preg_match('/^([A-Z]{1,3})(\d+)?(?:\.(\d+))?(.*)$/s', $n, $m)) {
            return self::naturalKey($n);
        }
        $key = str_pad($m[1], 3);
        if (($m[2] ?? '') !== '') {
            $key .= str_pad($m[2], 6, '0', STR_PAD_LEFT);
        }
        if (($m[3] ?? '') !== '') {
            $key .= '.'.$m[3];
        }

        return $key.self::tailKey($m[4] ?? '');
    }

    private static function deweyKey(string $n): string
    {
        if (! preg_match('/^(\d{1,3})(?:\.(\d+))?(.*)$/s', $n, $m)) {
            return self::naturalKey($n);
        }
        $key = str_pad($m[1], 3, '0', STR_PAD_LEFT);
        if (($m[2] ?? '') !== '') {
            $key .= '.'.$m[2];
        }

        return $key.self::tailKey($m[3] ?? '');
    }

    /**
     * UDC filing order: + / (simple number) : :: [ = (0 ( (= " * A-Z - .0 '
     * Each symbol maps to a byte below '0', so all auxiliaries of 5 file before 51.
     */
    private static function udcKey(string $n): string
    {
        $key = '';
        $len = strlen($n);
        for ($i = 0; $i < $len; $i++) {
            $c = $n[$i];
            $next = $n[$i + 1] ?? '';
            if (ctype_digit($c)) {
                $key .= $c;
            } elseif ($c === '+') {
                $key .= ' ';
            } elseif ($c === '/') {
                $key .= '!';
            } elseif ($c === ':') {
                if ($next === ':') {
                    $key .= '$';
                    $i++;
                } else {
                    $key .= '#';
                }
            } elseif ($c === '[') {
                $key .= '%';
            } elseif ($c === '=') {
                $key .= '&';
            } elseif ($c === '(') {
                $key .= $next === '0' ? "'" : ($next === '=' ? ')' : '(');
                if ($next === '=') {
                    $i++;
                }
            } elseif ($c === '"') {
                $key .= '*';
            } elseif ($c === '*') {
                $key .= '+';
            } elseif (ctype_alpha($c)) {
                $key .= ','.strtoupper($c);
            } elseif ($c === '-') {
                $key .= '-';
            } elseif ($c === '.') {
                // A point is only a reading aid, except before 0 (special auxiliary).
                if ($next === '0') {
                    $key .= '.';
                }
            } elseif ($c === "'") {
                $key .= '/';
            }
            // Closing brackets and anything else carry no filing value.
        }

        return $key.'"'; // End of number: after + and /, before : and every digit.
    }

    /** Cutters compare as decimals (J38 before J4); plain numbers (dates, volumes) numerically. */
    private static function tailKey(string $tail): string
    {
        preg_match_all('/[A-Z]+\d+|[A-Z]+|\d+/', strtoupper($tail), $m);
        $key = '';
        foreach ($m[0] as $token) {
            $key .= ' '.(ctype_digit($token) ? str_pad($token, 6, '0', STR_PAD_LEFT) : $token);
        }

        return $key;
    }

    private static function naturalKey(string $n): string
    {
        $raw = $n;
        if (class_exists(\Normalizer::class)) {
            // Latin accents fold (E-acute files with E); marks in other scripts carry meaning and stay.
            $n = preg_replace('/(?<=\p{Latin})\p{Mn}+/u', '', \Normalizer::normalize($n, \Normalizer::FORM_KD) ?: $n);
            $n = \Normalizer::normalize($n, \Normalizer::FORM_KC) ?: $n;
        }
        $n = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', mb_strtoupper($n, 'UTF-8'));
        if (class_exists(\IntlChar::class)) { // Any script's decimal digits count as numbers.
            $n = preg_replace_callback('/\p{Nd}/u', fn ($m) => (string) \IntlChar::charDigitValue($m[0]), $n);
        }
        $key = trim(preg_replace_callback('/\d+/', fn ($m) => str_pad($m[0], 8, '0', STR_PAD_LEFT), $n));

        return $key !== '' ? $key : $raw; // A notation of symbols alone still needs a filing value.
    }
}
