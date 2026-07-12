<?php

/**
 * EntityTypeLabels - human-readable labels for NER entity-type codes.
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

namespace AhgAiServices\Support;

/**
 * Canonical mapping from spaCy / model NER entity-type codes (PERSON, ORG,
 * GPE, LOC, NORP, ...) to human-readable labels shown in the UI. The raw
 * codes are model output and were leaking onto the NER extraction / review /
 * authority-resolution pages ("ORG", "GPE"); every display surface should
 * resolve through here so there is a single source of truth (SA English).
 */
final class EntityTypeLabels
{
    /** code => friendly label. Codes are always upper-cased before lookup. */
    public const LABELS = [
        'PERSON'      => 'Person',
        'PER'         => 'Person',
        'ORG'         => 'Organisation',
        'NORP'        => 'Group / Nationality',
        'FAC'         => 'Facility',
        'GPE'         => 'Place',
        'LOC'         => 'Location',
        'PLACE'       => 'Place',
        'PRODUCT'     => 'Product',
        'EVENT'       => 'Event',
        'WORK_OF_ART' => 'Work of Art',
        'LAW'         => 'Law',
        'LANGUAGE'    => 'Language',
        'DATE'        => 'Date',
        'TIME'        => 'Time',
        'PERCENT'     => 'Percent',
        'MONEY'       => 'Money',
        'QUANTITY'    => 'Quantity',
        'ORDINAL'     => 'Ordinal',
        'CARDINAL'    => 'Number',
        'MISC'        => 'Other',
    ];

    /**
     * Friendly label for a single code. Unknown codes fall back to a
     * title-cased rendering (UNKNOWN_TYPE -> "Unknown Type") rather than the
     * raw upper-case code, so nothing ever renders as a bare token.
     */
    public static function label(?string $code): string
    {
        $key = strtoupper(trim((string) $code));
        if ($key === '') {
            return '';
        }
        if (isset(self::LABELS[$key])) {
            return self::LABELS[$key];
        }

        return ucwords(strtolower(str_replace('_', ' ', $key)));
    }

    /** Full code => label map, for injecting into client-side JS (@json). */
    public static function all(): array
    {
        return self::LABELS;
    }
}
