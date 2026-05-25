<?php

/**
 * ConflictEvaluator - Service for Heratio
 *
 * Task-4 hard-exclusion evidence for PERSON / ORG candidates. Pulls the
 * candidate's parsed year span and the mention's nearby_dates, then fires
 * a conflict if the candidate's end-of-existence year is STRICTLY BEFORE
 * any nearby_date year (i.e. the person was dead, or the org dissolved,
 * before the mention's referenced events).
 *
 * Symmetric end-of-existence-only check is deliberate: AtoM dates_of_existence
 * often only contains a death year (e.g. "1818-1895" for Frederick Douglass).
 * A start-year-after check would punish candidates who simply lack a birth
 * year - which is most of them. Conflicts must be unambiguous.
 *
 * Signals:
 *   conflict - candidate.end_year < any nearby_date year (margin of 0)
 *   silent   - both sides have parseable dates AND no conflict
 *   absent   - either side missing parseable dates
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

class ConflictEvaluator implements EvaluatorInterface
{
    private const PERSON_ORG_TYPES = ['PERSON', 'ORG'];

    public function dimension(): string
    {
        return 'conflict';
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

        $row = DB::table('actor_i18n')
            ->where('id', $authId)
            ->orderByRaw("CASE WHEN culture = 'en' THEN 0 ELSE 1 END")
            ->first(['dates_of_existence']);
        $candText = $row ? trim((string) ($row->dates_of_existence ?? '')) : '';
        if ($candText === '') {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'no_candidate_dates']);
        }

        $span = EvidenceDateUtil::parseYearSpan($candText);
        if ($span === null) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'unparseable_candidate_dates',
                'candidate_text' => $candText,
            ]);
        }

        $years = EvidenceDateUtil::collectYearsFromNearbyDates($context->nearby_dates ?? null);
        if (empty($years)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_nearby_dates',
                'candidate_span' => $span,
            ]);
        }

        $violations = [];
        foreach ($years as $y) {
            if ($y > $span['end']) {
                $violations[] = $y;
            }
        }

        if (! empty($violations)) {
            return EvidenceSignal::make(EvidenceSignal::CONFLICT, [
                'candidate_span' => $span,
                'candidate_text' => $candText,
                'mention_years_after_end' => $violations,
            ]);
        }

        return EvidenceSignal::make(EvidenceSignal::SILENT, [
            'reason' => 'no_temporal_conflict',
            'candidate_span' => $span,
            'nearby_years' => $years,
        ]);
    }
}
