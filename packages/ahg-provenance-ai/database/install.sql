-- ============================================================================
-- ahg-provenance-ai - install schema
-- ============================================================================
-- Issue:  ArchiveHeritageGroup/heratio#61
-- ADR:    docs/adr/0002-provenance-discipline-baseline.md
-- Date:   2026-05-04
--
-- Tables:
--   ahg_ai_inference  - one row per AI inference write (NER entity, HTR page,
--                       translation pass, LLM completion, ...). Created BEFORE
--                       the target row is written so the target carries an
--                       inference_id FK or its triple can reference the
--                       inference UUID in the Fuseki RDF-Star annotation.
--   ahg_ai_override   - one row per human reviewer correction of an AI
--                       inference. The original inference row is never
--                       deleted; effective value = latest non-rejected
--                       override OR the original output if no override.
--
-- Storage strategy: MySQL is the operational store (fast filtering, dashboards,
-- review queues). Fuseki RDF-Star is the canonical defensible semantic record.
-- Every write goes through AhgProvenanceAi\Services\InferenceService which
-- writes the SQL row first, then enqueues a Fuseki insert. A NULL
-- fuseki_graph_uri means the Fuseki write is pending or failed; a separate
-- replay job will retry.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `ahg_ai_inference` (
    `id`                  bigint unsigned NOT NULL AUTO_INCREMENT,
    `uuid`                char(36) COLLATE utf8mb4_unicode_ci NOT NULL                              COMMENT 'External-facing identifier; used as the activity URI in Fuseki',
    `service_name`        varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL                            COMMENT 'NER, HTR, TRANSLATION, LLM, DONUT, OCR (uppercase)',
    `model_name`          varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL                           COMMENT 'Free-text identifier as reported by the model: spaCy en_core_web_sm, NLLB-200-distilled-600M, qwen3:8b',
    `model_version`       varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown'          COMMENT 'Version string when retrievable; literal "unknown" otherwise',
    `endpoint`            varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL                       COMMENT 'URL the inference was performed against, for forensics',
    `input_hash`          char(64) COLLATE utf8mb4_unicode_ci NOT NULL                              COMMENT 'sha256 of canonical input bytes',
    `input_excerpt`       varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL                       COMMENT 'First 500 chars of input, truncated; for human inspection only',
    `output_hash`         char(64) COLLATE utf8mb4_unicode_ci NOT NULL                              COMMENT 'sha256 of canonical output (json_encode for structured)',
    `output_excerpt`      varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL                       COMMENT 'First 500 chars of output',
    `confidence`          decimal(6,5) DEFAULT NULL                                                  COMMENT 'Normalised 0.0-1.0; NULL when model does not expose a score',
    `standard`            varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL                        COMMENT 'ICIP, ISAD(G), Spectrum-5.1, RiC-O, etc. - inference-site declaration',
    `target_entity_type`  varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL                            COMMENT 'information_object, actor, term, museum_metadata, ...',
    `target_entity_id`    bigint NOT NULL                                                            COMMENT 'PK in the entity table',
    `target_field`        varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL                            COMMENT 'Column / RDF predicate touched by the inference',
    `elapsed_ms`          int DEFAULT NULL                                                           COMMENT 'Service call latency for ops dashboards',
    `fuseki_graph_uri`    varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL                       COMMENT 'NULL until the RDF-Star annotation has been written to Fuseki',
    `user_id`             int DEFAULT NULL                                                           COMMENT 'Triggering user when known (NULL for batch / cron paths)',
    `occurred_at`         datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`          datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ai_inference_uuid` (`uuid`),
    KEY `idx_ai_inference_target` (`target_entity_type`, `target_entity_id`),
    KEY `idx_ai_inference_field` (`target_entity_type`, `target_entity_id`, `target_field`),
    KEY `idx_ai_inference_service_time` (`service_name`, `occurred_at`),
    KEY `idx_ai_inference_input_hash` (`input_hash`),
    KEY `idx_ai_inference_output_hash` (`output_hash`),
    KEY `idx_ai_inference_fuseki_pending` (`fuseki_graph_uri`(1), `created_at`),
    KEY `idx_ai_inference_user` (`user_id`, `occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ahg_ai_override` (
    `id`                  bigint unsigned NOT NULL AUTO_INCREMENT,
    `uuid`                char(36) COLLATE utf8mb4_unicode_ci NOT NULL                              COMMENT 'External-facing identifier; used as the override activity URI in Fuseki',
    `inference_id`        bigint unsigned NOT NULL                                                   COMMENT 'FK to ahg_ai_inference - the inference being corrected',
    `reviewer_user_id`    int NOT NULL                                                               COMMENT 'User who issued the correction',
    `reason`              varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL                       COMMENT 'Free-text rationale; nullable but encouraged',
    `original_value`      text COLLATE utf8mb4_unicode_ci NOT NULL                                   COMMENT 'Snapshot of what the AI produced (for the trace, even though it is also derivable from the inference row)',
    `override_value`      text COLLATE utf8mb4_unicode_ci NOT NULL                                   COMMENT 'What the reviewer set the field to',
    `status`              varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'applied'          COMMENT 'applied, rejected, superseded - lifecycle of the correction',
    `fuseki_override_uri` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL                       COMMENT 'NULL until the prov:Activity has been written to Fuseki',
    `occurred_at`         datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`          datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ai_override_uuid` (`uuid`),
    KEY `idx_ai_override_inference` (`inference_id`),
    KEY `idx_ai_override_reviewer` (`reviewer_user_id`, `occurred_at`),
    KEY `idx_ai_override_status` (`status`, `occurred_at`),
    KEY `idx_ai_override_fuseki_pending` (`fuseki_override_uri`(1), `created_at`),
    CONSTRAINT `fk_ai_override_inference` FOREIGN KEY (`inference_id`) REFERENCES `ahg_ai_inference` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
