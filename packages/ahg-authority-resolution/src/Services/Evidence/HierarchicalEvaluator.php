<?php

/**
 * HierarchicalEvaluator - Service for Heratio
 *
 * Task-4 hierarchical evidence for PLACE candidates. Walks the candidate
 * term's parent chain (term.parent_id) and checks whether any ancestor's
 * name matches one of the mention's nearby_places.
 *
 * Example: mention nearby_places = ["United Kingdom", "Europe"], candidate
 * "London" has parent "United Kingdom" -> match.
 *
 * Signals:
 *   match    - at least one ancestor term name matches a nearby place
 *   silent   - candidate has ancestors AND mention has nearby_places, no overlap
 *   absent   - candidate is root-level (no parent) OR mention has no nearby_places
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

class HierarchicalEvaluator implements EvaluatorInterface
{
    private const PLACE_TYPES = ['GPE', 'PLACE', 'LOC'];
    private const MAX_DEPTH = 16;
    private const PLACE_TAXONOMY_ROOT_ID = 110;  // AtoM convention; never a useful "ancestor"

    public function dimension(): string
    {
        return 'hierarchical';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PLACE_TYPES, true);
    }

    public function evaluate(object $mention, object $context, object $candidate): array
    {
        if ((string) ($candidate->candidate_source ?? '') !== 'mysql_term') {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'candidate_source_not_term']);
        }
        $authId = $candidate->candidate_authority_id ?? null;
        if ($authId === null) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'no_authority_id']);
        }

        $nearby = $this->extractPlaceNames($context->nearby_places ?? null);
        $ancestors = $this->walkAncestors((int) $authId);

        if (empty($ancestors) && empty($nearby)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_ancestors_and_no_nearby_places',
            ]);
        }
        if (empty($ancestors)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'candidate_has_no_meaningful_ancestors',
                'nearby_places' => $nearby,
            ]);
        }
        if (empty($nearby)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_nearby_places',
                'ancestors' => $ancestors,
            ]);
        }

        $overlaps = [];
        $ancestorsLower = array_map(fn($a) => mb_strtolower($a['name']), $ancestors);
        foreach ($nearby as $place) {
            $pLower = mb_strtolower($place);
            foreach ($ancestorsLower as $idx => $aLower) {
                if ($aLower === $pLower || str_contains($aLower, $pLower) || str_contains($pLower, $aLower)) {
                    $overlaps[] = [
                        'nearby_place' => $place,
                        'ancestor_name' => $ancestors[$idx]['name'],
                        'ancestor_id' => $ancestors[$idx]['id'],
                    ];
                    break;
                }
            }
        }

        if (!empty($overlaps)) {
            return EvidenceSignal::make(EvidenceSignal::MATCH, [
                'overlaps' => $overlaps,
                'ancestors' => $ancestors,
                'nearby_places' => $nearby,
            ]);
        }

        return EvidenceSignal::make(EvidenceSignal::SILENT, [
            'reason' => 'no_overlap',
            'ancestors' => $ancestors,
            'nearby_places' => $nearby,
        ]);
    }

    /**
     * @return list<string>
     */
    private function extractPlaceNames($nearbyPlacesJson): array
    {
        $rows = EvidenceDateUtil::decodeJsonish($nearbyPlacesJson);
        if (!is_array($rows)) {
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
     * @return list<array{id:int,name:string}>  In order from immediate parent up
     */
    private function walkAncestors(int $termId): array
    {
        $ancestors = [];
        $seen = [$termId];
        $current = DB::table('term')->where('id', $termId)->first(['id', 'parent_id']);
        if (!$current) {
            return [];
        }
        $parentId = $current->parent_id !== null ? (int) $current->parent_id : null;

        for ($depth = 0; $depth < self::MAX_DEPTH && $parentId !== null; $depth++) {
            if (in_array($parentId, $seen, true)) {
                break;
            }
            $seen[] = $parentId;

            if ($parentId === self::PLACE_TAXONOMY_ROOT_ID) {
                // Taxonomy root carries no place semantics; stop.
                break;
            }

            $row = DB::table('term as t')
                ->leftJoin('term_i18n as ti', function ($j) {
                    $j->on('ti.id', '=', 't.id')->where('ti.culture', '=', 'en');
                })
                ->where('t.id', $parentId)
                ->first(['t.id', 't.parent_id', 'ti.name']);
            if (!$row) {
                break;
            }
            $name = trim((string) ($row->name ?? ''));
            if ($name !== '') {
                $ancestors[] = ['id' => (int) $row->id, 'name' => $name];
            }
            $parentId = $row->parent_id !== null ? (int) $row->parent_id : null;
        }

        return $ancestors;
    }
}
