<?php

/**
 * EvidenceSignal - Service for Heratio
 *
 * Constants + tiny value-object helper for the four signal types every
 * Task-4 evidence evaluator can return:
 *
 *   match    - overlap found between mention context and candidate authority data
 *   conflict - direct contradiction (e.g. candidate death year < mention date)
 *   silent   - data exists for the dimension but no overlap or conflict found
 *   absent   - data MISSING entirely - distinct from silent; the engine
 *              must not score absence the same way it scores presence
 *              without overlap. Absent is the most common outcome on
 *              sparse archival corpora.
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

class EvidenceSignal
{
    public const MATCH = 'match';

    public const CONFLICT = 'conflict';

    public const SILENT = 'silent';

    public const ABSENT = 'absent';

    public const VALID = [
        self::MATCH,
        self::CONFLICT,
        self::SILENT,
        self::ABSENT,
    ];

    /**
     * Score-delta weights applied by EvidenceScorer over name_similarity_score.
     * Composite = clamp(name_similarity + sum(weights), 0, 1).
     */
    public const WEIGHT = [
        self::MATCH => 0.10,
        self::CONFLICT => -0.30,
        self::SILENT => 0.0,
        self::ABSENT => 0.0,
    ];

    /**
     * Build a uniform Signal array shape.
     *
     * @param  string  $signal  One of the constants above
     * @param  array<string,mixed>  $data  Evidence-specific raw data for the UI / audit
     * @return array{signal:string,data:array<string,mixed>}
     */
    public static function make(string $signal, array $data = []): array
    {
        if (! in_array($signal, self::VALID, true)) {
            $signal = self::ABSENT;
        }

        return ['signal' => $signal, 'data' => $data];
    }
}
