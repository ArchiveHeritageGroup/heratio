<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

/**
 * Enumeration / chronology parser for serials (heratio#1092).
 *
 * Parses ANSI/NISO Z39.71-style holdings enumeration strings such as
 * "Vol. 1-no.12 (Jan-Dec 2025)" into structured volume / issue / chronology
 * parts, and increments them intelligently for prediction.
 *
 * This is deliberately dependency-free (no DB, no framework) so it can be unit
 * tested in isolation and reused by both LibrarySerialService prediction and
 * any future MARC 853/863 caption work.
 *
 * Recognised shapes (case-insensitive, flexible whitespace/punctuation):
 *   "Vol. 1-no.12 (Jan-Dec 2025)"
 *   "Vol. 12, No. 3 (Mar 2025)"
 *   "v.5 no.2 (2024)"
 *   "Vol 7 (2023)"
 *   "No. 145 (Spring 2025)"
 *   "2025, no. 4"
 */
class LibrarySerialEnumerationParser
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private const MONTH_LABELS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    private const SEASONS = ['spring', 'summer', 'autumn', 'fall', 'winter'];

    /**
     * Parse an enumeration string into structured parts.
     *
     * @return array{
     *     volume: ?string,
     *     volume_end: ?string,
     *     issue: ?string,
     *     issue_end: ?string,
     *     month_start: ?int,
     *     month_end: ?int,
     *     season: ?string,
     *     year: ?int,
     *     raw: string
     * }
     */
    public function parse(string $enumeration): array
    {
        $raw = trim($enumeration);

        $result = [
            'volume'      => null,
            'volume_end'  => null,
            'issue'       => null,
            'issue_end'   => null,
            'month_start' => null,
            'month_end'   => null,
            'season'      => null,
            'year'        => null,
            'raw'         => $raw,
        ];

        // ── Volume: "vol. 1", "v.5", "volume 7", optional range "vol. 1-3" ──
        if (preg_match('/\b(?:vol(?:ume)?\.?|v\.?)\s*(\d+)\s*(?:-\s*(\d+))?/i', $raw, $m)) {
            $result['volume'] = $m[1];
            $result['volume_end'] = isset($m[2]) && $m[2] !== '' ? $m[2] : null;
        }

        // ── Issue / number: "no.12", "n.3", "issue 4", optional range ───────
        if (preg_match('/\b(?:no\.?|nr\.?|n\.?|iss(?:ue)?\.?)\s*(\d+)\s*(?:-\s*(\d+))?/i', $raw, $m)) {
            $result['issue'] = $m[1];
            $result['issue_end'] = isset($m[2]) && $m[2] !== '' ? $m[2] : null;
        }

        // ── Year: any 4-digit 19xx/20xx ─────────────────────────────────────
        if (preg_match('/\b((?:19|20)\d{2})\b/', $raw, $m)) {
            $result['year'] = (int) $m[1];
        }

        // ── Season ──────────────────────────────────────────────────────────
        foreach (self::SEASONS as $season) {
            if (preg_match('/\b' . $season . '\b/i', $raw)) {
                $result['season'] = $season === 'fall' ? 'autumn' : $season;
                break;
            }
        }

        // ── Months / month range: "jan-dec", "mar" ──────────────────────────
        if (preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\w*\.?\s*(?:-\s*(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\w*\.?)?/i', $raw, $m)) {
            $result['month_start'] = self::MONTHS[strtolower($m[1])] ?? null;
            if (!empty($m[2])) {
                $result['month_end'] = self::MONTHS[strtolower($m[2])] ?? null;
            }
        }

        return $result;
    }

    /**
     * Given a parsed enumeration and a serial frequency, produce the next
     * volume / issue numbers and a human-readable chronology label.
     *
     * Rules:
     *   - Issue number increments by 1 when numeric.
     *   - When the incremented issue would exceed the issues-per-year for the
     *     frequency, the issue resets to 1 and the volume increments (the
     *     classic volume-rollover at the start of a new volume year).
     *   - Volume-only serials (no issue) increment the volume.
     *
     * @param array<string,mixed> $parsed  Output of parse()
     * @return array{volume: ?string, issue_number: ?string, chronology: ?string}
     */
    public function increment(array $parsed, string $frequency): array
    {
        $issuesPerYear = $this->issuesPerYear($frequency);

        $volume = $parsed['volume'];
        $issue  = $parsed['issue'];

        $nextVolume = $volume;
        $nextIssue  = $issue;

        if ($issue !== null && is_numeric($issue)) {
            $candidate = (int) $issue + 1;
            if ($issuesPerYear > 0 && $candidate > $issuesPerYear) {
                // Volume rollover: reset issue, bump volume.
                $nextIssue = '1';
                if ($volume !== null && is_numeric($volume)) {
                    $nextVolume = (string) ((int) $volume + 1);
                }
            } else {
                $nextIssue = (string) $candidate;
            }
        } elseif ($volume !== null && is_numeric($volume) && $issue === null) {
            // Volume-only enumeration (e.g. annuals).
            $nextVolume = (string) ((int) $volume + 1);
        }

        return [
            'volume'       => $nextVolume,
            'issue_number' => $nextIssue,
            'chronology'   => $this->chronologyLabel($parsed),
        ];
    }

    /**
     * Build a compact chronology label from the parsed chronology parts, e.g.
     * "Jan-Dec 2025", "Spring 2025", "2024". Returns null when nothing is known.
     */
    public function chronologyLabel(array $parsed): ?string
    {
        $year = $parsed['year'] ?? null;

        if (!empty($parsed['season'])) {
            $season = ucfirst((string) $parsed['season']);
            return $year ? "{$season} {$year}" : $season;
        }

        if (!empty($parsed['month_start'])) {
            $start = self::MONTH_LABELS[$parsed['month_start']] ?? '';
            if (!empty($parsed['month_end'])) {
                $end = self::MONTH_LABELS[$parsed['month_end']] ?? '';
                $label = "{$start}-{$end}";
            } else {
                $label = $start;
            }
            return $year ? "{$label} {$year}" : $label;
        }

        return $year ? (string) $year : null;
    }

    /**
     * Format a structured enumeration back into a display string, e.g.
     * "Vol. 5, No. 3 (Mar 2025)".
     *
     * @param array{volume:?string,issue_number:?string,chronology:?string} $parts
     */
    public function format(array $parts): string
    {
        $segments = [];
        if (!empty($parts['volume'])) {
            $segments[] = 'Vol. ' . $parts['volume'];
        }
        if (!empty($parts['issue_number'])) {
            $segments[] = 'No. ' . $parts['issue_number'];
        }
        $main = implode(', ', $segments);

        if (!empty($parts['chronology'])) {
            $main = $main !== '' ? "{$main} ({$parts['chronology']})" : (string) $parts['chronology'];
        }

        return $main;
    }

    /**
     * Expected issues per publication year for a frequency code. Mirrors the
     * mapping in LibrarySerialService so volume rollover lines up with the
     * date-math prediction.
     */
    public function issuesPerYear(string $frequency): int
    {
        return match (strtolower($frequency)) {
            'weekly'     => 52,
            'biweekly'   => 26,
            'monthly'    => 12,
            'bimonthly'  => 6,
            'quarterly'  => 4,
            'semiannual' => 2,
            'annual'     => 1,
            default      => 0, // irregular / unknown: no rollover
        };
    }
}
