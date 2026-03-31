# ahgDisplayPlugin - Technical Documentation

**Version:** 1.3.0
**Category:** Display
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Context-aware display system for GLAM (Galleries, Libraries, Archives, Museums) institutions. Provides automatic type detection, domain-specific display profiles, multiple layout modes, and Elasticsearch 7 integration for faceted searching and browsing.

---

## Architecture

```
+-----------------------------------------------------------------------+
|                        ahgDisplayPlugin                                |
+-----------------------------------------------------------------------+
|                                                                        |
|  +---------------------------+      +----------------------------+    |
|  |     Plugin Configuration  |      |     Event Dispatching      |    |
|  |  ahgDisplayPluginConfig   |      |  * routing.load_config     |    |
|  |  * PSR-4 autoloader       |      |  * template.filter_params  |    |
|  |  * Module registration    |      |  * QubitIO.save/insert     |    |
|  +---------------------------+      +----------------------------+    |
|               |                                  |                     |
|               V                                  V                     |
|  +------------------------------------------------------------+       |
|  |                    Core Services                            |       |
|  +------------------------------------------------------------+       |
|  |                         |                        |          |       |
|  |  DisplayService         |  DisplayTypeDetector   |  Display |       |
|  |  * getObjectDisplay()   |  * detect()            |  Registry|       |
|  |  * getLevels()          |  * detectAndSave()     |  * ext   |       |
|  |  * setObjectType()      |  * getProfile()        |  actions |       |
|  |                         |                        |          |       |
|  +------------------------------------------------------------+       |
|               |                                  |                     |
|               V                                  V                     |
|  +---------------------------+      +----------------------------+    |
|  |    Display Preferences    |      |   Elasticsearch Service    |    |
|  |  DisplayModeService       |      |  DisplayElasticsearchSvc   |    |
|  |  * getDisplaySettings()   |      |  * search()                |    |
|  |  * switchMode()           |      |  * browseByType()          |    |
|  |  * renderToggleButtons()  |      |  * reindexDisplayData()    |    |
|  +---------------------------+      +----------------------------+    |
|               |                                  |                     |
|               V                                  V                     |
|  +------------------------------------------------------------+       |
|  |                     Database Layer                          |       |
|  |   display_profile, display_level, display_object_config,    |       |
|  |   display_field, display_collection_type, display_mode_global|      |
|  |   user_display_preference                                   |       |
|  +------------------------------------------------------------+       |
|                                                                        |
+-----------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+---------------------------+       +---------------------------+
|   display_collection_type |       |    display_profile        |
+---------------------------+       +---------------------------+
| PK id INT                 |       | PK id INT                 |
|    code VARCHAR(30) UK    |       |    code VARCHAR(50) UK    |
|    parent_id INT          |       |    domain VARCHAR(20)     |
|    icon VARCHAR(50)       |       |    layout_mode ENUM       |
|    color VARCHAR(20)      |       |    thumbnail_size ENUM    |
| FK default_profile_id INT |------>|    thumbnail_position ENUM|
|    sort_order INT         |       |    identity_fields JSON   |
|    is_active TINYINT      |       |    description_fields JSON|
|    created_at DATETIME    |       |    context_fields JSON    |
+---------------------------+       |    access_fields JSON     |
         |                          |    technical_fields JSON  |
         V                          |    hidden_fields JSON     |
+---------------------------+       |    field_labels JSON      |
| display_collection_type_  |       |    available_actions JSON |
|        i18n               |       |    css_class VARCHAR(100) |
+---------------------------+       |    is_default TINYINT     |
| FK id INT                 |       |    is_active TINYINT      |
|    culture VARCHAR(10)    |       |    sort_order INT         |
|    name VARCHAR(100)      |       |    created_at DATETIME    |
|    description TEXT       |       +---------------------------+
+---------------------------+                    |
                                                 V
                                    +---------------------------+
                                    |   display_profile_i18n    |
                                    +---------------------------+
                                    | FK id INT                 |
                                    |    culture VARCHAR(10)    |
                                    |    name VARCHAR(100)      |
                                    |    description TEXT       |
                                    +---------------------------+

+---------------------------+       +---------------------------+
|      display_level        |       |     display_field         |
+---------------------------+       +---------------------------+
| PK id INT                 |       | PK id INT                 |
|    code VARCHAR(30) UK    |       |    code VARCHAR(50) UK    |
|    parent_code VARCHAR(30)|       |    field_group ENUM       |
|    domain VARCHAR(20)     |       |    data_type ENUM         |
|    valid_parent_codes JSON|       |    source_table VARCHAR   |
|    valid_child_codes JSON |       |    source_column VARCHAR  |
|    icon VARCHAR(50)       |       |    source_i18n TINYINT    |
|    color VARCHAR(20)      |       |    property_type_id INT   |
|    sort_order INT         |       |    taxonomy_id INT        |
|    is_active TINYINT      |       |    relation_type_id INT   |
|    atom_term_id INT       |       |    event_type_id INT      |
|    created_at DATETIME    |       |    isad_element VARCHAR   |
+---------------------------+       |    spectrum_unit VARCHAR  |
         |                          |    dc_element VARCHAR     |
         V                          |    sort_order INT         |
+---------------------------+       |    is_active TINYINT      |
|   display_level_i18n      |       |    created_at DATETIME    |
+---------------------------+       +---------------------------+
| FK id INT                 |                    |
|    culture VARCHAR(10)    |                    V
|    name VARCHAR(100)      |       +---------------------------+
|    description TEXT       |       |   display_field_i18n      |
+---------------------------+       +---------------------------+
                                    | FK id INT                 |
                                    |    culture VARCHAR(10)    |
                                    |    name VARCHAR(100)      |
                                    |    help_text TEXT         |
                                    +---------------------------+

+---------------------------+       +---------------------------+
|  display_object_config    |       |  display_object_profile   |
+---------------------------+       +---------------------------+
| PK id INT                 |       | PK id INT                 |
| FK object_id INT UK       |----+  | FK object_id INT          |
|    object_type VARCHAR(30)|    |  | FK profile_id INT         |
| FK primary_profile_id INT |    |  |    context VARCHAR(30)    |
|    created_at DATETIME    |    |  |    is_primary TINYINT     |
|    updated_at DATETIME    |    |  |    created_at DATETIME    |
+---------------------------+    |  +---------------------------+
         |                       |
         | FK to information_object
         V

+---------------------------+       +---------------------------+
|   display_mode_global     |       | user_display_preference   |
+---------------------------+       +---------------------------+
| PK id INT                 |       | PK id INT                 |
|    module VARCHAR(100) UK |       | FK user_id INT            |
|    display_mode VARCHAR   |       |    module VARCHAR(100)    |
|    items_per_page INT     |       |    display_mode VARCHAR   |
|    sort_field VARCHAR     |       |    items_per_page INT     |
|    sort_direction ENUM    |       |    sort_field VARCHAR     |
|    show_thumbnails TINYINT|       |    sort_direction ENUM    |
|    show_descriptions TINY |       |    show_thumbnails TINYINT|
|    card_size ENUM         |       |    show_descriptions TINY |
|    available_modes JSON   |       |    card_size ENUM         |
|    allow_user_override TIN|       |    is_custom TINYINT      |
|    is_active TINYINT      |       |    created_at DATETIME    |
|    created_at DATETIME    |       |    updated_at DATETIME    |
|    updated_at DATETIME    |       +---------------------------+
+---------------------------+       | UK (user_id, module)      |
                                    +---------------------------+
```

### Table Descriptions

| Table | Purpose |
|-------|---------|
| display_collection_type | GLAM type definitions (archive, museum, gallery, library, dam) |
| display_profile | Layout configurations per domain with field mappings |
| display_level | Extended levels of description (40+ levels across all domains) |
| display_field | Field definitions mapping to AtoM tables/properties |
| display_object_config | Per-object type assignment and profile override |
| display_object_profile | Object-to-profile assignments with context |
| display_mode_global | System-wide default view settings per module |
| user_display_preference | Per-user view preferences |

---

## GLAM Type Detection

### Detection Hierarchy

```
+-----------------------------------------------------------------------+
|                     TYPE DETECTION FLOW                                |
+-----------------------------------------------------------------------+
|                                                                        |
|  Information Object                                                    |
|         |                                                              |
|         V                                                              |
|  1. Check display_object_config (cached assignment)                    |
|         |                                                              |
|         | (not found)                                                  |
|         V                                                              |
|  2. Detect by Level of Description                                     |
|     +----------------------------------------------------------+      |
|     | Level Name    ->  Domain                                 |      |
|     +----------------------------------------------------------+      |
|     | fonds, series, file, item          ->  archive           |      |
|     | object, specimen, artefact         ->  museum            |      |
|     | artwork, painting, sculpture       ->  gallery           |      |
|     | book, periodical, volume           ->  library           |      |
|     | photograph, album, negative        ->  dam               |      |
|     | collection                         ->  universal         |      |
|     +----------------------------------------------------------+      |
|         |                                                              |
|         | (no match)                                                   |
|         V                                                              |
|  3. Inherit from Parent                                                |
|     Check parent's display_object_config.object_type                   |
|         |                                                              |
|         | (no parent type)                                             |
|         V                                                              |
|  4. Detect by Events                                                   |
|     +----------------------------------------------------------+      |
|     | Event Type         ->  Domain                            |      |
|     +----------------------------------------------------------+      |
|     | photographer       ->  dam                               |      |
|     | artist, painter    ->  gallery                           |      |
|     | author, writer     ->  library                           |      |
|     | production         ->  museum                            |      |
|     +----------------------------------------------------------+      |
|         |                                                              |
|         | (no match)                                                   |
|         V                                                              |
|  5. Default to 'archive'                                               |
|         |                                                              |
|         V                                                              |
|  Save to display_object_config                                         |
|                                                                        |
+-----------------------------------------------------------------------+
```

---

## Service Classes

### DisplayService

```php
namespace AhgDisplay\Services;

class DisplayService
{
    // Singleton
    public static function getInstance(): self

    // Main display data retrieval
    public function getObjectDisplay(int $objectId): array
    // Returns: ['object', 'type', 'profile', 'fields', 'extensions']

    // Object data
    public function getObjectData(int $objectId): ?object

    // Profile and field management
    public function getFieldsForProfile(?object $profile): array
    public function getLevels(?string $domain = null): array
    public function getCollectionTypes(): array

    // Type assignment
    public function setObjectType(int $objectId, string $type): void
    public function setObjectTypeRecursive(int $parentId, string $type): int

    // Profile assignment
    public function assignProfile(
        int $objectId,
        int $profileId,
        string $context = 'default',
        bool $primary = false
    ): void

    // Extension registry integration
    public function getActionsForEntity(object $entity, array $context = []): array
    public function getPanelsForEntity(object $entity, array $context = []): array
    public function getBadgesForEntity(object $entity, array $context = []): array
    public function renderActions(object $entity, array $context = []): string
    public function renderBadges(object $entity, array $context = []): string
}
```

### DisplayTypeDetector

```php
class DisplayTypeDetector
{
    // Detection
    public static function detect(int $objectId): string
    public static function detectAndSave(int $objectId, bool $force = false): string
    public static function getType(int $objectId): string

    // Profile resolution
    public static function getProfile(int $objectId): ?object

    // Internal detection methods
    protected static function detectByLevel(?string $levelName): ?string
    protected static function detectByParent(?int $parentId): ?string
    protected static function detectByEvents(int $objectId): ?string
    protected static function saveType(int $objectId, string $type): void
}
```

### DisplayModeService

```php
namespace AhgDisplay\Services;

class DisplayModeService
{
    // User context
    public function setCurrentUser(?int $userId): self
    public function getCurrentUserId(): int

    // Preference retrieval
    public function getDisplaySettings(string $module): array
    public function getCurrentMode(string $module): string
    public function getSettingsSource(string $module): string
    // Source: 'user' | 'global' | 'default'

    // Preference modification
    public function switchMode(string $module, string $mode): bool
    public function savePreferences(string $module, array $prefs): bool
    public function resetToGlobal(string $module): bool
    public function hasCustomPreference(string $module): bool
    public function canOverride(string $module): bool

    // Display helpers
    public function getModeMetas(string $module): array
    public function getItemsPerPage(string $module): int
    public function getSortSettings(string $module): array
    public function getContainerClass(string $mode): string
    public function renderToggleButtons(
        string $module,
        string $baseUrl = '',
        bool $useAjax = true
    ): string

    // Admin functions
    public function getAllGlobalSettings(): Collection
    public function saveGlobalSettings(string $module, array $settings): bool
    public function resetGlobalSettings(string $module): bool
    public function getAuditLog(array $filters = [], int $limit = 100): Collection
}
```

### DisplayRegistry

```php
namespace AhgDisplay\Services;

class DisplayRegistry
{
    // Singleton
    public static function getInstance(): self

    // Provider management
    public function registerProvider(DisplayActionProviderInterface $provider): void
    public function getProviders(): array
    public function getProvider(string $id): ?DisplayActionProviderInterface

    // Extension retrieval
    public function getActions(object $entity, array $context = []): array
    public function getPanels(object $entity, array $context = []): array
    public function getPanelsByPosition(object $entity, string $position, array $context = []): array
    public function getBadges(object $entity, array $context = []): array

    // Discovery
    public function discover(): void
    public function reset(): void
    public function getProviderInfo(): array

    // Rendering
    public function renderActions(object $entity, array $context = [], string $template = 'buttons'): string
    public function renderBadges(object $entity, array $context = []): string
}
```

---

## Elasticsearch 7 Integration

### Index Mapping

```json
{
  "display_object_type": {
    "type": "keyword"
  },
  "display_profile": {
    "type": "keyword"
  },
  "display_level_code": {
    "type": "keyword"
  },
  "display": {
    "type": "object",
    "properties": {
      "identifier": { "type": "keyword" },
      "title": { "type": "text" },
      "level": { "type": "keyword" },
      "extent": { "type": "text" },
      "scope_content": { "type": "text" },
      "description": { "type": "text" },
      "creator": { "type": "text" },
      "creator_keyword": { "type": "keyword" },
      "artist": { "type": "text" },
      "artist_keyword": { "type": "keyword" },
      "author": { "type": "text" },
      "photographer": { "type": "text" },
      "date_display": { "type": "text" },
      "date_start": { "type": "date" },
      "date_end": { "type": "date" },
      "has_digital_object": { "type": "boolean" },
      "thumbnail_path": { "type": "keyword" },
      "master_path": { "type": "keyword" },
      "mime_type": { "type": "keyword" },
      "media_type": { "type": "keyword" },
      "subjects": { "type": "keyword" },
      "places": { "type": "keyword" },
      "genres": { "type": "keyword" },
      "child_count": { "type": "integer" },
      "ancestor_ids": { "type": "integer" },
      "ancestor_slugs": { "type": "keyword" },
      "parent_title": { "type": "text" },
      "object_number": { "type": "keyword" },
      "object_name": { "type": "text" },
      "materials": { "type": "text" },
      "dimensions": { "type": "text" },
      "medium": { "type": "text" },
      "technique": { "type": "text" },
      "call_number": { "type": "keyword" },
      "isbn": { "type": "keyword" }
    }
  }
}
```

### DisplayElasticsearchService

```php
class DisplayElasticsearchService
{
    // Mapping management
    public function updateMapping(): bool
    public function hasDisplayMapping(): bool

    // Indexing
    public function getIndexData(int $objectId): array
    public function indexDocument(int $objectId, array $existingBody = []): array
    public function reindexDisplayData(
        int $batchSize = 100,
        ?callable $progressCallback = null
    ): int

    // Searching
    public function search(array $params): array
    /*
     * $params:
     *   query           - Search query string
     *   object_type     - GLAM type filter
     *   has_digital_object - Boolean filter
     *   media_type      - image|video|audio|document
     *   date_from/to    - Date range
     *   subjects        - Subject term IDs
     *   places          - Place term IDs
     *   sort            - title_asc|title_desc|date_asc|date_desc|relevance
     *   from            - Pagination offset
     *   size            - Results per page
     *   aggregations    - Include facets (bool)
     */

    // Browse
    public function browseByType(string $objectType, array $params = []): array

    // Facets
    public function getFacets(array $filters = []): array

    // Autocomplete
    public function autocomplete(string $query, int $size = 10): array
}
```

### Search Result Format

```php
[
    'total' => 1234,
    'hits' => [
        [
            'id' => 123,
            'score' => 15.23,
            'slug' => 'example-record',
            'identifier' => 'REF/001',
            'title' => 'Example Record',
            'object_type' => 'archive',
            'profile' => 'isad_full',
            'level' => 'File',
            'creator' => 'John Smith',
            'date' => '1985-1990',
            'description' => 'Truncated description...',
            'has_digital_object' => true,
            'thumbnail_path' => '/uploads/...',
            'media_type' => 'image',
            'subjects' => ['History', 'Local'],
            'child_count' => 15,
        ],
        // ...
    ],
    'aggregations' => [
        'object_types' => [
            ['key' => 'archive', 'count' => 500],
            ['key' => 'museum', 'count' => 234],
        ],
        'media_types' => [...],
        'levels' => [...],
        'subjects' => [...],
        'creators' => [...],
        'has_digital' => [...],
    ],
    'from' => 0,
    'size' => 20,
]
```

---

## Display Action Registry

### Extensibility Pattern

Other plugins can register display actions, panels, and badges via their `extension.json`:

```json
{
  "name": "My Plugin",
  "machine_name": "myPlugin",
  "display_actions": [
    {
      "id": "my_action",
      "label": "My Action",
      "icon": "fa-star",
      "url": "/my/action/{slug}",
      "contexts": ["informationobject"],
      "permission": "update",
      "weight": 50
    }
  ],
  "display_panels": [
    {
      "id": "my_panel",
      "title": "My Panel",
      "template": "myPlugin/templates/panels/_myPanel.php",
      "position": "sidebar",
      "contexts": ["informationobject"],
      "weight": 30
    }
  ],
  "display_badges": [
    {
      "id": "my_badge",
      "label": "Special",
      "icon": "fa-certificate",
      "class": "badge bg-warning",
      "check_method": "MyPlugin\\BadgeChecker::isSpecial",
      "contexts": ["informationobject"],
      "weight": 20
    }
  ]
}
```

### DisplayActionRegistry

```php
namespace AhgDisplay\Registry;

class DisplayActionRegistry
{
    // Registration (called automatically from extension.json)
    public static function registerAction(string $plugin, array $config): void
    public static function registerPanel(string $plugin, array $config): void
    public static function registerBadge(string $plugin, array $config): void

    // Retrieval
    public static function getActionsForContext(string $context, $resource = null): array
    public static function getPanelsForContext(string $context, ?string $position = null, $resource = null): array
    public static function getBadgesForContext(string $context, $resource = null): array

    // All items
    public static function getAllActions(): array
    public static function getAllPanels(): array
    public static function getAllBadges(): array

    // Rendering
    public static function renderAction(array $action, $resource = null): string
    public static function renderPanel(array $panel, $resource = null): string
    public static function renderBadge(array $badge, $resource = null): string

    // Utility
    public static function clear(): void
}
```

---

## CLI Commands

### display:reindex

Reindex display data in Elasticsearch.

```bash
php symfony display:reindex [options]

Options:
  --batch=100          Batch size for bulk updates
  --update-mapping     Update ES mapping before reindexing

Examples:
  php symfony display:reindex
  php symfony display:reindex --batch=200
  php symfony display:reindex --update-mapping
  php symfony display:reindex --update-mapping --batch=50
```

### display:auto-detect

Auto-detect GLAM types for all information objects.

```bash
php symfony display:auto-detect

Output:
  display: Starting auto-detection...
  display: Processed 100 objects...
  display: Processed 200 objects...
  display: Complete! Processed 2341 objects:
  display:   archive: 1523
  display:   museum: 234
  display:   library: 156
  display:   dam: 312
  display:   gallery: 89
  display:   universal: 27
```

---

## Modules and Routes

### display Module (Admin)

| Route | Action | Description |
|-------|--------|-------------|
| `/display` | index | Admin dashboard with stats |
| `/display/browse` | browse | Main GLAM browse interface |
| `/display/profiles` | profiles | View/manage display profiles |
| `/display/levels` | levels | View extended levels by domain |
| `/display/fields` | fields | View field mappings |
| `/display/bulkSetType` | bulkSetType | Bulk type assignment form |
| `/display/setType` | setType | Set single object type |
| `/display/changeType` | changeType | Change type (AJAX) |
| `/display/assignProfile` | assignProfile | Assign profile to object |
| `/display/print` | print | Print view (up to 500) |
| `/display/exportCsv` | exportCsv | Export filtered results |

### displaySearch Module

| Route | Action | Description |
|-------|--------|-------------|
| `/displaySearch/search` | search | ES-powered search with facets |
| `/displaySearch/browse` | browse | Browse by object type |
| `/displaySearch/autocomplete` | autocomplete | AJAX autocomplete |
| `/displaySearch/facets` | facets | AJAX facet updates |
| `/displaySearch/reindex` | reindex | ES reindex admin form |
| `/displaySearch/updateMapping` | updateMapping | Update ES mapping |

---

## Layout Templates

### Available Layouts

| Layout | Template | Best For |
|--------|----------|----------|
| card | `_card.php` | Search results, mixed content |
| catalog | `_catalog.php` | Print catalogs, reports |
| detail | `_detail.php` | Single record full view |
| gallery | `_gallery.php` | Artworks, hero images |
| grid | `_grid.php` | Photos, thumbnails |
| hierarchy | `_hierarchy.php` | Archival tree view |
| list | `_list.php` | Reference lists, tables |
| masonry | `_masonry.php` | Variable-size images |

### Template Variables

All layout templates receive:

```php
$object          // Information object data
$objectType      // GLAM type string
$profile         // Display profile object
$digitalObject   // Digital object (if exists)
$fields          // Organized field groups
$data            // Profile settings (actions, thumbnail_size, etc.)
```

---

## Configuration

### extension.json

```json
{
  "name": "Display Plugin",
  "machine_name": "ahgDisplayPlugin",
  "version": "1.3.0",
  "description": "GLAM browser and display modes for archival content",
  "author": "The Archive and Heritage Group",
  "license": "GPL-3.0",
  "category": "display",
  "requires": {
    "atom_framework": ">=1.0.0",
    "atom": ">=2.8",
    "php": ">=8.1"
  },
  "dependencies": ["ahgCorePlugin"],
  "provides": {
    "services": [
      "AhgDisplay\\Services\\DisplayService",
      "AhgDisplay\\Services\\DisplayRegistry"
    ]
  },
  "tables": [
    "display_profile",
    "display_profile_i18n",
    "display_object_config",
    "display_collection_type",
    "display_field",
    "display_level",
    "display_mode_global",
    "user_display_preference"
  ]
}
```

### Display Mode Settings

```php
// Available display modes
$modes = [
    'tree'     => 'Hierarchy view with parent-child relationships',
    'grid'     => 'Thumbnail grid with cards',
    'gallery'  => 'Large images for visual browsing',
    'list'     => 'Compact table/list view',
    'timeline' => 'Chronological timeline view',
];

// Card sizes
$cardSizes = ['small', 'medium', 'large'];

// Sort directions
$sortDirections = ['asc', 'desc'];
```

---

## Preference Fallback Chain

```
+-----------------------------------------------------------------------+
|                    PREFERENCE RESOLUTION                               |
+-----------------------------------------------------------------------+
|                                                                        |
|  1. User Preference (user_display_preference)                          |
|     - Only if is_custom = 1                                            |
|     - Only if global.allow_user_override = 1                           |
|              |                                                         |
|              V (not found or not allowed)                              |
|  2. Global Setting (display_mode_global)                               |
|     - Per-module defaults set by admin                                 |
|              |                                                         |
|              V (not found)                                              |
|  3. Hardcoded Defaults                                                 |
|     - informationobject: list, 30 items                                |
|     - digitalobject: grid, 24 items                                    |
|     - gallery: gallery, 12 items                                       |
|     - dam: grid, 24 items                                              |
|                                                                        |
+-----------------------------------------------------------------------+
```

---

## Integration Points

### Symfony Events

```php
// Plugin configuration hooks into these events:
$this->dispatcher->connect('routing.load_configuration', [$this, 'loadRoutes']);
$this->dispatcher->connect('template.filter_parameters', [$this, 'onTemplateFilterParameters']);
$this->dispatcher->connect('QubitInformationObject.save', [$this, 'onInformationObjectSave']);
$this->dispatcher->connect('QubitInformationObject.insert', [$this, 'onInformationObjectSave']);
```

### Template Integration

```php
// In templates, access display data via:
$display_type    // Auto-detected GLAM type
$display_profile // Current display profile object

// Use helper functions:
get_type_color($objectType)  // Bootstrap color class
get_type_icon($objectType)   // FontAwesome icon class
format_field_value($field)   // Format field for display
```

---

## Default Profiles

| Code | Domain | Layout | Use Case |
|------|--------|--------|----------|
| isad_full | archive | detail | Full archival description |
| isad_hierarchy | archive | hierarchy | Tree/hierarchy view |
| isad_list | archive | list | Compact reference list |
| spectrum_full | museum | detail | Full Spectrum object |
| spectrum_card | museum | card | Object cards |
| spectrum_catalog | museum | catalog | Print catalog |
| gallery_full | gallery | gallery | Artwork detail |
| gallery_wall | gallery | gallery | Gallery wall view |
| gallery_catalog | gallery | catalog | Exhibition catalog |
| book_full | library | detail | Full bibliographic |
| book_list | library | list | Library listing |
| book_card | library | card | Book cards |
| photo_full | dam | detail | Full photo details |
| photo_grid | dam | grid | Photo grid |
| photo_lightbox | dam | masonry | Lightbox/masonry |
| search_result | universal | card | Search results |
| print_record | universal | detail | Print-ready view |

---

## Extended Levels of Description

The plugin provides 40+ levels organized by domain:

| Domain | Levels |
|--------|--------|
| universal | repository, collection |
| archive | fonds, subfonds, series, subseries, file, item, piece |
| museum | holding, object_group, object, component, specimen |
| gallery | artist_archive, artwork_series, artwork, study, edition, impression |
| library | book_collection, book, periodical, volume, issue, chapter, pamphlet, map |
| dam | photo_collection, album, shoot, photograph, negative, slide, digital_asset |
| archive (AV) | av_collection, film, recording, reel, segment |

---

## Security

### Permission Checks

```php
// Browse actions check publication status for guests
if (!$this->isAuthenticated) {
    $query->whereExists(function($q) {
        $q->select(DB::raw(1))
          ->from('status')
          ->whereRaw('status.object_id = io.id')
          ->where('status.type_id', 158)    // publication status
          ->where('status.status_id', 160);  // Published
    });
}
```

### Module Security

```yaml
# modules/display/config/security.yml
all:
  is_secure: false

index:
  is_secure: true
  credentials: editor

bulkSetType:
  is_secure: true
  credentials: administrator
```

---

## Performance Considerations

1. **Type Detection Caching**: Detected types are stored in `display_object_config` and not re-computed
2. **ES Bulk Updates**: Reindexing uses bulk API with configurable batch sizes
3. **Lazy Loading**: Extension registry discovery happens once per request
4. **Facet Counts**: Use ES aggregations instead of MySQL COUNT queries

---

*Part of the AtoM AHG Framework*
