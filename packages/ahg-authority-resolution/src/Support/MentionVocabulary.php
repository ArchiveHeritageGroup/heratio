<?php

/**
 * MentionVocabulary - Service for Heratio
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

namespace AhgAuthorityResolution\Support;

/**
 * Single source of truth for the authority-resolution mention vocabulary.
 *
 * These are SYSTEM ENUMS, not admin-curatable controlled vocabularies:
 *  - ENTITY_TYPES are spaCy NER model labels; the pipeline and stored
 *    mention rows depend on these literal strings.
 *  - MENTION_STATES are the mention-review workflow enum; the resolver
 *    code compares against these literal strings.
 *
 * They are therefore correctly hardcoded (never sourced from ahg_dropdown),
 * and centralized here only to remove the duplicated inline arrays that
 * previously lived in the queue/park blades and the console commands.
 */
final class MentionVocabulary
{
    /**
     * spaCy NER entity labels handled by the resolution engine.
     * Canonical order - reused everywhere so filters render identically.
     */
    public const ENTITY_TYPES = ['PERSON', 'ORG', 'GPE', 'PLACE', 'LOC'];

    /**
     * Stored mention-review workflow states.
     *
     * Note: 'any' is a filter-only sentinel (not a stored state) and is
     * deliberately excluded here; the queue filter appends it explicitly.
     */
    public const MENTION_STATES = ['pending', 'linked', 'parked', 'rejected', 'new_record_created'];
}
