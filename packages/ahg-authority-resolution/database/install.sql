-- ==========================================================================
-- AHG Authority Resolution Engine - schema install
-- Package: ahg/authority-resolution
-- Identical to atom-ahg-plugins/ahgAuthorityResolutionPlugin/database/install.sql
-- on the AtoM Heratio side. Schemas converge here.
--
-- Design: coexist + project. ahg_ner_entity remains the canonical extraction
-- pool used by ahg-discovery / ahg-actor-manage / ahg-information-object-manage.
-- ahg_mention promotes selected rows into the authority-resolution workflow.
-- On a 'link' decision the resolver writes both ahg_mention_decision (audit)
-- AND back-updates ahg_ner_entity.linked_actor_id (existing consumer contract).
--
-- No FK constraints to base AtoM tables (matches existing ahg_ner_entity
-- convention - decouples from base schema migrations).
-- All enumerated values are VARCHAR + COMMENT (no ENUM per CLAUDE.md).
-- ==========================================================================

-- One workflow row per promoted NER entity.
-- The assigned_* / workflow_task_id columns back the Assign / Workflow
-- feature: an archivist can assign a mention to another archivist, which
-- routes it through the ahg-workflow plugin (ahg_workflow_task).
CREATE TABLE IF NOT EXISTS ahg_mention (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ner_entity_id        BIGINT UNSIGNED NOT NULL,
    object_id            INT NOT NULL,
    entity_type          VARCHAR(20) NOT NULL COMMENT 'PERSON, ORG, GPE, PLACE',
    state                VARCHAR(30) NOT NULL DEFAULT 'pending'
                         COMMENT 'pending, linked, parked, rejected, new_record_created',
    promoted_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
    assigned_to_user_id  INT NULL
                         COMMENT 'user.id the mention is assigned to (Assign / Workflow feature)',
    assigned_by_user_id  INT NULL
                         COMMENT 'user.id of the archivist who made the assignment',
    assigned_at          DATETIME NULL
                         COMMENT 'when the mention was last assigned',
    workflow_task_id     BIGINT UNSIGNED NULL
                         COMMENT 'ahg_workflow_task.id created when the mention was assigned',
    PRIMARY KEY (id),
    UNIQUE KEY uq_mention_ner_entity (ner_entity_id),
    KEY idx_mention_object (object_id),
    KEY idx_mention_state (state, entity_type),
    KEY idx_mention_assigned (assigned_to_user_id, state),
    KEY idx_mention_workflow_task (workflow_task_id),
    CONSTRAINT fk_mention_ner_entity
        FOREIGN KEY (ner_entity_id) REFERENCES ahg_ner_entity (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Neighbourhood context packet (1:1 with mention).
-- Scalars as columns, variable-shape lists as JSON.
CREATE TABLE IF NOT EXISTS ahg_mention_context (
    id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mention_id               BIGINT UNSIGNED NOT NULL,
    character_offset_start   INT UNSIGNED NULL,
    character_offset_end     INT UNSIGNED NULL,
    paragraph_offset_start   INT UNSIGNED NULL,
    paragraph_offset_end     INT UNSIGNED NULL,
    surrounding_text_before  TEXT NULL COMMENT 'up to 150 chars before mention',
    surrounding_text_after   TEXT NULL COMMENT 'up to 150 chars after mention',
    ner_model_version        VARCHAR(100) NULL,
    real_confidence          DECIMAL(6,4) NULL
                             COMMENT 'NULL until upstream API exposes per-entity scores',
    co_occurring_entities    JSON NULL
                             COMMENT 'Array of {ner_entity_id?, value, type, distance_tokens}',
    nearby_dates             JSON NULL
                             COMMENT 'Array of {value, normalized?, distance_tokens}',
    nearby_places            JSON NULL
                             COMMENT 'Array of {value, term_id?, distance_tokens}',
    role_language_tokens     JSON NULL
                             COMMENT 'Array of {token, position_offset, kind: kinship|witness|location|movement|other}',
    computed_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_context_mention (mention_id),
    CONSTRAINT fk_context_mention
        FOREIGN KEY (mention_id) REFERENCES ahg_mention (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ranked candidates surfaced per mention. Cached after Task 3, scored at Task 4.
CREATE TABLE IF NOT EXISTS ahg_mention_candidate (
    id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mention_id               BIGINT UNSIGNED NOT NULL,
    rank_position            TINYINT UNSIGNED NOT NULL,
    candidate_source         VARCHAR(30) NOT NULL
                             COMMENT 'mysql_actor, fuseki_agent, mysql_term, fuseki_place',
    candidate_authority_id   INT NULL
                             COMMENT 'actor.id or term.id; NULL when candidate is Fuseki-only',
    candidate_fuseki_uri     VARCHAR(2048) NULL,
    candidate_display_name   VARCHAR(1000) NOT NULL,
    name_similarity_score    DECIMAL(6,4) NOT NULL DEFAULT 0,
    evidence_signals         JSON NULL
                             COMMENT 'Per-dimension match|conflict|silent|absent for temporal/geographic/relational/role/conflict (persons,orgs) or geographic/hierarchical/document_prior/co_occurring/conflict/scale (places)',
    evidence_data            JSON NULL
                             COMMENT 'Underlying values per dimension for the UI to render',
    composite_score          DECIMAL(6,4) NULL
                             COMMENT 'Weighted aggregate after Task 4 scoring',
    computed_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_candidate_mention_rank (mention_id, rank_position),
    KEY idx_candidate_authority (candidate_authority_id),
    CONSTRAINT fk_candidate_mention
        FOREIGN KEY (mention_id) REFERENCES ahg_mention (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable decision audit. One row per decision event.
-- Frozen snapshots so "what did the archivist see" is answerable from this row alone.
CREATE TABLE IF NOT EXISTS ahg_mention_decision (
    id                            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mention_id                    BIGINT UNSIGNED NOT NULL,
    decision_type                 VARCHAR(30) NOT NULL
                                  COMMENT 'link, link_different, create_new, park, reject',
    chosen_candidate_id           BIGINT UNSIGNED NULL,
    chosen_authority_id           INT NULL
                                  COMMENT 'actor.id or term.id depending on entity_type',
    original_system_top_score     DECIMAL(6,4) NULL
                                  COMMENT 'Engine score of top candidate at decision time',
    archivist_user_id             INT NOT NULL,
    decided_at                    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fuseki_graph_uri              VARCHAR(2048) NULL
                                  COMMENT 'Set asynchronously by Task 8 RDF-Star writer',
    evidence_snapshot             JSON NULL
                                  COMMENT 'Frozen copy of evidence_signals + evidence_data shown at decision time',
    candidates_visible_snapshot   JSON NULL
                                  COMMENT 'Array of {candidate_id, display_name, rank} visible at decision',
    PRIMARY KEY (id),
    KEY idx_decision_mention (mention_id, decided_at),
    KEY idx_decision_archivist (archivist_user_id, decided_at),
    CONSTRAINT fk_decision_mention
        FOREIGN KEY (mention_id) REFERENCES ahg_mention (id) ON DELETE CASCADE,
    CONSTRAINT fk_decision_candidate
        FOREIGN KEY (chosen_candidate_id) REFERENCES ahg_mention_candidate (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Park queue. One active row per mention (UNIQUE on mention_id).
CREATE TABLE IF NOT EXISTS ahg_mention_park (
    id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mention_id                  BIGINT UNSIGNED NOT NULL,
    parked_by_user_id           INT NOT NULL,
    parked_at                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason                      TEXT NOT NULL,
    new_candidate_available     TINYINT(1) NOT NULL DEFAULT 0
                                COMMENT 'Set to 1 by Task 7 re-scan job when authority store has new candidates since parking',
    new_candidate_check_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_park_mention (mention_id),
    KEY idx_park_user (parked_by_user_id, parked_at),
    KEY idx_park_new_candidate (new_candidate_available, parked_at),
    CONSTRAINT fk_park_mention
        FOREIGN KEY (mention_id) REFERENCES ahg_mention (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task 9: NER false-positive feedback capture. One row per 'reject' decision.
-- Mirrors the rejection context (source text + span offsets + entity_type) so
-- the /opt/ahg-ai retrainer can read it back into a NER training set.
CREATE TABLE IF NOT EXISTS ahg_ner_feedback (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mention_id BIGINT UNSIGNED NOT NULL,
    ner_entity_id BIGINT UNSIGNED NOT NULL,
    decision_id BIGINT UNSIGNED NOT NULL,
    source_text MEDIUMTEXT NOT NULL,
    mention_value VARCHAR(1000) NOT NULL,
    mention_entity_type VARCHAR(20) NOT NULL,
    mention_offset_start INT UNSIGNED NULL,
    mention_offset_end INT UNSIGNED NULL,
    rejection_reason TEXT NOT NULL,
    archivist_user_id INT NOT NULL,
    ner_model_version VARCHAR(100) NULL,
    training_exported TINYINT(1) NOT NULL DEFAULT 0,
    exported_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_decision (decision_id),
    KEY idx_feedback_mention (mention_id),
    KEY idx_feedback_unexported (training_exported, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- External authority lookup cache (VIAF/Wikidata/GeoNames/TGN/GND/ISNI/SAGNC).
CREATE TABLE IF NOT EXISTS ahg_authority_lookup_cache (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source          VARCHAR(30) NOT NULL
                    COMMENT 'viaf, wikidata, geonames, tgn, gnd, isni, sagnc',
    entity_type     VARCHAR(20) NOT NULL COMMENT 'PERSON, ORG, PLACE',
    query_text      VARCHAR(500) NOT NULL,
    payload         JSON NOT NULL,
    license_note    VARCHAR(500) NULL,
    retrieved_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ttl_seconds     INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cache_source_query (source, entity_type, query_text),
    KEY idx_cache_retrieved (retrieved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
