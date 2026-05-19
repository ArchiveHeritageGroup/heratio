<?php

/**
 * PriorEvaluator - Service for Heratio
 *
 * Task-4 document-prior evidence for PLACE candidates. Asks
 * DocumentPriorService for the fonds-level distribution of already-resolved
 * places under this mention's fonds, then checks whether the candidate
 * term_id sits inside the top-3 of that distribution.
 *
 * Why top-3 (not exact match): on a sparse archive the prior is most useful
 * as a sanity nudge - "candidate is a popular place in this fonds" - rather
 * than as a single-term predictor.
 *
 * Signals:
 *   match    - candidate authority id is in the top-3 most-resolved places
 *              under this fonds (with at least 2 hits in absolute terms)
 *   silent   - distribution is non-empty but candidate is not in the top-3
 *   absent   - distribution is empty (no resolved places yet under this fonds)
 *              or fonds_id could not be determined
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

class PriorEvaluator implements EvaluatorInterface
{
    private const PLACE_TYPES = ['GPE', 'PLACE', 'LOC'];
    private const TOP_N = 3;
    private const MIN_HITS = 2;

    public function __construct(private DocumentPriorService $prior) {}

    public function dimension(): string
    {
        return 'document_prior';
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
        $objectId = (int) ($mention->object_id ?? 0);
        if ($objectId <= 0) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'no_object_id']);
        }

        $prior = $this->prior->priorFor($objectId);
        $distribution = $prior['distribution'];

        if (empty($distribution)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_prior_data',
                'fonds_id' => $prior['fonds_id'],
                'io_count' => $prior['io_count'],
            ]);
        }

        // Sort distribution descending by count; keep top-N
        arsort($distribution);
        $top = array_slice($distribution, 0, self::TOP_N, true);

        $authIdInt = (int) $authId;
        if (isset($top[$authIdInt]) && $top[$authIdInt] >= self::MIN_HITS) {
            return EvidenceSignal::make(EvidenceSignal::MATCH, [
                'fonds_id' => $prior['fonds_id'],
                'io_count' => $prior['io_count'],
                'candidate_hits' => $top[$authIdInt],
                'top_n' => $top,
            ]);
        }

        return EvidenceSignal::make(EvidenceSignal::SILENT, [
            'reason' => 'candidate_not_in_top_n',
            'fonds_id' => $prior['fonds_id'],
            'io_count' => $prior['io_count'],
            'top_n' => $top,
        ]);
    }
}
