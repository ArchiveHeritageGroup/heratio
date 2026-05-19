<?php

/**
 * PlaceConflictEvaluator - Service for Heratio
 *
 * Task-4 hard-exclusion evidence for PLACE candidates. The place taxonomy
 * (term.taxonomy_id=42) doesn't carry authoritative founded/abolished dates
 * today, so this evaluator is structurally honest: it always returns silent.
 *
 * It stays in the pipeline as a stub so that when term-level dates are added
 * later (e.g. via a Fuseki place vocabulary), the conflict logic plugs in
 * without re-shaping the orchestrator, the evidence_signals JSON schema, or
 * the scorer's weight table.
 *
 * Signals:
 *   silent - always (until term-level dates exist)
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

class PlaceConflictEvaluator implements EvaluatorInterface
{
    private const PLACE_TYPES = ['GPE', 'PLACE', 'LOC'];

    public function dimension(): string
    {
        return 'conflict';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PLACE_TYPES, true);
    }

    public function evaluate(object $mention, object $context, object $candidate): array
    {
        return EvidenceSignal::make(EvidenceSignal::SILENT, [
            'reason' => 'term_taxonomy_has_no_authoritative_dates_yet',
        ]);
    }
}
