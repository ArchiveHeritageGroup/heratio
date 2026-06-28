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
    -- General AI settings (api_url = AHG AI gateway, never a direct node — #1368)
    (1, 'general', 'api_url', 'https://ai.theahg.co.za/ai/v1'),
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
    (2, 'api_url', 'https://ai.theahg.co.za/ai/v1'),   -- AHG AI gateway, never a direct node (#1368)
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

-- Default Ollama configuration — routed through the AHG AI gateway, never a
-- direct :11434 node (#1368). The LlmService node-guard rejects a stale node
-- endpoint_url at runtime regardless, but seed the gateway so fresh installs
-- are correct out of the box.
INSERT IGNORE INTO ahg_llm_config (id, provider, name, is_active, is_default, endpoint_url, api_key_encrypted, model, max_tokens, temperature, timeout_seconds)
VALUES (1, 'ollama', 'AHG AI Gateway (Ollama)', 1, 1, 'https://ai.theahg.co.za/ai/v1', NULL, 'llama3.1:8b', 2000, 0.70, 120);

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

-- ============================================================================
-- SECTION 13: OCR settings (#665 Phase 4 - multi-lang Tesseract + LLM post-correction)
-- ============================================================================
-- All keys live under feature='ocr'. Defaults are conservative:
--   - LLM post-correction OFF until the operator opts in
--   - Multi-language default = SA core (osd + eng + afr)
--   - Min-confidence gate = 70 (only post-correct pages Tesseract was unsure about)
-- The Tesseract binary path defaults to 'tesseract' so $PATH resolution works.
-- `ahg:tesseract:list-languages` populates the *_languages* keys.

INSERT IGNORE INTO ahg_ai_settings (feature, setting_key, setting_value) VALUES
    ('ocr', 'ocr_tesseract_binary',              'tesseract'),
    ('ocr', 'ocr_default_languages',             'osd+eng+afr'),
    ('ocr', 'ocr_default_psm',                   '3'),
    ('ocr', 'ocr_default_oem',                   '3'),
    ('ocr', 'ocr_llm_correction_enabled',        '0'),
    ('ocr', 'ocr_llm_correction_min_confidence', '70'),
    ('ocr', 'ocr_premis_events_enabled',         '1');

-- ============================================================================
-- SECTION 7: Issue #667 Phase 1 — quotas, cost tracking, translation memory,
-- custom NER entities, face detection placeholder
-- ============================================================================

-- Per-tenant quotas. `tenant_id = 0` represents the global default ("any
-- tenant"); the service auto-seeds one row per service with daily/monthly
-- limits of 0 meaning "unlimited". Operators override per tenant.
CREATE TABLE IF NOT EXISTS ahg_ai_quota (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 0,
    service VARCHAR(32) NOT NULL,
    daily_limit INT NOT NULL DEFAULT 0,
    monthly_limit INT NOT NULL DEFAULT 0,
    used_today INT NOT NULL DEFAULT 0,
    used_this_month INT NOT NULL DEFAULT 0,
    reset_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
    last_reset_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_quota_tenant_service (tenant_id, service),
    INDEX idx_quota_service (service)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-call cost ledger. One row per inference dispatch, cross-linked into
-- the inference-receipt chain via `request_id`. cost_usd is computed from
-- ahg_ai_pricing at insert time; null = no pricing row for this model.
CREATE TABLE IF NOT EXISTS ahg_ai_call_cost (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 0,
    service VARCHAR(32) NOT NULL,
    model_id VARCHAR(128) NOT NULL,
    tokens_in INT NOT NULL DEFAULT 0,
    tokens_out INT NOT NULL DEFAULT 0,
    cost_usd DECIMAL(10,4) NULL,
    duration_ms INT NULL,
    request_id VARCHAR(64) NULL,
    called_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_call_cost_tenant (tenant_id),
    INDEX idx_call_cost_service (service),
    INDEX idx_call_cost_called_at (called_at),
    INDEX idx_call_cost_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-model pricing reference. Tokens-per-1k convention matches OpenAI /
-- Anthropic published rates. currency defaults to USD; operator may extend
-- to ZAR/EUR/etc. The cost service falls back to NULL cost_usd when no row
-- matches the model_id.
CREATE TABLE IF NOT EXISTS ahg_ai_pricing (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id VARCHAR(128) NOT NULL,
    input_cost_per_1k_tokens DECIMAL(10,6) NOT NULL DEFAULT 0,
    output_cost_per_1k_tokens DECIMAL(10,6) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pricing_model (model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Translation memory. SHA-256 of (source_text || '\0' || source_lang ||
-- '\0' || target_lang) is the lookup key. Provenance distinguishes human
-- review from machine, gateway from local, etc.
CREATE TABLE IF NOT EXISTS ahg_translation_memory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_text_hash CHAR(64) NOT NULL,
    source_lang CHAR(8) NOT NULL DEFAULT '',
    target_lang CHAR(8) NOT NULL,
    source_text TEXT NOT NULL,
    target_text TEXT NOT NULL,
    provenance VARCHAR(32) NOT NULL DEFAULT 'machine',
    confidence FLOAT NULL,
    hit_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tm_hash_target (source_text_hash, target_lang),
    INDEX idx_tm_target (target_lang),
    INDEX idx_tm_provenance (provenance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Operator-curated NER gazetteer. NerService::extract() does an
-- exact + alias substring pre-pass against this table before invoking the
-- ML model so high-value local labels (project names, place names, etc.)
-- are never missed by the ML side.
CREATE TABLE IF NOT EXISTS ahg_ner_custom_entity (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(64) NOT NULL,
    label VARCHAR(255) NOT NULL,
    aliases JSON NULL,
    definition TEXT NULL,
    target_uri VARCHAR(512) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ner_custom_type (entity_type),
    INDEX idx_ner_custom_label (label),
    INDEX idx_ner_custom_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default quotas (unlimited) for the seven gated services on the
-- global tenant row. Operators override per tenant via the admin UI.
INSERT IGNORE INTO ahg_ai_quota (tenant_id, service, daily_limit, monthly_limit) VALUES
    (0, 'llm',         0, 0),
    (0, 'ner',         0, 0),
    (0, 'htr',         0, 0),
    (0, 'donut',       0, 0),
    (0, 'translate',   0, 0),
    (0, 'spellcheck',  0, 0),
    (0, 'face_detect', 0, 0);

-- Seed common pricing rows so cost tracking has something to read on day 1.
-- Operators update via the admin UI; rates here are 2026-Q1 published.
INSERT IGNORE INTO ahg_ai_pricing (model_id, input_cost_per_1k_tokens, output_cost_per_1k_tokens, currency, notes) VALUES
    ('local',                0.000000, 0.000000, 'USD', 'local Ollama / vLLM endpoint - amortised, not metered'),
    ('gpt-4o-mini',          0.000150, 0.000600, 'USD', 'OpenAI gpt-4o-mini Q1-2026'),
    ('gpt-4o',               0.002500, 0.010000, 'USD', 'OpenAI gpt-4o Q1-2026'),
    ('claude-3-5-sonnet',    0.003000, 0.015000, 'USD', 'Anthropic Claude 3.5 Sonnet Q1-2026'),
    ('claude-3-5-haiku',     0.000800, 0.004000, 'USD', 'Anthropic Claude 3.5 Haiku Q1-2026'),
    ('qwen3:14b',            0.000000, 0.000000, 'USD', 'self-hosted Ollama'),
    ('qwen3:8b',             0.000000, 0.000000, 'USD', 'self-hosted Ollama'),
    ('htr-gateway',          0.000000, 0.000000, 'USD', 'AHG HTR gateway - amortised'),
    ('donut-gateway',        0.000000, 0.000000, 'USD', 'AHG Donut gateway - amortised'),
    ('ner-gateway',          0.000000, 0.000000, 'USD', 'AHG NER gateway - amortised');

-- Face detection settings.
INSERT IGNORE INTO ahg_ai_settings (feature, setting_key, setting_value) VALUES
    ('face_detect', 'enabled',        '0'),
    ('face_detect', 'driver',         'null'),
    ('face_detect', 'api_url',        ''),
    ('face_detect', 'api_key',        ''),
    ('face_detect', 'min_confidence', '0.70');

-- ============================================================================
-- Suggested Connections cache (North Star generative scholarship #1210)
-- Stores the LLM-written hypothesis for a non-obvious record pair, keyed by
-- the ordered pair (object_id_1 < object_id_2) so each pair is cached once.
-- Candidate discovery is pure SQL over object_term_relation / relation; only
-- the explanation is persisted here.
-- ============================================================================
CREATE TABLE IF NOT EXISTS ahg_suggested_connection (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id_1 INT NOT NULL,
    object_id_2 INT NOT NULL,
    shared_count INT NOT NULL DEFAULT 0,
    shared_terms VARCHAR(2000) DEFAULT NULL,
    explanation TEXT DEFAULT NULL,
    model VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_pair (object_id_1, object_id_2),
    INDEX idx_obj1 (object_id_1),
    INDEX idx_obj2 (object_id_2)
);

SET FOREIGN_KEY_CHECKS = 1;
