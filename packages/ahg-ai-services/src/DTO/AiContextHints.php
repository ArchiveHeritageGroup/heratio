<?php

/**
 * AiContextHints - DTO for Heratio
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Author: Johan Pieterse <johan@plainsailingisystems.co.za>
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

declare(strict_types=1);

namespace AhgAiServices\DTO;

/**
 * Structured hint set distilled from embedded EXIF / IPTC / XMP metadata for
 * a single digital_object. Consumed by NerService / HtrService / DonutService
 * / LlmService to ground LLM prompts in real-world coordinates the model
 * could not otherwise infer.
 *
 * Empty instance means "no hints available" (sidecar tables empty, gate
 * tripped, or no digital_object id supplied) - callers must treat this as a
 * silent no-op and proceed exactly as they did before issue #750.
 *
 * Issue #750. See docs/reference/ai-embedded-metadata-context.md.
 */
final class AiContextHints
{
    /**
     * @param list<string> $subjectHints  IPTC Keywords / XMP dc:subject terms,
     *                                    deduplicated, in original order.
     * @param list<string> $suppressedReasons  Human-readable reasons a hint was
     *                                    dropped (e.g. "GPS suppressed by PII
     *                                    finding 1234"). Surfaced via the
     *                                    inference_context_used receipt so
     *                                    operators can audit gating.
     */
    public function __construct(
        public readonly ?string $dateHint = null,
        public readonly ?string $placeHint = null,
        public readonly ?string $creatorHint = null,
        public readonly array $subjectHints = [],
        public readonly array $suppressedReasons = [],
    ) {
    }

    /**
     * Empty hint set - returned when the digital_object has no embedded
     * metadata stored, or when no digital_object id was supplied to the
     * consumer in the first place.
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * True when every field is empty. Consumers use this to short-circuit
     * prompt injection entirely (no prefix added, no receipt event logged).
     */
    public function isEmpty(): bool
    {
        return $this->dateHint === null
            && $this->placeHint === null
            && $this->creatorHint === null
            && $this->subjectHints === [];
    }

    /**
     * Render a single-line system-prompt prefix that the LLM can use to
     * disambiguate dates, places, creators, and subjects. Order is fixed
     * (date, location, creator, subjects) so receipts are deterministic
     * over the same input regardless of insertion order.
     */
    public function toPromptPrefix(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $parts = [];
        if ($this->dateHint !== null) {
            $parts[] = 'date=' . $this->dateHint;
        }
        if ($this->placeHint !== null) {
            $parts[] = 'location=' . $this->placeHint;
        }
        if ($this->creatorHint !== null) {
            $parts[] = 'creator=' . $this->creatorHint;
        }
        if ($this->subjectHints !== []) {
            $parts[] = 'subjects=' . implode(', ', $this->subjectHints);
        }

        return 'Hints from image metadata: ' . implode('; ' , $parts)
            . '. Use these to disambiguate entities.';
    }

    /**
     * Receipt payload shape: a flat associative array suitable for json_encode
     * inside the inference_context_used event. Includes the suppressedReasons
     * list so operators can audit "the GPS was gated by PII finding X".
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'date'                => $this->dateHint,
            'place'               => $this->placeHint,
            'creator'             => $this->creatorHint,
            'subjects'            => $this->subjectHints,
            'suppressed_reasons'  => $this->suppressedReasons,
        ];
    }
}
