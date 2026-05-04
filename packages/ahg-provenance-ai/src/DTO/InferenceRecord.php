<?php

/**
 * InferenceRecord - DTO for Heratio
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

namespace AhgProvenanceAi\DTO;

/**
 * Value object describing a single AI inference write.
 *
 * Every AI service (NER, HTR, Translation, LLM, Donut) constructs one of
 * these and hands it to InferenceService::record(). The DTO is the contract:
 * if a field is missing here, it cannot be persisted, so the contract is
 * enforced at the type level rather than via documentation.
 *
 * Hashes are sha256 hex (64 chars). Excerpts are truncated to 500 chars at
 * service-call time (not here) to keep the DTO purely structural.
 *
 * See ADR-0002 for the full inference-write contract.
 */
final class InferenceRecord
{
    public function __construct(
        public readonly string $serviceName,        // 'NER', 'HTR', 'TRANSLATION', 'LLM', 'DONUT', 'OCR'
        public readonly string $modelName,          // free-text identifier from the model
        public readonly string $modelVersion,       // 'unknown' if not retrievable
        public readonly string $inputHash,          // sha256 hex
        public readonly string $outputHash,         // sha256 hex
        public readonly string $targetEntityType,   // 'information_object', 'actor', 'museum_metadata', etc.
        public readonly int    $targetEntityId,
        public readonly string $targetField,        // RDF predicate / column being touched
        public readonly ?float  $confidence  = null, // 0.0-1.0; null when model does not expose
        public readonly ?string $standard    = null, // 'ICIP', 'ISAD(G)', 'Spectrum-5.1', etc.
        public readonly ?string $endpoint    = null, // URL the inference was performed against
        public readonly ?string $inputExcerpt  = null,
        public readonly ?string $outputExcerpt = null,
        public readonly ?int    $elapsedMs   = null,
        public readonly ?int    $userId      = null, // triggering user; null for batch / cron
    ) {}

    /**
     * Convenience: hash + excerpt a string in one call. Mirrors what every
     * AI service has to do before constructing the DTO.
     *
     * @return array{0:string,1:string} [hash, excerpt]
     */
    public static function hashAndExcerpt(string $text, int $excerptLen = 500): array
    {
        $hash = hash('sha256', $text);
        $excerpt = mb_strlen($text) > $excerptLen
            ? mb_substr($text, 0, $excerptLen)
            : $text;
        return [$hash, $excerpt];
    }
}
