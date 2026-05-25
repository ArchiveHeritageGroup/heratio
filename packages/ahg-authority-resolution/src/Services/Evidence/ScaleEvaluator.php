<?php

/**
 * ScaleEvaluator - Service for Heratio
 *
 * Task-4 lexical-scale evidence for PLACE candidates. Scans the mention's
 * surrounding_text_before + surrounding_text_after for scale tokens (city,
 * town, village, province, region, district, country, kingdom, empire,
 * county) and compares against a heuristic about the candidate term's
 * name.
 *
 * Heuristic on the candidate side is intentionally simple: we look for a
 * trailing token in the candidate's display name (e.g. "Kyoto Bookshop"
 * -> "bookshop" maps to a non-scale category, "Baltic Port" -> "port"
 * maps to a port category) and also for the surrounding-text scale token
 * appearing inside the candidate's name (e.g. mention text mentions "city"
 * and candidate name ends in "City").
 *
 * Signals:
 *   match    - scale token in surrounding text aligns with candidate name
 *              suffix (same scale family)
 *   conflict - both sides have explicit but incompatible scale tokens
 *              (e.g. text says "kingdom" and candidate ends in "Bookshop")
 *   silent   - mention has a scale token AND candidate has a scale-suffix,
 *              but neither aligns nor explicitly conflicts (rare; e.g. text
 *              says "district" and candidate name has no scale suffix)
 *   absent   - mention surrounding text has no scale token at all
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

class ScaleEvaluator implements EvaluatorInterface
{
    private const PLACE_TYPES = ['GPE', 'PLACE', 'LOC'];

    /**
     * Token -> scale family. Family aligns when both sides map to the same key.
     */
    private const SCALE_FAMILY = [
        'city' => 'settlement',
        'town' => 'settlement',
        'village' => 'settlement',
        'hamlet' => 'settlement',
        'borough' => 'settlement',
        'suburb' => 'settlement',
        'port' => 'settlement',
        'harbour' => 'settlement',
        'harbor' => 'settlement',

        'province' => 'subregion',
        'region' => 'subregion',
        'district' => 'subregion',
        'county' => 'subregion',
        'state' => 'subregion',
        'shire' => 'subregion',
        'prefecture' => 'subregion',

        'country' => 'nation',
        'kingdom' => 'nation',
        'empire' => 'nation',
        'nation' => 'nation',
        'republic' => 'nation',
        'sultanate' => 'nation',

        'continent' => 'continent',

        // non-place but commonly inside term names; map to "facility" so we can flag conflict
        'bookshop' => 'facility',
        'shop' => 'facility',
        'school' => 'facility',
        'church' => 'facility',
        'cathedral' => 'facility',
        'university' => 'facility',
        'library' => 'facility',
        'museum' => 'facility',
        'hospital' => 'facility',
    ];

    public function dimension(): string
    {
        return 'scale';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PLACE_TYPES, true);
    }

    public function evaluate(object $mention, object $context, object $candidate): array
    {
        $surrounding = ((string) ($context->surrounding_text_before ?? '')).' '
                     .((string) ($context->surrounding_text_after ?? ''));
        $textTokens = $this->detectTokens(mb_strtolower($surrounding));

        $candName = (string) ($candidate->candidate_display_name ?? '');
        $candTokens = $this->detectTokens(mb_strtolower($candName));

        if (empty($textTokens)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_scale_tokens_in_surrounding_text',
                'candidate_tokens' => $candTokens,
            ]);
        }
        if (empty($candTokens)) {
            return EvidenceSignal::make(EvidenceSignal::SILENT, [
                'reason' => 'candidate_name_has_no_scale_suffix',
                'text_tokens' => $textTokens,
            ]);
        }

        $textFamilies = array_unique(array_map(fn ($t) => self::SCALE_FAMILY[$t], $textTokens));
        $candFamilies = array_unique(array_map(fn ($t) => self::SCALE_FAMILY[$t], $candTokens));

        $overlap = array_values(array_intersect($textFamilies, $candFamilies));
        if (! empty($overlap)) {
            return EvidenceSignal::make(EvidenceSignal::MATCH, [
                'aligned_families' => $overlap,
                'text_tokens' => $textTokens,
                'candidate_tokens' => $candTokens,
            ]);
        }

        // If both sides are "real place" families (settlement/subregion/nation/continent)
        // but different, treat as silent (e.g. "district" vs "city" - same world, no scale match).
        // If one side is "facility" and the other is a place family, that's a conflict
        // ("kingdom of X" mentions don't fit a term named "X Bookshop").
        $isFacilityContradiction = in_array('facility', $candFamilies, true)
            && ! empty(array_intersect($textFamilies, ['settlement', 'subregion', 'nation', 'continent']));

        if ($isFacilityContradiction) {
            return EvidenceSignal::make(EvidenceSignal::CONFLICT, [
                'reason' => 'candidate_is_facility_but_text_describes_place',
                'text_families' => $textFamilies,
                'candidate_families' => $candFamilies,
                'text_tokens' => $textTokens,
                'candidate_tokens' => $candTokens,
            ]);
        }

        return EvidenceSignal::make(EvidenceSignal::SILENT, [
            'reason' => 'families_differ_without_explicit_contradiction',
            'text_families' => $textFamilies,
            'candidate_families' => $candFamilies,
        ]);
    }

    /**
     * @return list<string> Distinct scale tokens detected in $haystack (lowercased)
     */
    private function detectTokens(string $haystack): array
    {
        if ($haystack === '') {
            return [];
        }
        $found = [];
        foreach (array_keys(self::SCALE_FAMILY) as $token) {
            // word boundary on both sides; Unicode word chars
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($token, '/').'(?![\p{L}\p{N}])/u', $haystack)) {
                $found[] = $token;
            }
        }

        return array_values(array_unique($found));
    }
}
