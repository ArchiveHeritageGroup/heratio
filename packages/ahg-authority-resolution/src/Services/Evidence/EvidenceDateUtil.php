<?php

/**
 * EvidenceDateUtil - Service for Heratio
 *
 * Shared date-parsing helpers for the Task-4 evaluators. Two responsibilities:
 *
 *   parseYearSpan($freeText)
 *     Pull 4-digit years (1000-2099) out of a free-text dates_of_existence
 *     string and return ['start' => min, 'end' => max], or null on no match.
 *     Treats a single year as both start and end. Strings like "circa 1850"
 *     or "1820-1900" both work.
 *
 *   collectYearsFromNearbyDates($json)
 *     Decode the ahg_mention_context.nearby_dates JSON and pull every
 *     parseable 4-digit year. Tolerates a raw string, a decoded array,
 *     or null. Returns deduplicated sorted list<int>.
 *
 * Kept in its own file because TemporalEvaluator + ConflictEvaluator
 * both depend on it - DRY.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgAuthorityResolution\Services\Evidence;

class EvidenceDateUtil
{
    /**
     * @return array{start:int,end:int}|null
     */
    public static function parseYearSpan(string $freeText): ?array
    {
        if (! preg_match_all('/\b(1[0-9]{3}|20[0-9]{2})\b/', $freeText, $matches)) {
            return null;
        }
        $years = array_map('intval', $matches[1]);
        if (empty($years)) {
            return null;
        }

        return [
            'start' => min($years),
            'end' => max($years),
        ];
    }

    /**
     * @param  mixed  $nearbyDatesJson  raw json string OR decoded array OR null
     * @return list<int> sorted unique 4-digit years
     */
    public static function collectYearsFromNearbyDates($nearbyDatesJson): array
    {
        $rows = self::decodeJsonish($nearbyDatesJson);
        if (! is_array($rows)) {
            return [];
        }

        $years = [];
        foreach ($rows as $row) {
            $value = '';
            if (is_array($row)) {
                $value = (string) ($row['value'] ?? '');
            } elseif (is_string($row)) {
                $value = $row;
            }
            if ($value === '') {
                continue;
            }
            if (preg_match_all('/\b(1[0-9]{3}|20[0-9]{2})\b/', $value, $m)) {
                foreach ($m[1] as $y) {
                    $years[] = (int) $y;
                }
            }
        }

        $years = array_values(array_unique($years));
        sort($years);

        return $years;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    public static function decodeJsonish($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return $decoded === null && json_last_error() !== JSON_ERROR_NONE ? null : $decoded;
        }

        return null;
    }
}
