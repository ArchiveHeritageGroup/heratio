<?php

namespace AhgCore\Constants;

/**
 * AtoM term IDs — canonical references to well-known term records.
 * These IDs are fixed in the AtoM database schema and used across
 * the status, event, and classification systems.
 *
 * Source: term / term_i18n tables in the archive database.
 */
class TermId
{
    // Publication Status (taxonomy_id=60 "Publication Status")
    const PUBLICATION_STATUS_DRAFT = 159;
    const PUBLICATION_STATUS_PUBLISHED = 160;

    // Status Types (taxonomy_id=59 "Status Types")
    const STATUS_TYPE_PUBLICATION = 158;

    // Job Status (taxonomy_id=61 "Job Status")
    const JOB_STATUS_IN_PROGRESS = 183;
    const JOB_STATUS_COMPLETED = 184;
    const JOB_STATUS_ERROR = 185;

    // Event Types (taxonomy_id=39)
    const EVENT_TYPE_CREATION = 111;
    const EVENT_TYPE_ACCUMULATION = 113;
    const EVENT_TYPE_COLLECTION = 117;

    // Actor Entity Types (taxonomy_id=32)
    const ACTOR_ENTITY_CORPORATE_BODY = 131;
    const ACTOR_ENTITY_PERSON = 132;
    const ACTOR_ENTITY_FAMILY = 133;

    // Root term
    const ROOT = 110;
}
