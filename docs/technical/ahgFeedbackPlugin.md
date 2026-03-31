# ahgFeedbackPlugin Technical Documentation

## Overview

The ahgFeedbackPlugin provides user feedback management functionality for AtoM using Laravel Query Builder. It allows users to submit feedback on archival records or general feedback, with full CRUD operations for administrators.

## Architecture

### Plugin Structure
```
ahgFeedbackPlugin/
├── config/
│   └── ahgFeedbackPluginConfiguration.class.php
├── data/
│   └── install.sql
├── extension.json
├── lib/
│   └── task/
├── modules/
│   └── ahgFeedback/
│       ├── actions/
│       │   ├── browseAction.class.php
│       │   ├── deleteAction.class.php
│       │   ├── editAction.class.php
│       │   ├── generalAction.class.php
│       │   ├── submitAction.class.php
│       │   └── viewAction.class.php
│       └── templates/
│           ├── browseSuccess.php
│           ├── deleteSuccess.php
│           ├── editSuccess.php
│           ├── generalSuccess.php
│           ├── submitSuccess.php
│           └── viewSuccess.php
```

### Database Schema

The plugin uses existing AtoM feedback tables with the object inheritance pattern:
```sql
-- Base object table (AtoM standard)
object (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_name VARCHAR(255),  -- 'QubitFeedback'
    created_at DATETIME,
    updated_at DATETIME,
    serial_number INT
)

-- Main feedback table
feedback (
    id INT PRIMARY KEY,        -- FK to object.id
    feed_name VARCHAR(50),
    feed_surname VARCHAR(50),
    feed_phone VARCHAR(50),
    feed_email VARCHAR(50),
    feed_relationship TEXT,
    parent_id VARCHAR(50),
    feed_type_id INT,
    lft INT,                   -- Nested set
    rgt INT,                   -- Nested set
    source_culture VARCHAR(14)
)

-- Internationalized fields
feedback_i18n (
    id INT,                    -- FK to feedback.id
    culture VARCHAR(14),
    name VARCHAR(1024),        -- Subject/record title
    unique_identifier VARCHAR(1024),
    remarks TEXT,
    object_id TEXT,            -- FK to information_object.id
    completed_at DATETIME,
    created_at DATETIME,
    status_id INT              -- QubitTerm::PENDING_ID or COMPLETED_ID
)
```

### Key Design Decisions

1. **No QubitFeedback Model**: Uses Laravel Query Builder exclusively to avoid Propel model dependencies
2. **Object Table Inheritance**: Inserts into `object` table first, then `feedback`, then `feedback_i18n`
3. **Nested Set Pattern**: Maintains `lft`/`rgt` columns for AtoM compatibility
4. **i18n Support**: Culture-aware queries joining `feedback` and `feedback_i18n`

## Actions

### browseAction

Lists all feedback with filtering and pagination.

**Route**: `/feedback` or `/feedback?filter=pending|completed`

**Query Builder Pattern**:
```php
$query = DB::table('feedback')
    ->join('feedback_i18n', 'feedback.id', '=', 'feedback_i18n.id')
    ->where('feedback_i18n.culture', $culture)
    ->select('feedback.*', 'feedback_i18n.*');
```

**Template Variables**:
- `$feedbackItems` - Collection of feedback records
- `$totalCount`, `$pendingCount`, `$completedCount` - Count statistics
- `$filter`, `$sort`, `$page` - Current filter state
- `$totalPages`, `$currentPage` - Pagination info

### generalAction

Handles general feedback submission (not linked to a record).

**Route**: `/feedback/general`

**Insert Pattern**:
```php
// 1. Insert into object table
$objectId = DB::table('object')->insertGetId([
    'class_name' => 'QubitFeedback',
    'created_at' => $now,
    'updated_at' => $now,
    'serial_number' => 0,
]);

// 2. Get nested set values
$maxRgt = DB::table('feedback')->max('rgt') ?? 0;

// 3. Insert into feedback table
DB::table('feedback')->insert([
    'id' => $objectId,
    'feed_name' => $value,
    // ... other fields
    'lft' => $maxRgt + 1,
    'rgt' => $maxRgt + 2,
    'source_culture' => $culture,
]);

// 4. Insert into feedback_i18n
DB::table('feedback_i18n')->insert([
    'id' => $objectId,
    'culture' => $culture,
    'status_id' => QubitTerm::PENDING_ID,
    // ... other fields
]);
```

### submitAction

Handles feedback submission linked to an information object.

**Route**: `/{slug}/ahgFeedback/submit`

**Key Difference from generalAction**:
- Receives `slug` parameter to identify linked record
- Stores `object_id` in `feedback_i18n` referencing the information object
- Pre-populates `name` field with record title

### editAction

Administrator edit interface for feedback.

**Route**: `/feedback/{id}/edit`

**Update Pattern**:
```php
DB::table('feedback')
    ->where('id', $id)
    ->update([...]);

DB::table('feedback_i18n')
    ->where('id', $id)
    ->where('culture', $culture)
    ->update([...]);

DB::table('object')
    ->where('id', $id)
    ->update(['updated_at' => $now]);
```

### deleteAction

Deletes feedback (cascades via foreign keys).

**Route**: `/feedback/{id}/delete`

**Delete Pattern**:
```php
// Object table has ON DELETE CASCADE
DB::table('object')->where('id', $id)->delete();
```

## Integration Points

### Template Integration

The Item Feedback button is conditionally displayed based on plugin availability:
```php
<?php if (class_exists('ahgFeedbackPluginConfiguration')): ?>
    <?php echo link_to(
        '<i class="fas fa-comment me-1"></i>' . __('Item Feedback'),
        ['module' => 'ahgFeedback', 'action' => 'submit', 'slug' => $resource->slug],
        ['class' => 'btn btn-sm btn-outline-secondary']
    ); ?>
<?php endif; ?>
```

**Integrated Templates**:

| Location | Template | Button Type |
|----------|----------|-------------|
| ISAD | `sfIsadPlugin/templates/indexSuccess.php` | Standalone button |
| Museum (CCO) | `ahgMuseumPlugin/modules/cco/templates/indexSuccess.php` | Standalone button |
| Museum | `ahgMuseumPlugin/modules/ahgMuseumPlugin/templates/indexSuccess.php` | Standalone button |
| Library | `ahgLibraryPlugin/modules/ahgLibraryPlugin/templates/indexSuccess.php` | Dropdown menu |
| Gallery | `ahgGalleryPlugin/modules/ahgGalleryPlugin/templates/indexSuccess.php` | Dropdown menu |
| DAM/Others | `ahgThemeB5Plugin/modules/informationobject/templates/_actions.php` | Dropdown menu |

### Routing

Defined in `ahgThemeB5Plugin/config/routing.yml`:
```yaml
ahg_feedback_browse:
  url: /feedback
  param: { module: ahgFeedback, action: browse }

ahg_feedback_general:
  url: /feedback/general
  param: { module: ahgFeedback, action: general }

ahg_feedback_submit:
  url: /:slug/ahgFeedback/submit
  param: { module: ahgFeedback, action: submit }

ahg_feedback_edit:
  url: /feedback/:id/edit
  param: { module: ahgFeedback, action: edit }

ahg_feedback_delete:
  url: /feedback/:id/delete
  param: { module: ahgFeedback, action: delete }
```

### Plugin Registration

Registered in `atom_plugin` table:
```sql
INSERT INTO atom_plugin (name, class_name, is_enabled, category, version, description)
VALUES (
    'ahgFeedbackPlugin',
    'ahgFeedbackPluginConfiguration',
    1,
    'ahg',
    '1.0.0',
    'User feedback and suggestions management'
);
```

## Feedback Types

Stored in `feed_type_id`:

| ID | Type |
|----|------|
| 0 | General Feedback |
| 1 | Error Report |
| 2 | Suggestion |
| 3 | Correction Request |
| 4 | Need Assistance |

## Status Values

Uses AtoM's QubitTerm constants:

| Constant | Value | Description |
|----------|-------|-------------|
| `QubitTerm::PENDING_ID` | (varies) | Awaiting review |
| `QubitTerm::COMPLETED_ID` | (varies) | Addressed/closed |

## Security

- **Authentication**: Required for browse/edit/delete actions
- **Authorization**: Administrator access required for management
- **Public Access**: Submit and general actions can be public (configurable)
- **XSS Prevention**: All output escaped with `esc_entities()`

## Dependencies

- **atom-framework**: Laravel Query Builder (`Illuminate\Database`)
- **AtoM 2.10**: Base system with Symfony 1.x
- **Bootstrap 5**: UI framework (via ahgThemeB5Plugin)

## Configuration

Plugin can be enabled/disabled via:
- Admin → AHG Settings → Plugin Management
- CLI: `php bin/atom extension:enable ahgFeedbackPlugin`

## Testing

**Test URLs**:
- Browse: `https://[domain]/feedback`
- General: `https://[domain]/feedback/general`
- Edit: `https://[domain]/feedback/[id]/edit`
- Item Feedback: Click button on any record

## Troubleshooting

### Common Issues

**"Class QubitFeedback not found"**
- Cause: Old action using Propel model
- Solution: All actions must use Laravel Query Builder

**"Cannot use object of type stdClass as array"**
- Cause: url_for() expecting QubitObject
- Solution: Use explicit URL: `url_for(['module' => 'ahgFeedback', 'action' => 'edit', 'id' => $id])`

**"Foreign key constraint fails on feedback"**
- Cause: Not inserting into `object` table first
- Solution: Insert sequence: object → feedback → feedback_i18n

### Debug Queries
```php
// Enable query logging
DB::enableQueryLog();
// ... run queries
dd(DB::getQueryLog());
```

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-13 | Initial release with Laravel Query Builder |
