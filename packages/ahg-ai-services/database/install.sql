-- ============================================================================
-- ahg-ai-services — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgAIPlugin/database/install.sql
-- on 2026-04-30. Heratio standalone install — Phase 1 #3.
--
-- Transforms applied:
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE → CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - VIEWs stripped (recreate by hand if needed)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- ahgAIPlugin Database Tables
-- Version: 2.1.0
-- Last Updated: 2026-02-15
--
-- Consolidated AI Plugin: NER, Translation, Summarization, Spellcheck,
-- LLM Suggestions, Job Queue, Auto-Trigger
-- ============================================================================

-- ============================================================================
-- SECTION 1: SHARED TABLES (used by all AI features)
-- ============================================================================

-- AI Settings table (central settings for all AI features)
CREATE TABLE IF NOT EXISTS ahg_ai_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature VARCHAR(50) NOT NULL DEFAULT 'general',
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_feature_key (feature, setting_key),
    INDEX idx_ai_settings_feature (feature)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API usage tracking table
CREATE TABLE IF NOT EXISTS ahg_ai_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature VARCHAR(50) NOT NULL,
    user_id INT DEFAULT NULL,
    api_key VARCHAR(100) DEFAULT NULL,
    endpoint VARCHAR(100) NOT NULL,
    request_size INT DEFAULT 0,
    response_time_ms INT DEFAULT NULL,
    status_code INT DEFAULT 200,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_usage_feature (feature),
    INDEX idx_ai_usage_user (user_id),
    INDEX idx_ai_usage_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 2: NER (Named Entity Recognition) TABLES
-- ============================================================================

-- Extraction jobs table
CREATE TABLE IF NOT EXISTS ahg_ner_extraction (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    backend_used VARCHAR(50) DEFAULT 'local',
    status VARCHAR(50) DEFAULT 'pending',
    entity_count INT DEFAULT 0,
    extracted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ner_extraction_object (object_id),
    INDEX idx_ner_extraction_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extracted entities table with review workflow
CREATE TABLE IF NOT EXISTS ahg_ner_entity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    extraction_id BIGINT UNSIGNED NULL,
    object_id INT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_value VARCHAR(500) NOT NULL,
    original_value VARCHAR(500) DEFAULT NULL,
    original_type VARCHAR(50) DEFAULT NULL,
    correction_type VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT 'none, value_edit, type_change, both, rejected, approved',
    training_exported TINYINT(1) DEFAULT 0,
    confidence DECIMAL(5,4) DEFAULT 1.0000,
    status VARCHAR(50) DEFAULT 'pending',
    linked_actor_id INT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ner_entity_extraction (extraction_id),
    INDEX idx_ner_entity_object (object_id),
    INDEX idx_ner_entity_status (status),
    INDEX idx_ner_entity_type (entity_type),
    INDEX idx_ner_entity_correction (correction_type),
    INDEX idx_ner_entity_training (training_exported),
    FOREIGN KEY (extraction_id) REFERENCES ahg_ner_extraction(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Entity linking to AtoM actors
CREATE TABLE IF NOT EXISTS ahg_ner_entity_link (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id BIGINT UNSIGNED NOT NULL,
    actor_id INT NOT NULL,
    link_type VARCHAR(32) COMMENT 'exact, fuzzy, manual' DEFAULT 'manual',
    confidence DECIMAL(5,4) DEFAULT 1.0000,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ner_link_entity (entity_id),
    INDEX idx_ner_link_actor (actor_id),
    FOREIGN KEY (entity_id) REFERENCES ahg_ner_entity(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NER-specific settings (legacy table, retained for backward compatibility)
CREATE TABLE IF NOT EXISTS ahg_ner_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ner_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NER-specific usage tracking (legacy table, retained for backward compatibility)
CREATE TABLE IF NOT EXISTS ahg_ner_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    api_key VARCHAR(100) DEFAULT NULL,
    endpoint VARCHAR(100) NOT NULL,
    request_size INT DEFAULT 0,
    response_time_ms INT DEFAULT NULL,
    status_code INT DEFAULT 200,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ner_usage_user (user_id),
    INDEX idx_ner_usage_endpoint (endpoint),
    INDEX idx_ner_usage_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 3: SPELLCHECK TABLES
-- ============================================================================

-- Spellcheck results per information object
CREATE TABLE IF NOT EXISTS ahg_spellcheck_result (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    errors_json JSON DEFAULT NULL,
    error_count INT DEFAULT 0,
    status VARCHAR(38) COMMENT 'pending, reviewed, ignored' DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_spellcheck_object (object_id),
    INDEX idx_spellcheck_status (status),
    INDEX idx_spellcheck_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 4: TRANSLATION TABLES
-- ============================================================================

-- Translation queue for batch jobs
CREATE TABLE IF NOT EXISTS ahg_translation_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    source_culture VARCHAR(10) NOT NULL,
    target_culture VARCHAR(10) NOT NULL,
    fields TEXT NOT NULL COMMENT 'JSON array of fields to translate',
    status VARCHAR(50) COMMENT 'pending, processing, completed, failed' DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_translation_queue_status (status),
    INDEX idx_translation_queue_object (object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Translation log/audit
CREATE TABLE IF NOT EXISTS ahg_translation_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    source_culture VARCHAR(10) NOT NULL,
    target_culture VARCHAR(10) NOT NULL,
    source_text TEXT DEFAULT NULL,
    translated_text TEXT DEFAULT NULL,
    translation_engine VARCHAR(50) DEFAULT 'argos',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_translation_log_object (object_id),
    INDEX idx_translation_log_cultures (source_culture, target_culture)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 5: LLM DESCRIPTION SUGGESTION TABLES
-- ============================================================================

-- LLM Provider Configurations (Ollama, OpenAI, Anthropic)
CREATE TABLE IF NOT EXISTS ahg_llm_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,              -- 'ollama', 'openai', 'anthropic'
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    endpoint_url VARCHAR(500),                  -- 'http://localhost:11434'
    api_key_encrypted TEXT,                     -- Encrypted API key (NULL for Ollama)
    model VARCHAR(100) NOT NULL,                -- 'llama3.1:8b', 'gpt-4o-mini', 'claude-3-haiku-20240307'
    max_tokens INT DEFAULT 2000,
    temperature DECIMAL(3,2) DEFAULT 0.70,
    timeout_seconds INT DEFAULT 120,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_llm_config_provider (provider),
    INDEX idx_llm_config_active (is_active),
    INDEX idx_llm_config_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prompt Templates for different description contexts
CREATE TABLE IF NOT EXISTS ahg_prompt_template (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    system_prompt TEXT NOT NULL,
    user_prompt_template TEXT NOT NULL,         -- Contains {title}, {ocr_text}, etc.
    level_of_description VARCHAR(50),           -- NULL=all, or 'fonds','series','file','item'
    repository_id INT,                          -- NULL=global
    is_default TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    include_ocr TINYINT(1) DEFAULT 1,
    max_ocr_chars INT DEFAULT 8000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prompt_template_level (level_of_description),
    INDEX idx_prompt_template_repo (repository_id),
    INDEX idx_prompt_template_default (is_default),
    INDEX idx_prompt_template_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Description Suggestions with review workflow
CREATE TABLE IF NOT EXISTS ahg_description_suggestion (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    suggested_text TEXT NOT NULL,
    existing_text TEXT,
    prompt_template_id INT UNSIGNED,
    llm_config_id INT UNSIGNED,
    source_data JSON,                           -- {has_ocr: true, fields: [...]}
    status VARCHAR(47) COMMENT 'pending, approved, rejected, edited' DEFAULT 'pending',
    edited_text TEXT,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,
    generation_time_ms INT,
    tokens_used INT,
    model_used VARCHAR(100),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_suggestion_object (object_id),
    INDEX idx_suggestion_status (status),
    INDEX idx_suggestion_created (created_at),
    INDEX idx_suggestion_template (prompt_template_id),
    INDEX idx_suggestion_llm (llm_config_id),
    FOREIGN KEY (prompt_template_id) REFERENCES ahg_prompt_template(id) ON DELETE SET NULL,
    FOREIGN KEY (llm_config_id) REFERENCES ahg_llm_config(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 6: AI JOB QUEUE TABLES
-- ============================================================================

-- Batch Jobs (container for multiple job items)
CREATE TABLE IF NOT EXISTS ahg_ai_batch (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    task_types JSON NOT NULL,                    -- ["ner", "summarize", "suggest", "translate", "spellcheck"]
    status VARCHAR(66) COMMENT 'pending, running, paused, completed, failed, cancelled' DEFAULT 'pending',
    priority TINYINT DEFAULT 5,                  -- 1=highest, 10=lowest
    total_items INT DEFAULT 0,
    completed_items INT DEFAULT 0,
    failed_items INT DEFAULT 0,
    progress_percent DECIMAL(5,2) DEFAULT 0.00,

    -- Resource throttling
    max_concurrent INT DEFAULT 5,                -- Max parallel jobs
    delay_between_ms INT DEFAULT 1000,           -- Delay between jobs (ms)
    max_retries INT DEFAULT 3,                   -- Max retries per item

    -- Scheduling
    scheduled_at TIMESTAMP NULL,                 -- When to start (NULL = immediate)
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    estimated_completion TIMESTAMP NULL,

    -- Options
    options JSON,                                -- Task-specific options

    -- Audit
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_batch_status (status),
    INDEX idx_batch_priority (priority),
    INDEX idx_batch_scheduled (scheduled_at),
    INDEX idx_batch_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual Job Items within a Batch
CREATE TABLE IF NOT EXISTS ahg_ai_job (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    object_id INT NOT NULL,
    task_type VARCHAR(50) NOT NULL,              -- 'ner', 'summarize', 'suggest', 'translate', 'spellcheck', 'ocr'
    status VARCHAR(64) COMMENT 'pending, queued, running, completed, failed, skipped' DEFAULT 'pending',
    priority TINYINT DEFAULT 5,

    -- Execution
    gearman_handle VARCHAR(255),                 -- Gearman job handle
    worker_id VARCHAR(100),                      -- Worker that processed this
    attempt_count INT DEFAULT 0,

    -- Results
    result_data JSON,                            -- Task-specific results
    error_message TEXT,
    error_code VARCHAR(50),

    -- Timing
    queued_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    processing_time_ms INT,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_job_batch (batch_id),
    INDEX idx_job_object (object_id),
    INDEX idx_job_status (status),
    INDEX idx_job_task_type (task_type),
    INDEX idx_job_batch_status (batch_id, status),
    FOREIGN KEY (batch_id) REFERENCES ahg_ai_batch(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job Event Log (for tracking progress and debugging)
CREATE TABLE IF NOT EXISTS ahg_ai_job_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED,
    job_id BIGINT UNSIGNED,
    event_type VARCHAR(50) NOT NULL,             -- 'started', 'completed', 'failed', 'retry', 'paused', 'resumed'
    message TEXT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_job_log_batch (batch_id),
    INDEX idx_job_log_job (job_id),
    INDEX idx_job_log_type (event_type),
    INDEX idx_job_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 7: AUTO-TRIGGER ON UPLOAD
-- ============================================================================

-- Pending extraction queue (fallback when Gearman unavailable)
CREATE TABLE IF NOT EXISTS ahg_ai_pending_extraction (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    digital_object_id INT DEFAULT NULL,
    task_type VARCHAR(50) NOT NULL DEFAULT 'ner',
    status VARCHAR(50) COMMENT 'pending, processing, completed, failed' DEFAULT 'pending',
    attempt_count INT DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_pending_status (status),
    INDEX idx_pending_object (object_id),
    INDEX idx_pending_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auto-trigger log for tracking and debugging
CREATE TABLE IF NOT EXISTS ahg_ai_auto_trigger_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    digital_object_id INT DEFAULT NULL,
    task_type VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auto_trigger_object (object_id),
    INDEX idx_auto_trigger_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 8: DEFAULT SEED DATA - ahg_ai_settings
-- ============================================================================

INSERT IGNORE INTO ahg_ai_settings (id, feature, setting_key, setting_value) VALUES
    -- General AI settings
    (1, 'general', 'api_url', 'http://localhost:5004/ai/v1'),
    (2, 'general', 'api_key', ''),
    (3, 'general', 'api_timeout', '60'),

    -- NER settings
    (4, 'ner', 'enabled', '1'),
    (5, 'ner', 'auto_link_exact', '0'),
    (6, 'ner', 'confidence_threshold', '0.85'),
    (7, 'ner', 'enabled_entity_types', '["PERSON","ORG","GPE","DATE"]'),

    -- Summarization settings
    (8, 'summarize', 'enabled', '1'),
    (9, 'summarize', 'max_length', '1000'),
    (10, 'summarize', 'min_length', '100'),
    (11, 'summarize', 'target_field', 'scope_and_content'),

    -- Translation settings
    (12, 'translate', 'enabled', '1'),
    (13, 'translate', 'engine', 'argos'),
    (14, 'translate', 'supported_languages', '["en","af","fr","nl","pt","es","de"]'),
    (15, 'translate', 'auto_install_packages', '0'),

    -- Spellcheck settings
    (16, 'spellcheck', 'enabled', '1'),
    (17, 'spellcheck', 'language', 'en'),
    (18, 'spellcheck', 'ignore_capitalized', '1'),

    -- Suggest (LLM description) settings
    (19, 'suggest', 'enabled', '1'),
    (20, 'suggest', 'require_review', '1'),
    (21, 'suggest', 'auto_expire_days', '30'),

    -- Job Queue settings
    (80, 'jobqueue', 'enabled', '1'),
    (81, 'jobqueue', 'default_max_concurrent', '5'),
    (82, 'jobqueue', 'default_delay_ms', '1000'),
    (83, 'jobqueue', 'default_max_retries', '3'),
    (84, 'jobqueue', 'auto_cleanup_days', '30');

-- Additional suggest settings (no explicit IDs, use INSERT IGNORE on unique key)
INSERT IGNORE INTO ahg_ai_settings (feature, setting_key, setting_value) VALUES
    ('suggest', 'default_llm_config', '1'),
    ('suggest', 'default_template', '1'),
    ('suggest', 'max_pending_per_object', '3');

-- Additional job queue settings
INSERT IGNORE INTO ahg_ai_settings (feature, setting_key, setting_value) VALUES
    ('jobqueue', 'pause_on_high_load', '1'),
    ('jobqueue', 'high_load_threshold', '80'),
    ('jobqueue', 'notification_email', ''),
    ('jobqueue', 'notify_on_complete', '0'),
    ('jobqueue', 'notify_on_failure', '1');

-- NER auto-trigger settings
INSERT IGNORE INTO ahg_ai_settings (feature, setting_key, setting_value) VALUES
    ('ner', 'auto_trigger_on_upload', '0'),
    ('ner', 'auto_trigger_mime_types', '["application/pdf","text/plain"]'),
    ('ner', 'auto_trigger_min_file_size', '100'),
    ('ner', 'auto_trigger_max_file_size', '52428800');

-- ============================================================================
-- SECTION 9: DEFAULT SEED DATA - ahg_ner_settings
-- ============================================================================

INSERT IGNORE INTO ahg_ner_settings (id, setting_key, setting_value) VALUES
    (1, 'backend', 'local'),
    (2, 'api_url', 'http://localhost:5004/ai/v1'),
    (3, 'api_key', ''),
    (4, 'auto_extract', '0'),
    (5, 'require_review', '1'),
    (6, 'ner_enabled', '1'),
    (7, 'summarizer_enabled', '1'),
    (8, 'spellcheck_enabled', '0'),
    (9, 'processing_mode', 'job'),
    (10, 'summary_field', 'scopeAndContent'),
    (11, 'api_timeout', '60'),
    (12, 'auto_extract_on_upload', '0'),
    (18, 'extract_from_pdf', '1'),
    (19, 'translation_enabled', '1');

-- ============================================================================
-- SECTION 10: DEFAULT SEED DATA - ahg_llm_config
-- ============================================================================

-- Default Ollama configuration (local)
INSERT IGNORE INTO ahg_llm_config (id, provider, name, is_active, is_default, endpoint_url, api_key_encrypted, model, max_tokens, temperature, timeout_seconds)
VALUES (1, 'ollama', 'Local Ollama (llama3.1:8b)', 1, 1, 'http://localhost:11434', NULL, 'llama3.1:8b', 2000, 0.70, 120);

-- OpenAI placeholder (disabled by default, needs API key)
INSERT IGNORE INTO ahg_llm_config (id, provider, name, is_active, is_default, endpoint_url, api_key_encrypted, model, max_tokens, temperature, timeout_seconds)
VALUES (2, 'openai', 'OpenAI GPT-4o-mini', 0, 0, 'https://api.openai.com/v1', NULL, 'gpt-4o-mini', 2000, 0.70, 60);

-- Anthropic placeholder (disabled by default, needs API key)
INSERT IGNORE INTO ahg_llm_config (id, provider, name, is_active, is_default, endpoint_url, api_key_encrypted, model, max_tokens, temperature, timeout_seconds)
VALUES (3, 'anthropic', 'Anthropic Claude Haiku', 0, 0, 'https://api.anthropic.com/v1', NULL, 'claude-3-haiku-20240307', 2000, 0.70, 60);

-- ============================================================================
-- SECTION 11: DEFAULT PROMPT TEMPLATES
-- ============================================================================

-- Standard Archival Description Template
INSERT IGNORE INTO ahg_prompt_template (name, slug, system_prompt, user_prompt_template, level_of_description, is_default, is_active, include_ocr, max_ocr_chars)
VALUES (
    'Standard Archival Description',
    'standard-archival',
    'You are an expert archivist creating scope and content descriptions for archival records. Write professional, objective descriptions following ISAD(G) standards. Focus on:
- What the materials document
- The activities, functions, or transactions they record
- Any significant persons, organizations, places, or events mentioned
- The date range and extent of materials
- The arrangement and organization

Write in third person, past tense. Be concise but comprehensive. Do not include subjective assessments or opinions.',
    'Create a scope and content description for the following archival record:

Title: {title}
Reference Code: {identifier}
Level of Description: {level_of_description}
Date Range: {date_range}
Creator: {creator}
Repository: {repository}

{existing_metadata}

{ocr_section}

Based on the above information, write a professional scope and content description (2-4 paragraphs).',
    NULL,
    1,
    1,
    1,
    8000
);

-- Item-Level OCR Focus Template
INSERT IGNORE INTO ahg_prompt_template (name, slug, system_prompt, user_prompt_template, level_of_description, is_default, is_active, include_ocr, max_ocr_chars)
VALUES (
    'Item-Level with OCR',
    'item-ocr',
    'You are an expert archivist creating item-level descriptions based primarily on OCR text extracted from documents. Your task is to:
- Summarize the main content and purpose of the document
- Identify key persons, organizations, and places mentioned
- Note significant dates and events
- Describe the document type and any notable features

Write in third person, past tense. Be concise and accurate. If the OCR text is fragmentary, acknowledge limitations.',
    'Create a scope and content description for this item:

Title: {title}
Reference Code: {identifier}
Date: {date_range}
Document Type: {extent_and_medium}

The following OCR text was extracted from the document:
---
{ocr_text}
---

Based on the document content, write a scope and content description (1-2 paragraphs) summarizing what this document contains and its significance.',
    'item',
    0,
    1,
    1,
    12000
);

-- Photograph/Image Description Template
INSERT IGNORE INTO ahg_prompt_template (name, slug, system_prompt, user_prompt_template, level_of_description, is_default, is_active, include_ocr, max_ocr_chars)
VALUES (
    'Photograph Description',
    'photograph',
    'You are an expert archivist creating descriptions for historical photographs. Focus on:
- The subject matter and scene depicted
- Identifiable persons, places, and events
- The photographic technique and format
- Historical context and significance

Write in third person, past tense. Be objective and descriptive. Note any inscriptions, captions, or annotations.',
    'Create a scope and content description for this photograph:

Title: {title}
Reference Code: {identifier}
Date: {date_range}
Physical Description: {extent_and_medium}
Creator: {creator}

{existing_metadata}

{ocr_section}

Based on the available information, write a scope and content description (1-2 paragraphs) for this photograph.',
    'item',
    0,
    1,
    1,
    4000
);

-- ============================================================================
-- SECTION 12: MIGRATION - Move data from old ahg_ner_settings if exists
-- ============================================================================

SET @ner_settings_exists = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ahg_ner_settings');

SET @migration_sql = IF(@ner_settings_exists > 0,
    'INSERT IGNORE INTO ahg_ai_settings (feature, setting_key, setting_value) SELECT CASE WHEN setting_key LIKE ''summarizer_%'' THEN ''summarize'' ELSE ''ner'' END, CASE WHEN setting_key = ''summarizer_max_length'' THEN ''max_length'' WHEN setting_key = ''summarizer_min_length'' THEN ''min_length'' ELSE setting_key END, setting_value FROM ahg_ner_settings WHERE setting_key NOT IN (''api_url'', ''api_key'', ''api_timeout'')',
    'SELECT 1'
);

PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET FOREIGN_KEY_CHECKS = 1;
