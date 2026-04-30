# ahgAIPlugin - Technical Documentation

**Version:** 2.2.0
**Category:** AI
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Consolidated AI-powered tools plugin for AtoM providing Named Entity Recognition (NER), Translation, Summarization, Spellcheck, Handwriting Text Recognition (HTR), and **LLM-powered Description Suggestions**. This plugin consolidates previously separate NER and Translation plugins into a unified AI tools suite.

### Key Features

| Feature | Description | Backend |
|---------|-------------|---------|
| NER | Named Entity Recognition (persons, organizations, places, dates) | Python/spaCy |
| Translate | Offline machine translation | Argos Translate |
| Summarize | AI-powered text summarization | Python |
| Spellcheck | Spelling and grammar checking | Python/aspell |
| HTR | Handwriting Text Recognition | Python/TrOCR |
| **Suggest Description** | LLM-based scope_and_content generation from OCR/metadata | Ollama/OpenAI/Anthropic |
| **Job Queue** | Batch processing with progress tracking, throttling, retry | Gearman/Cron |

---

## Architecture

```
+-------------------------------------------------------------------------+
|                            ahgAIPlugin                                   |
+-------------------------------------------------------------------------+
|                                                                          |
|  +--------------------------------------------------------------------+  |
|  |                       Web Interface Layer                           |  |
|  |  +--------------+  +---------------+  +--------------------+        |  |
|  |  | AI Module    |  | Components    |  | Templates          |        |  |
|  |  | (actions.php)|  | (buttons)     |  | (review modals)    |        |  |
|  |  +--------------+  +---------------+  +--------------------+        |  |
|  +--------------------------------------------------------------------+  |
|                                 |                                        |
|                                 v                                        |
|  +--------------------------------------------------------------------+  |
|  |                        Service Layer                                |  |
|  |  +----------------+  +----------------+  +--------------------+     |  |
|  |  | NerService     |  | NerRepository  |  | NerTrainingSync    |     |  |
|  |  | - extract()    |  | - save/get     |  | - pushCorrections  |     |  |
|  |  | - summarize()  |  |   entities     |  | - exportToFile     |     |  |
|  |  +----------------+  +----------------+  +--------------------+     |  |
|  |                                                                     |  |
|  |  +--------------------+  +--------------------+  +--------------+   |  |
|  |  | DescriptionService |  | LlmService         |  | PromptService|   |  |
|  |  | - generateSugg()   |  | - getProvider()    |  | - getTempl() |   |  |
|  |  | - approve/reject() |  | - complete()       |  | - buildPrmpt |   |  |
|  |  | - gatherContext()  |  | - encrypt/decrypt  |  +--------------+   |  |
|  |  +--------------------+  +--------------------+                     |  |
|  |                                                                     |  |
|  |  +--------------------+                                             |  |
|  |  | JobQueueService    |  ← Batch processing orchestrator            |  |
|  |  | - createBatch()    |                                             |  |
|  |  | - processJob()     |                                             |  |
|  |  | - checkServerLoad()|                                             |  |
|  |  +--------------------+                                             |  |
|  |                                  |                                  |  |
|  |              +-------------------+-------------------+              |  |
|  |              v                   v                   v              |  |
|  |  +------------------+  +------------------+  +------------------+   |  |
|  |  | OllamaProvider   |  | OpenAIProvider   |  | AnthropicProvider|   |  |
|  |  | - complete()     |  | - complete()     |  | - complete()     |   |  |
|  |  | - getModels()    |  | - getModels()    |  | - getModels()    |   |  |
|  |  +------------------+  +------------------+  +------------------+   |  |
|  +--------------------------------------------------------------------+  |
|                                 |                                        |
|                                 v                                        |
|  +--------------------------------------------------------------------+  |
|  |                    Background Job Layer                             |  |
|  |  +------------------+  +------------------+                         |  |
|  |  | arNerExtractJob  |  | ahgMediaTranscr. |                         |  |
|  |  | - Gearman job    |  | - Transcription  |                         |  |
|  |  | - NER + Summarize|  |   job            |                         |  |
|  |  +------------------+  +------------------+                         |  |
|  +--------------------------------------------------------------------+  |
|                                 |                                        |
|                                 v                                        |
|  +--------------------------------------------------------------------+  |
|  |                       CLI Task Layer                                |  |
|  |  +------------+ +------------+ +------------+ +------------+        |  |
|  |  | ai:ner-    | | ai:trans-  | | ai:summ-   | | ai:spell-  |        |  |
|  |  | extract    | | late       | | arize      | | check      |        |  |
|  |  +------------+ +------------+ +------------+ +------------+        |  |
|  |  +------------+ +------------+ +------------+ +-------------------+ |  |
|  |  | ai:install | | ai:uninstl | | ai:ner-sync| | ai:suggest-descr. | |  |
|  |  +------------+ +------------+ +------------+ +-------------------+ |  |
|  +--------------------------------------------------------------------+  |
|                                 |                                        |
|                                 v                                        |
|  +--------------------------------------------------------------------+  |
|  |                    External Services                                |  |
|  |  +------------------+  +------------------+  +------------------+   |  |
|  |  | Python AI API    |  | Argos Translate  |  | LLM Providers    |   |  |
|  |  | (spaCy NER)      |  | (offline)        |  | - Ollama (local) |   |  |
|  |  | (Summarization)  |  +------------------+  | - OpenAI API     |   |  |
|  |  | (HTR models)     |  | aspell           |  | - Anthropic API  |   |  |
|  |  +------------------+  | (spellcheck)     |  +------------------+   |  |
|  |                        +------------------+                         |  |
|  +--------------------------------------------------------------------+  |
|                                                                          |
+-------------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+-----------------------------+     +----------------------------+
|      ahg_ai_settings        |     |       ahg_ai_usage         |
+-----------------------------+     +----------------------------+
| PK id INT AUTO_INCREMENT    |     | PK id BIGINT UNSIGNED      |
|    feature VARCHAR(50)      |     |    feature VARCHAR(50)     |
|    setting_key VARCHAR(100) |     |    user_id INT             |
|    setting_value TEXT       |     |    api_key VARCHAR(100)    |
|    updated_at TIMESTAMP     |     |    endpoint VARCHAR(100)   |
+-----------------------------+     |    request_size INT        |
                                    |    response_time_ms INT    |
                                    |    status_code INT         |
                                    |    ip_address VARCHAR(45)  |
                                    |    created_at TIMESTAMP    |
                                    +----------------------------+

+--------------------------------+
|       ahg_ner_extraction       |
+--------------------------------+
| PK id BIGINT UNSIGNED          |
|    object_id INT               |----+
|    backend_used VARCHAR(50)    |    |
|    status VARCHAR(50)          |    |
|    entity_count INT            |    |
|    extracted_at TIMESTAMP      |    |
+--------------------------------+    |
              |                       |
              | 1:N                   |
              v                       |
+--------------------------------+    |
|        ahg_ner_entity          |    |
+--------------------------------+    |
| PK id BIGINT UNSIGNED          |    |
| FK extraction_id BIGINT        |----+
| FK object_id INT               |-----> information_object
|    entity_type VARCHAR(50)     |
|    entity_value VARCHAR(500)   |
|    original_value VARCHAR(500) |  (for training)
|    original_type VARCHAR(50)   |  (for training)
|    correction_type ENUM        |  (training feedback)
|    training_exported TINYINT   |
|    confidence DECIMAL(5,4)     |
|    status VARCHAR(50)          |
|    linked_actor_id INT         |-----> actor / term
|    reviewed_by INT             |-----> user
|    reviewed_at TIMESTAMP       |
|    created_at TIMESTAMP        |
+--------------------------------+
              |
              | 1:N
              v
+--------------------------------+
|      ahg_ner_entity_link       |
+--------------------------------+
| PK id BIGINT UNSIGNED          |
| FK entity_id BIGINT            |
| FK actor_id INT                |-----> actor
|    link_type ENUM              |
|    confidence DECIMAL(5,4)     |
|    created_by INT              |
|    created_at TIMESTAMP        |
+--------------------------------+

+--------------------------------+
|    ahg_translation_queue       |
+--------------------------------+
| PK id BIGINT UNSIGNED          |
|    object_id INT               |-----> information_object
|    source_culture VARCHAR(10)  |
|    target_culture VARCHAR(10)  |
|    fields TEXT (JSON)          |
|    status ENUM                 |
|    error_message TEXT          |
|    created_by INT              |
|    created_at TIMESTAMP        |
|    processed_at TIMESTAMP      |
+--------------------------------+

+--------------------------------+
|     ahg_translation_log        |
+--------------------------------+
| PK id BIGINT UNSIGNED          |
|    object_id INT               |-----> information_object
|    field_name VARCHAR(100)     |
|    source_culture VARCHAR(10)  |
|    target_culture VARCHAR(10)  |
|    source_text TEXT            |
|    translated_text TEXT        |
|    translation_engine VARCHAR  |
|    created_by INT              |
|    created_at TIMESTAMP        |
+--------------------------------+

+--------------------------------+       +--------------------------------+
|       ahg_llm_config           |       |     ahg_prompt_template        |
+--------------------------------+       +--------------------------------+
| PK id INT UNSIGNED             |       | PK id INT UNSIGNED             |
|    provider VARCHAR(50)        |       |    name VARCHAR(100)           |
|    name VARCHAR(100) UNIQUE    |       |    slug VARCHAR(100) UNIQUE    |
|    is_active TINYINT(1)        |       |    system_prompt TEXT          |
|    is_default TINYINT(1)       |       |    user_prompt_template TEXT   |
|    endpoint_url VARCHAR(500)   |       |    level_of_description VARCHAR|
|    api_key_encrypted TEXT      |       |    repository_id INT           |
|    model VARCHAR(100)          |       |    is_default TINYINT(1)       |
|    max_tokens INT              |       |    is_active TINYINT(1)        |
|    temperature DECIMAL(3,2)    |       |    include_ocr TINYINT(1)      |
|    timeout_seconds INT         |       |    max_ocr_chars INT           |
|    created_at TIMESTAMP        |       |    created_at TIMESTAMP        |
|    updated_at TIMESTAMP        |       +--------------------------------+
+--------------------------------+
         |
         | 1:N
         v
+--------------------------------+
|  ahg_description_suggestion    |
+--------------------------------+
| PK id BIGINT UNSIGNED          |
|    object_id INT               |-----> information_object
|    suggested_text TEXT         |
|    existing_text TEXT          |
| FK prompt_template_id INT      |-----> ahg_prompt_template
| FK llm_config_id INT           |-----> ahg_llm_config
|    source_data JSON            |
|    status ENUM                 |  (pending/approved/rejected/edited)
|    edited_text TEXT            |
|    reviewed_by INT             |-----> user
|    reviewed_at TIMESTAMP       |
|    review_notes TEXT           |
|    generation_time_ms INT      |
|    tokens_used INT             |
|    model_used VARCHAR(100)     |
|    created_by INT              |
|    created_at TIMESTAMP        |
|    expires_at TIMESTAMP        |
+--------------------------------+
```

### SQL Schema

```sql
-- AI Settings table (replaces ahg_ner_settings)
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

-- NER Extraction jobs table
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
    correction_type ENUM('none', 'value_edit', 'type_change', 'both', 'rejected', 'approved') DEFAULT 'none',
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
    INDEX idx_ner_entity_training (training_exported)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Entity linking to AtoM actors
CREATE TABLE IF NOT EXISTS ahg_ner_entity_link (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id BIGINT UNSIGNED NOT NULL,
    actor_id INT NOT NULL,
    link_type ENUM('exact', 'fuzzy', 'manual') DEFAULT 'manual',
    confidence DECIMAL(5,4) DEFAULT 1.0000,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ner_link_entity (entity_id),
    INDEX idx_ner_link_actor (actor_id),
    FOREIGN KEY (entity_id) REFERENCES ahg_ner_entity(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Translation queue
CREATE TABLE IF NOT EXISTS ahg_translation_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    source_culture VARCHAR(10) NOT NULL,
    target_culture VARCHAR(10) NOT NULL,
    fields TEXT NOT NULL COMMENT 'JSON array of fields to translate',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_translation_queue_status (status),
    INDEX idx_translation_queue_object (object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Translation log
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

-- LLM Configuration (Ollama, OpenAI, Anthropic)
CREATE TABLE IF NOT EXISTS ahg_llm_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    endpoint_url VARCHAR(500),
    api_key_encrypted TEXT,
    model VARCHAR(100) NOT NULL,
    max_tokens INT DEFAULT 2000,
    temperature DECIMAL(3,2) DEFAULT 0.70,
    timeout_seconds INT DEFAULT 120,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_llm_config_provider (provider),
    INDEX idx_llm_config_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prompt Templates for LLM Description Suggestions
CREATE TABLE IF NOT EXISTS ahg_prompt_template (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    system_prompt TEXT NOT NULL,
    user_prompt_template TEXT NOT NULL,
    level_of_description VARCHAR(50) DEFAULT NULL,
    repository_id INT DEFAULT NULL,
    is_default TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    include_ocr TINYINT(1) DEFAULT 1,
    max_ocr_chars INT DEFAULT 8000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prompt_template_level (level_of_description),
    INDEX idx_prompt_template_repo (repository_id),
    INDEX idx_prompt_template_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Description Suggestions with Review Workflow
CREATE TABLE IF NOT EXISTS ahg_description_suggestion (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    suggested_text TEXT NOT NULL,
    existing_text TEXT,
    prompt_template_id INT UNSIGNED,
    llm_config_id INT UNSIGNED,
    source_data JSON,
    status ENUM('pending','approved','rejected','edited') DEFAULT 'pending',
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
    INDEX idx_suggestion_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Default Settings

| Feature | Setting Key | Default Value | Description |
|---------|-------------|---------------|-------------|
| general | api_url | http://192.168.0.112:5004/ai/v1 | AI API base URL |
| general | api_key | ahg_ai_demo_internal_2026 | API authentication key |
| general | api_timeout | 60 | Request timeout (seconds) |
| ner | enabled | 1 | Enable NER feature |
| ner | auto_link_exact | 0 | Auto-link exact matches |
| ner | confidence_threshold | 0.85 | Minimum confidence score |
| ner | enabled_entity_types | ["PERSON","ORG","GPE","DATE"] | Active entity types |
| summarize | enabled | 1 | Enable summarization |
| summarize | max_length | 1000 | Maximum summary length |
| summarize | min_length | 100 | Minimum summary length |
| summarize | target_field | scope_and_content | Field to populate |
| translate | enabled | 1 | Enable translation |
| translate | engine | argos | Translation engine |
| translate | supported_languages | ["en","af","fr","nl","pt","es","de"] | Available languages |
| spellcheck | enabled | 1 | Enable spellcheck |
| spellcheck | language | en | Default language |
| suggest | enabled | 1 | Enable LLM description suggestions |
| suggest | require_review | 1 | Require custodian review before applying |
| suggest | auto_expire_days | 30 | Auto-expire pending suggestions |
| suggest | default_llm_config | 1 | Default LLM configuration ID |
| suggest | default_template | 1 | Default prompt template ID |

### Default LLM Configurations

| Provider | Name | Model | Endpoint | Default |
|----------|------|-------|----------|---------|
| ollama | Ollama Local | llama3.1:8b | http://localhost:11434 | Yes |
| openai | OpenAI | gpt-4o-mini | https://api.openai.com | No |
| anthropic | Anthropic | claude-3-haiku-20240307 | https://api.anthropic.com | No |

### Default Prompt Templates

| Name | Slug | Use Case |
|------|------|----------|
| Standard Archival | standard-archival | General archival descriptions |
| Item-Level OCR | item-level-ocr | Items with OCR text available |
| Photograph | photograph | Photograph descriptions |

---

## Service Methods

### ahgNerService

```php
class ahgNerService
{
    /**
     * Extract named entities from text
     * @param string $text Input text
     * @param bool $clean Clean text before processing
     * @return array ['success' => bool, 'entities' => ['PERSON' => [], 'ORG' => [], ...]]
     */
    public function extract($text, $clean = true)

    /**
     * Extract entities from PDF file
     * @param string $filePath Path to PDF file
     * @return array
     */
    public function extractFromPdf($filePath)

    /**
     * Generate summary from text
     * @param string $text Input text
     * @param int $maxLength Maximum summary length
     * @param int $minLength Minimum summary length
     * @return array ['success' => bool, 'summary' => string]
     */
    public function summarize($text, $maxLength = 1000, $minLength = 100)

    /**
     * Generate summary from PDF
     * @param string $filePath Path to PDF
     * @param int $maxLength Maximum length
     * @param int $minLength Minimum length
     * @return array
     */
    public function summarizeFromPdf($filePath, $maxLength = 1000, $minLength = 100)

    /**
     * Check API health status
     * @return array ['status' => string, 'services' => [...]]
     */
    public function health()

    /**
     * Get API usage statistics
     * @return array
     */
    public function usage()

    /**
     * Check if summarizer is available
     * @return bool
     */
    public function isSummarizerAvailable()
}
```

### NerRepository

```php
namespace ahgAIPlugin\Repository;

class NerRepository
{
    /**
     * Save extracted entities
     * @param int $objectId Information object ID
     * @param array $entities Entities grouped by type
     * @param string $backend Backend used for extraction
     * @return int Extraction ID
     */
    public function saveExtraction(int $objectId, array $entities, string $backend = 'local'): int

    /**
     * Get pending entities for an object
     * @param int $objectId
     * @return array
     */
    public function getPendingEntities(int $objectId): array

    /**
     * Get all entities for an object
     * @param int $objectId
     * @return array
     */
    public function getEntities(int $objectId): array

    /**
     * Update entity status
     * @param int $entityId
     * @param string $status
     * @param int|null $linkedActorId
     * @param int|null $reviewedBy
     * @return bool
     */
    public function updateEntityStatus(int $entityId, string $status, ?int $linkedActorId = null, ?int $reviewedBy = null): bool

    /**
     * Find matching actors for entity value
     * @param string $entityValue
     * @param string $entityType
     * @return array ['exact' => [], 'partial' => []]
     */
    public function findMatchingActors(string $entityValue, string $entityType): array

    /**
     * Get extraction history for object
     * @param int $objectId
     * @return array
     */
    public function getExtractionHistory(int $objectId): array

    /**
     * Get count of pending entities
     * @return int
     */
    public function getPendingCount(): int
}
```

### NerTrainingSync

```php
class NerTrainingSync
{
    /**
     * Get unexported corrections
     * @param int $limit Maximum to retrieve
     * @return Collection
     */
    public function getUnexportedCorrections($limit = 500)

    /**
     * Get context around entity in source text
     * @param int $objectId
     * @param string $entityValue
     * @param int $contextLength Characters before/after
     * @return array|null ['text' => string, 'start' => int, 'end' => int]
     */
    public function getEntityContext($objectId, $entityValue, $contextLength = 200)

    /**
     * Push corrections to central training server
     * @return array ['status' => string, 'exported' => int]
     */
    public function pushCorrections()

    /**
     * Export corrections to local file
     * @param string|null $filename Custom filename
     * @return array ['status' => string, 'file' => string, 'exported' => int]
     */
    public function exportToFile($filename = null)

    /**
     * Get training statistics
     * @return Collection
     */
    public function getStats()
}
```

---

## CLI Commands

### ai:install

Install plugin database tables.

```bash
php symfony ai:install
```

### ai:uninstall

Remove plugin (optionally keeping data).

```bash
php symfony ai:uninstall [--keep-data]
```

### ai:ner-extract

Extract named entities from records.

```bash
php symfony ai:ner-extract [options]

Options:
  --object=ID        Process specific object ID
  --repository=ID    Process all objects in repository
  --all              Process all unprocessed objects
  --uploaded-today   Process objects uploaded today
  --limit=N          Maximum to process (default: 100)
  --dry-run          Show what would be processed
  --queue            Queue jobs instead of direct processing
  --with-pdf         Extract text from PDFs
```

### ai:translate

Translate records between languages.

```bash
php symfony ai:translate [options]

Options:
  --from=LANG        Source culture (e.g., en) [required]
  --to=LANG          Target culture (e.g., af) [required]
  --object=ID        Translate specific object
  --repository=ID    Translate all in repository
  --fields=LIST      Fields to translate (comma-separated)
  --limit=N          Maximum to translate (default: 100)
  --dry-run          Show what would be translated
  --install-package  Install language package if missing
```

### ai:summarize

Generate summaries for records.

```bash
php symfony ai:summarize [options]

Options:
  --object=ID        Process specific object ID
  --repository=ID    Process all in repository
  --all-empty        Process records with empty summary
  --limit=N          Maximum to process (default: 100)
  --dry-run          Show what would be processed
  --field=NAME       Target field (default: scope_and_content)
```

### ai:spellcheck

Check spelling in metadata fields.

```bash
php symfony ai:spellcheck [options]

Options:
  --object=ID        Check specific object ID
  --repository=ID    Check all in repository
  --all              Check all objects
  --limit=N          Maximum to check (default: 100)
  --dry-run          Show what would be checked
  --language=CODE    Language code (e.g., en_ZA)
```

### ai:ner-sync

Sync NER corrections to training server.

```bash
php symfony ai:ner-sync [options]

Options:
  --export-file      Export to file instead of pushing to server
  --stats            Show training statistics only
```

### ai:process-pending

Process pending AI extraction queue (fallback for Gearman).

```bash
php symfony ai:process-pending [options]

Options:
  --limit=N          Maximum items to process (default: 50)
  --task-type=TYPE   Task type to process: ner, summarize (default: ner)
  --dry-run          Preview without processing
```

**Examples:**

```bash
# Process up to 50 pending NER extractions
php symfony ai:process-pending --limit=50

# Preview what would be processed
php symfony ai:process-pending --dry-run

# Process pending summarization tasks
php symfony ai:process-pending --task-type=summarize
```

### ai:suggest-description

Generate AI-powered description suggestions using LLM providers.

```bash
php symfony ai:suggest-description [options]

Options:
  --object=ID        Process specific object ID
  --repository=ID    Process all objects in repository
  --level=LEVEL      Filter by level of description (e.g., item, file)
  --empty-only       Only process records with empty scope_and_content
  --with-ocr         Only process records that have OCR text
  --limit=N          Maximum to process (default: 50)
  --template=ID      Prompt template ID to use
  --llm-config=ID    LLM configuration ID to use
  --dry-run          Show what would be processed without generating
  --delay=MS         Delay between API calls in milliseconds (default: 1000)
```

**Examples:**

```bash
# Preview what would be processed
php symfony ai:suggest-description --repository=5 --empty-only --dry-run

# Generate suggestions for items with OCR text
php symfony ai:suggest-description --with-ocr --limit=20

# Process specific object
php symfony ai:suggest-description --object=12345

# Use specific template and LLM config
php symfony ai:suggest-description --template=2 --llm-config=1 --limit=10
```

### Cron Job Scheduling

Recommended cron entries for automated description suggestions:

```bash
# Generate suggestions for records with empty scope_and_content (daily at 2am)
0 2 * * * cd /usr/share/nginx/atom && php symfony ai:suggest-description --empty-only --limit=100 >> /var/log/atom/ai-suggest.log 2>&1

# Generate suggestions for records with OCR text (weekly on Sunday at 3am)
0 3 * * 0 cd /usr/share/nginx/atom && php symfony ai:suggest-description --with-ocr --limit=200 >> /var/log/atom/ai-suggest-ocr.log 2>&1

# Cleanup expired suggestions (monthly on 1st at 4am)
0 4 1 * * cd /usr/share/nginx/atom && php symfony ai:suggest-description --cleanup >> /var/log/atom/ai-suggest-cleanup.log 2>&1
```

---

## Routes

### Primary Routes

| Route | URL | Action |
|-------|-----|--------|
| ahg_ai_ner_extract | /ai/ner/extract/:id | Extract entities from object |
| ahg_ai_ner_review | /ai/ner/review | Review dashboard |
| ahg_ai_ner_entities | /ai/ner/entities/:id | Get entities for object |
| ahg_ai_ner_bulk_save | /ai/ner/bulk-save | Save multiple entity decisions |
| ahg_ai_summarize | /ai/summarize/:id | Generate summary |
| ahg_ai_translate | /ai/translate/:id | Translate record |
| ahg_ai_htr | /ai/htr/:id | Handwriting text recognition |
| ahg_ai_health | /ai/health | API health check |
| ahg_ai_suggest | /ai/suggest/:id | Generate description suggestion |
| ahg_ai_suggest_review | /ai/suggest/review | Suggestion review dashboard |
| ahg_ai_suggest_view | /ai/suggest/:id/view | View specific suggestion |
| ahg_ai_suggest_decision | /ai/suggest/:id/decision | Approve/reject suggestion |
| ahg_ai_suggest_object | /ai/suggest/object/:id | Get suggestions for object |
| ahg_ai_llm_configs | /ai/llm/configs | List LLM configurations |
| ahg_ai_llm_health | /ai/llm/health | Check LLM provider health |
| ahg_ai_templates | /ai/templates | List prompt templates |

### Legacy Routes (Backward Compatibility)

| Route | URL | Action |
|-------|-----|--------|
| ahg_ner_extract | /ner/extract/:id | NER extraction |
| ahg_ner_review | /ner/review | Review dashboard |
| ahg_ner_entities | /ner/entities/:id | Get entities |
| ahg_ner_bulk_save | /ner/bulk-save | Bulk save |
| ahg_ner_summarize | /ner/summarize/:id | Summarization |

---

## Action Methods

### aiActions Class

```php
class aiActions extends sfActions
{
    // NER Actions
    public function executeExtract(sfWebRequest $request)     // Extract entities
    public function executeReview(sfWebRequest $request)      // Review dashboard
    public function executeGetEntities(sfWebRequest $request) // Get entities JSON
    public function executeUpdateEntity(sfWebRequest $request)// Update single entity
    public function executeBulkSave(sfWebRequest $request)    // Bulk save decisions
    public function executeCreateActor(sfWebRequest $request) // Create actor from entity
    public function executeCreatePlace(sfWebRequest $request) // Create place term
    public function executeCreateSubject(sfWebRequest $request)// Create subject term

    // Summarization
    public function executeSummarize(sfWebRequest $request)   // Generate summary

    // Translation
    public function executeTranslate(sfWebRequest $request)   // Translate record
    public function executeTranslateLanguages(sfWebRequest $request) // Get languages

    // HTR
    public function executeHtr(sfWebRequest $request)         // Handwriting recognition

    // Health
    public function executeHealth(sfWebRequest $request)      // API health check

    // LLM Description Suggestions
    public function executeSuggest(sfWebRequest $request)        // Generate suggestion
    public function executeSuggestReview(sfWebRequest $request)  // Review dashboard
    public function executeSuggestView(sfWebRequest $request)    // View suggestion
    public function executeSuggestDecision(sfWebRequest $request)// Approve/reject
    public function executeSuggestObject(sfWebRequest $request)  // Get for object
    public function executeSuggestPreview(sfWebRequest $request) // Preview context
    public function executeLlmConfigs(sfWebRequest $request)     // List LLM configs
    public function executeLlmHealth(sfWebRequest $request)      // Check LLM health
    public function executeTemplates(sfWebRequest $request)      // List templates
}
```

---

## LLM Description Suggestion

### Overview

The LLM Description Suggestion feature uses large language models (Ollama, OpenAI, or Anthropic) to generate `scope_and_content` descriptions from OCR text, metadata, and digital object information. All suggestions require custodian review before application.

### Workflow

```
1. User clicks "Suggest Description (AI)" button on record
         ↓
2. System gathers context (title, identifier, dates, OCR text, etc.)
         ↓
3. System selects appropriate prompt template (by level/repository)
         ↓
4. System calls LLM via configured provider (Ollama/OpenAI/Anthropic)
         ↓
5. Modal displays side-by-side comparison:
   [Current Description]     [AI Suggestion (editable)]
         ↓
6. Custodian reviews, optionally edits, adds notes
         ↓
7. Custodian clicks Approve → saves to scope_and_content
   OR clicks Reject → suggestion marked rejected
```

### Batch Selection Methods

The batch selection supports multiple input methods:

1. **Explicit Object IDs** - Direct array of object IDs
2. **Repository Filter** - All objects within a specific repository
3. **Search Query** - Full-text search across records

**Search-Based Selection:**

```php
// Search for records matching a query string
} elseif (!empty($data['search_query'])) {
    $searchTerm = '%' . trim($data['search_query']) . '%';
    $query = DB::table('information_object')
        ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
        ->where('information_object.id', '!=', 1)
        ->where('information_object_i18n.culture', '=', 'en')
        ->where(function ($q) use ($searchTerm) {
            $q->where('information_object_i18n.title', 'LIKE', $searchTerm)
              ->orWhere('information_object_i18n.scope_and_content', 'LIKE', $searchTerm)
              ->orWhere('information_object.identifier', 'LIKE', $searchTerm);
        });

    // Optional repository filter
    if (!empty($data['repository_id'])) {
        $query->where('information_object.repository_id', '=', (int) $data['repository_id']);
    }

    // Optional level of description filter
    if (!empty($data['level_id'])) {
        $query->where('information_object.level_of_description_id', '=', (int) $data['level_id']);
    }

    $objectIds = $query->limit($limit)->pluck('information_object.id')->toArray();
}
```

The search functionality enables flexible record selection for batch AI operations, allowing users to target records by keyword, repository, and/or level of description.

### Service Classes

#### LlmProviderInterface

Provider contract for all LLM backends.

```php
namespace ahgAIPlugin\Services;

interface LlmProviderInterface
{
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): array;
    public function isAvailable(): bool;
    public function getName(): string;
    public function getModels(): array;
    public function getHealth(): array;
}
```

#### LlmService

Factory/orchestrator for LLM providers.

```php
class LlmService
{
    public function getProvider(?int $configId = null): LlmProviderInterface;
    public function complete(string $systemPrompt, string $userPrompt, ?int $configId = null, array $options = []): array;
    public function getConfigurations(): array;
    public function getDefaultConfig(): ?object;
    public static function encryptApiKey(string $key): string;
    public static function decryptApiKey(string $encrypted): string;
}
```

#### PromptService

Template management and variable substitution.

```php
class PromptService
{
    public function getTemplateForObject(int $objectId, ?int $templateId = null): ?object;
    public function getDefaultTemplate(): ?object;
    public function buildPrompt(object $template, array $context): array;
    public function getTemplates(): array;
}
```

**Template Variables:**
- `{title}` - Record title
- `{identifier}` - Reference code/identifier
- `{level_of_description}` - Level (fonds, series, file, item)
- `{date_range}` - Date expression
- `{creator}` - Creator name
- `{repository}` - Repository name
- `{ocr_text}` - Full text from OCR
- `{existing_metadata}` - All available metadata

#### DescriptionService

Main orchestrator for generating and managing suggestions.

```php
class DescriptionService
{
    public function generateSuggestion(int $objectId, ?int $templateId = null, ?int $llmConfigId = null): array;
    public function gatherContext(int $objectId): array;
    public function saveSuggestion(int $objectId, array $result, ?object $template, ?object $config, array $context): int;
    public function approveSuggestion(int $suggestionId, int $userId, ?string $editedText = null, ?string $notes = null): bool;
    public function rejectSuggestion(int $suggestionId, int $userId, ?string $notes = null): bool;
    public function getPendingSuggestions(?int $repositoryId = null, int $limit = 50): array;
    public function getSuggestion(int $id): ?object;
}
```

### LLM Providers

#### OllamaProvider

Local LLM via Ollama server.

```php
// Configuration
$config = [
    'provider' => 'ollama',
    'endpoint_url' => 'http://localhost:11434',
    'model' => 'llama3.1:8b',
    'max_tokens' => 2000,
    'temperature' => 0.7
];
```

**Ollama Setup:**
```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Start server
ollama serve

# Pull model
ollama pull llama3.1:8b

# Verify
curl http://localhost:11434/api/tags
```

#### OpenAIProvider

OpenAI API integration.

```php
// Configuration
$config = [
    'provider' => 'openai',
    'endpoint_url' => 'https://api.openai.com',
    'api_key_encrypted' => '[encrypted key]',
    'model' => 'gpt-4o-mini',
    'max_tokens' => 2000,
    'temperature' => 0.7
];
```

#### AnthropicProvider

Anthropic Claude API integration.

```php
// Configuration
$config = [
    'provider' => 'anthropic',
    'endpoint_url' => 'https://api.anthropic.com',
    'api_key_encrypted' => '[encrypted key]',
    'model' => 'claude-3-haiku-20240307',
    'max_tokens' => 2000,
    'temperature' => 0.7
];
```

### OCR Integration

OCR text is retrieved from the `iiif_ocr_text` table (from ahgIiifPlugin):

```php
$ocr = DB::table('iiif_ocr_text')
    ->where('digital_object_id', $digitalObjectId)
    ->first();
$ocrText = $ocr->full_text;
```

### API Response Format

**Suggestion Generation:**
```json
{
    "success": true,
    "suggestion_id": 123,
    "suggested_text": "This collection contains...",
    "model_used": "llama3.1:8b",
    "tokens_used": 450,
    "generation_time_ms": 2340,
    "context": {
        "has_ocr": true,
        "ocr_length": 5420,
        "fields_used": ["title", "identifier", "date", "ocr_text"]
    }
}
```

**Suggestion Decision:**
```json
{
    "success": true,
    "message": "Suggestion approved and applied",
    "object_id": 12345,
    "applied_text": "The edited description text..."
}
```

---

## Entity Types and Linking

### Entity Types

| Type | Description | Links To |
|------|-------------|----------|
| PERSON | Individual names | Actor (entityType: PERSON) |
| ORG | Organizations | Actor (entityType: CORPORATE_BODY) |
| GPE | Places/Locations | Term (taxonomy: PLACE) |
| DATE | Dates and periods | Term (taxonomy: SUBJECT) or Event |

### Linking Process

```
Entity Extraction
      |
      v
+---------------------+
| Pending Status      |
| (awaiting review)   |
+---------------------+
      |
      v
+---------------------+     +----------------------+
| Review Action       |---->| Create New           |
|                     |     | - Actor (PERSON/ORG) |
|                     |     | - Place Term         |
|                     |     | - Subject Term       |
|                     |     +----------------------+
|                     |              |
|                     |     +----------------------+
|                     |---->| Link to Existing     |
|                     |     | - Actor match        |
|                     |     | - Term match         |
|                     |     +----------------------+
|                     |              |
|                     |     +----------------------+
|                     |---->| Approve (no link)    |
|                     |     +----------------------+
|                     |              |
|                     |     +----------------------+
|                     +---->| Reject               |
+---------------------+     +----------------------+
                                    |
                                    v
                       +------------------------+
                       | Status Updated         |
                       | correction_type set    |
                       | (for training)         |
                       +------------------------+
```

---

## Background Jobs

### arNerExtractJob

Gearman job for background NER extraction and summarization.

```php
class arNerExtractJob extends arBaseJob
{
    protected $extraRequiredParameters = ['objectId'];

    /**
     * Parameters:
     *   objectId      - Information object to process
     *   runNer        - Run NER extraction (default: true)
     *   runSummarize  - Run summarization (default: false)
     *   runSpellCheck - Run spell check (default: false)
     */
    public function runJob($parameters)
}
```

---

## Auto-trigger NER on Document Upload

### Overview

The plugin can automatically trigger NER extraction when digital objects are uploaded. This feature listens to the `QubitDigitalObject::insert` event and queues NER extraction jobs for processable document types.

### How It Works

```
1. User uploads digital object (PDF, DOCX, etc.)
         ↓
2. Symfony event dispatcher fires QubitDigitalObject::insert
         ↓
3. Plugin checks if auto-trigger is enabled
         ↓
4. Plugin checks if MIME type is processable
         ↓
5a. If Gearman available → Queue job to arNerExtractJob
5b. If Gearman unavailable → Add to ahg_ai_pending_extraction table
         ↓
6. Job processed (immediately by Gearman or later by cron)
```

### Processable MIME Types

| MIME Type | Description |
|-----------|-------------|
| application/pdf | PDF documents |
| text/plain | Plain text files |
| text/html | HTML documents |
| application/msword | Word documents (.doc) |
| application/vnd.openxmlformats-officedocument.wordprocessingml.document | Word documents (.docx) |
| application/rtf | Rich text format |

### Configuration

Auto-trigger is controlled by the setting `auto_extract_on_upload` in `ahg_ner_settings`:

| Setting Key | Value | Description |
|-------------|-------|-------------|
| auto_extract_on_upload | 1 | Enable auto-trigger on document upload |
| auto_extract_on_upload | 0 | Disable auto-trigger (default) |

**To enable via SQL:**

```sql
INSERT INTO ahg_ner_settings (setting_key, setting_value)
VALUES ('auto_extract_on_upload', '1')
ON DUPLICATE KEY UPDATE setting_value = '1';
```

**To enable via UI:**

Navigate to **Admin > AHG Settings > AI Services > NER** and enable "Auto-extract on upload".

### Fallback Queue Tables

When Gearman is unavailable, jobs are queued in the database:

#### ahg_ai_pending_extraction

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| object_id | INT | Information object ID |
| digital_object_id | INT | Digital object that triggered extraction |
| task_type | VARCHAR(50) | Task type (ner, summarize) |
| status | ENUM | pending, processing, completed, failed |
| attempt_count | INT | Retry attempt counter |
| error_message | TEXT | Last error message |
| created_at | TIMESTAMP | Queue time |
| processed_at | TIMESTAMP | Completion time |

#### ahg_ai_auto_trigger_log

Audit log for auto-triggered extractions:

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| object_id | INT | Information object ID |
| digital_object_id | INT | Digital object ID |
| task_type | VARCHAR(50) | Task type |
| status | VARCHAR(50) | queued or pending |
| created_at | TIMESTAMP | Event time |

### Cron Job for Pending Queue

If Gearman is not available, run the pending queue processor via cron:

```bash
# Process pending NER extractions every 5 minutes
*/5 * * * * cd /usr/share/nginx/atom && php symfony ai:process-pending --limit=20 >> /var/log/atom/ai-pending.log 2>&1
```

The `ai:process-pending` command:
- Fetches pending items from `ahg_ai_pending_extraction`
- Processes each item using the appropriate service
- Automatically retries failed items up to 3 times
- Marks items as completed or failed after processing

### Implementation Details

**Event Hook (in ahgAIPluginConfiguration.class.php):**

```php
public function initialize()
{
    // ... other initialization ...

    // Auto-trigger NER on digital object upload (Issue #19)
    $this->dispatcher->connect('QubitDigitalObject::insert', [$this, 'onDigitalObjectInsert']);
}

public function onDigitalObjectInsert(sfEvent $event)
{
    $digitalObject = $event->getSubject();
    $objectId = $digitalObject->objectId;

    if (!$this->isAutoTriggerEnabled()) {
        return;
    }

    if (!$this->isProcessableMimeType($digitalObject->mimeType ?? '')) {
        return;
    }

    $this->queueNerExtraction($objectId, $digitalObject->id);
}
```

---

## Python Integration

### Translation (Argos Translate)

Location: `atom-ahg-python/src/atom_ahg/resources/translation.py`

```python
# List installed language packages
python translation.py list

# Install language package
python translation.py install --from=en --to=af

# Translate text
python translation.py translate "Hello world" --from=en --to=af
```

### NER (spaCy)

Location: `atom-ahg-python/src/atom_ahg/resources/ner.py`

Uses spaCy models for entity extraction:
- en_core_web_sm (English small)
- en_core_web_lg (English large - better accuracy)

### Summarization

Location: `atom-ahg-python/src/atom_ahg/resources/summarize.py`

Uses transformer-based summarization models.

---

## API Endpoints

### AI API (Python Backend)

| Endpoint | Method | Description |
|----------|--------|-------------|
| /ai/v1/health | GET | Health check |
| /ai/v1/ner/extract | POST | Extract entities from text |
| /ai/v1/ner/extract-pdf | POST | Extract entities from PDF |
| /ai/v1/summarize | POST | Summarize text |
| /ai/v1/summarize-pdf | POST | Summarize PDF content |
| /ai/v1/translate | POST | Translate text |
| /ai/v1/translate/languages | GET | List available languages |
| /ai/v1/htr | POST | Handwriting text recognition |

### Request/Response Examples

**NER Extract:**
```json
// Request
POST /ai/v1/ner/extract
{
    "text": "John Smith met with UNESCO representatives in London on 15 January 2024.",
    "clean": true
}

// Response
{
    "success": true,
    "entities": {
        "PERSON": ["John Smith"],
        "ORG": ["UNESCO"],
        "GPE": ["London"],
        "DATE": ["15 January 2024"]
    },
    "entity_count": 4,
    "processing_time_ms": 245
}
```

**Summarize:**
```json
// Request
POST /ai/v1/summarize
{
    "text": "[long document text]",
    "max_length": 500,
    "min_length": 100
}

// Response
{
    "success": true,
    "summary": "Concise summary of the document...",
    "original_length": 15000,
    "summary_length": 450,
    "processing_time_ms": 1234
}
```

**Translate:**
```json
// Request
POST /ai/v1/translate
{
    "text": "Hello world",
    "source": "en",
    "target": "af"
}

// Response
{
    "success": true,
    "translated": "Hallo wereld",
    "source": "en",
    "target": "af"
}
```

---

## Training Feedback System

### Correction Types

| Type | Description | Used When |
|------|-------------|-----------|
| none | No correction made | Entity accepted as-is and linked |
| value_edit | Value was corrected | User edited entity text |
| type_change | Type was changed | User changed entity type |
| both | Value and type changed | Both edited |
| approved | Approved without link | Marked correct but not linked |
| rejected | Marked incorrect | Entity rejected as not valid |

### Export Format

```json
{
    "site_id": "abc123...",
    "site_name": "My Archive",
    "exported_at": "2026-01-30T14:32:15+00:00",
    "total_corrections": 45,
    "corrections": [
        {
            "entity_id": 123,
            "original_value": "J Smith",
            "corrected_value": "John Smith",
            "original_type": "PERSON",
            "corrected_type": "PERSON",
            "correction_type": "value_edit",
            "confidence": 0.85,
            "context": {
                "text": "...letter from J Smith regarding...",
                "start": 12,
                "end": 20
            },
            "reviewed_at": "2026-01-30T10:15:00"
        }
    ]
}
```

---

## File Structure

```
ahgAIPlugin/
+-- config/
|   +-- ahgAIPluginConfiguration.class.php  # Plugin configuration
|   +-- routing.yml                          # Route definitions
|   +-- settings.yml                         # Module settings
+-- database/
|   +-- install.sql                          # Database schema
+-- lib/
|   +-- job/
|   |   +-- arNerExtractJob.class.php        # Background job
|   |   +-- ahgMediaTranscriptionJob.class.php
|   +-- repository/
|   |   +-- NerRepository.php                # Data access layer
|   +-- Services/
|   |   +-- NerService.php                   # Core NER service
|   |   +-- ahgFaceDetectionService.php      # Face detection
|   |   +-- LlmProviderInterface.php         # LLM provider contract
|   |   +-- LlmService.php                   # LLM factory/orchestrator
|   |   +-- PromptService.php                # Prompt template management
|   |   +-- DescriptionService.php           # Description suggestion orchestrator
|   |   +-- providers/
|   |       +-- OllamaProvider.php           # Ollama local LLM
|   |       +-- OpenAIProvider.php           # OpenAI API
|   |       +-- AnthropicProvider.php        # Anthropic API
|   +-- task/
|   |   +-- aiInstallTask.class.php          # Install CLI
|   |   +-- aiUninstallTask.class.php        # Uninstall CLI
|   |   +-- aiNerExtractTask.class.php       # NER extract CLI
|   |   +-- aiTranslateTask.class.php        # Translate CLI
|   |   +-- aiSummarizeTask.class.php        # Summarize CLI
|   |   +-- aiSpellcheckTask.class.php       # Spellcheck CLI
|   |   +-- aiNerSyncTask.class.php          # Training sync CLI
|   |   +-- aiSuggestDescriptionTask.class.php # Description suggestion CLI
|   +-- NerTrainingSync.class.php            # Training data sync
+-- modules/
|   +-- ai/
|       +-- actions/
|       |   +-- actions.class.php            # Controller actions
|       |   +-- components.class.php         # View components
|       +-- config/
|       |   +-- module.yml
|       |   +-- security.yml
|       +-- templates/
|           +-- _aiTools.php                 # AI tools sidebar
|           +-- _extractButton.php           # Extract button
|           +-- _summarizeButton.php         # Summarize button
|           +-- _suggestButton.php           # Suggest description button
|           +-- reviewSuccess.php            # NER review dashboard
|           +-- suggestReviewSuccess.php     # Suggestion review dashboard
+-- extension.json                           # Plugin metadata
```

---

## Dependencies

### PHP Dependencies
- atom-framework (Laravel Query Builder)
- ahgCorePlugin (AhgDb initialization)
- php-curl (for LLM API calls)
- openssl (for API key encryption)

### System Dependencies
- pdftotext (poppler-utils) - PDF text extraction
- aspell - Spellchecking
- python3 - AI backend

### Python Dependencies
- argostranslate>=1.9.0 - Offline translation
- spacy - NER extraction
- transformers - Summarization models

### LLM Providers (Optional - at least one required for suggestions)
- **Ollama** (recommended for local/privacy)
  - Install: `curl -fsSL https://ollama.com/install.sh | sh`
  - Models: llama3.1:8b, llama3.1:70b, mistral, mixtral
- **OpenAI API** (cloud)
  - Requires API key from https://platform.openai.com
  - Models: gpt-4o-mini, gpt-4o, gpt-4-turbo
- **Anthropic API** (cloud)
  - Requires API key from https://console.anthropic.com
  - Models: claude-3-haiku, claude-3-sonnet, claude-3-opus

---

## Security

### Access Control
- All actions require authentication
- Review actions require editor role or higher
- Settings modifications require administrator role

### API Security
- API key authentication via X-API-Key header
- Rate limiting configured at API level
- Request/response logging for audit

---

## Performance Considerations

### Batch Processing
- CLI commands support --limit option
- Background jobs for large volumes
- Batch entity saving (3 at a time in UI)

### Caching
- Settings cached in session
- API responses not cached (real-time results)

### Timeouts
- Default API timeout: 60 seconds
- PDF processing timeout: 120 seconds
- HTR processing timeout: 120 seconds

---

## Migration from Separate Plugins

### From arNerPlugin

```sql
-- Settings migration (automatic in install.sql)
INSERT IGNORE INTO ahg_ai_settings (feature, setting_key, setting_value)
SELECT 'ner', setting_key, setting_value
FROM ahg_ner_settings
WHERE setting_key NOT IN ('api_url', 'api_key', 'api_timeout');
```

### Route Compatibility

Legacy routes (/ner/*) are maintained for backward compatibility and redirect to new routes (/ai/ner/*).

---

## AI Job Queue

### Overview

The Job Queue system enables batch processing of AI tasks (NER, Summarize, Suggest, Translate, Spellcheck, OCR) on multiple records with progress tracking, throttling, and retry capability.

### Database Tables

#### ahg_ai_batch

Batch job container.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| name | VARCHAR(255) | Batch name |
| description | TEXT | Optional description |
| task_types | JSON | Array of task types to run |
| status | ENUM | pending, running, paused, completed, failed, cancelled |
| priority | TINYINT | 1-10, lower is higher priority |
| total_items | INT | Total jobs in batch |
| completed_items | INT | Successfully completed jobs |
| failed_items | INT | Failed jobs |
| progress_percent | DECIMAL(5,2) | Calculated progress |
| max_concurrent | INT | Max parallel jobs (default: 5) |
| delay_between_ms | INT | Delay between jobs (default: 1000) |
| max_retries | INT | Retry attempts (default: 3) |
| scheduled_at | TIMESTAMP | Optional scheduled start |
| started_at | TIMESTAMP | Actual start time |
| completed_at | TIMESTAMP | Completion time |

#### ahg_ai_job

Individual job items.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| batch_id | BIGINT | Foreign key to batch |
| object_id | INT | Information object ID |
| task_type | VARCHAR(50) | ner, summarize, suggest, translate, spellcheck, ocr |
| status | ENUM | pending, queued, running, completed, failed, skipped |
| gearman_handle | VARCHAR(255) | Gearman job handle |
| attempt_count | INT | Current retry count |
| result_data | JSON | Task results |
| error_message | TEXT | Error details if failed |
| processing_time_ms | INT | Execution time |

#### ahg_ai_job_log

Event log for auditing.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| batch_id | BIGINT | Batch reference |
| job_id | BIGINT | Job reference (optional) |
| event_type | VARCHAR(50) | Event type identifier |
| message | TEXT | Human-readable message |
| details | JSON | Additional context |
| created_at | TIMESTAMP | Event timestamp |

### Service: JobQueueService

Path: `lib/Services/JobQueueService.php`

#### Task Types

```php
JobQueueService::TASK_NER       // Named entity extraction
JobQueueService::TASK_SUMMARIZE // Text summarization
JobQueueService::TASK_SUGGEST   // LLM description suggestion
JobQueueService::TASK_TRANSLATE // Machine translation
JobQueueService::TASK_SPELLCHECK // Spelling check
JobQueueService::TASK_OCR       // OCR text extraction
```

#### Key Methods

```php
// Batch management
createBatch(array $data): int
addItemsToBatch(int $batchId, array $objectIds, array $taskTypes): int
startBatch(int $batchId): bool
pauseBatch(int $batchId): bool
resumeBatch(int $batchId): bool
cancelBatch(int $batchId): bool
deleteBatch(int $batchId): bool

// Job processing
queueJob(int $jobId): bool  // Queue to Gearman
processJob(int $jobId): array
retryFailed(int $batchId): int

// Progress tracking
getBatch(int $batchId): ?object
getBatches(array $filters, int $limit): array
getBatchStats(int $batchId): array
getBatchJobs(int $batchId, array $filters, int $limit): array
updateBatchProgress(int $batchId, bool $hasFailed): void

// Server load protection
checkServerLoad(): bool  // Checks CPU < 80%
```

### Gearman Worker

Path: `lib/job/arAiBatchJob.class.php`

The Gearman worker processes jobs asynchronously:

```php
class arAiBatchJob extends arBaseJob
{
    protected $extraRequiredParameters = ['jobId'];

    public function runJob($parameters)
    {
        $service = new JobQueueService();

        // Check server load before processing
        if (!$service->checkServerLoad()) {
            sleep(10);  // Back off under high load
        }

        return $service->processJob($parameters['jobId']);
    }
}
```

### Web Routes

| Route | Method | Action | Description |
|-------|--------|--------|-------------|
| /ai/batch | GET | batch | Job queue dashboard |
| /ai/batch/create | POST | batchCreate | Create new batch |
| /ai/batch/:id | GET | batchView | View batch details |
| /ai/batch/:id/progress | GET | batchProgress | AJAX progress update |
| /ai/batch/:id/action | POST | batchAction | Start/pause/resume/cancel/retry |
| /ai/batch/:id/process | POST | batchProcess | Process next jobs (cron) |
| /ai/job/:id | GET | jobView | View job details |

### UI Components

#### Batch Dashboard (batchSuccess.php)

- Stats cards (pending, running, completed, failed)
- Batch list with progress bars
- Create Batch modal
  - Task type selection (checkboxes)
  - Object selection (by repository or IDs)
  - Advanced options (concurrency, delay, retries)
  - Auto-start option

#### Batch View (batchViewSuccess.php)

- Progress bar with real-time updates
- Stats breakdown (total, pending, running, completed, failed)
- Job list table with filtering
- Action buttons (start, pause, resume, cancel, retry)
- Activity log

### CLI Command

```bash
php symfony ai:batch [options]

Options:
  --create                Create a new batch
  --name="Batch Name"     Batch name (required with --create)
  --tasks=ner,summarize   Comma-separated task types
  --repository=ID         Process objects from repository
  --object-ids=1,2,3      Specific object IDs
  --limit=100             Maximum objects
  --start                 Auto-start after creation
  --status                Show batch status
  --process               Process pending jobs (cron mode)
```

### Workflow

```
1. User creates batch via UI or CLI
   ↓
2. System adds job records for each object × task type
   ↓
3. User starts batch (or auto-start)
   ↓
4. Jobs queued to Gearman (or processed by cron)
   ↓
5. Worker checks server load
   ↓
6. Worker executes task via appropriate service
   ↓
7. Progress updated, batch checked for completion
   ↓
8. Failed jobs can be retried
```

### Resource Protection

- **Max Concurrent**: Limits parallel jobs (default: 5)
- **Delay Between Jobs**: Prevents server overload (default: 1000ms)
- **Server Load Check**: Pauses if CPU > 80%
- **Timeouts**: Individual job timeout protection
- **Max Retries**: Automatic retry with exponential backoff

### Example: Create Batch via API

```javascript
fetch('/ai/batch/create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        name: 'NER for Repository X',
        task_types: ['ner', 'summarize'],
        repository_id: 123,
        limit: 500,
        max_concurrent: 3,
        delay_between_ms: 2000,
        auto_start: true
    })
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        console.log('Batch created:', data.batch_id);
        window.location.href = '/ai/batch/' + data.batch_id;
    }
});
```

---

*Part of the AtoM AHG Framework*
