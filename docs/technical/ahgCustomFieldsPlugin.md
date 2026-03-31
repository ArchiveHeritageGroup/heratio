# ahgCustomFieldsPlugin — Technical Manual

**Version:** 1.0.0
**Category:** Metadata Management
**Load Order:** 45
**Dependencies:** ahgCorePlugin

---

## Architecture

The plugin implements an Entity-Attribute-Value (EAV) pattern for extensible metadata. Field definitions (attributes) are stored in `custom_field_definition`, and field values are stored in `custom_field_value` with typed columns.

### Layer Diagram

```
┌──────────────────────────────────────────────────┐
│  Admin UI (/admin/customFields)                   │
│  customFieldAdmin module — CRUD for definitions   │
├──────────────────────────────────────────────────┤
│  Entity UI (edit/view pages)                      │
│  customField module — save/get values (AJAX)      │
│  Display panel — _custom_fields_panel.php         │
├──────────────────────────────────────────────────┤
│  Services                                         │
│  CustomFieldService — business logic              │
│  CustomFieldRenderService — HTML generation       │
├──────────────────────────────────────────────────┤
│  Repositories                                     │
│  FieldDefinitionRepository — definition CRUD      │
│  FieldValueRepository — value CRUD                │
├──────────────────────────────────────────────────┤
│  Database                                         │
│  custom_field_definition + custom_field_value      │
└──────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_58e9982e.png)
```

---

## Database Schema

### custom_field_definition

Stores field definitions (the EAV "attribute" catalog).

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED PK | Auto-increment |
| field_key | VARCHAR(100) | Machine name, unique per entity_type |
| field_label | VARCHAR(255) | Display name |
| field_type | VARCHAR(30) | text, textarea, date, number, boolean, dropdown, url |
| entity_type | VARCHAR(50) | informationobject, actor, accession, repository, donor, function |
| field_group | VARCHAR(100) NULL | UI section grouping label |
| dropdown_taxonomy | VARCHAR(100) NULL | ahg_dropdown taxonomy key (when type=dropdown) |
| is_required | TINYINT(1) | Require value on save |
| is_searchable | TINYINT(1) | Flag for search index integration |
| is_visible_public | TINYINT(1) | Show on public view page |
| is_visible_edit | TINYINT(1) | Show on edit form |
| is_repeatable | TINYINT(1) | Allow multiple values |
| default_value | VARCHAR(500) NULL | Pre-filled default |
| help_text | VARCHAR(500) NULL | Form guidance text |
| validation_rule | VARCHAR(255) NULL | e.g. max:255, regex:/^[A-Z]/ |
| sort_order | INT | Display ordering |
| is_active | TINYINT(1) | Active/inactive (soft delete) |
| created_at | DATETIME | Auto |
| updated_at | DATETIME | Auto |

**Indexes:** `uk_field_entity` (field_key, entity_type), `idx_entity_type`, `idx_active_entity`, `idx_group`

### custom_field_value

Stores field values (the EAV "value" rows). Uses typed columns to preserve data types.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED PK | Auto-increment |
| field_definition_id | BIGINT UNSIGNED FK | References custom_field_definition(id) ON DELETE CASCADE |
| object_id | INT | FK to the entity (information_object.id, actor.id, etc.) |
| value_text | TEXT NULL | For text, textarea, url types |
| value_number | DECIMAL(15,4) NULL | For number type |
| value_date | DATE NULL | For date type |
| value_boolean | TINYINT(1) NULL | For boolean type |
| value_dropdown | VARCHAR(100) NULL | For dropdown type (ahg_dropdown code) |
| sequence | INT | Ordering for repeatable fields (0 = first) |
| created_at | DATETIME | Auto |
| updated_at | DATETIME | Auto |

**Indexes:** `idx_field_object` (field_definition_id, object_id), `idx_object`, `idx_dropdown`

---

## Plugin Configuration

### File: `config/ahgCustomFieldsPluginConfiguration.class.php`

- Registers modules: `customFieldAdmin`, `customField`
- Connects to `routing.load_configuration` to register routes via RouteLoader
- Connects to `response.filter_content` to inject CSS/JS assets
- Registers PSR-4 autoloader for `AhgCustomFieldsPlugin\` namespace under `lib/`

### Routes (registered in PluginConfiguration)

| Route Name | URL | Module | Action |
|------------|-----|--------|--------|
| custom_field_admin_index | /admin/customFields | customFieldAdmin | index |
| custom_field_admin_edit | /admin/customFields/edit | customFieldAdmin | edit |
| custom_field_admin_save | /admin/customFields/save | customFieldAdmin | save |
| custom_field_admin_delete | /admin/customFields/delete | customFieldAdmin | delete |
| custom_field_admin_reorder | /admin/customFields/reorder | customFieldAdmin | reorder |
| custom_field_admin_export | /admin/customFields/export | customFieldAdmin | export |
| custom_field_admin_import | /admin/customFields/import | customFieldAdmin | import |
| custom_field_save_values | /customFields/save | customField | saveValues |
| custom_field_get_values | /customFields/get/:entityType/:objectId | customField | getValues |

---

## Services

### CustomFieldService

**Namespace:** `AhgCustomFieldsPlugin\Service\CustomFieldService`
**Path:** `lib/Service/CustomFieldService.php`

| Method | Returns | Description |
|--------|---------|-------------|
| `getDefinitionsForEntity($entityType)` | array | Active definitions sorted by sort_order |
| `getDefinitionsByGroup($entityType)` | array | Definitions grouped by field_group |
| `getDefinition($id)` | object\|null | Single definition by ID |
| `getValuesForObject($objectId, $entityType)` | array | Values keyed by field_key |
| `getRawValuesForObject($objectId, $entityType)` | array | Raw value rows with joined definition data |
| `saveValues($objectId, $entityType, $fieldValues)` | void | Upsert values from form submission |
| `deleteValuesForObject($objectId)` | void | Cleanup on entity delete |
| `createDefinition($data)` | int | Create definition, returns ID |
| `updateDefinition($id, $data)` | void | Update definition |
| `deleteDefinition($id)` | void | Soft-delete (is_active=0) |
| `hardDeleteDefinition($id)` | bool | Hard-delete if no values exist |
| `reorderDefinitions($orderedIds)` | void | Update sort_order by ID array |
| `validateValue($definition, $value)` | bool\|string | Validate; returns true or error message |
| `exportDefinitions($entityType)` | array | Export schema as array |
| `importDefinitions($definitions)` | int | Import schema, returns count imported |
| `getEntityTypes()` | array | Available entity types |
| `getFieldTypes()` | array | Available field types |
| `getDropdownTaxonomies()` | array | Taxonomies from ahg_dropdown |
| `getDropdownOptions($taxonomy)` | array | Options for a taxonomy |
| `generateFieldKey($label)` | string | Generate machine name from label |

### CustomFieldRenderService

**Namespace:** `AhgCustomFieldsPlugin\Service\CustomFieldRenderService`
**Path:** `lib/Service/CustomFieldRenderService.php`

| Method | Returns | Description |
|--------|---------|-------------|
| `renderViewFields($entityType, $objectId, $publicOnly)` | string | HTML for view display (grouped, read-only) |
| `renderEditFields($entityType, $objectId)` | string | HTML form inputs (grouped, editable) |
| `getFieldInput($def, $value)` | string | Single form input by field type |
| `getRepeatableInput($def, $values)` | string | Repeatable field with add/remove buttons |

---

## Repositories

### FieldDefinitionRepository

**Path:** `lib/Repository/FieldDefinitionRepository.php`

All operations via `Illuminate\Database\Capsule\Manager as DB` on `custom_field_definition`.

| Method | Description |
|--------|-------------|
| `getByEntityType($entityType)` | Active definitions for entity type |
| `getByEntityTypeGrouped($entityType)` | Grouped by field_group |
| `getAll()` | All definitions (admin listing) |
| `getAllGroupedByEntity()` | Grouped by entity_type |
| `find($id)` | Find by ID |
| `findByKey($fieldKey, $entityType)` | Find by key + entity type |
| `create($data)` | Insert, returns ID |
| `update($id, $data)` | Update |
| `deactivate($id)` | Set is_active=0 |
| `delete($id)` | Hard delete |
| `isKeyUnique($key, $entityType, $excludeId)` | Check uniqueness |
| `reorder($orderedIds)` | Update sort_order |
| `getFieldGroups()` | Distinct field groups |
| `getUsedEntityTypes()` | Distinct entity types in use |
| `exportByEntityType($entityType)` | Export for entity type |
| `countValues($defId)` | Count values for a definition |

### FieldValueRepository

**Path:** `lib/Repository/FieldValueRepository.php`

| Method | Description |
|--------|-------------|
| `getByObject($objectId)` | All values for an object |
| `getByObjectAndEntity($objectId, $entityType)` | Values joined with definitions |
| `getByDefinitionAndObject($defId, $objectId)` | Values for specific field + object |
| `upsertValue($defId, $objectId, $valueData, $seq)` | Insert or update a value |
| `deleteByDefinitionAndObject($defId, $objectId)` | Delete values for field + object |
| `deleteByObject($objectId)` | Delete all values for object |
| `deleteByDefinition($defId)` | Delete all values for definition |
| `countByDefinition($defId)` | Count values |
| `countObjectsByDefinition($defId)` | Count distinct objects |

---

## Modules

### customFieldAdmin (Admin CRUD)

Extends `AhgController`. All actions require admin role (`$this->checkAdmin()`).

| Action | Method | Description |
|--------|--------|-------------|
| index | GET | List definitions grouped by entity type |
| edit | GET | Create/edit form |
| save | POST/AJAX | Create or update definition |
| delete | POST/AJAX | Soft or hard delete |
| reorder | POST/AJAX | Drag-drop reorder |
| export | GET | Download JSON |
| import | POST/AJAX | Import JSON |

### customField (Entity Value CRUD)

| Action | Method | Description |
|--------|--------|-------------|
| saveValues | POST/AJAX | Save field values for an entity |
| getValues | GET/AJAX | Get field values for an entity |

---

## Display Panel Integration

The plugin registers display panels via `extension.json`:

```json
"display_panels": [
    { "id": "custom_fields_io", "contexts": ["informationobject"], ... },
    { "id": "custom_fields_actor", "contexts": ["actor"], ... },
    { "id": "custom_fields_accession", "contexts": ["accession"], ... },
    { "id": "custom_fields_repository", "contexts": ["repository"], ... }
]
```

The panel template (`templates/display/_custom_fields_panel.php`) renders custom field values in a card with grouped `<dl>` elements.

---

## Template Integration

### Including edit fields in a plugin template

```php
<?php include_partial('customField/editFields', [
    'entityType' => 'informationobject',
    'objectId' => $resource->id,
]); ?>
```

### Including view fields in a plugin template

```php
<?php include_partial('customField/viewFields', [
    'entityType' => 'informationobject',
    'objectId' => $resource->id,
    'publicOnly' => true,
]); ?>
```

### JavaScript API

The edit partial exposes a global function for saving values programmatically:

```javascript
// Save custom fields via AJAX
window.cfSaveFields().then(function(data) {
    if (data.success) { /* saved */ }
});
```

---

## Validation Rules

| Rule Syntax | Example | Description |
|-------------|---------|-------------|
| `max:N` | `max:255` | Maximum string length |
| `regex:/pattern/` | `regex:/^[A-Z]{2}\d+$/` | Regular expression match |

Built-in type validation is also applied:
- **number**: Must be numeric
- **date**: Must match YYYY-MM-DD format
- **url**: Must pass PHP `FILTER_VALIDATE_URL`

---

## Reporting Views

**Path:** `atom-framework/database/views/reporting_views.sql`

### v_report_descriptions

Joins: `information_object`, `information_object_i18n`, `term_i18n` (level, status), `repository`/`actor_i18n` (repository name), `event`/`event_i18n` (creation date), `status`/`term_i18n` (publication status).

Excludes root node (id=1). Uses MIN(event.id) subquery to avoid duplicate rows from multiple events.

### v_report_authorities

Joins: `actor`, `actor_i18n`, `term_i18n` (entity type, description status).

Excludes root node (id=1).

### v_report_accessions

Joins: `accession`, `accession_i18n`, `term_i18n` (acquisition type, processing status, processing priority).

---

## File Structure

```
ahgCustomFieldsPlugin/
├── config/
│   ├── ahgCustomFieldsPluginConfiguration.class.php
│   └── routing.yml
├── database/
│   └── install.sql
├── extension.json
├── lib/
│   ├── Repository/
│   │   ├── FieldDefinitionRepository.php
│   │   └── FieldValueRepository.php
│   └── Service/
│       ├── CustomFieldService.php
│       └── CustomFieldRenderService.php
├── modules/
│   ├── customFieldAdmin/
│   │   ├── actions/actions.class.php
│   │   └── templates/
│   │       ├── indexSuccess.php
│   │       ├── editSuccess.php
│   │       └── _fieldForm.php
│   └── customField/
│       ├── actions/actions.class.php
│       └── templates/
│           ├── _editFields.php
│           └── _viewFields.php
├── templates/
│   └── display/
│       └── _custom_fields_panel.php
└── web/
    ├── css/custom-fields.css
    └── js/custom-fields.js
```

---

## Seed Data

### Restriction Codes (in ahgCorePlugin)

Added to `ahgCorePlugin/database/install.sql` under taxonomy `restriction_code` with 9 base values. See Feature Overview for the full list.

---

*The Archive and Heritage Group (Pty) Ltd*
