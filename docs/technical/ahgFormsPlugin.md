# ahgFormsPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Administration
**Dependencies:** atom-framework

---

## Overview

A configurable forms system enabling administrators to create custom metadata entry forms for different record types, repositories, and levels of description. Similar to DSpace's configurable submission forms, it allows tailoring the data entry experience without code modifications.

---

## Architecture

```
+---------------------------------------------------------------------+
|                       ahgFormsPlugin                                 |
+---------------------------------------------------------------------+
|                                                                      |
|  +---------------------------------------------------------------+  |
|  |                    Configuration Layer                        |  |
|  |  ahgFormsPluginConfiguration.class.php                        |  |
|  |  - Route registration                                         |  |
|  |  - Asset loading (CSS/JS)                                     |  |
|  |  - Module enablement                                          |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                      Service Layer                            |  |
|  |  FormService.php                                              |  |
|  |  - Template CRUD operations                                   |  |
|  |  - Field management                                           |  |
|  |  - Assignment resolution                                      |  |
|  |  - Draft/autosave handling                                    |  |
|  |  - Import/export functionality                                |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Controller Layer                           |  |
|  |  actions.class.php                                            |  |
|  |  - Web interface actions                                      |  |
|  |  - API endpoints (JSON)                                       |  |
|  |  - AJAX field operations                                      |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                     Database Layer                            |  |
|  |  ahg_form_template, ahg_form_field, ahg_form_assignment       |  |
|  |  ahg_form_field_mapping, ahg_form_draft, ahg_form_submission  |  |
|  +---------------------------------------------------------------+  |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+---------------------------+        +---------------------------+
|    ahg_form_template      |        |     ahg_form_field        |
+---------------------------+        +---------------------------+
| PK id BIGINT              |<-------| PK id BIGINT              |
|    name VARCHAR(255)      |   1:N  | FK template_id BIGINT     |
|    description TEXT       |        |    field_name VARCHAR(255)|
|    form_type ENUM         |        |    field_type ENUM        |
|    config_json JSON       |        |    label VARCHAR(255)     |
|    is_default TINYINT     |        |    label_i18n JSON        |
|    is_system TINYINT      |        |    help_text TEXT         |
|    is_active TINYINT      |        |    help_text_i18n JSON    |
|    version INT            |        |    placeholder VARCHAR    |
|    created_by INT         |        |    default_value TEXT     |
|    created_at DATETIME    |        |    validation_rules JSON  |
|    updated_at DATETIME    |        |    options_json JSON      |
+---------------------------+        |    autocomplete_source    |
            |                        |    section_name VARCHAR   |
            |                        |    tab_name VARCHAR       |
            |                        |    sort_order INT         |
            | 1:N                    |    is_repeatable TINYINT  |
            |                        |    is_required TINYINT    |
            v                        |    is_readonly TINYINT    |
+---------------------------+        |    is_hidden TINYINT      |
|   ahg_form_assignment     |        |    conditional_logic JSON |
+---------------------------+        |    css_class VARCHAR      |
| PK id BIGINT              |        |    width ENUM             |
| FK template_id BIGINT     |        |    created_at DATETIME    |
|    repository_id INT      |        |    updated_at DATETIME    |
|    level_of_description_id|        +---------------------------+
|    collection_id INT      |                    |
|    priority INT           |                    | 1:N
|    inherit_to_children    |                    v
|    is_active TINYINT      |        +---------------------------+
|    created_at DATETIME    |        | ahg_form_field_mapping    |
|    updated_at DATETIME    |        +---------------------------+
+---------------------------+        | PK id BIGINT              |
                                     | FK field_id BIGINT        |
+---------------------------+        |    target_table VARCHAR   |
|     ahg_form_draft        |        |    target_column VARCHAR  |
+---------------------------+        |    target_type_id INT     |
| PK id BIGINT              |        |    transformation VARCHAR |
| FK template_id BIGINT     |        |    transformation_config  |
|    object_type VARCHAR    |        |    is_i18n TINYINT        |
|    object_id INT          |        |    culture VARCHAR(10)    |
|    user_id INT            |        |    created_at DATETIME    |
|    form_data JSON         |        |    updated_at DATETIME    |
|    created_at DATETIME    |        +---------------------------+
|    updated_at DATETIME    |
+---------------------------+        +---------------------------+
                                     | ahg_form_submission_log   |
                                     +---------------------------+
                                     | PK id BIGINT              |
                                     | FK template_id BIGINT     |
                                     |    object_type VARCHAR    |
                                     |    object_id INT          |
                                     |    user_id INT            |
                                     |    action ENUM            |
                                     |    form_data JSON         |
                                     |    submitted_at DATETIME  |
                                     |    ip_address VARCHAR(45) |
                                     |    user_agent VARCHAR(500)|
                                     +---------------------------+
```

### SQL Schema

```sql
-- Form Templates
CREATE TABLE IF NOT EXISTS ahg_form_template (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    form_type ENUM('information_object', 'accession', 'actor',
                   'repository', 'custom') NOT NULL DEFAULT 'information_object',
    config_json JSON COMMENT 'Template-level configuration (sections, tabs, layout)',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'System templates cannot be deleted',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    version INT NOT NULL DEFAULT 1,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_form_type (form_type),
    INDEX idx_is_default (is_default),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Fields
CREATE TABLE IF NOT EXISTS ahg_form_field (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    field_name VARCHAR(255) NOT NULL COMMENT 'Internal field identifier',
    field_type ENUM('text', 'textarea', 'richtext', 'date', 'daterange',
                    'select', 'multiselect', 'autocomplete', 'checkbox',
                    'radio', 'file', 'hidden', 'heading', 'divider')
                    NOT NULL DEFAULT 'text',
    label VARCHAR(255) NOT NULL,
    label_i18n JSON COMMENT 'Translated labels {"en": "Title", "af": "Titel"}',
    help_text TEXT,
    help_text_i18n JSON,
    placeholder VARCHAR(255),
    default_value TEXT,
    validation_rules JSON COMMENT '{"required": true, "minLength": 5, "pattern": "regex"}',
    options_json JSON COMMENT 'For select/multiselect: [{"value": "x", "label": "X"}]',
    autocomplete_source VARCHAR(255) COMMENT 'taxonomy:123 or actor:all or custom:endpoint',
    section_name VARCHAR(100) COMMENT 'Group fields into sections',
    tab_name VARCHAR(100) COMMENT 'Group sections into tabs',
    sort_order INT NOT NULL DEFAULT 0,
    is_repeatable TINYINT(1) NOT NULL DEFAULT 0,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_readonly TINYINT(1) NOT NULL DEFAULT 0,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    conditional_logic JSON COMMENT '{"field": "fieldName", "operator": "equals", "value": "x"}',
    css_class VARCHAR(255),
    width ENUM('full', 'half', 'third', 'quarter') DEFAULT 'full',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES ahg_form_template(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id),
    INDEX idx_sort_order (sort_order),
    INDEX idx_section (section_name),
    INDEX idx_tab (tab_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Assignments
CREATE TABLE IF NOT EXISTS ahg_form_assignment (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    repository_id INT COMMENT 'NULL = all repositories',
    level_of_description_id INT COMMENT 'NULL = all levels (term_id from taxonomy)',
    collection_id INT COMMENT 'Specific collection/fonds to apply to',
    priority INT NOT NULL DEFAULT 100 COMMENT 'Higher priority wins when multiple match',
    inherit_to_children TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Apply to descendant records',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES ahg_form_template(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id),
    INDEX idx_repository_id (repository_id),
    INDEX idx_level_id (level_of_description_id),
    INDEX idx_collection_id (collection_id),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Field Mappings (connect form fields to AtoM fields)
CREATE TABLE IF NOT EXISTS ahg_form_field_mapping (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    field_id BIGINT UNSIGNED NOT NULL,
    target_table VARCHAR(100) NOT NULL COMMENT 'information_object, information_object_i18n, etc.',
    target_column VARCHAR(100) NOT NULL COMMENT 'title, scope_and_content, etc.',
    target_type_id INT COMMENT 'For property/note tables - the type taxonomy term ID',
    transformation VARCHAR(100) COMMENT 'Transformation function: uppercase, lowercase, date_format',
    transformation_config JSON COMMENT 'Config for transformation',
    is_i18n TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether this maps to i18n table',
    culture VARCHAR(10) DEFAULT 'en',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (field_id) REFERENCES ahg_form_field(id) ON DELETE CASCADE,
    INDEX idx_field_id (field_id),
    INDEX idx_target (target_table, target_column)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Draft Auto-save Storage
CREATE TABLE IF NOT EXISTS ahg_form_draft (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    object_type VARCHAR(50) NOT NULL COMMENT 'information_object, accession, etc.',
    object_id INT COMMENT 'NULL for new records',
    user_id INT NOT NULL,
    form_data JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES ahg_form_template(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id),
    INDEX idx_object (object_type, object_id),
    INDEX idx_user_id (user_id),
    UNIQUE KEY uk_draft (template_id, object_type, object_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Submission Log (audit trail)
CREATE TABLE IF NOT EXISTS ahg_form_submission_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    object_type VARCHAR(50) NOT NULL,
    object_id INT NOT NULL,
    user_id INT NOT NULL,
    action ENUM('create', 'update', 'autosave') NOT NULL,
    form_data JSON,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    INDEX idx_template_id (template_id),
    INDEX idx_object (object_type, object_id),
    INDEX idx_user_id (user_id),
    INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Field Types

| Type | Description | Database Storage |
|------|-------------|------------------|
| text | Single-line text input | VARCHAR |
| textarea | Multi-line text input | TEXT |
| richtext | Rich text editor (TinyMCE/CKEditor) | TEXT |
| date | Single date picker | DATE |
| daterange | Start and end date | Two DATE columns |
| select | Single-select dropdown | VARCHAR |
| multiselect | Multi-select list | JSON array |
| autocomplete | Type-ahead search | INT (foreign key) |
| checkbox | Boolean toggle | TINYINT |
| radio | Radio button group | VARCHAR |
| file | File upload | VARCHAR (path) |
| hidden | Hidden field | VARCHAR |
| heading | Section heading (display only) | N/A |
| divider | Visual separator (display only) | N/A |

---

## Service Methods

### FormService

```php
namespace ahgFormsPlugin\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class FormService
{
    // Template Operations
    public function getTemplates(?string $formType = null): Collection
    public function getTemplate(int $templateId): ?object
    public function getTemplateFields(int $templateId): Collection
    public function createTemplate(array $data): int
    public function updateTemplate(int $templateId, array $data): bool
    public function deleteTemplate(int $templateId): bool
    public function cloneTemplate(int $templateId, string $newName): int

    // Field Operations
    public function addField(int $templateId, array $data): int
    public function updateField(int $fieldId, array $data): bool
    public function deleteField(int $fieldId): bool
    public function reorderFields(int $templateId, array $fieldOrder): bool

    // Assignment Operations
    public function getAssignments(): Collection
    public function createAssignment(array $data): int
    public function deleteAssignment(int $assignmentId): bool
    public function resolveTemplate(
        string $formType,
        ?int $repositoryId = null,
        ?int $levelId = null,
        ?int $parentId = null
    ): ?object

    // Draft/Autosave Operations
    public function saveDraft(int $templateId, string $objectType, ?int $objectId, array $formData): int
    public function getDraft(int $templateId, string $objectType, ?int $objectId): ?object
    public function deleteDraft(int $templateId, string $objectType, ?int $objectId): bool

    // Import/Export
    public function exportTemplate(int $templateId): array
    public function importTemplate(array $data, ?string $name = null): int

    // Statistics
    public function getStatistics(): array
}
```

---

## Routes

### Web Routes

| Route | Action | Description |
|-------|--------|-------------|
| `/admin/forms` | index | Dashboard with statistics |
| `/admin/forms/templates` | templates | List all templates |
| `/admin/forms/template/create` | templateCreate | Create new template |
| `/admin/forms/template/:id/edit` | templateEdit | Edit template settings |
| `/admin/forms/template/:id/delete` | templateDelete | Delete template |
| `/admin/forms/template/:id/clone` | templateClone | Clone template |
| `/admin/forms/template/:id/export` | templateExport | Export as JSON |
| `/admin/forms/template/import` | templateImport | Import from JSON |
| `/admin/forms/template/:id/builder` | builder | Drag-drop field builder |
| `/admin/forms/assignments` | assignments | List assignments |
| `/admin/forms/assignment/create` | assignmentCreate | Create assignment |
| `/admin/forms/assignment/:id/delete` | assignmentDelete | Delete assignment |
| `/admin/forms/library` | library | Pre-built template library |

### API Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/api/forms/template/:id/fields` | POST | Save all fields (JSON body) |
| `/api/forms/template/:id/reorder` | POST | Reorder fields (JSON array of IDs) |
| `/api/forms/render/:type/:id` | GET | Get resolved form for context |
| `/api/forms/autosave` | POST | Save draft (JSON body) |

---

## CLI Commands

### forms:list

List form templates and assignments.

```bash
# List all templates
php symfony forms:list

# Filter by form type
php symfony forms:list --type=information_object
php symfony forms:list --type=accession

# Show fields for specific template
php symfony forms:list --fields=1

# Show assignments
php symfony forms:list --assignments
```

### forms:export

Export a template to JSON file.

```bash
# Export to file
php symfony forms:export --template-id=1 --output=template.json

# Export to stdout
php symfony forms:export --template-id=1
```

### forms:import

Import a template from JSON file.

```bash
# Basic import
php symfony forms:import --input=template.json

# Import with custom name
php symfony forms:import --input=template.json --name="My Custom Form"

# Dry run (preview without importing)
php symfony forms:import --input=template.json --dry-run
```

---

## Template Resolution Algorithm

When determining which form template to use:

```
1. Find all active assignments matching the form_type
2. For each assignment, calculate a score:
   - Repository match: +100 points
   - Level of description match: +50 points
   - Collection/ancestor match: +25 points
   - Assignment priority: +priority value
3. Select the assignment with highest score
4. If no match, use default template (is_default = 1)
5. If no default, return null
```

### Resolution Flow

```
                     +-------------------+
                     | New Record        |
                     | form_type: io     |
                     | repository: 5     |
                     | level: Item       |
                     +-------------------+
                              |
                              v
              +-------------------------------+
              | Find matching assignments     |
              | WHERE form_type = 'io'        |
              | AND is_active = 1             |
              +-------------------------------+
                              |
                              v
              +-------------------------------+
              | Calculate scores:             |
              |                               |
              | Assignment A:                 |
              |   repo match: +100            |
              |   level match: +50            |
              |   priority: +100              |
              |   Total: 250                  |
              |                               |
              | Assignment B:                 |
              |   repo match: 0 (mismatch)    |
              |   SKIP                        |
              |                               |
              | Assignment C:                 |
              |   repo: null (all) +0         |
              |   level match: +50            |
              |   priority: +50               |
              |   Total: 100                  |
              +-------------------------------+
                              |
                              v
              +-------------------------------+
              | Select highest: Assignment A  |
              | Return template ID            |
              +-------------------------------+
```

---

## JSON Configuration Structures

### Template config_json

```json
{
  "layout": "tabs",
  "tabs": ["Identity", "Context", "Content", "Access"],
  "sections": ["identity", "context", "content"],
  "submitLabel": "Save Record",
  "showProgress": true,
  "enableAutosave": true
}
```

### Field validation_rules

```json
{
  "required": true,
  "minLength": 5,
  "maxLength": 255,
  "pattern": "^[A-Z0-9-]+$",
  "min": 0,
  "max": 100,
  "email": true,
  "url": true
}
```

### Field options_json (for select/radio)

```json
[
  {"value": "fonds", "label": "Fonds"},
  {"value": "series", "label": "Series"},
  {"value": "file", "label": "File"},
  {"value": "item", "label": "Item"}
]
```

### Field conditional_logic

```json
{
  "field": "levelOfDescription",
  "operator": "equals",
  "value": "item",
  "action": "show"
}
```

Supported operators: `equals`, `not_equals`, `contains`, `empty`, `not_empty`

### Field autocomplete_source

| Source Pattern | Description |
|----------------|-------------|
| `taxonomy:level_of_description` | Taxonomy by slug |
| `taxonomy:123` | Taxonomy by ID |
| `actor:all` | All actors |
| `actor:creator` | Actors with creator events |
| `repository:all` | All repositories |
| `custom:/api/endpoint` | Custom endpoint |

---

## Pre-built Templates

The plugin includes seed data for common templates:

| Template | Form Type | Fields | Tabs/Sections |
|----------|-----------|--------|---------------|
| ISAD-G Minimal | information_object | 8 | Single page |
| ISAD-G Full | information_object | 26 | 7 tabs |
| Dublin Core Simple | information_object | 15 | Single page |
| Accession Standard | accession | 15 | 4 tabs |
| Photo Collection Item | information_object | 19 | 4 tabs |

---

## Frontend JavaScript

### FormBuilder Module

```javascript
window.FormBuilder = {
    templateId: null,
    fields: [],
    draggedItem: null,

    init: function(templateId, fields) {},
    bindEvents: function() {},
    renderFields: function() {},
    createFieldItem: function(field, index) {},
    addField: function(fieldType) {},
    editField: function(index) {},
    deleteField: function(index) {},
    moveField: function(fromIndex, toIndex) {},
    saveFields: function() {}
};
```

### FormAutosave Module

```javascript
window.FormAutosave = {
    templateId: null,
    objectType: null,
    objectId: null,
    interval: null,

    init: function(templateId, objectType, objectId) {},
    save: function() {},
    stop: function() {}
};
```

---

## Integration Points

### With AtoM Records

When editing an information object, accession, or other record:

1. Controller calls `resolveTemplate()` with context
2. If template found, render custom form
3. If not, fall back to standard AtoM form
4. Form submission saves via normal AtoM process

### With ahgAuditTrailPlugin

Form submissions can be logged to the audit trail:

```php
// In form save handler
$auditService->logUpdate('information_object', $objectId, $oldData, $newData);
```

### With ahgSecurityClearancePlugin

Form fields can be conditionally shown based on user clearance:

```php
// In form render
foreach ($fields as $field) {
    if ($field->security_level > $user->clearance_level) {
        continue; // Skip field
    }
}
```

---

## CSS Classes

| Class | Description |
|-------|-------------|
| `.form-builder-container` | Main builder layout |
| `.form-builder-palette` | Left sidebar with field types |
| `.form-builder-canvas` | Center drop zone |
| `.field-item` | Individual field in canvas |
| `.field-item.selected` | Currently selected field |
| `.field-item.dragging` | Field being dragged |
| `.palette-item` | Draggable field type |
| `.palette-item.atom-field` | Heratio-specific field |
| `.section-header` | Section divider |
| `.tab-header` | Tab divider |
| `.field-width-full` | Full width field |
| `.field-width-half` | Half width field |
| `.field-width-third` | Third width field |
| `.field-width-quarter` | Quarter width field |

---

## Security

### Access Control

All actions require administrator privileges:

```php
protected function checkAdmin(): void
{
    if (!$this->context->user->isAuthenticated() ||
        !$this->context->user->isAdministrator()) {
        $this->forward('admin', 'secure');
    }
}
```

### System Templates

Templates marked `is_system = 1` cannot be:
- Deleted
- Modified (basic fields only)

### Validation

- Field names sanitized to alphanumeric + underscore
- JSON input validated before processing
- File uploads restricted by type/size

---

## Performance Considerations

### Caching

Template resolution can be cached per session:

```php
$cacheKey = "form_template_{$formType}_{$repositoryId}_{$levelId}";
if ($cached = $cache->get($cacheKey)) {
    return $cached;
}
```

### Indexes

Key indexes for performance:

```sql
INDEX idx_form_type (form_type)
INDEX idx_is_active (is_active)
INDEX idx_template_id (template_id)
INDEX idx_sort_order (sort_order)
INDEX idx_priority (priority)
```

### Field Loading

Fields are loaded sorted by `sort_order` to avoid client-side sorting.

---

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| Template not found | No active assignment | Create assignment or default |
| Fields not saving | Invalid JSON | Check browser console |
| Import fails | Invalid JSON structure | Validate JSON format |
| Draft not loading | Wrong user/context | Check user_id and object_type |
| Form not rendering | Template inactive | Enable template |

---

## Migration Path

### From Standard AtoM Forms

1. Create template matching current fields
2. Create assignment for all records
3. Test with preview
4. Enable for specific repository/level
5. Gradually roll out

### Between Systems

1. Export templates via CLI
2. Transfer JSON files
3. Import on target system
4. Recreate assignments (context-specific)

---

*Part of the AtoM AHG Framework*
