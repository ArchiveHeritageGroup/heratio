<?php

/**
 * TermId - Constants for Heratio
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

namespace AhgCore\Constants;

/**
 * Canonical AtoM term IDs — well-known term records used across status,
 * event, relation, note, and classification systems.
 *
 * These IDs are fixed in the AtoM database schema and inherited by Heratio
 * (the heratio data was migrated from AtoM with the same term IDs intact).
 *
 * Source: AtoM upstream `lib/model/QubitTerm.php` constants.
 */
class TermId
{
    // ── Root ────────────────────────────────────────────────
    const ROOT = 110;

    // ── Event Types (taxonomy "Event types") ────────────────
    const EVENT_TYPE_CREATION     = 111;
    const EVENT_TYPE_CUSTODY      = 113;
    const EVENT_TYPE_PUBLICATION  = 114;
    const EVENT_TYPE_CONTRIBUTION = 115;
    const EVENT_TYPE_COLLECTION   = 117;
    const EVENT_TYPE_ACCUMULATION = 118;

    // ── Note Types (taxonomy "Note types") ──────────────────
    const NOTE_TITLE              = 119;
    const NOTE_PUBLICATION        = 120;
    const NOTE_SOURCE             = 121;
    const NOTE_SCOPE              = 122;
    const NOTE_DISPLAY            = 123;
    const NOTE_ARCHIVIST          = 124;
    const NOTE_GENERAL            = 125;
    const NOTE_OTHER_DESCRIPTIVE  = 126;
    const NOTE_MAINTENANCE        = 127;
    const NOTE_LANGUAGE           = 174;
    const NOTE_ACTOR_OCCUPATION   = 188;

    // ── Collection Types (taxonomy "Collection types") ──────
    const COLLECTION_ARCHIVAL     = 128;
    const COLLECTION_PUBLISHED    = 129;
    const COLLECTION_ARTEFACT     = 130;

    // ── Actor Entity Types (taxonomy "Actor entity types") ──
    const ACTOR_ENTITY_CORPORATE_BODY = 131;
    const ACTOR_ENTITY_PERSON         = 132;
    const ACTOR_ENTITY_FAMILY         = 133;

    // ── Other Name Types (taxonomy "Other name types") ──────
    const OTHER_NAME_FAMILY_FIRST = 134;
    const OTHER_NAME_PARALLEL     = 148;
    const OTHER_NAME_OTHER_FORM   = 149;
    const OTHER_NAME_STANDARDIZED = 165;

    // ── Media Types (taxonomy "Media types") ────────────────
    const MEDIA_AUDIO = 135;
    const MEDIA_IMAGE = 136;
    const MEDIA_TEXT  = 137;
    const MEDIA_VIDEO = 138;
    const MEDIA_OTHER = 139;

    // ── Digital Object Usage (taxonomy "Digital object usage") ─
    const DO_USAGE_MASTER       = 140;
    const DO_USAGE_REFERENCE    = 141;
    const DO_USAGE_THUMBNAIL    = 142;
    const DO_USAGE_COMPOUND     = 143;
    const DO_USAGE_EXTERNAL_URI = 166;
    const DO_USAGE_OFFLINE      = 186;
    const DO_USAGE_EXTERNAL_FILE = 191;

    // ── Physical Object Types (taxonomy "Physical object types") ─
    const PHYSICAL_LOCATION  = 144;
    const PHYSICAL_CONTAINER = 145;
    const PHYSICAL_ARTEFACT  = 146;

    // ── Relation Types (taxonomy "Relation types") ──────────
    // These appear as `relation.type_id` values.
    /** Same term ID as EVENT_TYPE_CREATION (111) — used in `relation.type_id`
     *  for accession→creator links per AtoM `addInformationObjectAction`. */
    const RELATION_CREATION                     = 111;
    const RELATION_HAS_PHYSICAL_OBJECT          = 147;
    const RELATION_ACTOR_HIERARCHICAL           = 150;
    const RELATION_ACTOR_TEMPORAL               = 151;
    const RELATION_ACTOR_FAMILY                 = 152;
    const RELATION_ACTOR_ASSOCIATIVE            = 153;
    const RELATION_NAME_ACCESS_POINT            = 161;
    const RELATION_ACCESSION                    = 167;
    const RELATION_RIGHT                        = 168;
    const RELATION_DONOR                        = 169;
    const RELATION_ACCRUAL                      = 175;
    const RELATION_RELATED_MATERIAL_DESCRIPTIONS = 176;
    const RELATION_CONVERSE_TERM                = 177;
    const RELATION_AIP                          = 178;
    const RELATION_MAINTAINING_REPOSITORY       = 187;

    // ── Actor Relation Note (taxonomy "Actor relation note") ─
    const ACTOR_RELATION_NOTE_DESCRIPTION = 154;
    const ACTOR_RELATION_NOTE_DATE        = 155;

    // ── Term Relation (taxonomy "Term relations") ───────────
    const TERM_RELATION_ALTERNATIVE_LABEL = 156;
    const TERM_RELATION_ASSOCIATIVE       = 157;

    // ── Status Types (taxonomy "Status types") ──────────────
    const STATUS_TYPE_PUBLICATION = 158;

    // ── Publication Status (taxonomy "Publication status") ──
    const PUBLICATION_STATUS_DRAFT     = 159;
    const PUBLICATION_STATUS_PUBLISHED = 160;

    // ── ISDF Function Relation Types ────────────────────────
    const ISDF_RELATION_HIERARCHICAL = 162;
    const ISDF_RELATION_TEMPORAL     = 163;
    const ISDF_RELATION_ASSOCIATIVE  = 164;

    // ── Rights Basis (taxonomy "Rights basis") ──────────────
    const RIGHT_BASIS_COPYRIGHT = 170;
    const RIGHT_BASIS_LICENSE   = 171;
    const RIGHT_BASIS_STATUTE   = 172;
    const RIGHT_BASIS_POLICY    = 173;

    // ── AIP Component Types ─────────────────────────────────
    const AIP_ARTWORK_COMPONENT      = 179;
    const AIP_ARTWORK_MATERIAL       = 180;
    const AIP_SUPPORTING_DOCUMENTATION = 181;
    const AIP_SUPPORTING_TECHNOLOGY  = 182;

    // ── Job Status (taxonomy "Job status") ──────────────────
    const JOB_STATUS_IN_PROGRESS = 183;
    const JOB_STATUS_COMPLETED   = 184;
    const JOB_STATUS_ERROR       = 185;
    const JOB_NOTE_ERROR         = 197;

    // ── User Action ─────────────────────────────────────────
    const USER_ACTION_CREATION     = 189;
    const USER_ACTION_MODIFICATION = 190;

    // ── Accession Alternative Identifier ────────────────────
    const ACCESSION_ALT_ID_DEFAULT_TYPE   = 192;
    const ACCESSION_EVENT_PHYSICAL_TRANSFER = 193;
    const ACCESSION_EVENT_NOTE            = 194;

    // ── Digital Object Subtitles/Chapters ───────────────────
    const DO_CHAPTERS  = 195;
    const DO_SUBTITLES = 196;

    // ─────────────────────────────────────────────────────────
    // Backwards-compat aliases (deprecated — kept so existing
    // call sites don't break while the codebase migrates).
    // ─────────────────────────────────────────────────────────
    /** @deprecated Use EVENT_TYPE_CREATION */
    const EVENT_CREATION = self::EVENT_TYPE_CREATION;
    /** @deprecated Use EVENT_TYPE_ACCUMULATION */
    const EVENT_ACCUMULATION = self::EVENT_TYPE_ACCUMULATION;
    /** @deprecated Use EVENT_TYPE_COLLECTION */
    const EVENT_COLLECTION = self::EVENT_TYPE_COLLECTION;
}
