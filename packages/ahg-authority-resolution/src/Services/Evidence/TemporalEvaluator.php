<?php

/**
 * TemporalEvaluator - Service for Heratio
 *
 * Task-4 temporal evidence. Parses the candidate actor's free-text
 * dates_of_existence (actor + actor_i18n) for 4-digit years and compares
 * the resulting span against the mention's nearby_dates JSON.
 *
 * Signals:
 *   match    - at least one nearby_date year falls inside [candidate_start, candidate_end]
 *   silent   - candidate has parseable dates but no overlap with nearby_dates
 *              (or nearby_dates contains parseable years but none overlap)
 *   absent   - candidate dates_of_existence missing/unparseable OR
 *              mention nearby_dates empty/unparseable
 *
 * (ConflictEvaluator handles the harder "candidate died before mention's date"
 * case so this evaluator stays focused on the positive-evidence path.)
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

class TemporalEvaluator implements EvaluatorInterface
{
    private const PERSON_ORG_TYPES = ['PERSON', 'ORG'];

    public function dimension(): string
    {
        return 'temporal';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PERSON_ORG_TYPES, true);
    }

    public function evaluate(object $mention, object $context, object $candidate): array
    {
        $candSource = (string) ($candidate->candidate_source ?? '');
        if (!in_array($candSource, ['mysql_actor', 'fuseki_agent'], true)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'candidate_source_not_actor']);
        }

        $authId = $candidate->candidate_authority_id ?? null;
        if ($authId === null) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'no_authority_id']);
        }

        $datesRaw = $this->candidateDatesText((int) $authId);
        $candSpan = $datesRaw !== null ? EvidenceDateUtil::parseYearSpan($datesRaw) : null;

        $nearbyYears = EvidenceDateUtil::collectYearsFromNearbyDates($context->nearby_dates ?? null);

        if ($candSpan === null && empty($nearbyYears)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_candidate_dates_and_no_nearby_dates',
            ]);
        }
        if ($candSpan === null) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_candidate_dates',
                'nearby_years' => $nearbyYears,
            ]);
        }
        if (empty($nearbyYears)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_nearby_dates',
                'candidate_span' => $candSpan,
                'candidate_text' => $datesRaw,
            ]);
        }

        $overlaps = [];
        foreach ($nearbyYears as $year) {
            if ($year >= $candSpan['start'] && $year <= $candSpan['end']) {
                $overlaps[] = $year;
            }
        }

        if (!empty($overlaps)) {
            return EvidenceSignal::make(EvidenceSignal::MATCH, [
                'candidate_span' => $candSpan,
                'candidate_text' => $datesRaw,
                'overlapping_years' => $overlaps,
                'nearby_years' => $nearbyYears,
            ]);
        }

        return EvidenceSignal::make(EvidenceSignal::SILENT, [
            'candidate_span' => $candSpan,
            'candidate_text' => $datesRaw,
            'nearby_years' => $nearbyYears,
            'reason' => 'no_year_overlap',
        ]);
    }

    private function candidateDatesText(int $actorId): ?string
    {
        $row = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->orderByRaw("CASE WHEN culture = 'en' THEN 0 ELSE 1 END")
            ->first(['dates_of_existence']);

        if (!$row) {
            return null;
        }
        $value = trim((string) ($row->dates_of_existence ?? ''));
        return $value === '' ? null : $value;
    }
}
