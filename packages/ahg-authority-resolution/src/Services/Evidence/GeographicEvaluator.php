<?php

/**
 * GeographicEvaluator - Service for Heratio
 *
 * Task-4 geographic evidence for PERSON / ORG candidates. Looks at the
 * candidate actor's known event locations and the i18n.places free-text
 * field, then compares against the mention's nearby_places JSON.
 *
 * Lookup paths used:
 *
 *   1. event JOIN object_term_relation JOIN term_i18n
 *      where event.actor_id = candidate. event has no place_id in AtoM/Heratio;
 *      the standard AtoM idiom is object_term_relation rows with
 *      object_id = event.id linking to a term (places taxonomy_id=42).
 *
 *   2. actor_i18n.places free-text scan. If the candidate's authority record
 *      has a "places" string, we substring-search it for each nearby place name.
 *
 * Signals:
 *   match    - at least one nearby_place name appears in either known-locations
 *              source for the candidate
 *   silent   - candidate has known locations (events or places-text) but none
 *              overlap with nearby_places
 *   absent   - candidate has no known locations at all OR nearby_places empty
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

use Illuminate\Support\Facades\DB;

class GeographicEvaluator implements EvaluatorInterface
{
    private const PERSON_ORG_TYPES = ['PERSON', 'ORG'];

    public function dimension(): string
    {
        return 'geographic';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PERSON_ORG_TYPES, true);
    }

    public function evaluate(object $mention, object $context, object $candidate): array
    {
        $candSource = (string) ($candidate->candidate_source ?? '');
        if (! in_array($candSource, ['mysql_actor', 'fuseki_agent'], true)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'candidate_source_not_actor']);
        }

        $authId = $candidate->candidate_authority_id ?? null;
        if ($authId === null) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'no_authority_id']);
        }

        $nearbyPlaces = $this->extractPlaceNames($context->nearby_places ?? null);

        $eventPlaces = $this->candidateEventPlaces((int) $authId);
        $textPlaces = $this->candidatePlacesText((int) $authId);

        $candKnown = array_values(array_unique(array_merge($eventPlaces, $textPlaces)));

        if (empty($candKnown) && empty($nearbyPlaces)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_candidate_locations_and_no_nearby_places',
            ]);
        }
        if (empty($candKnown)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_candidate_locations',
                'nearby_places' => $nearbyPlaces,
            ]);
        }
        if (empty($nearbyPlaces)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_nearby_places',
                'candidate_locations' => $candKnown,
            ]);
        }

        $overlaps = [];
        $candKnownLower = array_map(fn ($s) => mb_strtolower($s), $candKnown);
        foreach ($nearbyPlaces as $place) {
            $placeLower = mb_strtolower($place);
            foreach ($candKnownLower as $idx => $kLower) {
                // either exact or substring match in either direction
                if ($kLower === $placeLower
                    || str_contains($kLower, $placeLower)
                    || str_contains($placeLower, $kLower)) {
                    $overlaps[] = [
                        'mention_place' => $place,
                        'candidate_location' => $candKnown[$idx],
                    ];
                    break;
                }
            }
        }

        if (! empty($overlaps)) {
            return EvidenceSignal::make(EvidenceSignal::MATCH, [
                'overlaps' => $overlaps,
                'candidate_locations' => $candKnown,
                'nearby_places' => $nearbyPlaces,
            ]);
        }

        return EvidenceSignal::make(EvidenceSignal::SILENT, [
            'reason' => 'no_overlap',
            'candidate_locations' => $candKnown,
            'nearby_places' => $nearbyPlaces,
        ]);
    }

    /**
     * @return list<string>
     */
    private function extractPlaceNames($nearbyPlacesJson): array
    {
        $rows = EvidenceDateUtil::decodeJsonish($nearbyPlacesJson);
        if (! is_array($rows)) {
            return [];
        }
        $names = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['value']) && $row['value'] !== '') {
                $names[] = (string) $row['value'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function candidateEventPlaces(int $actorId): array
    {
        $rows = DB::table('event')
            ->where('event.actor_id', $actorId)
            ->join('object_term_relation as otr', 'otr.object_id', '=', 'event.id')
            ->join('term', 'term.id', '=', 'otr.term_id')
            ->join('term_i18n', function ($join) {
                $join->on('term_i18n.id', '=', 'term.id')
                    ->where('term_i18n.culture', '=', 'en');
            })
            ->where('term.taxonomy_id', 42)  // places taxonomy
            ->pluck('term_i18n.name')
            ->all();

        return array_values(array_filter(array_map('strval', $rows), fn ($s) => $s !== ''));
    }

    private function candidatePlacesText(int $actorId): array
    {
        $row = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->orderByRaw("CASE WHEN culture = 'en' THEN 0 ELSE 1 END")
            ->first(['places']);

        if (! $row) {
            return [];
        }
        $text = trim((string) ($row->places ?? ''));
        if ($text === '') {
            return [];
        }
        // Split on common separators: comma, semicolon, newline, pipe
        $parts = preg_split('/[,;\n\|]/u', $text);
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }
}
