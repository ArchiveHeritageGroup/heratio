# ahgExhibitionPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** GLAM
**Dependencies:** atom-framework
**Sectors:** Archive, Museum, Gallery, Library, DAM

---

## Overview

Comprehensive exhibition management system for GLAM/DAM institutions. Manages the complete exhibition lifecycle from concept to archive, including object selection, storyline creation, event scheduling, and task tracking.

---

## Architecture

```
+---------------------------------------------------------------------+
|                      ahgExhibitionPlugin                             |
+---------------------------------------------------------------------+
|                                                                       |
|  +---------------------------------------------------------------+   |
|  |                   Plugin Configuration                        |   |
|  |  ahgExhibitionPluginConfiguration.class.php                   |   |
|  |  - Registers routes                                           |   |
|  |  - Enables exhibition module                                  |   |
|  +---------------------------------------------------------------+   |
|                              |                                        |
|                              v                                        |
|  +---------------------------------------------------------------+   |
|  |                   ExhibitionService                           |   |
|  |  lib/Services/ExhibitionService.php                           |   |
|  |  - Exhibition CRUD operations                                 |   |
|  |  - Status transitions                                         |   |
|  |  - Object management                                          |   |
|  |  - Section management                                         |   |
|  |  - Storyline management                                       |   |
|  |  - Event management                                           |   |
|  |  - Checklist management                                       |   |
|  |  - Statistics and reporting                                   |   |
|  +---------------------------------------------------------------+   |
|                              |                                        |
|                              v                                        |
|  +---------------------------------------------------------------+   |
|  |                   ExhibitionWorkflow                          |   |
|  |  lib/Workflow/ExhibitionWorkflow.php                          |   |
|  |  - State machine definition                                   |   |
|  |  - Valid transitions                                          |   |
|  |  - Transition requirements                                    |   |
|  |  - Progress tracking                                          |   |
|  +---------------------------------------------------------------+   |
|                              |                                        |
|                              v                                        |
|  +---------------------------------------------------------------+   |
|  |                   Actions Controller                          |   |
|  |  modules/exhibition/actions/actions.class.php                 |   |
|  |  - HTTP request handling                                      |   |
|  |  - Form processing                                            |   |
|  |  - AJAX endpoints                                             |   |
|  +---------------------------------------------------------------+   |
|                              |                                        |
|                              v                                        |
|  +---------------------------------------------------------------+   |
|  |                   Database Tables                             |   |
|  |  exhibition, exhibition_object, exhibition_section,           |   |
|  |  exhibition_storyline, exhibition_event, exhibition_checklist |   |
|  +---------------------------------------------------------------+   |
|                                                                       |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+----------------------+         +----------------------+
|   exhibition_venue   |         |   exhibition         |
+----------------------+         +----------------------+
| PK id                |<--+     | PK id                |
|    name              |   |     |    title             |
|    code              |   |     |    subtitle          |
|    venue_type        |   |     |    slug (unique)     |
|    address_line1     |   |     |    description       |
|    city              |   +---->| FK venue_id          |
|    country           |         |    exhibition_type   |
|    contact_*         |         |    status            |
|    has_climate_ctrl  |         |    opening_date      |
|    has_security      |         |    closing_date      |
|    is_active         |         |    curator_id        |
+----------------------+         |    budget_amount     |
        |                        |    created_by        |
        v                        +----------------------+
+----------------------+                  |
| exhibition_gallery   |                  |
+----------------------+                  |
| PK id                |                  |
| FK venue_id          |                  |
|    name              |                  |
|    gallery_type      |                  |
|    floor_level       |                  |
|    square_meters     |                  |
|    has_climate_ctrl  |                  |
|    max_lux_level     |                  |
|    max_visitors      |                  |
+----------------------+                  |
                                          |
        +---------------------+-----------+-----------+
        |                     |                       |
        v                     v                       v
+----------------------+ +----------------------+ +----------------------+
| exhibition_section   | | exhibition_object    | | exhibition_storyline |
+----------------------+ +----------------------+ +----------------------+
| PK id                | | PK id                | | PK id                |
| FK exhibition_id     | | FK exhibition_id     | | FK exhibition_id     |
|    title             | | FK section_id        | |    title             |
|    subtitle          | | FK information_obj_id| |    slug              |
|    description       | |    sequence_order    | |    narrative_type    |
|    section_type      | |    display_position  | |    introduction      |
|    sequence_order    | |    status            | |    target_audience   |
|    gallery_name      | |    requires_loan     | |    is_primary        |
|    theme             | |    loan_id           | |    duration_minutes  |
|    target_temp_*     | |    insurance_value   | |    created_by        |
|    max_lux_level     | |    label_text        | +----------------------+
+----------------------+ |    installed_by      |           |
        |                |    installed_at      |           v
        |                +----------------------+ +----------------------+
        |                          |             | exhibition_storyline |
        |                          |             |        _stop         |
        |                          |             +----------------------+
        |                          |             | PK id                |
        +--------------------------+------------>| FK storyline_id      |
                                                 | FK exhibition_obj_id |
                                                 |    sequence_order    |
                                                 |    stop_number       |
                                                 |    narrative_text    |
                                                 |    audio_transcript  |
                                                 +----------------------+

+----------------------+         +----------------------+
| exhibition_event     |         | exhibition_checklist |
+----------------------+         +----------------------+
| PK id                |         | PK id                |
| FK exhibition_id     |         | FK exhibition_id     |
|    title             |         | FK template_id       |
|    event_type        |         |    name              |
|    event_date        |         |    checklist_type    |
|    start_time        |         |    due_date          |
|    end_time          |         |    status            |
|    max_attendees     |         |    assigned_to       |
|    requires_regist.  |         +----------------------+
|    is_free           |                  |
|    ticket_price      |                  v
|    presenter_name    |         +----------------------+
|    status            |         | exhibition_checklist |
+----------------------+         |        _item         |
                                 +----------------------+
+----------------------+         | PK id                |
| exhibition_status    |         | FK checklist_id      |
|      _history        |         |    name              |
+----------------------+         |    description       |
| PK id                |         |    is_required       |
| FK exhibition_id     |         |    is_completed      |
|    from_status       |         |    completed_at      |
|    to_status         |         |    completed_by      |
|    changed_by        |         +----------------------+
|    change_reason     |
|    created_at        |         +----------------------+
+----------------------+         | exhibition_checklist |
                                 |      _template       |
+----------------------+         +----------------------+
| exhibition_media     |         | PK id                |
+----------------------+         |    name              |
| PK id                |         |    checklist_type    |
| FK exhibition_id     |         |    items (JSON)      |
| FK section_id        |         |    is_default        |
|    media_type        |         +----------------------+
|    usage_type        |
|    file_path         |
|    title             |
|    is_primary        |
+----------------------+
```

### Core Tables

#### exhibition
Main exhibition records.

```sql
CREATE TABLE exhibition (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    subtitle VARCHAR(500),
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    theme TEXT,
    exhibition_type ENUM('permanent','temporary','traveling','online','pop_up') DEFAULT 'temporary',
    status ENUM('concept','planning','preparation','installation','open','closing','closed','archived','canceled') DEFAULT 'concept',

    -- Dates
    planning_start_date DATE,
    preparation_start_date DATE,
    installation_start_date DATE,
    opening_date DATE,
    closing_date DATE,
    actual_closing_date DATE,

    -- Venue
    venue_id BIGINT UNSIGNED,
    venue_name VARCHAR(255),
    venue_address TEXT,
    is_external_venue TINYINT(1) DEFAULT 0,

    -- Admission
    admission_fee DECIMAL(10,2),
    admission_currency VARCHAR(3) DEFAULT 'ZAR',
    is_free_admission TINYINT(1) DEFAULT 0,
    expected_visitors INT,
    actual_visitors INT,

    -- Budget & Insurance
    budget_amount DECIMAL(12,2),
    budget_currency VARCHAR(3) DEFAULT 'ZAR',
    actual_cost DECIMAL(12,2),
    total_insurance_value DECIMAL(15,2),
    insurance_policy_number VARCHAR(100),

    -- Team
    curator_id INT,
    curator_name VARCHAR(255),
    designer_name VARCHAR(255),
    organized_by VARCHAR(255),

    -- Metadata
    project_code VARCHAR(50),
    notes TEXT,
    internal_notes TEXT,
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_exhibition_status (status),
    INDEX idx_exhibition_type (exhibition_type),
    INDEX idx_exhibition_dates (opening_date, closing_date),
    INDEX idx_exhibition_venue (venue_id)
);
```

#### exhibition_object
Links information objects to exhibitions.

```sql
CREATE TABLE exhibition_object (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exhibition_id BIGINT UNSIGNED NOT NULL,
    section_id BIGINT UNSIGNED,
    information_object_id INT NOT NULL,
    sequence_order INT DEFAULT 0,
    display_position VARCHAR(100),

    -- Status
    status ENUM('proposed','confirmed','on_loan_request','installed','removed','returned') DEFAULT 'proposed',

    -- Loan
    requires_loan TINYINT(1) DEFAULT 0,
    loan_id BIGINT UNSIGNED,
    lender_institution VARCHAR(255),

    -- Display requirements
    display_case_required TINYINT(1) DEFAULT 0,
    mount_required TINYINT(1) DEFAULT 0,
    mount_description TEXT,
    special_lighting TINYINT(1) DEFAULT 0,
    lighting_notes TEXT,
    security_level ENUM('standard','enhanced','maximum') DEFAULT 'standard',

    -- Environmental
    climate_controlled TINYINT(1) DEFAULT 0,
    max_lux_level INT,
    uv_filtering_required TINYINT(1) DEFAULT 0,
    rotation_required TINYINT(1) DEFAULT 0,
    max_display_days INT,
    display_start_date DATE,
    display_end_date DATE,

    -- Condition reports
    pre_installation_condition_report_id BIGINT UNSIGNED,
    post_exhibition_condition_report_id BIGINT UNSIGNED,

    -- Insurance
    insurance_value DECIMAL(15,2),

    -- Labels
    label_text TEXT,
    label_credits TEXT,
    extended_label TEXT,
    audio_stop_number VARCHAR(20),

    -- Installation
    installation_notes TEXT,
    handling_notes TEXT,
    installed_by INT,
    installed_at TIMESTAMP,
    removed_by INT,
    removed_at TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (exhibition_id) REFERENCES exhibition(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES exhibition_section(id) ON DELETE SET NULL,
    INDEX idx_exobj_exhibition (exhibition_id),
    INDEX idx_exobj_section (section_id),
    INDEX idx_exobj_object (information_object_id),
    INDEX idx_exobj_status (status)
);
```

---

## Exhibition Types

| Type | Description |
|------|-------------|
| permanent | Long-term or indefinite display |
| temporary | Fixed duration exhibition |
| traveling | Moves between venues |
| online | Virtual/digital exhibition |
| pop_up | Short-term temporary display |

---

## Status Workflow

### States

| Status | Label | Color | Order | Description |
|--------|-------|-------|-------|-------------|
| concept | Concept | #9e9e9e | 1 | Initial concept development |
| planning | Planning | #2196f3 | 2 | Detailed planning phase |
| preparation | Preparation | #ff9800 | 3 | Physical/content preparation |
| installation | Installation | #9c27b0 | 4 | Physical installation |
| open | Open | #4caf50 | 5 | Exhibition open to public |
| closing | Closing | #ff5722 | 6 | Preparing for closure |
| closed | Closed | #795548 | 7 | Closed, deinstallation |
| archived | Archived | #607d8b | 8 | Final state |
| canceled | Canceled | #f44336 | 9 | Exhibition was canceled |

### Valid Transitions

```
concept       --> planning, canceled
planning      --> concept, preparation, canceled
preparation   --> planning, installation, canceled
installation  --> preparation, open, canceled
open          --> closing
closing       --> open (reopen), closed
closed        --> archived
archived      --> (final state)
canceled      --> concept (revive)
```

### State Machine Diagram

```
                              +----------+
                              | CANCELED |<------------------+
                              +----------+                   |
                                   ^                         |
                                   |                         |
+----------+    +----------+    +-------------+    +--------------+
| CONCEPT  |--->| PLANNING |--->| PREPARATION |--->| INSTALLATION |
+----------+    +----------+    +-------------+    +--------------+
     ^              |                  |                   |
     |              v                  v                   v
     |         (return)           (return)             (return)
     |                                                     |
     |                                                     v
     |         +----------+    +----------+    +--------+
     +---------|CANCELED  |    | CLOSING  |<---| OPEN   |
               +----------+    +----------+    +--------+
                                    |              ^
                                    v              |
                              +----------+    (reopen)
                              | CLOSED   |         |
                              +----------+---------+
                                    |
                                    v
                              +----------+
                              | ARCHIVED |
                              +----------+
```

---

## Service Methods

### ExhibitionService

```php
namespace ahgExhibitionPlugin\Services;

class ExhibitionService
{
    // Constants
    public const TYPES = [...];           // Exhibition types
    public const STATUSES = [...];        // Status definitions
    public const OBJECT_STATUSES = [...]; // Object status labels

    // Exhibition CRUD
    public function create(array $data, int $userId): int
    public function get(int $exhibitionId, bool $includeDetails = false): ?array
    public function getBySlug(string $slug): ?array
    public function update(int $exhibitionId, array $data, int $userId): bool
    public function transitionStatus(int $exhibitionId, string $newStatus, int $userId, ?string $reason = null): bool
    public function search(array $filters = [], int $limit = 50, int $offset = 0): array

    // Sections
    public function addSection(int $exhibitionId, array $data): int
    public function getSections(int $exhibitionId): array
    public function updateSection(int $sectionId, array $data): bool
    public function deleteSection(int $sectionId): bool
    public function reorderSections(int $exhibitionId, array $sectionOrder): bool

    // Objects
    public function addObject(int $exhibitionId, int $objectId, array $data = []): int
    public function getObjects(int $exhibitionId, ?int $sectionId = null): array
    public function updateObject(int $exhibitionObjectId, array $data): bool
    public function updateObjectStatus(int $exhibitionObjectId, string $status, int $userId, ?string $notes = null): bool
    public function removeObject(int $exhibitionObjectId): bool
    public function reorderObjects(array $order): void
    public function checkObjectAvailability(int $objectId, int $exhibitionId): array

    // Storylines
    public function createStoryline(int $exhibitionId, array $data, int $userId): int
    public function getStorylines(int $exhibitionId): array
    public function updateStoryline(int $storylineId, array $data): void
    public function deleteStoryline(int $storylineId): void
    public function getStorylineWithStops(int $storylineId): ?array
    public function addStorylineStop(int $storylineId, ?int $exhibitionObjectId, array $data): int
    public function updateStorylineStop(int $stopId, array $data): void
    public function deleteStorylineStop(int $stopId): void

    // Events
    public function createEvent(int $exhibitionId, array $data, int $userId): int
    public function getEvents(int $exhibitionId, bool $upcomingOnly = false): array
    public function updateEvent(int $eventId, array $data): void
    public function deleteEvent(int $eventId): void

    // Checklists
    public function createChecklistFromTemplate(int $exhibitionId, int $templateId, ?int $assignedTo = null): int
    public function getChecklists(int $exhibitionId): array
    public function completeChecklistItem(int $itemId, int $userId, ?string $notes = null): bool
    public function addChecklistItem(int $checklistId, array $data): int

    // Statistics & Reports
    public function getExhibitionStatistics(int $exhibitionId): array
    public function getStatistics(): array
    public function generateObjectList(int $exhibitionId): array

    // Helpers
    public function getTypes(): array
    public function getStatuses(): array
    public function getValidTransitions(string $status): array
    public function getChecklistTemplates(): array
}
```

---

## Routes

| Route | URL Pattern | Action |
|-------|-------------|--------|
| exhibition_index | /exhibitions | Browse exhibitions |
| exhibition_dashboard | /exhibition/dashboard | Dashboard view |
| exhibition_add | /exhibition/add | Create exhibition |
| exhibition_show | /exhibition/:id | View exhibition |
| exhibition_edit | /exhibition/:id/edit | Edit exhibition |
| exhibition_objects | /exhibition/:id/objects | Manage objects |
| exhibition_sections | /exhibition/:id/sections | Manage sections |
| exhibition_storylines | /exhibition/:id/storylines | Manage storylines |
| exhibition_storyline | /exhibition/:id/storyline/:storyline_id | Edit storyline |
| exhibition_events | /exhibition/:id/events | Manage events |
| exhibition_checklists | /exhibition/:id/checklists | Manage checklists |
| exhibition_object_list | /exhibition/:id/object-list | Object list report |
| exhibition_venues | /exhibition/venues | Manage venues |
| exhibition_view | /exhibition/:slug | View by slug |

---

## CLI Commands

```bash
# List exhibitions
php symfony museum:exhibition --list

# Show currently open exhibitions
php symfony museum:exhibition --current

# Show upcoming exhibitions
php symfony museum:exhibition --upcoming

# Show overdue exhibitions (past closing date but not closed)
php symfony museum:exhibition --overdue

# Show exhibition details
php symfony museum:exhibition --show=1
php symfony museum:exhibition --show=summer-landscapes-2026

# Generate object list (table, JSON, or CSV)
php symfony museum:exhibition --object-list=1
php symfony museum:exhibition --object-list=1 --format=csv
php symfony museum:exhibition --object-list=1 --format=json

# Show statistics
php symfony museum:exhibition --statistics

# Change exhibition status
php symfony museum:exhibition --exhibition-id=1 --status=planning

# Install database schema
php symfony museum:exhibition --install-schema

# Filter by year or type
php symfony museum:exhibition --list --year=2026
php symfony museum:exhibition --list --type=temporary
```

---

## Configuration

The plugin uses database tables for configuration. No settings are stored in `config.php`.

### Checklist Templates

Checklist templates are stored in `exhibition_checklist_template`:

```sql
INSERT INTO exhibition_checklist_template (name, checklist_type, items, is_default) VALUES
('Planning Checklist', 'planning', '[
    {"name": "Define exhibition theme", "required": true},
    {"name": "Identify target audience", "required": true},
    {"name": "Set preliminary budget", "required": true},
    {"name": "Initial object selection", "required": false}
]', 1);
```

---

## Object Availability Checking

The service checks for scheduling conflicts when adding objects:

```php
// Returns conflicts with other exhibitions and loans
$conflicts = $service->checkObjectAvailability($objectId, $exhibitionId);
// Returns: ['exhibitions' => [...], 'loans' => [...]]
```

This prevents double-booking objects across exhibitions and loan agreements.

---

## Integration Points

### Information Objects
Links to AtoM's `information_object` table via `exhibition_object.information_object_id`.

### Digital Objects
Retrieves thumbnails from `digital_object` table for display.

### Users
References `user` table for:
- `curator_id`
- `created_by`, `updated_by`
- `assigned_to` (checklists)
- `installed_by`, `removed_by` (objects)
- `completed_by` (checklist items)

### Loans (if ahgLoanPlugin enabled)
Can link to loan records via `exhibition_object.loan_id` for loaned objects.

---

## Templates

| Template | Purpose |
|----------|---------|
| indexSuccess.php | Browse exhibitions list |
| dashboardSuccess.php | Exhibition dashboard |
| showSuccess.php | Exhibition detail view |
| addSuccess.php | Create exhibition form |
| editSuccess.php | Edit exhibition form |
| objectsSuccess.php | Manage exhibition objects |
| sectionsSuccess.php | Manage sections |
| storylinesSuccess.php | Manage storylines |
| storylineSuccess.php | Edit single storyline with stops |
| eventsSuccess.php | Manage events |
| checklistsSuccess.php | Manage checklists |
| objectListSuccess.php | Object list report |
| _objectListCsv.php | CSV export partial |

---

## Event Hooks

The ExhibitionWorkflow defines transition triggers:

```php
'open_exhibition' => [
    'triggers' => ['send_opening_notification'],
],
'cancel_exhibition' => [
    'requires_comment' => true,
    'triggers' => ['send_cancellation_notification'],
],
```

---

## AJAX Endpoints

| Action | Method | Purpose |
|--------|--------|---------|
| executeTransition | POST | Change exhibition status |
| executeReorderObjects | POST | Drag-drop reorder objects |
| executeSearchObjects | GET | Search objects for adding |
| executeCompleteItem | POST | Complete checklist item |

---

## Statistics

### Exhibition Statistics

```php
$stats = $service->getExhibitionStatistics($exhibitionId);
// Returns:
// - object_count
// - objects_by_status
// - section_count
// - storyline_count
// - event_count
// - total_insurance_value
// - checklist_progress
```

### Global Statistics

```php
$stats = $service->getStatistics();
// Returns:
// - total_exhibitions
// - by_status
// - by_type
// - current_exhibitions
// - upcoming_exhibitions
// - total_objects_on_display
// - total_insurance_value
```

---

## Security

### Required Permissions
- View exhibitions: Authenticated user
- Create/Edit exhibitions: Editor role or higher
- Delete exhibitions: Administrator only
- Change status: Based on workflow permissions

### Access Control
The plugin uses AtoM's standard ACL system. No custom permissions are defined.

---

## Installation

```bash
# Enable the plugin
php bin/atom extension:enable ahgExhibitionPlugin

# Install database schema
php symfony museum:exhibition --install-schema

# Clear cache
php symfony cc
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Exhibition not showing | Check status filter, verify exhibition exists |
| Objects not appearing | Verify information_object_id is valid |
| Status transition fails | Check valid transitions for current status |
| Checklist not creating | Verify template exists and is valid JSON |
| Slug conflict | Slugs must be unique - change title or slug |

---

*Part of the AtoM AHG Framework*
