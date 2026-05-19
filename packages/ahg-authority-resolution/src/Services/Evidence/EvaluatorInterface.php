<?php

/**
 * EvaluatorInterface - Service for Heratio
 *
 * Contract for every Task-4 evidence evaluator. Evaluators are stateless
 * services registered in the container; EvidenceScorer composes them.
 *
 * Each evaluator owns exactly ONE dimension (temporal, geographic,
 * hierarchical, etc.) and emits a Signal of the four canonical kinds
 * (match | conflict | silent | absent).
 *
 * supports($entityType) gates which evaluators run for a given mention's
 * entity_type, so place-only evaluators don't fire for PERSON mentions
 * and vice versa.
 *
 * evaluate() receives three already-loaded rows so the orchestrator can
 * batch DB reads and individual evaluators stay test-friendly.
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

interface EvaluatorInterface
{
    /**
     * Stable dimension key written into ahg_mention_candidate.evidence_signals
     * (e.g. 'temporal', 'geographic', 'hierarchical', 'document_prior').
     */
    public function dimension(): string;

    /**
     * Filter by mention entity type (PERSON / ORG / GPE / LOC / PLACE).
     */
    public function supports(string $entityType): bool;

    /**
     * Compute the evidence signal for one (mention, candidate) pair.
     *
     * @param object $mention   Row from ahg_mention join ahg_ner_entity
     * @param object $context   Row from ahg_mention_context (JSON columns already decoded by caller, OR raw - evaluator must cope with both)
     * @param object $candidate Row from ahg_mention_candidate
     * @return array{signal:string,data:array<string,mixed>}  Use EvidenceSignal::make()
     */
    public function evaluate(object $mention, object $context, object $candidate): array;
}
