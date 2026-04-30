# ahgTranslationPlugin - Technical Documentation

**Version:** 1.0.1
**Category:** AI
**Dependencies:** None (standalone plugin)

---

## Overview

On-premises translation plugin for AtoM that integrates with NLLB-200 (No Language Left Behind) machine translation service. Supports all 11 South African official languages plus major international languages. Provides a draft-based workflow for reviewing and editing translations before applying them to archival descriptions.

---

## Architecture

```
+---------------------------------------------------------------------+
|                     ahgTranslationPlugin                             |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |                    Translation Modal UI                       |  |
|  |  _translateModal.php                                          |  |
|  |  - Field selection                                            |  |
|  |  - Language picker (source/target)                            |  |
|  |  - Preview and edit interface                                 |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                  translationActions                           |  |
|  |  - executeHealth()     - Connectivity check                   |  |
|  |  - executeSettings()   - Configuration UI                     |  |
|  |  - executeTranslate()  - Translate field, create draft        |  |
|  |  - executeApply()      - Apply draft to record                |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                 AhgTranslationService                         |  |
|  |  - translateText()     - Call MT endpoint                     |  |
|  |  - createDraft()       - Store translation draft              |  |
|  |  - applyDraft()        - Write to i18n table                  |  |
|  |  - logAttempt()        - Audit logging                        |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |               AhgTranslationRepository                        |  |
|  |  - getSetting() / setSetting()                                |  |
|  |  - createDraft() / getDraft() / markDraftApplied()            |  |
|  |  - getInformationObjectField() / updateInformationObjectField()|  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                    AhgTranslationDb                           |  |
|  |  - Propel/PDO database connection wrapper                     |  |
|  |  - fetchOne() / fetchAll() / exec() / lastInsertId()          |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |              NLLB-200 MT Service (External)                   |  |
|  |  - http://192.168.0.112:5004/ai/v1/translate                  |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+---------------------------------------+
|       ahg_translation_settings        |
+---------------------------------------+
| PK id INT UNSIGNED                    |
|    setting_key VARCHAR(128) UNIQUE    |
|    setting_value TEXT                 |
|    updated_at DATETIME                |
+---------------------------------------+
         |
         | 1:N (settings reference)
         |
+---------------------------------------+
|        ahg_translation_draft          |
+---------------------------------------+
| PK id BIGINT UNSIGNED                 |
|                                       |
| -- Target Record --                   |
| FK object_id BIGINT UNSIGNED          |
|    entity_type VARCHAR(64)            |
|    field_name VARCHAR(64)             |
|                                       |
| -- Languages --                       |
|    source_culture VARCHAR(8)          |
|    target_culture VARCHAR(8)          |
|                                       |
| -- Content --                         |
|    source_hash CHAR(64)               |
|    source_text LONGTEXT               |
|    translated_text LONGTEXT           |
|                                       |
| -- Workflow --                        |
|    status ENUM('draft','applied',     |
|                'rejected')            |
| FK created_by_user_id BIGINT UNSIGNED |
|    created_at DATETIME                |
|    applied_at DATETIME                |
+---------------------------------------+
| UNIQUE KEY (object_id, field_name,    |
|             source_culture,           |
|             target_culture,           |
|             source_hash)              |
+---------------------------------------+
         |
         | Related logs
         |
+---------------------------------------+
|         ahg_translation_log           |
+---------------------------------------+
| PK id BIGINT UNSIGNED                 |
|                                       |
| FK object_id BIGINT UNSIGNED          |
|    field_name VARCHAR(64)             |
|    source_culture VARCHAR(8)          |
|    target_culture VARCHAR(8)          |
|    endpoint VARCHAR(255)              |
|    http_status INT                    |
|    ok TINYINT(1)                      |
|    error TEXT                         |
|    elapsed_ms INT                     |
|    created_at DATETIME                |
+---------------------------------------+
```

### SQL Schema

```sql
-- Settings table
CREATE TABLE IF NOT EXISTS ahg_translation_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(128) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
             ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Draft table (translation queue)
CREATE TABLE IF NOT EXISTS ahg_translation_draft (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  object_id BIGINT UNSIGNED NOT NULL,
  entity_type VARCHAR(64) NOT NULL DEFAULT 'information_object',
  field_name VARCHAR(64) NOT NULL,
  source_culture VARCHAR(8) NOT NULL,
  target_culture VARCHAR(8) NOT NULL DEFAULT 'en',
  source_hash CHAR(64) NOT NULL,
  source_text LONGTEXT NOT NULL,
  translated_text LONGTEXT NOT NULL,
  status ENUM('draft','applied','rejected') NOT NULL DEFAULT 'draft',
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  applied_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_object_field (object_id, field_name),
  KEY idx_status (status),
  UNIQUE KEY uk_draft_dedupe (object_id, field_name, source_culture,
                              target_culture, source_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Log table (API call audit)
CREATE TABLE IF NOT EXISTS ahg_translation_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  object_id BIGINT UNSIGNED NULL,
  field_name VARCHAR(64) NULL,
  source_culture VARCHAR(8) NULL,
  target_culture VARCHAR(8) NULL,
  endpoint VARCHAR(255) NULL,
  http_status INT NULL,
  ok TINYINT(1) NOT NULL DEFAULT 0,
  error TEXT NULL,
  elapsed_ms INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_object (object_id),
  KEY idx_ok (ok)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Default Settings

```sql
-- Inserted on install
INSERT INTO ahg_translation_settings (setting_key, setting_value) VALUES
  ('mt.endpoint', 'http://127.0.0.1:5100/translate'),
  ('mt.timeout_seconds', '30'),
  ('mt.target_culture', 'en');
```

| Setting Key | Default Value | Description |
|-------------|---------------|-------------|
| mt.endpoint | http://127.0.0.1:5100/translate | NLLB-200 API endpoint URL |
| mt.timeout_seconds | 30 | HTTP request timeout |
| mt.target_culture | en | Default target language |
| mt.api_key | ahg_ai_demo_internal_2026 | API authentication key |

---

## Routes

Registered in `ahgTranslationPluginConfiguration`:

| Route Name | URL Pattern | Action | Method |
|------------|-------------|--------|--------|
| ahg_translation_health | /translation/health | health | GET |
| ahg_translation_settings | /translation/settings | settings | GET/POST |
| ahg_translation_translate | /translation/translate/:id | translate | POST |
| ahg_translation_apply | /translation/apply/:draftId | apply | POST |

---

## API Endpoints

### Health Check

**GET** `/translation/health`

Response:
```json
{
  "ok": true,
  "endpoint": "http://192.168.0.112:5004/ai/v1/translate",
  "http_status": 200,
  "curl_error": null
}
```

### Translate Field

**POST** `/translation/translate/:id`

Parameters:
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| id | int | Yes | - | Information object ID |
| field | string | Yes | - | Source field key |
| targetField | string | No | field | Target field key |
| source | string | No | user culture | Source language code |
| target | string | No | from settings | Target language code |
| apply | int | No | 0 | Apply immediately (0/1) |
| overwrite | int | No | 0 | Overwrite existing (0/1) |
| saveCulture | int | No | 1 | Save with culture code (0/1) |

Response (success):
```json
{
  "ok": true,
  "draft_id": 123,
  "deduped": false,
  "translation": "Translated text here",
  "source_text": "Original text here",
  "source_field": "title",
  "target_field": "title"
}
```

Response (with apply=1):
```json
{
  "ok": true,
  "draft_id": 123,
  "translation": "Translated text",
  "apply_ok": true,
  "saved_culture": "en"
}
```

### Apply Draft

**POST** `/translation/apply/:draftId`

Parameters:
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| draftId | int | Yes | - | Draft ID to apply |
| overwrite | int | No | 0 | Overwrite existing (0/1) |
| saveCulture | int | No | 1 | Use culture code (0/1) |
| targetCulture | string | No | draft's target | Override culture |
| editedText | string | No | - | Edited translation text |

Response:
```json
{
  "ok": true,
  "culture": "en"
}
```

---

## Service Methods

### AhgTranslationService

```php
class AhgTranslationService
{
    // Settings
    public function getSetting(string $key, $default = null);
    public function setSetting(string $key, string $value): void;

    // Translation
    public function translateText(
        string $text,
        string $sourceCulture,
        string $targetCulture = 'en',
        ?int $maxLength = null
    ): array;

    // Draft Management
    public function createDraft(
        int $objectId,
        string $fieldName,
        string $sourceCulture,
        string $targetCulture,
        string $sourceText,
        string $translatedText,
        ?int $userId = null
    ): array;

    public function applyDraft(int $draftId, bool $overwrite = false): array;

    public function applyDraftWithCulture(
        int $draftId,
        bool $overwrite = false,
        ?string $targetCulture = null
    ): array;

    public function updateDraftText(int $draftId, string $newText): bool;

    // Logging
    public function logAttempt(
        ?int $objectId,
        ?string $field,
        ?string $src,
        ?string $tgt,
        array $result
    ): void;

    public function repo(): AhgTranslationRepository;
}
```

### AhgTranslationRepository

```php
class AhgTranslationRepository
{
    // Field mapping
    public static function allowedFields(): array;
    public static function fieldMaxLength(string $column): int;

    // Settings
    public function getSetting(string $key, $default = null);
    public function setSetting(string $key, string $value): void;

    // Information Object access
    public function getInformationObjectField(
        int $id,
        string $culture,
        string $column
    ): ?string;

    public function ensureInformationObjectI18nRow(
        int $id,
        string $culture
    ): void;

    public function updateInformationObjectField(
        int $id,
        string $culture,
        string $column,
        string $value
    ): void;

    // Draft operations
    public function createDraft(array $data): array;
    public function getDraft(int $draftId): ?array;
    public function markDraftApplied(int $draftId): void;
    public function updateDraftText(int $draftId, string $newText): bool;

    // Logging
    public function logAttempt(
        ?int $objectId,
        ?string $field,
        ?string $src,
        ?string $tgt,
        array $result
    ): void;
}
```

---

## Allowed Fields

The plugin supports all translatable fields from `information_object_i18n`:

| Field Key | Database Column | Max Length |
|-----------|-----------------|------------|
| title | title | 1024 |
| alternate_title | alternate_title | 1024 |
| edition | edition | 255 |
| extent_and_medium | extent_and_medium | 65535 |
| archival_history | archival_history | 65535 |
| acquisition | acquisition | 65535 |
| scope_and_content | scope_and_content | 65535 |
| appraisal | appraisal | 65535 |
| accruals | accruals | 65535 |
| arrangement | arrangement | 65535 |
| access_conditions | access_conditions | 65535 |
| reproduction_conditions | reproduction_conditions | 65535 |
| physical_characteristics | physical_characteristics | 65535 |
| finding_aids | finding_aids | 65535 |
| location_of_originals | location_of_originals | 65535 |
| location_of_copies | location_of_copies | 65535 |
| related_units_of_description | related_units_of_description | 65535 |
| institution_responsible_identifier | institution_responsible_identifier | 1024 |
| rules | rules | 65535 |
| sources | sources | 65535 |
| revision_history | revision_history | 65535 |

Both camelCase and underscore formats are accepted (e.g., `scopeAndContent` or `scope_and_content`).

---

## Supported Languages

| Code | Language | Code | Language |
|------|----------|------|----------|
| en | English | af | Afrikaans |
| zu | isiZulu | xh | isiXhosa |
| st | Sesotho | tn | Setswana |
| nso | Sepedi | ts | Xitsonga |
| ss | SiSwati | ve | Tshivenda |
| nr | isiNdebele | nl | Dutch |
| fr | French | de | German |
| es | Spanish | pt | Portuguese |
| sw | Swahili | ar | Arabic |

---

## MT Endpoint Contract

The plugin expects the translation endpoint to:

**Request:**
```json
{
  "text": "Source text to translate",
  "source": "af",
  "target": "en",
  "max_length": 1024
}
```

**Headers:**
```
Content-Type: application/json
X-API-Key: <api_key>
```

**Response:**
```json
{
  "translated": "Translated text output",
  "model": "nllb-200"
}
```

The plugin accepts any of these response fields:
- `translated` (preferred)
- `translatedText`
- `translation`

---

## Draft Deduplication

Drafts are deduplicated using a unique key:
- `object_id`
- `field_name`
- `source_culture`
- `target_culture`
- `source_hash` (SHA-256 of source text)

If a duplicate is detected, the existing draft ID is returned with `deduped: true`.

---

## File Structure

```
ahgTranslationPlugin/
+-- config/
|   +-- ahgTranslationPluginConfiguration.class.php
|   +-- routing.yml
+-- database/
|   +-- install.sql
+-- lib/
|   +-- Db/
|   |   +-- AhgTranslationDb.php
|   +-- Repository/
|   |   +-- AhgTranslationRepository.php
|   +-- Service/
|       +-- AhgTranslationService.php
+-- modules/
|   +-- translation/
|       +-- actions/
|       |   +-- actions.class.php
|       |   +-- components.class.php
|       +-- config/
|       |   +-- view.yml
|       +-- templates/
|           +-- settingsSuccess.php
|           +-- _translateModal.php
+-- extension.json
+-- README.md
```

---

## Integration

### Including the Translation Modal

Add to any template with a record context:

```php
<?php include_partial('translation/translateModal', array(
    'objectId' => $resource->id
)); ?>
```

### Programmatic Translation

```php
$service = new AhgTranslationService();

// Translate text
$result = $service->translateText(
    'Hierdie is die teks om te vertaal',
    'af',  // source: Afrikaans
    'en',  // target: English
    1024   // max length
);

if ($result['ok']) {
    echo $result['translation'];
}

// Create and apply draft
$draft = $service->createDraft(
    $objectId,
    'scope_and_content',
    'af',
    'en',
    $sourceText,
    $result['translation'],
    $userId
);

if ($draft['ok']) {
    $applied = $service->applyDraft($draft['draft_id'], $overwrite = false);
}
```

---

## Error Handling

| Error | Cause | Solution |
|-------|-------|----------|
| "cURL error X" | Network/connectivity issue | Check MT service is running |
| "Invalid JSON from MT endpoint" | Malformed response | Check MT service output |
| "MT endpoint returned non-2xx" | Service error | Check MT service logs |
| "Unsupported field" | Invalid field key | Use allowed field keys |
| "No source text" | Empty source field | Add content to source first |
| "Target field not empty" | Would overwrite | Use overwrite=1 parameter |
| "Draft not found" | Invalid draft ID | Check draft exists |
| "Draft not in draft state" | Already applied/rejected | Create new translation |

---

## Configuration

### Plugin Configuration Class

```php
class ahgTranslationPluginConfiguration extends sfPluginConfiguration
{
    public static $summary = 'AHG Translation Plugin';
    public static $version = '1.0.0';
    public static $category = 'ai';

    public function initialize()
    {
        $this->dispatcher->connect(
            'routing.load_configuration',
            array($this, 'routingLoadConfiguration')
        );
    }
}
```

### Database Layer

Uses Propel connection (not Laravel Query Builder) to avoid autoloader conflicts:

```php
class AhgTranslationDb
{
    public static function conn()
    {
        return Propel::getConnection();
    }

    public static function fetchOne(string $sql, array $params = array());
    public static function fetchAll(string $sql, array $params = array());
    public static function exec(string $sql, array $params = array()): int;
    public static function lastInsertId(): string;
}
```

---

## Installation

1. Copy plugin to AtoM plugins directory:
```bash
cp -r ahgTranslationPlugin /usr/share/nginx/atom/plugins/
```

2. Run database migrations:
```bash
mysql -u root archive < plugins/ahgTranslationPlugin/database/install.sql
```

3. Fix ownership:
```bash
chown -R www-data:www-data /usr/share/nginx/atom/plugins/ahgTranslationPlugin
```

4. Clear cache:
```bash
php symfony cc
```

5. Enable plugin in AtoM admin.

---

## Dependencies

- **PHP:** >= 8.1
- **AtoM:** >= 2.8
- **NLLB-200 Service:** External MT endpoint (ahg-ai service)
- **cURL:** PHP extension for HTTP requests

No dependencies on atom-framework or other AHG plugins.

---

*Part of the AtoM AHG Framework*
