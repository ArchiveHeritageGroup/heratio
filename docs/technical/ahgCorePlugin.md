# ahgCorePlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Core
**Dependencies:** atom-framework
**Status:** Core Plugin (Locked)

---

## Overview

The ahgCorePlugin provides the foundation layer for all AHG plugins. It offers centralized database access, configuration resolution, taxonomy lookup, file storage utilities, hook/event system, capability registry, and cross-plugin contracts. This plugin must be loaded first (load_order: 1) and cannot be disabled.

---

## Architecture

```
+-------------------------------------------------------------------------+
|                         ahgCorePlugin                                    |
+-------------------------------------------------------------------------+
|                                                                          |
|  +-------------------------------------------------------------------+  |
|  |                        AhgCore Facade                              |  |
|  |  - Unified entry point to all services                            |  |
|  |  - Service container with registration/retrieval                   |  |
|  |  - Quick access: db(), config(), taxonomy(), storage()            |  |
|  +-------------------------------------------------------------------+  |
|                                    |                                     |
|           +------------------------+------------------------+            |
|           |                        |                        |            |
|           v                        v                        v            |
|  +----------------+     +------------------+     +------------------+    |
|  |    AhgDb       |     |   AhgConfig      |     |  AhgTaxonomy     |    |
|  +----------------+     +------------------+     +------------------+    |
|  | Laravel Query  |     | Path resolver    |     | Taxonomy IDs     |    |
|  | Builder init   |     | URL resolver     |     | Term IDs         |    |
|  | Transactions   |     | Settings access  |     | Dynamic lookup   |    |
|  | Connection     |     | Environment      |     | Caching          |    |
|  +----------------+     +------------------+     +------------------+    |
|           |                        |                        |            |
|           v                        v                        v            |
|  +----------------+     +------------------+     +------------------+    |
|  |  AhgStorage    |     |   AhgHooks       |     | AhgCapabilities  |    |
|  +----------------+     +------------------+     +------------------+    |
|  | File upload    |     | Hook dispatcher  |     | Feature registry |    |
|  | Sanitization   |     | Filter system    |     | Plugin caps      |    |
|  | Permissions    |     | Priority order   |     | Dependency-free  |    |
|  +----------------+     +------------------+     +------------------+    |
|                                                                          |
|  +-------------------------------------------------------------------+  |
|  |                       Contracts (Interfaces)                       |  |
|  +-------------------------------------------------------------------+  |
|  | AuditServiceInterface      | DisplayActionProviderInterface       |  |
|  | PluginServiceInterface     | MetadataExtractorInterface           |  |
|  +-------------------------------------------------------------------+  |
|                                                                          |
|  +-------------------------------------------------------------------+  |
|  |                         Services                                   |  |
|  +-------------------------------------------------------------------+  |
|  | EmailService           | WatermarkService                         |  |
|  | WatermarkSettingsService | AhgAccessGate                          |  |
|  +-------------------------------------------------------------------+  |
|                                                                          |
|  +-------------------------------------------------------------------+  |
|  |                    Modules (Web Endpoints)                         |  |
|  +-------------------------------------------------------------------+  |
|  | tts/      - Text-to-speech API for accessibility                  |  |
|  | user/     - Password reset, registration                          |  |
|  | menu/     - Quick links menu component                            |  |
|  +-------------------------------------------------------------------+  |
|                                                                          |
+-------------------------------------------------------------------------+
```

---

## Core Services

### AhgDb - Database Bootstrap

Provides centralized Laravel Query Builder initialization. Plugins should use `AhgDb::table()` instead of requiring bootstrap.php directly.

```php
namespace AhgCore\Core;

class AhgDb
{
    // Initialize database connection (idempotent)
    public static function init(): bool

    // Get database connection
    public static function connection(): ?Connection

    // Query builder shortcut
    public static function table(string $tableName): Builder

    // Raw queries
    public static function select(string $query, array $bindings = []): array
    public static function statement(string $query, array $bindings = []): bool

    // Transactions
    public static function beginTransaction(): void
    public static function commit(): void
    public static function rollBack(): void
    public static function transaction(callable $callback, int $attempts = 1): mixed

    // Connection status
    public static function isConnected(): bool
}
```

**Usage:**
```php
use AhgCore\Core\AhgDb;

// Simple query
$results = AhgDb::table('information_object')
    ->where('id', $id)
    ->first();

// Transaction
AhgDb::transaction(function() {
    AhgDb::table('my_table')->insert([...]);
    AhgDb::table('related_table')->update([...]);
});
```

---

### AhgConfig - Configuration Resolver

Eliminates hardcoded paths and URLs throughout plugins.

```php
namespace AhgCore\Core;

class AhgConfig
{
    // URLs
    public static function getSiteBaseUrl(): string
    public static function getUploadUrl(string $subPath = ''): string

    // Paths
    public static function getRootPath(): string
    public static function getFrameworkRoot(): string
    public static function getPluginsPath(): string
    public static function getAhgPluginsPath(): string
    public static function getUploadPath(string $subPath = ''): string
    public static function getCachePath(): string
    public static function getTempPath(): string
    public static function getLogPath(): string

    // Settings
    public static function get(string $name, mixed $default = null): mixed
    public static function getApp(string $name, mixed $default = null): mixed
    public static function getDbSetting(string $name, ?string $culture = 'en'): ?string

    // Environment
    public static function getCulture(): string
    public static function getEnvironment(): string
    public static function isDevelopment(): bool
    public static function isProduction(): bool
    public static function getPhpVersion(): string
    public static function getMaxUploadSize(): int
}
```

**Usage:**
```php
use AhgCore\Core\AhgConfig;

// Get base URL without hardcoding
$baseUrl = AhgConfig::getSiteBaseUrl();

// Get upload path
$uploadPath = AhgConfig::getUploadPath('documents');

// Get settings
$culture = AhgConfig::getCulture();
$siteName = AhgConfig::getDbSetting('siteTitle');
```

---

### AhgTaxonomy - Taxonomy Resolution

Replaces hardcoded taxonomy and term IDs with dynamic lookup and caching.

```php
namespace AhgCore\Core;

class AhgTaxonomy
{
    // Standard Taxonomy ID Constants
    public const TAXONOMY_SUBJECT = 35;
    public const TAXONOMY_PLACE = 42;
    public const TAXONOMY_LEVEL_OF_DESCRIPTION = 34;
    public const TAXONOMY_EVENT_TYPE = 40;
    // ... many more constants

    // Standard Term ID Constants
    public const TERM_CREATION = 111;
    public const TERM_PERSON = 131;
    public const TERM_ITEM = 140;
    public const TERM_FONDS = 141;
    // ... many more constants

    // Dynamic lookup methods
    public static function getTaxonomyId(string $name): ?int
    public static function getTermId(string|int $taxonomy, string $termName, string $culture = 'en'): ?int
    public static function getTermName(int $termId, string $culture = 'en'): ?string
    public static function getTaxonomyName(int $taxonomyId, string $culture = 'en'): ?string

    // Batch operations
    public static function getTerms(string|int $taxonomy, string $culture = 'en'): array
    public static function getOrCreateTerm(string|int $taxonomy, string $termName, string $culture = 'en'): ?int
    public static function termExists(string|int $taxonomy, string $termName, string $culture = 'en'): bool

    // Cache management
    public static function clearCache(): void
}
```

**Usage:**
```php
use AhgCore\Core\AhgTaxonomy;

// Use constants for known IDs
$taxonomyId = AhgTaxonomy::TAXONOMY_SUBJECT;

// Dynamic lookup by name
$taxonomyId = AhgTaxonomy::getTaxonomyId('SUBJECT');

// Get term ID
$termId = AhgTaxonomy::getTermId('EVENT_TYPE', 'Creation');

// Get all terms for a taxonomy
$terms = AhgTaxonomy::getTerms('LEVEL_OF_DESCRIPTION');

// Create term if not exists
$termId = AhgTaxonomy::getOrCreateTerm('SUBJECT', 'New Subject');
```

---

### AhgTaxonomyService - Custom Dropdown Vocabularies

Plugin-specific controlled vocabulary system that replaces hardcoded dropdown values with database-driven terms. Stored in the `ahg_dropdown` table, separate from AtoM's core taxonomy system.

```php
namespace ahgCorePlugin\Services;

class AhgTaxonomyService
{
    // Taxonomy Constants
    public const EXHIBITION_TYPE = 'exhibition_type';
    public const EXHIBITION_STATUS = 'exhibition_status';
    public const LOAN_STATUS = 'loan_status';
    public const WORKFLOW_STATUS = 'workflow_status';
    public const EMBARGO_TYPE = 'embargo_type';
    public const EMBARGO_REASON = 'embargo_reason';
    public const EMBARGO_STATUS = 'embargo_status';
    public const CONDITION_GRADE = 'condition_grade';
    public const DAMAGE_TYPE = 'damage_type';
    public const ID_TYPE = 'id_type';
    public const ORGANIZATION_TYPE = 'organization_type';
    public const EQUIPMENT_TYPE = 'equipment_type';
    public const CREATOR_ROLE = 'creator_role';
    public const DOCUMENT_TYPE = 'document_type';
    public const REMINDER_TYPE = 'reminder_type';
    public const RDF_FORMAT = 'rdf_format';
    // ... many more

    // Core Methods
    public function getTermsAsChoices(string $taxonomy, bool $includeEmpty = true): array
    public function getTermsWithAttributes(string $taxonomy): array
    public function getTermByCode(string $taxonomy, string $code): ?object
    public function getTermName(string $taxonomy, string $code): ?string
    public function getTermColor(string $taxonomy, string $code): ?string
    public function getDefaultTerm(string $taxonomy): ?object

    // Convenience Methods (examples)
    public function getEmbargoTypes(bool $includeEmpty = true): array
    public function getEmbargoReasons(bool $includeEmpty = true): array
    public function getConditionGrades(bool $includeEmpty = true): array
    public function getConditionGradesWithColors(): array
    public function getIdTypes(bool $includeEmpty = true): array
    public function getCreatorRoles(bool $includeEmpty = true): array
    // ... many more

    // CRUD Methods
    public function addTerm(string $taxonomy, string $taxonomyLabel, string $code, string $label, array $options = []): int
    public function updateTerm(int $id, array $data): bool
    public function deleteTerm(int $id, bool $hardDelete = false): bool
    public function createTaxonomy(string $code, string $label, array $terms = []): bool

    // Cache
    public static function clearCache(): void
}
```

**Database Table:** `ahg_dropdown`

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| taxonomy | VARCHAR(100) | Taxonomy code (e.g., 'loan_status') |
| taxonomy_label | VARCHAR(255) | Display name (e.g., 'Loan Status') |
| code | VARCHAR(100) | Term code (e.g., 'draft') |
| label | VARCHAR(255) | Term display name |
| color | VARCHAR(7) | Hex color (e.g., '#4caf50') |
| icon | VARCHAR(50) | Icon class (e.g., 'fa-check') |
| sort_order | INT | Display order |
| is_default | TINYINT | Default selection flag |
| is_active | TINYINT | Soft delete flag |
| metadata | JSON | Extended attributes |

**Available Taxonomies (35 total):**
- Exhibition: `exhibition_type`, `exhibition_status`, `exhibition_object_status`
- Loans: `loan_status`, `loan_type`
- Workflow: `workflow_status`, `rtp_status`
- Rights: `rights_basis`, `copyright_status`, `act_type`, `restriction_type`
- Embargo: `embargo_type`, `embargo_reason`, `embargo_status`
- Condition: `condition_grade`, `damage_type`, `report_type`, `image_type`
- Shipping: `shipment_type`, `shipment_status`, `cost_type`
- Research: `id_type`, `organization_type`, `equipment_type`, `equipment_condition`, `workspace_privacy`
- Library: `creator_role`
- Documents: `document_type`, `reminder_type`
- Export: `rdf_format`
- Agreements: `agreement_status`
- Links: `link_status`
- Other: `work_type`, `source_type`

**Usage:**
```php
use ahgCorePlugin\Services\AhgTaxonomyService;

$taxonomyService = new AhgTaxonomyService();

// Get choices for a dropdown (code => label)
$statuses = $taxonomyService->getLoanStatuses();
// Returns: ['draft' => 'Draft', 'active' => 'Active', ...]

// Get full term data with colors/icons
$statusesWithColors = $taxonomyService->getLoanStatusesWithColors();
// Returns: ['draft' => {code: 'draft', name: 'Draft', color: '#9e9e9e', ...}, ...]

// Get single term
$termName = $taxonomyService->getTermName('loan_status', 'active');
// Returns: 'Active'

$termColor = $taxonomyService->getTermColor('loan_status', 'active');
// Returns: '#4caf50'
```

**In Templates:**
```php
<?php
$taxonomyService = new \ahgCorePlugin\Services\AhgTaxonomyService();
$statuses = $taxonomyService->getLoanStatuses(false);
?>
<select name="status" class="form-select">
  <?php foreach ($statuses as $code => $label): ?>
    <option value="<?php echo $code ?>"><?php echo __($label) ?></option>
  <?php endforeach; ?>
</select>
```

**Admin UI:** Managed via ahgSettingsPlugin at `/admin/dropdowns`

---

### AhgStorage - File Storage Utilities

Centralized file storage with proper permissions, sanitization, and security.

```php
namespace AhgCore\Core;

class AhgStorage
{
    public const DIR_MODE = 0775;
    public const FILE_MODE = 0664;
    public const MAX_FILENAME_LENGTH = 200;

    // Directory operations
    public static function mkdir(string $path, int $mode = self::DIR_MODE, bool $recursive = true): bool
    public static function deleteDirectory(string $path): bool

    // File operations
    public static function store(array $uploadedFile, string $subDirectory = '', ?string $customName = null): ?array
    public static function storeContent(string $content, string $filename, string $subDirectory = ''): ?array
    public static function copy(string $sourcePath, string $subDirectory = '', ?string $newName = null): ?array
    public static function delete(string $path): bool

    // Filename handling
    public static function sanitizeFilename(string $filename, bool $allowUnicode = true): string
    public static function uniqueFilename(string $directory, string $filename): string

    // File info
    public static function getInfo(string $path): ?array
    public static function isWithinUploads(string $path): bool
    public static function formatSize(int $bytes, int $precision = 2): string
    public static function getMimeType(string $extension): string
}
```

**Blocked Extensions:**
- php, phtml, php3, php4, php5, php7, phps, phar
- exe, bat, cmd, sh, bash, ps1
- js, htaccess, htpasswd

**Usage:**
```php
use AhgCore\Core\AhgStorage;

// Store uploaded file
$result = AhgStorage::store($_FILES['upload'], 'documents');
// Returns: ['path' => '...', 'filename' => '...', 'url' => '...', 'size' => ..., 'mime_type' => '...']

// Sanitize filename
$safe = AhgStorage::sanitizeFilename('My File (1).pdf');
// Returns: 'My_File_1.pdf'

// Create directory with proper permissions
AhgStorage::mkdir('/path/to/dir');
```

---

## Extensibility Systems

### AhgHooks - Hook/Event Dispatcher

Simple hook system allowing plugins to extend functionality without module conflicts.

```php
namespace AhgCore;

class AhgHooks
{
    // Register a callback for a hook
    public static function register(string $hook, callable $callback, int $priority = 10): void

    // Trigger hook and collect results
    public static function trigger(string $hook, ...$args): array

    // Filter hook (passes value through callbacks)
    public static function filter(string $hook, $value, ...$args)

    // Check if hook has callbacks
    public static function has(string $hook): bool

    // Clear callbacks for a hook
    public static function clear(string $hook): void

    // Get registered hook names
    public static function getRegisteredHooks(): array
}
```

**Usage:**
```php
use AhgCore\AhgHooks;

// Register a hook callback
AhgHooks::register('record.view.panels', function($record) {
    return ['title' => 'My Panel', 'content' => '...'];
}, priority: 5);

// Trigger hooks and collect results
$panels = AhgHooks::trigger('record.view.panels', $record);

// Filter a value through callbacks
$content = AhgHooks::filter('content.display', $rawContent, $context);
```

---

### AhgPanels - Panel Registry

Register panels for record display pages without duplicating module actions.

```php
namespace AhgCore;

class AhgPanels
{
    // Position constants
    public const POSITION_SIDEBAR = 'sidebar';
    public const POSITION_MAIN = 'main';
    public const POSITION_HEADER = 'header';
    public const POSITION_FOOTER = 'footer';
    public const POSITION_ACTIONS = 'actions';

    // Register a panel
    public static function register(string $recordType, string $id, array $config): void

    // Get panels for a position
    public static function forPosition(string $recordType, string $position, $record = null): array

    // Get all panels for a record type
    public static function forRecordType(string $recordType): array

    // Check if panels exist
    public static function hasForPosition(string $recordType, string $position): bool

    // Remove a panel
    public static function remove(string $recordType, string $id): void
}
```

**Panel Configuration:**
| Key | Type | Description |
|-----|------|-------------|
| title | string | Panel heading |
| partial | string | Template path |
| position | string | sidebar, main, header, footer, actions |
| weight | int | Sort order (lower = earlier) |
| condition | callable | Optional visibility callback |
| provider | string | Plugin providing panel |

**Usage:**
```php
use AhgCore\AhgPanels;

// Register panel
AhgPanels::register('informationobject', 'iiif_viewer', [
    'title' => 'IIIF Viewer',
    'partial' => 'ahgIiifPlugin/iiifViewer',
    'position' => 'sidebar',
    'weight' => 10,
    'condition' => fn($record) => $record->hasDigitalObject(),
]);

// In template: render panels
<?php foreach (AhgPanels::forPosition('informationobject', 'sidebar', $resource) as $panel): ?>
    <?php include_partial($panel['partial'], ['resource' => $resource]); ?>
<?php endforeach; ?>
```

---

### AhgCapabilities - Feature Registry

Registry of capabilities provided by enabled plugins, allowing dependency-free feature detection.

```php
namespace AhgCore;

class AhgCapabilities
{
    // Standard capability constants
    public const IIIF = 'iiif';
    public const MODEL_3D = '3d';
    public const AI = 'ai';
    public const PII = 'pii';
    public const RIGHTS = 'rights';
    public const LOANS = 'loans';
    public const CART = 'cart';
    public const FAVORITES = 'favorites';
    public const BACKUP = 'backup';
    public const AUDIT = 'audit';
    public const SPECTRUM = 'spectrum';
    public const PRIVACY = 'privacy';
    public const SECURITY = 'security';

    // Register a capability
    public static function register(string $capability, string $provider, array $metadata = []): void

    // Check if capability is available
    public static function has(string $capability): bool

    // Get capability info
    public static function get(string $capability): ?array
    public static function getProvider(string $capability): ?string

    // Get all capabilities
    public static function all(): array
    public static function forPlugin(string $plugin): array

    // Feature check
    public static function hasFeature(string $capability, string $feature): bool
}
```

**Usage:**
```php
use AhgCore\AhgCapabilities;

// Plugin registers its capability
AhgCapabilities::register('iiif', 'ahgIiifPlugin', [
    'version' => '2.0',
    'features' => ['manifest', 'annotations', 'ocr']
]);

// Check if capability is available
if (AhgCapabilities::has('iiif')) {
    // Use IIIF features
}

// Check specific feature
if (AhgCapabilities::hasFeature('iiif', 'ocr')) {
    // OCR is available
}
```

---

### AhgSectorProfile - Sector Profiles

Manages sector-specific configurations (Museum, Library, Gallery, DAM, Archive).

```php
namespace AhgCore;

class AhgSectorProfile
{
    // Sector constants
    public const SECTOR_MUSEUM = 'museum';
    public const SECTOR_LIBRARY = 'library';
    public const SECTOR_GALLERY = 'gallery';
    public const SECTOR_DAM = 'dam';
    public const SECTOR_ARCHIVE = 'archive';

    // Register a sector profile
    public static function register(string $sector, array $profile): void

    // Set/get active sector
    public static function setActive(string $sector): void
    public static function getActive(): ?string

    // Get sector-specific values
    public static function getLabel(string $field, string $default = ''): string
    public static function getVocabulary(string $field): ?array
    public static function getDefault(string $field, $default = null)

    // Profile access
    public static function all(): array
    public static function get(string $sector): ?array
    public static function has(string $sector): bool
}
```

**Profile Structure:**
```php
[
    'name' => 'Museum',
    'standard' => 'Spectrum 5.0 / CCO',
    'labels' => [
        'extent' => 'Dimensions',
        'scope_and_content' => 'Description',
    ],
    'vocabularies' => [...],
    'defaults' => [...],
    'templates' => [...],
    'capabilities' => ['spectrum', 'loans'],
]
```

---

## Contracts (Interfaces)

### AuditServiceInterface

For audit logging services (implemented by ahgAuditTrailPlugin).

```php
namespace AhgCore\Contracts;

interface AuditServiceInterface
{
    // Action constants
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_VIEW = 'view';
    public const ACTION_DOWNLOAD = 'download';
    public const ACTION_EXPORT = 'export';
    public const ACTION_IMPORT = 'import';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_PUBLISH = 'publish';
    public const ACTION_UNPUBLISH = 'unpublish';

    // Status constants
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILURE = 'failure';
    public const STATUS_DENIED = 'denied';

    public function isEnabled(): bool;
    public function log(string $action, string $entityType, ?int $entityId = null, array $options = []): mixed;
    public function logCreate(object $entity, array $newValues = [], array $options = []): mixed;
    public function logUpdate(object $entity, array $oldValues = [], array $newValues = [], array $options = []): mixed;
    public function logDelete(object $entity, array $options = []): mixed;
    public function logDownload(object $entity, string $filePath, string $fileName, ?string $mimeType = null, ?int $fileSize = null): mixed;
    public function logAccessDenied(object $entity, string $reason, array $options = []): mixed;
}
```

---

### DisplayActionProviderInterface

For plugins that provide actions/panels/badges to display views.

```php
namespace AhgCore\Contracts;

interface DisplayActionProviderInterface
{
    public function getProviderId(): string;
    public function getActions(object $entity, array $context = []): array;
    public function getPanels(object $entity, array $context = []): array;
    public function getBadges(object $entity, array $context = []): array;
    public function supportsEntity(string $entityType): bool;
    public function getConfig(): array;
}
```

**Action Definition:**
```php
[
    'id' => 'edit_rights',
    'label' => 'Edit Rights',
    'url' => '/rights/edit/123',
    'icon' => 'fa-lock',
    'class' => 'btn btn-primary',
    'permission' => 'canEditRights',
    'order' => 100,
]
```

**Badge Definition:**
```php
[
    'id' => 'restricted_badge',
    'label' => 'Restricted',
    'class' => 'badge bg-danger',
    'icon' => 'fa-lock',
    'tooltip' => 'This record has access restrictions',
    'order' => 10,
]
```

---

### PluginServiceInterface

Base interface for plugin services.

```php
namespace AhgCore\Contracts;

interface PluginServiceInterface
{
    public function getPluginName(): string;
    public function isEnabled(): bool;
    public function initialize(): void;
    public function getConfig(): array;
}
```

---

### MetadataExtractorInterface

For metadata extraction services.

```php
namespace AhgCore\Contracts;

interface MetadataExtractorInterface
{
    public function extract(string $filePath, array $options = []): array;
    public function getSupportedTypes(): array;
    public function supports(string $mimeType): bool;
    public function getId(): string;
}
```

---

## Main Facade

### AhgCore

Unified entry point to all services.

```php
namespace AhgCore;

class AhgCore
{
    // Service access
    public static function db(): AhgDb
    public static function config(): AhgConfig
    public static function taxonomy(): AhgTaxonomy
    public static function storage(): AhgStorage

    // Database shortcut
    public static function table(string $tableName): Builder

    // Service container
    public static function registerService(string $interface, object|callable $implementation): void
    public static function getService(string $interface): ?object
    public static function hasService(string $interface): bool

    // Audit shortcuts
    public static function audit(): ?AuditServiceInterface
    public static function logAudit(string $action, string $entityType, ?int $entityId = null, array $options = []): mixed

    // User context
    public static function getCurrentUserId(): ?int
    public static function getCurrentUsername(): ?string

    // Utilities
    public static function getVersion(): string
    public static function isCli(): bool
    public static function clearCaches(): void
}
```

**Usage:**
```php
use AhgCore\AhgCore;

// Quick database access
$results = AhgCore::table('information_object')->get();

// Register a service
AhgCore::registerService(AuditServiceInterface::class, new MyAuditService());

// Get a service
$audit = AhgCore::getService(AuditServiceInterface::class);

// Log audit event
AhgCore::logAudit('create', 'QubitInformationObject', $id);
```

---

## Services

### EmailService

Centralized email functionality using SMTP or PHP mail().

```php
namespace AhgCore\Services;

class EmailService
{
    public static function isEnabled(): bool
    public static function getSetting(string $key, $default = null)
    public static function send(string $to, string $subject, string $body, array $options = []): bool
    public static function parseTemplate(string $template, array $data): string
    public static function testConnection(string $testEmail): array

    // Pre-built notifications
    public static function sendResearcherPending(object $researcher): bool
    public static function sendResearcherApproved(object $researcher): bool
    public static function sendResearcherRejected(object $researcher, string $reason = ''): bool
    public static function sendPasswordReset(object $user, string $resetUrl): bool
    public static function sendBookingConfirmed(object $booking, object $researcher): bool
}
```

---

### WatermarkService / WatermarkSettingsService

Manages watermark configuration and application to images.

**Watermark Priority:**
1. Security classification watermark (highest)
2. Object-specific watermark (object_watermark_setting)
3. Default watermark (global setting)

```php
namespace AhgCore\Services;

class WatermarkService
{
    public static function getWatermarkConfig(int $objectId): ?array
    public static function hasWatermark(int $objectId): bool
    public static function getWatermarkImage(int $objectId): ?string
    public static function applyWatermark(string $imagePath, int $objectId): bool
}

class WatermarkSettingsService
{
    public static function getSetting(string $key, ?string $default = null): ?string
    public static function setSetting(string $key, string $value): bool
    public static function getAllSettings(): array
    public static function getWatermarkConfig(int $objectId): ?array
    public static function getWatermarkType(int $id): ?object
    public static function getWatermarkTypeByCode(string $code): ?object
    public static function getWatermarkTypes(): array
    public static function saveObjectWatermark(int $objectId, ?int $watermarkTypeId, bool $enabled = true): bool
    public static function updateCantaloupeCache(): int
}
```

---

### AhgAccessGate

Centralized access control with embargo support.

```php
namespace AhgCore\Access;

class AhgAccessGate
{
    public static function canView(int $objectId, ?\sfAction $action = null): bool
    public static function getBlockingEmbargo(int $objectId): ?array
    public static function isEmbargoServiceAvailable(): bool
}
```

---

## Modules

### tts/ - Text-to-Speech

Accessibility feature for reading record content aloud.

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/tts/settings` | GET | Get TTS settings for sector |
| `/tts/pdfText` | GET | Extract text from PDF for TTS |

**Response (pdfText):**
```json
{
    "success": true,
    "text": "Extracted text content...",
    "pages": 15,
    "chars": 12500,
    "redacted": false
}
```

Features:
- Integrates with ahgPrivacyPlugin for redacted PDFs
- Replaces redaction markers with spoken "redacted" for accessibility
- Uses pdftotext for extraction

---

### user/ - User Authentication

Extended user authentication functionality.

| Action | Description |
|--------|-------------|
| passwordReset | Request password reset email |
| passwordResetConfirm | Complete password reset |
| register | User registration |
| setAttribute | Set user attributes |

---

## Helper Functions

### AhgPluginHelper

Global helper functions for plugin status checking.

```php
// Check if plugin exists in filesystem
function ahg_plugin_exists($pluginName): bool

// Check if plugin is enabled in database
function ahg_plugin_enabled($pluginName): bool
```

---

### ahgEmbeddedMetadataParser

Wrapper for embedded metadata extraction (delegates to framework).

```php
class ahgEmbeddedMetadataParser
{
    public static function extract($filePath): array
    public static function extractExif($filePath): array
    public static function extractIptc($filePath): array
    public static function extractXmp($filePath): array
}
```

---

## Vendor Assets

Bundled JavaScript and CSS libraries for use by all plugins.

### JavaScript
| Library | File | Purpose |
|---------|------|---------|
| Chart.js | chart.min.js | Data visualization |
| Tom Select | tom-select.complete.min.js | Enhanced select inputs |
| Select2 | select2.min.js | Searchable dropdowns |
| Model Viewer | model-viewer.min.js | 3D model display |
| Sortable | Sortable.min.js | Drag and drop |

### CSS
| Library | File |
|---------|------|
| Select2 | select2.min.css |
| Select2 Bootstrap 5 | select2-bootstrap-5-theme.min.css |
| Tom Select Bootstrap 5 | tom-select.bootstrap5.min.css |

---

## Installation

```sql
-- Register plugin (automatic via install.sql)
INSERT IGNORE INTO atom_plugin (name, class_name, version, description, category, is_enabled, is_core, is_locked, load_order)
VALUES (
    'ahgCorePlugin',
    'ahgCorePluginConfiguration',
    '1.0.0',
    'Core utilities and shared services for AHG plugins',
    'core',
    1,
    1,
    1,
    1
);
```

---

## Configuration

Plugin configuration is managed via Symfony autoloader registration:

```php
class ahgCorePluginConfiguration extends sfPluginConfiguration
{
    public function initialize()
    {
        $this->registerAutoloader();
    }

    protected function registerAutoloader()
    {
        spl_autoload_register(function ($class) {
            if (strpos($class, 'AhgCore\\') === 0) {
                // Load from lib/ directory
            }
        });
    }
}
```

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | >= 8.1 |
| AtoM | >= 2.8 |
| atom-framework | >= 1.0.0 |
| MySQL | 8.0+ |

---

## Dependencies

This plugin has no dependencies on other AHG plugins. It is the foundation that other plugins depend on.

**Plugins that depend on ahgCorePlugin:**
- All AHG plugins use core services
- ahgAuditTrailPlugin implements AuditServiceInterface
- ahgDisplayPlugin uses DisplayActionProviderInterface
- ahgExtendedRightsPlugin provides EmbargoService for AhgAccessGate

---

*Part of the AtoM AHG Framework*
