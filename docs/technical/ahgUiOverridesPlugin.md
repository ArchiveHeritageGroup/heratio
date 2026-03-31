# ahgUiOverridesPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** AHG Core
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

The ahgUiOverridesPlugin provides a centralized location for UI action overrides and helper function customizations in AtoM. This plugin intercepts and extends core AtoM module actions without modifying base AtoM files, following the AHG Framework architecture principles.

Key capabilities:
- **Action Overrides:** Override module actions (repository theme editing, ISAAR authority record editing)
- **Helper Functions:** Provide backward-compatible helper wrappers and Laravel-based data access functions
- **Template Rendering:** Enhanced digital object viewer support with transcription and IIIF integration

---

## Architecture

```
+-----------------------------------------------------------------+
|                    ahgUiOverridesPlugin                          |
+-----------------------------------------------------------------+
|                                                                  |
|  +----------------------------------------------------------+   |
|  |                    Helper Layer                          |   |
|  |  QubitHelper.php          - Legacy function wrappers     |   |
|  |  AhgLaravelHelper.php     - Laravel Query Builder data   |   |
|  |  informationobjectHelper  - Digital object viewers       |   |
|  +----------------------------------------------------------+   |
|                           |                                      |
|                           v                                      |
|  +----------------------------------------------------------+   |
|  |                   Action Overrides                        |   |
|  |  repository/editThemeAction   - Repository theming       |   |
|  |  sfIsaarPlugin/editAction     - Authority record editing |   |
|  +----------------------------------------------------------+   |
|                           |                                      |
|                           v                                      |
|  +----------------------------------------------------------+   |
|  |              Framework Integration                        |   |
|  |  AtomFramework\Helpers\QubitHelper                       |   |
|  |  AtomExtensions\Services\AclService                      |   |
|  |  AtomExtensions\Services\CacheService                    |   |
|  |  Illuminate\Database\Capsule\Manager                     |   |
|  +----------------------------------------------------------+   |
|                                                                  |
+-----------------------------------------------------------------+
```

---

## Plugin Structure

```
ahgUiOverridesPlugin/
+-- config/
|   +-- ahgUiOverridesPluginConfiguration.class.php
+-- lib/
|   +-- helper/
|       +-- QubitHelper.php             # Legacy function wrappers
|       +-- AhgLaravelHelper.php        # Laravel data access helpers
|       +-- informationobjectHelper.php # Digital object viewer
+-- modules/
|   +-- repository/
|   |   +-- actions/
|   |       +-- editThemeAction.class.php
|   +-- sfIsaarPlugin/
|       +-- actions/
|           +-- editAction.class.php
+-- extension.json
```

---

## Helper Functions

### QubitHelper.php - Legacy Compatibility Layer

Provides backward-compatible wrapper functions that delegate to the atom-framework's `AtomFramework\Helpers\QubitHelper`.

#### Functions

| Function | Description | Parameters |
|----------|-------------|------------|
| `format_script()` | Format ISO script code for display | `$script_iso`, `$culture = null` |
| `render_field()` | Render a form field | `$field`, `$resource = null`, `$options = []` |
| `render_title()` | Safely render resource title with fallbacks | `$resource`, `$fallback = true` |
| `get_search_i18n()` | Extract i18n field from search document | `$doc`, `$field`, `$options = []` |
| `render_value_inline()` | Render escaped inline value | `$value` |
| `render_value()` | Render escaped value | `$value` |
| `render_value_html()` | Render unescaped HTML value | `$value` |
| `render_show()` | Render field display with label | `$label`, `$value`, `$options = []` |
| `check_field_visibility()` | Check if field should be visible | `$fieldName`, `$options = []` |
| `strip_markdown()` | Remove markdown formatting from text | `$text` |

#### render_title() Logic

The `render_title()` function uses a cascade of methods to find a displayable title:

```php
function render_title($resource, $fallback = true)
{
    // 1. Handle null/empty/string/array inputs
    // 2. Try getTitle() with culture fallback
    // 3. Try getAuthorizedFormOfName() for actors
    // 4. Try getName() for generic objects
    // 5. Try getLabel() for terms
    // 6. Try __toString() magic method
    // 7. Try getSlug() as last resort
    // 8. Return empty string if all fail
}
```

---

### AhgLaravelHelper.php - Laravel Data Access

Provides optimized data access functions using Laravel Query Builder for information object templates.

#### Functions

| Function | Description | Returns |
|----------|-------------|---------|
| `ahg_get_subject_access_points()` | Get subject terms for a resource | `array` of terms |
| `ahg_get_place_access_points()` | Get place terms for a resource | `array` of terms |
| `ahg_get_actor_events()` | Get actor events linked to resource | `array` of events |
| `ahg_get_name_access_relations()` | Get name access point relations | `array` of actors |
| `ahg_get_collection_root_id()` | Find root collection ID | `int|null` |
| `ahg_has_digital_object()` | Check if resource has digital object | `bool` |
| `ahg_show_inventory()` | Check if resource has child items | `bool` |
| `ahg_url_for_dc_export()` | Generate Dublin Core export URL | `string` |
| `ahg_url_for_ead_export()` | Generate EAD export URL | `string` |
| `ahg_resource_url()` | Generate resource URL for module/action | `string` |

#### Example Usage

```php
// Get subject access points for an information object
$subjects = ahg_get_subject_access_points($resource->id);
foreach ($subjects as $subject) {
    echo link_to($subject->name, ['module' => 'term', 'slug' => $subject->slug]);
}

// Check for digital object before rendering viewer
if (ahg_has_digital_object($resource->id)) {
    echo render_digital_object_viewer($resource);
}

// Get collection root for breadcrumb
$rootId = ahg_get_collection_root_id($resource);
```

#### Query Builder Pattern

All helper functions use Laravel Query Builder with culture fallback:

```php
function ahg_get_subject_access_points($resourceId): array
{
    $culture = sfContext::getInstance()->getUser()->getCulture() ?? 'en';
    $subjectTaxonomyId = 35;

    return DB::table('object_term_relation as otr')
        ->join('term as t', 'otr.term_id', '=', 't.id')
        ->leftJoin('term_i18n as ti', function ($join) use ($culture) {
            $join->on('t.id', '=', 'ti.id')
                 ->where('ti.culture', '=', $culture);
        })
        ->leftJoin('term_i18n as ti_en', function ($join) {
            $join->on('t.id', '=', 'ti_en.id')
                 ->where('ti_en.culture', '=', 'en');
        })
        ->leftJoin('slug', 't.id', '=', 'slug.object_id')
        ->where('otr.object_id', $resourceId)
        ->where('t.taxonomy_id', $subjectTaxonomyId)
        ->select([
            't.id',
            'slug.slug',
            DB::raw('COALESCE(ti.name, ti_en.name) as name')
        ])
        ->orderBy(DB::raw('COALESCE(ti.name, ti_en.name)'))
        ->get()->toArray();
}
```

---

### informationobjectHelper.php - Digital Object Viewer

Provides enhanced digital object viewer rendering with support for video, audio, and IIIF images.

#### Functions

| Function | Description |
|----------|-------------|
| `render_digital_object_viewer()` | Render appropriate viewer for digital object |

#### Viewer Selection Logic

```
+------------------------+
| render_digital_object_viewer($resource, $digitalObject, $options)
+------------------------+
            |
            v
+------------------------+
| Get digital object     |
| if not provided        |
+------------------------+
            |
            v
+------------------------+     Yes     +-------------------------+
| Is Video/Audio?        |------------>| render_enhanced_media   |
+------------------------+             | _player() or HTML5      |
            | No                       +-------------------------+
            v
+------------------------+     Yes     +-------------------------+
| IIIF Helper available? |------------>| render_iiif_viewer()    |
+------------------------+             +-------------------------+
            | No
            v
+------------------------+
| Fallback: Alert        |
| "Viewer not available" |
+------------------------+
```

#### Media Type Detection

```php
// Video detection
$isVideo = ($mediaTypeId == QubitTerm::VIDEO_ID) ||
           strpos($mimeType, 'video') !== false;

// Audio detection
$isAudio = ($mediaTypeId == QubitTerm::AUDIO_ID) ||
           strpos($mimeType, 'audio') !== false;
```

---

## Module Action Overrides

### repository/editThemeAction

Overrides the repository theme editing action to use framework services for ACL and caching.

#### Fields Supported

| Field | Type | Description |
|-------|------|-------------|
| `backgroundColor` | color picker | Repository background color (hex) |
| `banner` | file upload | Repository banner image (PNG, max 256K) |
| `banner_delete` | checkbox | Delete existing banner |
| `logo` | file upload | Repository logo image (PNG, max 256K) |
| `logo_delete` | checkbox | Delete existing logo |
| `htmlSnippet` | textarea | Custom HTML for repository page |

#### Framework Service Integration

```php
// ACL check using AclService
if (!AclService::check($this->resource, 'update')) {
    AclService::forwardUnauthorized();
}

// Cache invalidation using CacheService
if (!$this->new && null !== $cache = CacheService::getInstance()) {
    $cacheKey = 'repository:htmlsnippet:'.$this->resource->id;
    $cache->remove($cacheKey);
}
```

#### File Validation

```php
$this->form->setValidator('banner', new sfValidatorFile([
    'max_size' => '262144',  // 256K
    'mime_types' => ['image/png'],
    'validated_file_class' => 'arRepositoryThemeCropValidatedFile',
    'path' => $this->resource->getUploadsPath(true),
    'required' => false,
]));
```

---

### sfIsaarPlugin/editAction

Extends the ISAAR-CPF authority record editing with contact information support.

#### Extended Fields

| Field | Type | Description |
|-------|------|-------------|
| `authorizedFormOfName` | text | Official name of authority |
| `corporateBodyIdentifiers` | text | Identifiers for corporate bodies |
| `datesOfExistence` | date range | Existence dates |
| `entityType` | select | Type of authority (person, family, corporate body) |
| `functions` | textarea | Functions/activities |
| `history` | textarea | Historical note |
| `places` | relation | Associated places |
| `placeAccessPoints` | relation | Place access points |
| `subjectAccessPoints` | relation | Subject access points |
| ... | ... | Additional ISAAR fields |

#### Contact Information Processing (AHG Extension)

```php
protected function processContactInformation(): void
{
    $contactsData = $this->request->getParameter('contacts');

    if (empty($contactsData) || !is_array($contactsData)) {
        return;
    }

    // Load framework bootstrap
    $frameworkPath = sfConfig::get('sf_root_dir') . '/atom-framework/bootstrap.php';
    if (file_exists($frameworkPath)) {
        require_once $frameworkPath;
    }

    $repo = new \AtomFramework\Extensions\Contact\Repositories\ContactInformationRepository();

    foreach ($contactsData as $index => $contactData) {
        // Handle deletion
        if (!empty($contactData['delete']) && !empty($contactData['id'])) {
            $repo->delete((int) $contactData['id']);
            continue;
        }

        // Check for actual data
        // Save via repository
        $repo->saveFromForm($contactData);
    }
}
```

---

## Extension Points

### Adding New Action Overrides

1. Create module directory structure:
```
modules/
  <module_name>/
    actions/
      <action>Action.class.php
```

2. Extend the appropriate base action class:
```php
class MyModuleMyAction extends BaseModuleAction
{
    public function execute($request)
    {
        // Custom logic
        parent::execute($request);
    }
}
```

### Adding New Helper Functions

1. Create or extend helper file in `lib/helper/`:
```php
<?php
use Illuminate\Database\Capsule\Manager as DB;

function ahg_my_custom_helper($resourceId): array
{
    $culture = sfContext::getInstance()->getUser()->getCulture() ?? 'en';

    return DB::table('my_table')
        ->where('object_id', $resourceId)
        ->get()->toArray();
}
```

2. Load helper in template or action:
```php
sfContext::getInstance()->getConfiguration()->loadHelpers(['MyHelper']);
```

---

## Configuration

### extension.json

```json
{
    "name": "UI Overrides",
    "machine_name": "ahgUiOverridesPlugin",
    "version": "1.0.0",
    "description": "UI action overrides for AtoM modules - centralized location for action customizations",
    "author": "The Archive and Heritage Group",
    "license": "GPL-3.0",
    "category": "ahg",
    "requires": {
        "atom_framework": ">=1.0.0",
        "atom": ">=2.8",
        "php": ">=8.1"
    },
    "dependencies": [
        "ahgCorePlugin"
    ]
}
```

### Plugin Configuration

```php
class ahgUiOverridesPluginConfiguration extends sfPluginConfiguration
{
    public static $summary = 'UI action overrides for AtoM modules';
    public static $version = '1.0.0';

    public function initialize()
    {
        // Plugin initializes automatically via Symfony module discovery
    }
}
```

---

## Taxonomy IDs Reference

| Taxonomy | ID | Usage |
|----------|-----|-------|
| Subject | 35 | `ahg_get_subject_access_points()` |
| Place | 42 | `ahg_get_place_access_points()` |
| Name Relation | 161 | `ahg_get_name_access_relations()` |

---

## Integration with Other Plugins

| Plugin | Integration |
|--------|-------------|
| ahgCorePlugin | Required dependency for base functionality |
| ahgContactPlugin | Contact information repository for authority records |
| ahgIiifPlugin | IIIF viewer for image rendering |
| ahgMediaPlugin | Enhanced media player for video/audio |
| ahgThemeB5Plugin | Bootstrap 5 widget classes |

---

## Best Practices

### Action Override Guidelines

1. **Always call parent methods** when extending base actions
2. **Use AclService** for permission checks instead of direct QubitAcl calls
3. **Invalidate cache** when modifying cached resources
4. **Load framework bootstrap** when using framework repositories

### Helper Function Guidelines

1. **Use Laravel Query Builder** - Never raw PDO in helpers
2. **Support culture fallback** - Always join with both user culture and English fallback
3. **Return arrays** - Helpers should return plain arrays, not Eloquent collections
4. **Handle null gracefully** - Check for empty resources before querying

### Template Integration

```php
// Load helpers at top of template
use_helper('AhgLaravel', 'informationobject');

// Use helper functions
$subjects = ahg_get_subject_access_points($resource->id);
$places = ahg_get_place_access_points($resource->id);

// Render viewer
if (ahg_has_digital_object($resource->id)) {
    echo render_digital_object_viewer($resource);
}
```

---

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| Helper function not found | Helper not loaded | Call `loadHelpers(['HelperName'])` |
| ACL permission denied | Missing AclService import | Add `use AtomExtensions\Services\AclService` |
| Database query error | Framework not bootstrapped | Include `atom-framework/bootstrap.php` |
| Culture fallback not working | Missing English i18n join | Add `ti_en` join with `culture = 'en'` |
| Cache not invalidating | Wrong cache key | Verify key format matches `entity:type:id` |

---

*Part of the AtoM AHG Framework*
