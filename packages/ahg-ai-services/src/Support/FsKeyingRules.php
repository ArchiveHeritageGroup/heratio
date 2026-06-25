<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgAiServices\Support;

/**
 * Deterministic post-processing of extracted FamilySearch fields to the
 * project's keying rules (FS-Scotland). Pure functions - no model, no I/O - so
 * they are fully unit-testable without the gateway. The `?`/`*` unreadable
 * markers and crossed-out handling are produced at extraction time and passed
 * through untouched here.
 */
final class FsKeyingRules
{
    /** System fields that hold a 3-letter month. */
    private const MONTH_FIELDS = ['EVENT_MONTH_ORIG', 'PR_BIR_MONTH_ORIG', 'PR_DEA_MONTH_ORIG'];
    private const DAY_FIELDS   = ['EVENT_DAY_ORIG', 'PR_BIR_DAY_ORIG', 'PR_DEA_DAY_ORIG'];
    private const YEAR_FIELDS  = ['EVENT_YEAR_ORIG', 'PR_BIR_YEAR_ORIG', 'PR_DEA_YEAR_ORIG'];
    private const MARITAL_FIELDS = ['PR_MARITAL_STATUS_ORIG', 'SP_MARITAL_STATUS_ORIG'];

    private const TITLES = ['mr', 'mrs', 'miss', 'ms', 'dr', 'rev', 'revd', 'sir', 'lady', 'master', 'mstr'];

    /** Ditto tokens that mean "inherit the previous record's value". */
    private const DITTO = ['do', 'do.', 'ditto', '"', "''", '”', '“', '″', '〃', '＂', '同'];

    /** Month -> 3-letter abbrev. Accepts names, abbrevs, or 1-12. '' if unknown. */
    public static function month(string $raw): string
    {
        $v = strtolower(trim($raw, " \t\n\r\0\x0B.,"));
        if ($v === '') {
            return '';
        }
        if (ctype_digit($v)) {
            $n = (int) $v;

            return FsScotlandProfile::MONTHS[$n] ?? $raw;
        }
        $map = [
            'jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar', 'apr' => 'Apr',
            'may' => 'May', 'jun' => 'Jun', 'jul' => 'Jul', 'aug' => 'Aug',
            'sep' => 'Sep', 'sept' => 'Sep', 'oct' => 'Oct', 'nov' => 'Nov', 'dec' => 'Dec',
        ];

        return $map[substr($v, 0, 4)] ?? $map[substr($v, 0, 3)] ?? $raw;
    }

    /** Day: drop the leading zero on 01-09; keep as-written otherwise. */
    public static function day(string $raw): string
    {
        $v = trim($raw);
        if (preg_match('/^0([1-9])$/', $v, $m)) {
            return $m[1];
        }

        return $v;
    }

    /** Year: keep a 4-digit year; otherwise return as written (no guessing here). */
    public static function year(string $raw): string
    {
        return trim($raw);
    }

    /** Marital status -> S/M/W/D. Passes single letters through; '' if unknown. */
    public static function marital(string $raw): string
    {
        $v = strtolower(trim($raw, " \t\n\r\0\x0B.,"));
        if ($v === '') {
            return '';
        }
        if (in_array(strtoupper($v), ['S', 'M', 'W', 'D'], true)) {
            return strtoupper($v);
        }
        foreach (['single' => 'S', 'unmarried' => 'S', 'bachelor' => 'S', 'spinster' => 'S',
                  'married' => 'M', 'widow' => 'W', 'widower' => 'W', 'widowed' => 'W',
                  'divorced' => 'D'] as $word => $code) {
            if (str_starts_with($v, $word)) {
                return $code;
            }
        }

        return '';
    }

    /** Sex -> M/F from an explicit value (never inferred from a given name). */
    public static function sex(string $raw): string
    {
        $v = strtolower(trim($raw));
        if ($v === '') {
            return '';
        }
        if (in_array($v, ['m', 'male', 'boy', 'son'], true)) {
            return 'M';
        }
        if (in_array($v, ['f', 'female', 'girl', 'daughter'], true)) {
            return 'F';
        }

        return '';
    }

    /** Name cleanup: strip leading titles + stray punctuation (keep - and '). */
    public static function name(string $raw): string
    {
        $v = trim($raw);
        if ($v === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $v) ?: [];
        while ($parts && in_array(strtolower(rtrim($parts[0], '.')), self::TITLES, true)) {
            array_shift($parts);
        }
        $v = implode(' ', $parts);

        // Strip punctuation except letters, marks/diacritics, spaces, hyphen, apostrophe.
        return trim(preg_replace("/[^\\p{L}\\p{M}\\s'\\-]/u", '', $v) ?? $v);
    }

    public static function isDitto(string $raw): bool
    {
        $v = strtolower(trim($raw));

        return $v !== '' && in_array($v, self::DITTO, true);
    }

    /**
     * Normalise one extracted record (system-name => value) per the keying
     * rules for its event type.
     *
     * @param array<string,string> $record
     * @return array<string,string>
     */
    public static function normalizeRecord(array $record): array
    {
        foreach ($record as $field => $value) {
            $value = (string) $value;
            if (in_array($field, self::MONTH_FIELDS, true)) {
                $record[$field] = self::month($value);
            } elseif (in_array($field, self::DAY_FIELDS, true)) {
                $record[$field] = self::day($value);
            } elseif (in_array($field, self::YEAR_FIELDS, true)) {
                $record[$field] = self::year($value);
            } elseif (in_array($field, self::MARITAL_FIELDS, true)) {
                $record[$field] = self::marital($value);
            } elseif ($field === 'PR_SEX_CODE_ORIG') {
                $record[$field] = self::sex($value);
            } elseif (str_contains($field, '_NAME_') && str_ends_with($field, '_ORIG')) {
                $record[$field] = self::name($value);
            }
        }

        return $record;
    }

    /**
     * Ditto inheritance: walk records in order; any field whose value is a
     * ditto mark inherits the previous record's value for that field. Run
     * BEFORE normalizeRecord so the inherited raw value is normalised too.
     *
     * @param array<int,array<string,string>> $records
     * @return array<int,array<string,string>>
     */
    public static function applyDitto(array $records): array
    {
        $prev = [];
        foreach ($records as $i => $record) {
            foreach ($record as $field => $value) {
                if (self::isDitto((string) $value) && isset($prev[$field]) && $prev[$field] !== '') {
                    $record[$field] = $prev[$field];
                }
            }
            $records[$i] = $record;
            foreach ($record as $field => $value) {
                if ((string) $value !== '') {
                    $prev[$field] = (string) $value;
                }
            }
        }

        return $records;
    }
}
