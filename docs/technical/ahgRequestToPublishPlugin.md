# ahgRequestToPublishPlugin Technical Reference

## Overview

Laravel Query Builder implementation for managing publication requests. Replaces QubitRequestToPublish Propel model with modern, maintainable code following the AHG Framework patterns.

## Architecture Diagram
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        ahgRequestToPublishPlugin                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐         │
│  │     Actions     │    │    Services     │    │  Repositories   │         │
│  ├─────────────────┤    ├─────────────────┤    ├─────────────────┤         │
│  │ browseAction    │───▶│ RequestTo       │───▶│ RequestTo       │         │
│  │ editAction      │    │ PublishService  │    │ PublishRepo     │         │
│  │ submitAction    │    │                 │    │                 │         │
│  │ deleteAction    │    │ • submitRequest │    │ • findById      │         │
│  └─────────────────┘    │ • approveReq    │    │ • findBySlug    │         │
│           │             │ • rejectReq     │    │ • paginate      │         │
│           ▼             │ • getStats      │    │ • create        │         │
│  ┌─────────────────┐    └─────────────────┘    │ • update        │         │
│  │   Templates     │             │             │ • delete        │         │
│  ├─────────────────┤             │             └─────────────────┘         │
│  │ browseSuccess   │             │                      │                  │
│  │ editSuccess     │             ▼                      ▼                  │
│  │ submitSuccess   │    ┌─────────────────────────────────────────┐        │
│  │ deleteSuccess   │    │         Laravel Query Builder           │        │
│  └─────────────────┘    │    Illuminate\Database\Capsule\Manager  │        │
│                         └─────────────────────────────────────────┘        │
│                                          │                                 │
└──────────────────────────────────────────┼─────────────────────────────────┘
                                           ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              MySQL Database                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌───────────────────────┐  ┌──────────────────┐         │
│  │    object    │  │  request_to_publish   │  │ request_to_      │         │
│  ├──────────────┤  ├───────────────────────┤  │ publish_i18n     │         │
│  │ id           │◀─│ id (FK)               │◀─├──────────────────┤         │
│  │ class_name   │  │ parent_id             │  │ id (FK)          │         │
│  │ created_at   │  │ rtp_type_id           │  │ culture          │         │
│  │ updated_at   │  │ lft                   │  │ rtp_name         │         │
│  └──────────────┘  │ rgt                   │  │ rtp_surname      │         │
│         ▲          │ source_culture        │  │ rtp_email        │         │
│         │          └───────────────────────┘  │ rtp_phone        │         │
│  ┌──────────────┐                             │ rtp_institution  │         │
│  │     slug     │                             │ rtp_planned_use  │         │
│  ├──────────────┤                             │ rtp_motivation   │         │
│  │ object_id(FK)│                             │ rtp_need_image_by│         │
│  │ slug         │                             │ status_id        │         │
│  └──────────────┘                             │ object_id        │         │
│                                               │ created_at       │         │
│                                               │ completed_at     │         │
│                                               │ rtp_admin_notes  │         │
│                                               └──────────────────┘         │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Request Flow Diagrams

### User Submission Flow
```
┌──────────┐     ┌─────────────────┐     ┌──────────────────┐     ┌─────────────┐
│   User   │     │ Information     │     │ Submit Form      │     │  Database   │
│          │     │ Object View     │     │ (submitAction)   │     │             │
└────┬─────┘     └────────┬────────┘     └────────┬─────────┘     └──────┬──────┘
     │                    │                       │                      │
     │  View Record       │                       │                      │
     │───────────────────▶│                       │                      │
     │                    │                       │                      │
     │  Click "Request    │                       │                      │
     │  to Publish"       │                       │                      │
     │───────────────────▶│                       │                      │
     │                    │                       │                      │
     │                    │  Route to submit      │                      │
     │                    │  with slug            │                      │
     │                    │──────────────────────▶│                      │
     │                    │                       │                      │
     │                    │                       │  GET: Load form      │
     │◀──────────────────────────────────────────│  with object info    │
     │                    │                       │                      │
     │  Fill form &       │                       │                      │
     │  Submit            │                       │                      │
     │───────────────────────────────────────────▶│                      │
     │                    │                       │                      │
     │                    │                       │  POST: Validate      │
     │                    │                       │────────────────────▶ │
     │                    │                       │                      │
     │                    │                       │  INSERT object       │
     │                    │                       │────────────────────▶ │
     │                    │                       │                      │
     │                    │                       │  INSERT request_to_  │
     │                    │                       │  publish             │
     │                    │                       │────────────────────▶ │
     │                    │                       │                      │
     │                    │                       │  INSERT request_to_  │
     │                    │                       │  publish_i18n        │
     │                    │                       │────────────────────▶ │
     │                    │                       │                      │
     │                    │                       │  INSERT slug         │
     │                    │                       │────────────────────▶ │
     │                    │                       │                      │
     │  Redirect with     │                       │◀────────────────────│
     │  success message   │                       │                      │
     │◀──────────────────────────────────────────│                      │
     │                    │                       │                      │
```

### Admin Review Flow
```
┌──────────┐     ┌─────────────────┐     ┌──────────────────┐     ┌─────────────┐
│  Admin   │     │ Browse Dashboard│     │  Edit/Review     │     │  Database   │
│          │     │ (browseAction)  │     │  (editAction)    │     │             │
└────┬─────┘     └────────┬────────┘     └────────┬─────────┘     └──────┬──────┘
     │                    │                       │                      │
     │  Navigate to       │                       │                      │
     │  /requesttopublish │                       │                      │
     │  /browse           │                       │                      │
     │───────────────────▶│                       │                      │
     │                    │                       │                      │
     │                    │  Query requests       │                      │
     │                    │  with pagination      │                      │
     │                    │──────────────────────────────────────────────▶
     │                    │                       │                      │
     │                    │◀─────────────────────────────────────────────│
     │                    │                       │                      │
     │  Display list      │                       │                      │
     │  with status tabs  │                       │                      │
     │◀──────────────────│                       │                      │
     │                    │                       │                      │
     │  Click review      │                       │                      │
     │  icon on request   │                       │                      │
     │───────────────────▶│                       │                      │
     │                    │                       │                      │
     │                    │  Route to edit        │                      │
     │                    │  with slug            │                      │
     │                    │──────────────────────▶│                      │
     │                    │                       │                      │
     │                    │                       │  Query request       │
     │                    │                       │  + info object       │
     │                    │                       │──────────────────────▶
     │                    │                       │                      │
     │  Display request   │                       │◀─────────────────────│
     │  details           │                       │                      │
     │◀──────────────────────────────────────────│                      │
     │                    │                       │                      │
     │  Click Approve     │                       │                      │
     │  or Reject         │                       │                      │
     │───────────────────────────────────────────▶│                      │
     │                    │                       │                      │
     │                    │                       │  UPDATE status_id    │
     │                    │                       │  SET completed_at    │
     │                    │                       │──────────────────────▶
     │                    │                       │                      │
     │  Redirect to       │                       │◀─────────────────────│
     │  browse with msg   │                       │                      │
     │◀──────────────────────────────────────────│                      │
     │                    │                       │                      │
```

### Status State Machine
```
                    ┌─────────────────┐
                    │   User Submit   │
                    └────────┬────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │    PENDING      │
                    │   (status=220)  │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              │              ▼
     ┌─────────────────┐     │     ┌─────────────────┐
     │    APPROVED     │     │     │    REJECTED     │
     │   (status=219)  │     │     │   (status=221)  │
     └─────────────────┘     │     └─────────────────┘
              │              │              │
              │              ▼              │
              │     ┌─────────────────┐     │
              │     │     DELETE      │     │
              │     │   (optional)    │     │
              │     └─────────────────┘     │
              │              ▲              │
              └──────────────┴──────────────┘
```

## Database Schema Details

### Table: request_to_publish
```sql
CREATE TABLE request_to_publish (
    id             INT NOT NULL PRIMARY KEY,  -- References object.id
    parent_id      VARCHAR(50) NULL,
    rtp_type_id    INT NULL,
    lft            INT NOT NULL DEFAULT 0,    -- Nested set left
    rgt            INT NOT NULL DEFAULT 1,    -- Nested set right
    source_culture VARCHAR(14) NOT NULL DEFAULT 'en'
);
```

### Table: request_to_publish_i18n
```sql
CREATE TABLE request_to_publish_i18n (
    id                INT NOT NULL,
    culture           VARCHAR(14) NOT NULL DEFAULT 'en',
    unique_identifier VARCHAR(1024) NULL,
    rtp_name          VARCHAR(50) NULL,
    rtp_surname       VARCHAR(50) NULL,
    rtp_phone         VARCHAR(50) NULL,
    rtp_email         VARCHAR(50) NULL,
    rtp_institution   VARCHAR(200) NULL,
    rtp_motivation    TEXT NULL,
    rtp_planned_use   TEXT NULL,
    rtp_need_image_by DATETIME NULL,
    status_id         INT NOT NULL DEFAULT 220,
    object_id         VARCHAR(50) NULL,
    completed_at      DATETIME NULL,
    created_at        DATETIME NOT NULL,
    rtp_admin_notes   TEXT NULL,
    PRIMARY KEY (id, culture),
    FOREIGN KEY (id) REFERENCES request_to_publish(id) ON DELETE CASCADE
);
```

### Status Term IDs

| Status | Term ID | AtoM Constant | Description |
|--------|---------|---------------|-------------|
| Pending | 220 | QubitTerm::IN_REVIEW_ID | Awaiting admin review |
| Approved | 219 | QubitTerm::APPROVED_ID | Request granted |
| Rejected | 221 | QubitTerm::REJECTED_ID | Request denied |

## File Structure
```
atom-ahg-plugins/ahgRequestToPublishPlugin/
├── config/
│   └── ahgRequestToPublishPluginConfiguration.class.php
├── lib/
│   ├── Repositories/
│   │   └── RequestToPublishRepository.php
│   └── Services/
│       └── RequestToPublishService.php
├── modules/
│   └── requestToPublish/
│       ├── actions/
│       │   ├── browseAction.class.php
│       │   ├── editAction.class.php
│       │   ├── deleteAction.class.php
│       │   └── submitAction.class.php
│       ├── config/
│       │   └── module.yml
│       └── templates/
│           ├── browseSuccess.php
│           ├── editSuccess.php
│           ├── submitSuccess.php
│           └── deleteSuccess.php
├── data/
└── extension.json
```

## Routing Configuration

Routes are registered in `ahgRequestToPublishPluginConfiguration.class.php` using Symfony's routing system:
```php
public function loadRoutes(sfEvent $event)
{
    $routing = $event->getSubject();

    // Order matters! Generic routes first, specific last (prepend adds to front)
    
    // Generic slug route (matched last)
    $routing->prependRoute('requesttopublish_edit',
        new sfRoute('/requesttopublish/:slug',
            ['module' => 'requestToPublish', 'action' => 'edit']));

    // Delete route
    $routing->prependRoute('requesttopublish_delete',
        new sfRoute('/requesttopublish/delete/:slug',
            ['module' => 'requestToPublish', 'action' => 'delete']));

    // Submit route (public form)
    $routing->prependRoute('requesttopublish_submit',
        new sfRoute('/requestToPublish/submit/:slug',
            ['module' => 'requestToPublish', 'action' => 'submit']));

    // Browse route (matched first - most specific)
    $routing->prependRoute('requesttopublish_browse',
        new sfRoute('/requesttopublish/browse',
            ['module' => 'requestToPublish', 'action' => 'browse']));
}
```

### Route Table

| Route | Method | Module | Action | Description |
|-------|--------|--------|--------|-------------|
| `/requesttopublish/browse` | GET | requestToPublish | browse | Admin dashboard |
| `/requesttopublish/:slug` | GET/POST | requestToPublish | edit | Review/update request |
| `/requesttopublish/delete/:slug` | GET/POST | requestToPublish | delete | Delete confirmation |
| `/requestToPublish/submit/:slug` | GET/POST | requestToPublish | submit | Public submission form |

## Repository Class

### RequestToPublishRepository

Located at: `lib/Repositories/RequestToPublishRepository.php`

#### Methods

| Method | Parameters | Return | Description |
|--------|------------|--------|-------------|
| `findById` | `int $id` | `?object` | Get request by ID |
| `findBySlug` | `string $slug` | `?object` | Get request by URL slug |
| `paginate` | `int $page, int $perPage, ?string $status, ?string $sort, string $order` | `array` | Paginated list with filters |
| `countByStatus` | `?string $status` | `int` | Count requests by status |
| `create` | `array $data` | `int` | Create new request, returns ID |
| `update` | `int $id, array $data` | `bool` | Update request |
| `delete` | `int $id` | `bool` | Delete request and related records |
| `getStatusCounts` | none | `array` | Get counts for all statuses |
| `getStatusLabel` | `int $statusId` | `string` | Human-readable status |
| `getStatusBadgeClass` | `int $statusId` | `string` | Bootstrap badge class |

#### Create Method Flow
```
create(array $data)
    │
    ├──▶ INSERT INTO object (class_name='QubitRequestToPublish')
    │    └── Returns: $objectId
    │
    ├──▶ INSERT INTO request_to_publish (id=$objectId, lft=0, rgt=1)
    │
    ├──▶ INSERT INTO request_to_publish_i18n (id=$objectId, culture='en', ...)
    │
    └──▶ INSERT INTO slug (object_id=$objectId, slug='request-to-publish-{$objectId}')
         └── Returns: $objectId
```

#### Delete Method Flow
```
delete(int $id)
    │
    ├──▶ DELETE FROM slug WHERE object_id = $id
    │
    ├──▶ DELETE FROM request_to_publish_i18n WHERE id = $id
    │
    ├──▶ DELETE FROM request_to_publish WHERE id = $id
    │
    └──▶ DELETE FROM object WHERE id = $id
         └── Returns: true
```

## Service Class

### RequestToPublishService

Located at: `lib/Services/RequestToPublishService.php`

#### Methods

| Method | Parameters | Return | Description |
|--------|------------|--------|-------------|
| `submitRequest` | `array $data` | `int` | Validate and create request |
| `approveRequest` | `int $id, ?string $adminNotes` | `bool` | Set status to approved |
| `rejectRequest` | `int $id, ?string $adminNotes` | `bool` | Set status to rejected |
| `getRequestWithObject` | `int $id` | `?object` | Get request with related info object |
| `getDigitalObjects` | `int $objectId` | `array` | Get digital objects for info object |
| `hasPendingRequest` | `int $objectId, string $email` | `bool` | Check for duplicate pending |
| `getStatistics` | none | `array` | Dashboard statistics |

## Action Classes

### browseAction

**Authentication**: Required (Administrator)

**Parameters**:
- `filter` (string): Status filter (all, pending, approved, rejected)
- `page` (int): Page number
- `sort` (string): Sort field
- `order` (string): Sort order (asc, desc)

**Template Variables**:
- `$requests`: Collection of request objects
- `$total`: Total count
- `$pages`: Total pages
- `$statusCounts`: Array of counts per status
- `$repository`: Repository instance for helpers

### editAction

**Authentication**: Required (Administrator)

**Parameters**:
- `slug` (string): Request slug from URL

**POST Parameters**:
- `action_type` (string): approve, reject, or save
- `rtp_admin_notes` (string): Admin notes

**Template Variables**:
- `$resource`: Request object with related data
- `$repository`: Repository instance for helpers

### submitAction

**Authentication**: None (Public)

**Parameters**:
- `slug` (string): Information object slug

**POST Parameters**:
- `rtp_name`, `rtp_surname`, `rtp_email` (required)
- `rtp_phone`, `rtp_institution` (optional)
- `rtp_planned_use` (required), `rtp_motivation` (optional)
- `rtp_need_image_by` (optional date)

**Template Variables**:
- `$informationObject`: The requested record
- `$userName`, `$userSurname`, `$userEmail`: Pre-filled from logged-in user

### deleteAction

**Authentication**: Required (Administrator)

**Parameters**:
- `slug` (string): Request slug

**POST Parameters**:
- `confirm` (string): Must be 'yes' to delete

## Template Integration

### Adding Button to Sector Templates

The Request to Publish button is conditionally displayed using:
```php
<?php if (class_exists('ahgRequestToPublishPluginConfiguration')): ?>
  <?php echo link_to(
    '<i class="fas fa-paper-plane me-1"></i>' . __('Request to Publish'),
    ['module' => 'requestToPublish', 'action' => 'submit', 'slug' => $resource->slug],
    ['class' => 'btn btn-sm btn-outline-primary']
  ); ?>
<?php endif; ?>
```

### Templates Updated

| Template | Location |
|----------|----------|
| ISAD | `ahgThemeB5Plugin/modules/sfIsadPlugin/templates/indexSuccess.php` |
| Museum | `ahgMuseumPlugin/modules/ahgMuseumPlugin/templates/indexSuccess.php` |
| Museum CCO | `ahgMuseumPlugin/modules/cco/templates/indexSuccess.php` |

## Plugin Registration

### atom_plugin Table Entry
```sql
INSERT INTO atom_plugin (
    name, class_name, version, description, author, category,
    is_enabled, is_core, is_locked, load_order
) VALUES (
    'ahgRequestToPublishPlugin',
    'ahgRequestToPublishPluginConfiguration',
    '1.0.0',
    'Manage publication requests for archival images and digital objects',
    'The Archive and Heritage Group',
    'ahg',
    1, 0, 0, 80
);
```

### Enabling/Disabling
```bash
# Enable
php bin/atom extension:enable ahgRequestToPublishPlugin

# Disable
php bin/atom extension:disable ahgRequestToPublishPlugin
```

## Error Handling

### Flash Message Pattern

All templates use the non-empty check pattern to avoid showing empty error alerts:
```php
<?php if ($sf_user->hasFlash('error') && $sf_user->getFlash('error')): ?>
  <div class="alert alert-danger">...</div>
<?php endif; ?>
```

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `Class not found` | Service not requiring Repository | Add `require_once` for Repository in Service |
| `now() undefined` | Laravel helper not available | Use `date('Y-m-d H:i:s')` instead |
| `Column not found` | DB schema mismatch | Check i18n table has all required columns |
| `Template not found` | Missing template file | Create submitSuccess.php, etc. |

## Testing

### Manual Test URLs
```
# Admin browse
https://[domain]/requesttopublish/browse

# Submit form (replace [slug] with actual record slug)
https://[domain]/requestToPublish/submit/[slug]

# Review request (replace [slug] with request slug)
https://[domain]/requesttopublish/[slug]
```

### Database Verification
```sql
-- Check request count
SELECT COUNT(*) FROM request_to_publish_i18n WHERE culture = 'en';

-- Check status distribution
SELECT status_id, COUNT(*) as count 
FROM request_to_publish_i18n 
WHERE culture = 'en' 
GROUP BY status_id;

-- View recent requests
SELECT i.*, s.slug 
FROM request_to_publish_i18n i
JOIN slug s ON i.id = s.object_id
WHERE i.culture = 'en'
ORDER BY i.created_at DESC
LIMIT 10;
```

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-13 | Initial release with Laravel Query Builder |

## Related Documentation

- [Feedback Module](ahgFeedbackPlugin.md) - Similar user engagement pattern
- [AHG Framework Architecture](../AtoM_AHG_Framework_Library_Architecture_Diagrams.md)
- [Plugin Development Guide](../technical/README.md)
