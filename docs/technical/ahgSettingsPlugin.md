# ahgSettingsPlugin - Technical Documentation

**Version:** 1.0.1
**Category:** Admin
**Dependencies:** atom-framework, ahgCorePlugin
**Load Order:** 20

---

## Overview

Centralized settings management plugin providing a unified admin interface for configuring AtoM core settings, AHG plugins, themes, AI services, API keys, email, numbering schemes, and preservation settings. Consolidates scattered configuration options into a single dashboard.

---

## Architecture

```
+---------------------------------------------------------------------+
|                        ahgSettingsPlugin                             |
+---------------------------------------------------------------------+
|                                                                      |
|  +---------------------------------------------------------------+  |
|  |                   Plugin Configuration                         |  |
|  |  ahgSettingsPluginConfiguration.class.php                      |  |
|  |  - Route registration                                          |  |
|  |  - Module initialization                                       |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Action Handlers                             |  |
|  |  +------------+  +------------+  +------------+  +----------+  |  |
|  |  | globalAction|  |sectionAction|  |pluginsAction|  |emailAction|  |
|  |  +------------+  +------------+  +------------+  +----------+  |  |
|  |  +------------+  +------------+  +------------+  +----------+  |  |
|  |  |aiServicesAct| |apiKeysAction| |preserveAction| |numberingAct|  |
|  |  +------------+  +------------+  +------------+  +----------+  |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Service Layer                               |  |
|  |  AhgSettingsService                                           |  |
|  |  - Settings CRUD                                               |  |
|  |  - Caching                                                     |  |
|  |  - Feature toggles                                             |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Database Tables                             |  |
|  |  ahg_settings | ahg_api_key | numbering_scheme | email_setting |  |
|  +---------------------------------------------------------------+  |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## File Structure

```
ahgSettingsPlugin/
+-- config/
|   +-- ahgSettingsPluginConfiguration.class.php
|   +-- routing.yml
+-- database/
|   +-- numbering_scheme.sql
+-- extension.json
+-- lib/
|   +-- filter/
|   |   +-- NumberingFilter.class.php
|   +-- Services/
|       +-- AhgSettingsService.php
+-- modules/
    +-- ahgSettings/
        +-- actions/
        |   +-- handlers/
        |   |   +-- ahgIntegrationAction.class.php
        |   |   +-- aiServicesAction.class.php
        |   |   +-- apiKeysAction.class.php
        |   |   +-- clipboardAction.class.php
        |   |   +-- csvValidatorAction.class.php
        |   |   +-- damToolsAction.class.php
        |   |   +-- deleteAction.class.php
        |   |   +-- diacriticsAction.class.php
        |   |   +-- digitalObjectDerivativesAction.class.php
        |   |   +-- dipUploadAction.class.php
        |   |   +-- editAction.class.php
        |   |   +-- emailAction.class.php
        |   |   +-- emailTestAction.class.php
        |   |   +-- exportAction.class.php
        |   |   +-- findingAidAction.class.php
        |   |   +-- fusekiTestAction.class.php
        |   |   +-- generateIdentifierAction.class.php
        |   |   +-- globalAction.class.php
        |   |   +-- identifierAction.class.php
        |   |   +-- importAction.class.php
        |   |   +-- interfaceLabelAction.class.php
        |   |   +-- inventoryAction.class.php
        |   |   +-- languageAction.class.php
        |   |   +-- ldapAction.class.php
        |   |   +-- levelsAction.class.php
        |   |   +-- markdownAction.class.php
        |   |   +-- numberingSchemeEditAction.class.php
        |   |   +-- numberingSchemesAction.class.php
        |   |   +-- oaiAction.class.php
        |   |   +-- pageElementsAction.class.php
        |   |   +-- permissionsAction.class.php
        |   |   +-- pluginsAction.class.php
        |   |   +-- preservationAction.class.php
        |   |   +-- privacyNotificationAction.class.php
        |   |   +-- resetAction.class.php
        |   |   +-- saveTiffPdfSettingsAction.class.php
        |   |   +-- sectionAction.class.php
        |   |   +-- sectorNumberingAction.class.php
        |   |   +-- securityAction.class.php
        |   |   +-- siteInformationAction.class.php
        |   |   +-- templateAction.class.php
        |   |   +-- treeviewAction.class.php
        |   |   +-- uploadsAction.class.php
        |   |   +-- validateIdentifierAction.class.php
        |   |   +-- visibleElementsAction.class.php
        |   |   +-- webhooksAction.class.php
        |   +-- menuComponent.class.php
        +-- config/
        |   +-- module.yml
        +-- templates/
            +-- _dynamicStyles.php
            +-- _glamDamSection.php
            +-- _glamDamTiffPdfMerge.php
            +-- _i18n_form_field.php
            +-- _landingPageWidget.php
            +-- _menu.php
            +-- _metadataSettings.php
            +-- _multiTenantSettings.php
            +-- _tiffPdfMergeDashboard.php
            +-- _tiffPdfMergeSettings.php
            +-- ahgImportSettingsSuccess.php
            +-- ahgIntegrationSuccess.php
            +-- ahgSettingsSuccess.php
            +-- aiServicesSuccess.php
            +-- apiKeysSuccess.php
            +-- clipboardSuccess.php
            +-- csvValidatorSuccess.php
            +-- damToolsSuccess.php
            +-- diacriticsSuccess.php
            +-- digitalObjectDerivativesSuccess.php
            +-- dipUploadSuccess.php
            +-- emailSuccess.php
            +-- findingAidSuccess.php
            +-- globalSuccess.php
            +-- identifierSuccess.php
            +-- indexSuccess.php
            +-- interfaceLabelSuccess.php
            +-- inventorySuccess.php
            +-- languageSuccess.php
            +-- ldapSuccess.php
            +-- levelsSuccess.php
            +-- markdownSuccess.php
            +-- numberingSchemeEditSuccess.php
            +-- numberingSchemesSuccess.php
            +-- oaiSuccess.php
            +-- pageElementsSuccess.php
            +-- pathsSuccess.php
            +-- permissionsSuccess.php
            +-- pluginsSuccess.php
            +-- preservationSuccess.php
            +-- privacyNotificationSuccess.php
            +-- sectionSuccess.php
            +-- sectorNumberingSuccess.php
            +-- securitySuccess.php
            +-- siteInformationSuccess.php
            +-- templateSuccess.php
            +-- treeviewSuccess.php
            +-- uploadsSuccess.php
            +-- visibleElementsSuccess.php
            +-- webhooksSuccess.php
```

---

## Database Schema

### ERD Diagram

```
+-----------------------------------+     +-----------------------------------+
|          ahg_settings             |     |           ahg_api_key             |
+-----------------------------------+     +-----------------------------------+
| PK id INT AUTO_INCREMENT          |     | PK id INT AUTO_INCREMENT          |
|    setting_key VARCHAR(255) UNIQUE|     |    user_id INT                    |
|    setting_value TEXT             |     |    name VARCHAR(255)              |
|    setting_group VARCHAR(100)     |     |    api_key VARCHAR(64)            |
|    updated_at TIMESTAMP           |     |    api_key_prefix VARCHAR(8)      |
+-----------------------------------+     |    scopes JSON                    |
             |                            |    rate_limit INT                 |
             |                            |    expires_at DATETIME            |
             v                            |    last_used_at DATETIME          |
+-----------------------------------+     |    is_active TINYINT(1)           |
|         email_setting             |     |    created_at TIMESTAMP           |
+-----------------------------------+     |    updated_at TIMESTAMP           |
| PK id INT AUTO_INCREMENT          |     +-----------------------------------+
|    setting_key VARCHAR(255)       |
|    setting_value TEXT             |     +-----------------------------------+
|    setting_group VARCHAR(50)      |     |        numbering_scheme           |
|    updated_at TIMESTAMP           |     +-----------------------------------+
+-----------------------------------+     | PK id INT AUTO_INCREMENT          |
                                          |    name VARCHAR(100)              |
+-----------------------------------+     |    sector ENUM(archive,library,   |
| preservation_replication_target   |     |           museum,gallery,dam,all) |
+-----------------------------------+     |    pattern VARCHAR(255)           |
| PK id INT AUTO_INCREMENT          |     |    description TEXT               |
|    name VARCHAR(100)              |     |    current_sequence BIGINT        |
|    target_type ENUM(local,sftp,   |     |    sequence_reset ENUM(never,     |
|                rsync,s3)          |     |                 yearly,monthly)   |
|    connection_config JSON         |     |    last_reset_date DATE           |
|    description TEXT               |     |    fill_gaps TINYINT(1)           |
|    is_active TINYINT(1)           |     |    validation_regex VARCHAR(255)  |
|    created_at TIMESTAMP           |     |    allow_manual_override TINYINT  |
|    updated_at TIMESTAMP           |     |    is_active TINYINT(1)           |
+-----------------------------------+     |    is_default TINYINT(1)          |
             |                            |    created_at TIMESTAMP           |
             v                            |    updated_at TIMESTAMP           |
+-----------------------------------+     +-----------------------------------+
|  preservation_replication_log     |                    |
+-----------------------------------+                    v
| PK id INT AUTO_INCREMENT          |     +-----------------------------------+
|    target_id INT                  |     |      numbering_sequence_used      |
|    started_at DATETIME            |     +-----------------------------------+
|    completed_at DATETIME          |     | PK id INT AUTO_INCREMENT          |
|    status ENUM(pending,running,   |     |    scheme_id INT                  |
|           completed,failed)       |     |    sequence_number BIGINT         |
|    bytes_synced BIGINT            |     |    generated_reference VARCHAR    |
|    files_synced INT               |     |    object_id INT                  |
|    error_message TEXT             |     |    object_type VARCHAR(50)        |
+-----------------------------------+     |    year_context YEAR              |
                                          |    month_context TINYINT          |
                                          |    created_at TIMESTAMP           |
                                          +-----------------------------------+
```

### SQL Schema - ahg_settings

```sql
CREATE TABLE ahg_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(100) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_setting_group (setting_group),
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### SQL Schema - ahg_api_key

```sql
CREATE TABLE ahg_api_key (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    api_key VARCHAR(64) NOT NULL,
    api_key_prefix VARCHAR(8) NOT NULL,
    scopes JSON,
    rate_limit INT DEFAULT 1000,
    expires_at DATETIME,
    last_used_at DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_api_key (api_key),
    INDEX idx_prefix (api_key_prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### SQL Schema - numbering_scheme

```sql
CREATE TABLE IF NOT EXISTS numbering_scheme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sector ENUM('archive', 'library', 'museum', 'gallery', 'dam', 'all') NOT NULL,
    pattern VARCHAR(255) NOT NULL,
    description TEXT,
    current_sequence BIGINT DEFAULT 0,
    sequence_reset ENUM('never', 'yearly', 'monthly') DEFAULT 'never',
    last_reset_date DATE,
    fill_gaps TINYINT(1) DEFAULT 0,
    validation_regex VARCHAR(255),
    allow_manual_override TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_sector (sector),
    INDEX idx_sector_default (sector, is_default),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Routes

Registered in `ahgSettingsPluginConfiguration.class.php`:

| Route Name | URL | Action |
|------------|-----|--------|
| admin_ahg_settings | /admin/ahg-settings | index |
| admin_ahg_settings_section | /admin/ahg-settings/section | section |
| admin_ahg_settings_plugins | /admin/ahg-settings/plugins | plugins |
| admin_ahg_settings_ai_services | /admin/ahg-settings/ai-services | aiServices |
| admin_ahg_settings_email | /admin/ahg-settings/email | email |
| ahg_settings_export | /ahgSettings/export | export |
| ahg_settings_import | /ahgSettings/import | import |
| ahg_settings_reset | /ahgSettings/reset | reset |
| ahg_settings_email_test | /ahgSettings/emailTest | emailTest |
| ahg_settings_fuseki_test | /ahgSettings/fusekiTest | fusekiTest |
| ahg_settings_plugins | /ahgSettings/plugins | plugins |
| ahg_settings_api_keys | /admin/ahg-settings/api-keys | apiKeys |
| ahg_settings_preservation | /ahgSettings/preservation | preservation |
| ahg_settings_levels | /ahgSettings/levels | levels |
| ahg_settings_dam_tools | /ahgSettings/damTools | damTools |
| ahg_settings_webhooks | /ahgSettings/webhooks | webhooks |
| admin_ahg_settings_webhooks | /admin/ahg-settings/webhooks | webhooks |
| ahg_settings_ahg_integration | /ahgSettings/ahgIntegration | ahgIntegration |
| admin_ahg_settings_ahg_integration | /admin/ahg-settings/ahg-integration | ahgIntegration |

---

## Service Layer

### AhgSettingsService

Primary service for settings management with caching.

```php
namespace AhgSettings\Services;

use Illuminate\Database\Capsule\Manager as DB;

class AhgSettingsService
{
    private static ?array $cache = null;

    /**
     * Get a single setting value
     */
    public static function get(string $key, $default = null);

    /**
     * Get a boolean setting (handles string 'true'/'false')
     */
    public static function getBool(string $key, bool $default = false): bool;

    /**
     * Get an integer setting
     */
    public static function getInt(string $key, int $default = 0): int;

    /**
     * Get all settings for a group
     */
    public static function getGroup(string $group): array;

    /**
     * Check if a feature is enabled
     */
    public static function isEnabled(string $feature): bool;

    /**
     * Save a setting
     */
    public static function set(string $key, $value, string $group = 'general'): bool;

    /**
     * Clear the cache (call after saving settings)
     */
    public static function clearCache(): void;
}
```

### Usage Examples

```php
use AhgSettings\Services\AhgSettingsService;

// Get a setting
$primaryColor = AhgSettingsService::get('ahg_primary_color', '#1a5f7a');

// Get boolean
$themeEnabled = AhgSettingsService::getBool('ahg_theme_enabled', false);

// Check feature enabled
if (AhgSettingsService::isEnabled('metadata')) {
    // Metadata extraction is enabled
}

// Save setting
AhgSettingsService::set('ahg_primary_color', '#2a6f8a', 'general');
```

---

## Setting Sections

### Section Configuration

Defined in `sectionAction.class.php`:

```php
protected $sections = [
    'general' => ['label' => 'General Settings', 'icon' => 'fa-cog'],
    'multi_tenant' => ['label' => 'Multi-Tenancy', 'icon' => 'fa-building'],
    'metadata' => [...],  // Metadata extraction settings
    'iiif' => ['label' => 'IIIF Viewer', 'icon' => 'fa-images'],
    'spectrum' => ['label' => 'Spectrum / Collections', 'icon' => 'fa-archive'],
    'data_protection' => ['label' => 'Data Protection', 'icon' => 'fa-shield-alt'],
    'faces' => ['label' => 'Face Detection', 'icon' => 'fa-user-circle'],
    'media' => ['label' => 'Media Player', 'icon' => 'fa-play-circle'],
    'photos' => ['label' => 'Condition Photos', 'icon' => 'fa-camera'],
    'ingest' => ['label' => 'Ingest Defaults', 'icon' => 'fa-upload'],
    'jobs' => ['label' => 'Background Jobs', 'icon' => 'fa-tasks'],
    'fuseki' => ['label' => 'Fuseki / RIC', 'icon' => 'fa-project-diagram'],
];
```

### Checkbox Fields by Section

```php
protected $checkboxFields = [
    'general' => ['ahg_theme_enabled', 'ahg_show_branding'],
    'multi_tenant' => ['tenant_enabled', 'tenant_enforce_filter', 'tenant_show_switcher'],
    'metadata' => [
        'meta_extract_on_upload', 'meta_auto_populate', 'meta_images',
        'meta_pdf', 'meta_office', 'meta_video', 'meta_audio',
        'meta_extract_gps', 'meta_extract_technical', 'meta_extract_xmp'
    ],
    'spectrum' => ['spectrum_enabled', 'spectrum_auto_create_movement'],
    'iiif' => ['iiif_enabled', 'iiif_show_navigator', 'iiif_show_fullscreen'],
    'data_protection' => ['dp_enabled', 'dp_notify_overdue', 'dp_anonymize_on_delete'],
    'faces' => ['face_detect_enabled', 'face_auto_match', 'face_blur_unmatched'],
    'media' => ['media_autoplay', 'media_show_controls', 'media_loop'],
    'ingest' => [
        'ingest_process_ner', 'ingest_process_ocr', 'ingest_process_virus_scan',
        'ingest_process_summarize', 'ingest_process_spellcheck', 'ingest_process_translate',
        'ingest_process_format_id', 'ingest_process_face_detect',
        'ingest_output_create_records', 'ingest_output_generate_sip',
        'ingest_output_generate_aip', 'ingest_output_generate_dip',
        'ingest_derivative_thumbnails', 'ingest_derivative_reference',
    ],
    'jobs' => ['jobs_enabled', 'jobs_notify_failure'],
    'fuseki' => ['fuseki_sync_enabled', 'fuseki_queue_enabled', 'fuseki_sync_on_save'],
];
```

### Section-Plugin Mapping

Sections only appear if required plugin is enabled:

```php
protected $sectionPluginMap = [
    'spectrum' => 'ahgSpectrumPlugin',
    'data_protection' => 'ahgDataProtectionPlugin',
    'photos' => 'ahgConditionPlugin',
    'fuseki' => 'ahgRicExplorerPlugin',
    'ingest' => 'ahgIngestPlugin',
    'audit' => 'ahgAuditTrailPlugin',
    'faces' => 'ahgFaceDetectionPlugin',
    'multi_tenant' => 'ahgMultiTenantPlugin',
];
```

---

## Plugin Management

### Plugin Categories

```php
protected function getCategories()
{
    return [
        'core' => ['label' => 'Core Plugins', 'icon' => 'fa-cube', 'class' => 'primary'],
        'theme' => ['label' => 'Themes', 'icon' => 'fa-palette', 'class' => 'info'],
        'ahg' => ['label' => 'AHG Extensions', 'icon' => 'fa-puzzle-piece', 'class' => 'success'],
        'integration' => ['label' => 'Integrations', 'icon' => 'fa-plug', 'class' => 'warning'],
        'other' => ['label' => 'Other', 'icon' => 'fa-ellipsis-h', 'class' => 'secondary'],
    ];
}
```

### Enable/Disable Flow

```
User Action (Enable/Disable)
          |
          v
+---------------------+
| Check Dependencies  |
+---------------------+
          |
          v
+---------------------+
| Update atom_plugin  |
| is_enabled column   |
+---------------------+
          |
          v
+---------------------+
| Sync to setting_i18n|
| (Legacy support)    |
+---------------------+
          |
          v
+---------------------+
| Log to audit table  |
+---------------------+
          |
          v
+---------------------+
| Clear Symfony cache |
+---------------------+
```

---

## Numbering Scheme System

### Pattern Tokens

| Token | Description | Example Output |
|-------|-------------|----------------|
| {SEQ:N} | Zero-padded sequence number | {SEQ:4} -> 0001 |
| {YEAR} | Current 4-digit year | 2026 |
| {MONTH} | Current 2-digit month | 01 |
| {REPO} | Repository identifier | NARSSA |
| {FONDS} | Fonds identifier | F001 |
| {SERIES} | Series identifier | S01 |
| {COLLECTION} | Collection identifier | COL001 |
| {DEPT} | Department code | ART |
| {TYPE} | Media type | IMG |
| {PROJECT} | Project code | PROJ01 |
| {PREFIX} | Custom prefix | LIB |
| {ITEM} | Item number | 01 |

### Default Schemes

```sql
-- Archives
('Archive Standard', 'archive', '{REPO}/{FONDS}/{SEQ:4}', 1),
('Archive Year-Based', 'archive', '{YEAR}/{SEQ:4}', 0),
('Archive Simple', 'archive', 'ARCH-{SEQ:5}', 0),

-- Libraries
('Library Accession', 'library', 'LIB{YEAR}{SEQ:5}', 1),
('Library Barcode', 'library', '3{SEQ:12}', 0),
('Library Call Number', 'library', '{PREFIX}/{YEAR}/{SEQ:4}', 0),

-- Museums
('Museum Object Number', 'museum', '{YEAR}.{SEQ:4}', 1),
('Museum Department', 'museum', '{DEPT}-{YEAR}-{SEQ:4}', 0),

-- Galleries
('Gallery Artwork', 'gallery', 'GAL-{SEQ:6}', 1),

-- DAM
('DAM Asset ID', 'dam', 'DAM-{YEAR}-{SEQ:6}', 1),
('DAM Media Type', 'dam', '{TYPE}-{SEQ:6}', 0),
```

### NumberingFilter Class

Hooks into object save to auto-assign identifiers:

```php
class NumberingFilter
{
    /**
     * Hook into QubitInformationObject pre-save
     */
    public static function assignIdentifier(QubitInformationObject $object): void;

    /**
     * Determine sector from information object display standard
     */
    private static function getSectorFromObject(QubitInformationObject $object): ?string;

    /**
     * Build context for token replacement
     */
    private static function buildContext(QubitInformationObject $object): array;

    /**
     * Validate identifier before save
     */
    public static function validateIdentifier(
        string $identifier,
        string $sector,
        ?int $excludeId = null,
        ?int $repositoryId = null
    ): array;
}
```

---

## AHG Central Integration

### Overview

AHG Central is a cloud service provided by The Archive and Heritage Group for shared AI training and future cloud features. Configuration is managed via the AHG Central settings page.

### Access

Navigate to: **Admin > AHG Plugin Settings > AHG Central**

Or directly: `/ahgSettings/ahgIntegration`

### Settings

| Setting | Storage Key | Description |
|---------|-------------|-------------|
| Enable Integration | ahg_central_enabled | Master switch for cloud features |
| API URL | ahg_central_api_url | AHG Central endpoint (default: https://train.theahg.co.za/api) |
| API Key | ahg_central_api_key | Authentication key (contact support@theahg.co.za) |
| Site ID | ahg_central_site_id | Unique identifier for this AtoM instance |

### Settings Hierarchy

Database settings take precedence over environment variables:

```
Database settings (preferred)
    ↓
Environment variables (fallback)
    ↓
Default values
```

### Legacy Environment Variables

For backward compatibility, the following environment variables are still supported:

| Variable | Maps To |
|----------|---------|
| NER_TRAINING_API_URL | ahg_central_api_url |
| NER_API_KEY | ahg_central_api_key |
| NER_SITE_ID | ahg_central_site_id |

### Test Connection

The settings page includes a "Test Connection" button that:
1. Sends a request to the `/health` endpoint
2. Validates API key authentication
3. Reports connection status

### NerTrainingSync Integration

The `NerTrainingSync` class automatically uses database settings when available:

```php
// In NerTrainingSync constructor
$this->centralApiUrl = $this->getDbSetting('ahg_central_api_url')
    ?: getenv('NER_TRAINING_API_URL')
    ?: 'https://train.theahg.co.za/api';
```

The `isEnabled()` method checks if integration is configured and enabled before attempting to sync training data.

---

## AI Services Configuration

### Settings Stored in ahg_ner_settings

| Setting Key | Default | Description |
|------------|---------|-------------|
| ner_enabled | 1 | Enable NER extraction |
| summarizer_enabled | 1 | Enable text summarization |
| spellcheck_enabled | 0 | Enable spell checking |
| translation_enabled | 1 | Enable translation |
| processing_mode | job | 'job' or 'realtime' |
| api_url | http://192.168.0.112:5004/ai/v1 | AI service endpoint |
| api_key | ahg_ai_demo_internal_2026 | API authentication |
| api_timeout | 60 | Request timeout seconds |
| ner_entity_types | ["PERSON","ORG","GPE","DATE"] | Entities to extract |
| translation_source_lang | en | Source language |
| translation_target_lang | af | Target language |
| translation_mode | review | 'review' or 'auto' |
| translation_sector | archives | GLAM sector |

### Translation Fields by Sector

```php
$this->translationFieldsBySector = [
    'archives' => [
        'title', 'scope_and_content', 'archival_history',
        'acquisition', 'arrangement', 'access_conditions',
        'reproduction_conditions', 'finding_aids', ...
    ],
    'library' => [
        'title', 'alternate_title', 'edition',
        'extent_and_medium', 'scope_and_content', ...
    ],
    'museum' => [...],
    'gallery' => [...],
    'dam' => [...]
];
```

---

## Export/Import

### Export Format

```json
{
    "exported_at": "2026-01-30 10:15:00",
    "version": "1.0",
    "settings": {
        "general": {
            "ahg_theme_enabled": "true",
            "ahg_primary_color": "#1a5f7a",
            "ahg_secondary_color": "#57837b"
        },
        "metadata": {
            "meta_extract_on_upload": "true",
            "meta_images": "true"
        },
        "iiif": {
            "iiif_enabled": "true"
        }
    }
}
```

### Export Action

```php
class AhgSettingsExportAction extends sfAction
{
    public function execute($request)
    {
        // Get all settings grouped by setting_group
        $sql = "SELECT setting_key, setting_value, setting_group
                FROM ahg_settings ORDER BY setting_group, setting_key";

        // Build export structure
        $export = [
            'exported_at' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'settings' => $settings
        ];

        // Send as download
        $response->setHttpHeader('Content-Type', 'application/json');
        $response->setHttpHeader('Content-Disposition',
            'attachment; filename="ahg-settings-' . date('Y-m-d') . '.json"');
    }
}
```

---

## API Key Management

### Key Generation Flow

```
1. Generate 32-byte random key (64 hex chars)
   $apiKey = bin2hex(random_bytes(32));

2. Store prefix for identification
   $apiKeyPrefix = substr($apiKey, 0, 8);

3. Hash key for storage
   $hashedKey = hash('sha256', $apiKey);

4. Insert to database
   [user_id, name, api_key (hashed), api_key_prefix, scopes, ...]

5. Display plain key ONCE to user
   Flash: 'new_api_key' => $apiKey
```

### API Key Scopes

```php
$scopes = $request->getParameter('scopes', ['read']);
// Available scopes: read, write, delete
```

---

## Preservation Targets

### Target Types

| Type | Configuration Fields |
|------|---------------------|
| local | path |
| sftp | host, port, path, user |
| rsync | host, port, path, user |
| s3 | bucket, region |

### Connection Config Storage

```json
// Local
{"path": "/backup/archive"}

// SFTP
{"host": "backup.example.com", "port": 22, "path": "/archive", "user": "backup"}

// S3
{"bucket": "archive-backup", "region": "af-south-1"}
```

---

## Menu Component

The settings sidebar menu is built dynamically:

```php
class ahgSettingsMenuComponent extends sfComponent
{
    public function execute($request)
    {
        $this->nodes = [
            ['label' => 'Clipboard', 'action' => 'clipboard'],
            ['label' => 'CSV Validator', 'action' => 'csvValidator'],
            ['label' => 'Default page elements', 'action' => 'pageElements'],
            ['label' => 'Default template', 'action' => 'template'],
            ['label' => 'Diacritics', 'action' => 'diacritics'],
            ['label' => 'Digital object derivatives', 'action' => 'digitalObjectDerivatives'],
            ['label' => 'DIP upload', 'action' => 'dipUpload'],
            ['label' => 'Finding Aid', 'action' => 'findingAid'],
            ['label' => 'Global', 'action' => 'global'],
            ['label' => 'I18n languages', 'action' => 'language'],
            // ... more nodes
        ];

        // Sort alphabetically
        usort($this->nodes, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
    }
}
```

---

## CSS Generation

When theme settings change, CSS is regenerated:

```php
if ($this->currentSection === 'general') {
    require_once sfConfig::get('sf_plugins_dir')
        . '/ahgThemeB5Plugin/lib/AhgCssGenerator.class.php';
    AhgCssGenerator::generate();
}
```

---

## Security

### Access Control

All actions check administrator privileges:

```php
if (!$this->context->user->isAdministrator()) {
    AclService::forwardUnauthorized();
}
```

### API Key Security

- Keys are hashed before storage (SHA-256)
- Plain key shown only once after creation
- Rate limiting supported
- Expiration dates optional

---

## Dependencies

| Package | Usage |
|---------|-------|
| atom-framework | Laravel Query Builder |
| ahgCorePlugin | AhgDb initialization |
| Propel | Legacy database operations |

---

## Configuration (extension.json)

```json
{
    "name": "AHG Settings",
    "machine_name": "ahgSettingsPlugin",
    "version": "1.0.1",
    "description": "Extended settings management for AtoM",
    "author": "The Archive and Heritage Group",
    "license": "GPL-3.0",
    "requires": {
        "atom_framework": ">=1.0.0",
        "atom": ">=2.8",
        "php": ">=8.1"
    },
    "dependencies": ["ahgCorePlugin"],
    "category": "admin",
    "load_order": 20
}
```

---

## Troubleshooting

### Settings Not Persisting

1. Check database connectivity:
```sql
SELECT * FROM ahg_settings LIMIT 5;
```

2. Verify table exists:
```sql
SHOW TABLES LIKE 'ahg_settings';
```

3. Check PHP error log for exceptions

### Plugin Enable/Disable Issues

1. Clear cache:
```bash
php symfony cc
rm -rf cache/*
```

2. Check atom_plugin table:
```sql
SELECT name, is_enabled, is_locked FROM atom_plugin;
```

3. Verify symlinks:
```bash
ls -la plugins/ | grep ahg
```

### Numbering Scheme Not Working

1. Check auto-generate setting:
```sql
SELECT * FROM ahg_settings WHERE setting_key LIKE '%numbering%';
```

2. Verify scheme is active:
```sql
SELECT * FROM numbering_scheme WHERE is_active = 1;
```

---

*Part of the AtoM AHG Framework*
