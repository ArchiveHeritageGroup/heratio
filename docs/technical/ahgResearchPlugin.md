# ahgResearchPlugin - Technical Documentation

**Version:** 2.0.0
**Category:** Public Services
**Dependencies:** atom-framework, ahgSecurityClearancePlugin

---

## Overview

Researcher registration, reading room booking, workspace management, and citation generation for archival research services.

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────────────────┐
│         research_researcher             │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK user_id INT                         │──────┐
│                                         │      │
│ -- PERSONAL --                          │      │
│    first_name VARCHAR                   │      │
│    last_name VARCHAR                    │      │
│    email VARCHAR                        │      │
│    phone VARCHAR                        │      │
│    institution VARCHAR                  │      │
│    position VARCHAR                     │      │
│    department VARCHAR                   │      │
│                                         │      │
│ -- IDENTIFICATION --                    │      │
│    id_type ENUM                         │      │
│    id_number VARCHAR                    │      │
│    id_verified TINYINT                  │      │
│    id_verified_by INT                   │      │
│    id_verified_at TIMESTAMP             │      │
│                                         │      │
│ -- RESEARCH --                          │      │
│    research_topic TEXT                  │      │
│    research_purpose ENUM                │      │
│    research_period VARCHAR              │      │
│    supervisor_name VARCHAR              │      │
│    supervisor_email VARCHAR             │      │
│    reference_letter VARCHAR             │      │
│                                         │      │
│ -- STATUS --                            │      │
│    status ENUM                          │      │
│    approved_by INT                      │      │
│    approved_at TIMESTAMP                │      │
│    card_number VARCHAR                  │      │
│    card_issued_at TIMESTAMP             │      │
│    card_expires_at TIMESTAMP            │      │
│                                         │      │
│ -- AGREEMENTS --                        │      │
│    terms_accepted TINYINT               │      │
│    terms_accepted_at TIMESTAMP          │      │
│    photo_consent TINYINT                │      │
│    publication_consent TINYINT          │      │
│                                         │      │
│    notes TEXT                           │      │
│    created_at TIMESTAMP                 │      │
│    updated_at TIMESTAMP                 │      │
└─────────────────────────────────────────┘      │
              │                                   │
              │ 1:N                               │
              ▼                                   │
┌─────────────────────────────────────────┐      │
│          research_booking               │      │
├─────────────────────────────────────────┤      │
│ PK id INT                              │      │
│ FK researcher_id INT                   │──────┤
│ FK reading_room_id INT                 │      │
│                                         │      │
│    booking_date DATE                    │      │
│    start_time TIME                      │      │
│    end_time TIME                        │      │
│    purpose TEXT                         │      │
│    materials_requested JSON             │      │
│    status ENUM                          │      │
│    checked_in_at TIMESTAMP              │      │
│    checked_out_at TIMESTAMP             │      │
│    desk_number VARCHAR                  │      │
│    notes TEXT                           │      │
│    cancelled_at TIMESTAMP               │      │
│    cancellation_reason TEXT             │      │
│    created_at TIMESTAMP                 │      │
│    updated_at TIMESTAMP                 │      │
└─────────────────────────────────────────┘      │
              │                                   │
              │ N:1                               │
              ▼                                   │
┌─────────────────────────────────────────┐      │
│         research_reading_room           │      │
├─────────────────────────────────────────┤      │
│ PK id INT                              │      │
│    name VARCHAR                         │      │
│    description TEXT                     │      │
│    capacity INT                         │      │
│    location VARCHAR                     │      │
│    operating_hours JSON                 │      │
│    rules TEXT                           │      │
│    amenities JSON                       │      │
│    is_active TINYINT                    │      │
│    created_at TIMESTAMP                 │      │
└─────────────────────────────────────────┘      │
                                                  │
┌─────────────────────────────────────────┐      │
│        research_workspace               │      │
├─────────────────────────────────────────┤      │
│ PK id INT                              │      │
│ FK researcher_id INT                   │──────┤
│    name VARCHAR                         │      │
│    description TEXT                     │      │
│    is_default TINYINT                   │      │
│    saved_searches JSON                  │      │
│    saved_records JSON                   │      │
│    notes TEXT                           │      │
│    created_at TIMESTAMP                 │      │
│    updated_at TIMESTAMP                 │      │
└─────────────────────────────────────────┘      │
                                                  │
┌─────────────────────────────────────────┐      │
│        research_citation_log            │      │
├─────────────────────────────────────────┤      │
│ PK id INT                              │      │
│ FK researcher_id INT                   │──────┘
│ FK object_id INT                       │
│    citation_style VARCHAR               │
│    citation_text TEXT                   │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│        research_annotation              │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK researcher_id INT                   │
│ FK object_id INT                       │
│    annotation_type ENUM                 │
│    title VARCHAR                        │
│    content TEXT                         │
│    target_selector TEXT                 │
│    tags VARCHAR                         │
│    is_private TINYINT                   │
│    created_at TIMESTAMP                 │
│    updated_at TIMESTAMP                 │
└─────────────────────────────────────────┘
```

### Reading Room Operations Schema (v2.0)

```
┌─────────────────────────────────────────┐
│      research_reading_room_seat         │
├─────────────────────────────────────────┤
│ PK id BIGINT                           │
│ FK room_id BIGINT                      │
│    seat_number VARCHAR(50)              │
│    seat_label VARCHAR(100)              │
│    seat_type ENUM                       │
│    zone VARCHAR(100)                    │
│    has_power TINYINT                    │
│    has_lamp TINYINT                     │
│    has_computer TINYINT                 │
│    has_magnifier TINYINT                │
│    is_active TINYINT                    │
│    notes TEXT                           │
│    created_at, updated_at               │
└─────────────────────────────────────────┘
              │
              │ 1:N
              ▼
┌─────────────────────────────────────────┐
│       research_seat_assignment          │
├─────────────────────────────────────────┤
│ PK id BIGINT                           │
│ FK seat_id BIGINT                      │
│ FK booking_id BIGINT (nullable)        │
│ FK walk_in_id BIGINT (nullable)        │
│    assigned_at TIMESTAMP                │
│    released_at TIMESTAMP                │
│    assigned_by BIGINT                   │
│    notes TEXT                           │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│         research_equipment              │
├─────────────────────────────────────────┤
│ PK id BIGINT                           │
│ FK room_id BIGINT                      │
│    name VARCHAR(255)                    │
│    code VARCHAR(50)                     │
│    equipment_type ENUM                  │
│    brand VARCHAR(100)                   │
│    model VARCHAR(100)                   │
│    serial_number VARCHAR(100)           │
│    location VARCHAR(255)                │
│    description TEXT                     │
│    condition_status ENUM                │
│    is_available TINYINT                 │
│    requires_training TINYINT            │
│    max_booking_hours INT                │
│    last_maintenance_date DATE           │
│    next_maintenance_date DATE           │
│    maintenance_notes TEXT               │
│    created_at, updated_at               │
└─────────────────────────────────────────┘
              │
              │ 1:N
              ▼
┌─────────────────────────────────────────┐
│      research_equipment_booking         │
├─────────────────────────────────────────┤
│ PK id BIGINT                           │
│ FK equipment_id BIGINT                 │
│ FK room_booking_id BIGINT              │
│    status ENUM                          │
│    checked_out_at TIMESTAMP             │
│    checked_out_by BIGINT                │
│    returned_at TIMESTAMP                │
│    returned_by BIGINT                   │
│    condition_on_return ENUM             │
│    notes TEXT                           │
│    created_at                           │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│        research_request_queue           │
├─────────────────────────────────────────┤
│ PK id BIGINT                           │
│    code VARCHAR(50) UNIQUE              │
│    name VARCHAR(100)                    │
│    description TEXT                     │
│    color VARCHAR(20)                    │
│    icon VARCHAR(50)                     │
│    sort_order INT                       │
│    is_active TINYINT                    │
│    auto_advance_to BIGINT               │
│    auto_advance_after_hours INT         │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│      research_retrieval_schedule        │
├─────────────────────────────────────────┤
│ PK id BIGINT                           │
│ FK room_id BIGINT                      │
│    schedule_name VARCHAR(100)           │
│    retrieval_time TIME                  │
│    days_of_week VARCHAR(50)             │
│    cutoff_minutes_before INT            │
│    is_active TINYINT                    │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│       research_walk_in_visitor          │
├─────────────────────────────────────────┤
│ PK id BIGINT                           │
│ FK room_id BIGINT                      │
│    first_name VARCHAR(100)              │
│    last_name VARCHAR(100)               │
│    email VARCHAR(255)                   │
│    phone VARCHAR(50)                    │
│    organization VARCHAR(255)            │
│    id_type ENUM                         │
│    id_number VARCHAR(100)               │
│    purpose TEXT                         │
│    research_topic TEXT                  │
│    visit_date DATE                      │
│    check_in_time TIMESTAMP              │
│    check_out_time TIMESTAMP             │
│    rules_acknowledged TINYINT           │
│    registered_by BIGINT                 │
│    notes TEXT                           │
│    converted_to_researcher_id BIGINT    │
│    created_at                           │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│        research_print_template          │
├─────────────────────────────────────────┤
│ PK id BIGINT                           │
│    code VARCHAR(50)                     │
│    name VARCHAR(100)                    │
│    template_type ENUM                   │
│    html_template TEXT                   │
│    css_styles TEXT                      │
│    paper_size VARCHAR(20)               │
│    orientation ENUM                     │
│    is_default TINYINT                   │
│    is_active TINYINT                    │
│    created_at, updated_at               │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│         research_activity               │
├─────────────────────────────────────────┤
│ PK id BIGINT                           │
│ FK booking_id BIGINT (nullable)        │
│ FK walk_in_id BIGINT (nullable)        │
│ FK room_id BIGINT                      │
│    activity_type ENUM                   │
│    activity_date DATE                   │
│    start_time TIME                      │
│    end_time TIME                        │
│    description TEXT                     │
│    recorded_by BIGINT                   │
│    created_at                           │
└─────────────────────────────────────────┘
```

### Default Queue Definitions

| Code | Name | Color | Icon |
|------|------|-------|------|
| new | New Requests | #3498db | inbox |
| rush | Rush Priority | #e74c3c | bolt |
| retrieval | Ready for Retrieval | #f39c12 | dolly |
| transit | In Transit | #9b59b6 | truck |
| delivery | Ready for Delivery | #27ae60 | hand-holding |
| curatorial | Curatorial Review | #e67e22 | glasses |
| return | Pending Return | #1abc9c | undo |

---

## Research Purpose Types

| Purpose | Description |
|---------|-------------|
| academic | University/college research |
| professional | Work-related research |
| personal | Genealogy, personal interest |
| journalistic | Media/press research |
| legal | Legal proceedings |
| government | Official government use |

---

## Booking Statuses

| Status | Description |
|--------|-------------|
| pending | Awaiting approval |
| approved | Confirmed booking |
| checked_in | Researcher arrived |
| completed | Visit finished |
| cancelled | Booking cancelled |
| no_show | Researcher didn't arrive |

---

## Service Methods

### ResearcherService

```php
namespace ahgResearchPlugin\Service;

class ResearcherService
{
    // Registration
    public function register(array $data): int
    public function updateProfile(int $id, array $data): bool
    public function getResearcher(int $id): ?array
    public function getResearcherByUser(int $userId): ?array
    public function verifyIdentity(int $id, int $verifiedBy): bool
    public function approveResearcher(int $id, int $approvedBy): bool
    public function issueCard(int $id, string $cardNumber, DateTime $expires): bool
    
    // Bookings
    public function createBooking(int $researcherId, array $data): int
    public function updateBooking(int $id, array $data): bool
    public function cancelBooking(int $id, string $reason): bool
    public function checkIn(int $bookingId, string $deskNumber): bool
    public function checkOut(int $bookingId): bool
    public function getBookings(int $researcherId): Collection
    public function getBookingsForDate(DateTime $date, int $roomId): Collection
    public function getAvailableSlots(DateTime $date, int $roomId): array
    
    // Workspace
    public function createWorkspace(int $researcherId, array $data): int
    public function addToWorkspace(int $workspaceId, int $objectId): bool
    public function removeFromWorkspace(int $workspaceId, int $objectId): bool
    public function getWorkspaces(int $researcherId): Collection
    
    // Citations
    public function generateCitation(int $objectId, string $style): string
    public function logCitation(int $researcherId, int $objectId, string $style, string $text): int
    public function getCitationHistory(int $researcherId): Collection
    
    // Annotations
    public function createAnnotation(int $researcherId, int $objectId, array $data): int
    public function getAnnotations(int $researcherId, ?int $objectId = null): Collection
}
```

### SeatService (v2.0)

```php
namespace ahgResearchPlugin\Service;

class SeatService
{
    // Seat Management
    public function getSeatsForRoom(int $roomId): Collection
    public function getSeat(int $seatId): ?object
    public function createSeat(int $roomId, array $data): int
    public function updateSeat(int $seatId, array $data): bool
    public function deleteSeat(int $seatId): bool
    public function bulkCreateSeats(int $roomId, string $pattern, string $type, ?string $zone): array

    // Seat Assignment
    public function getAvailableSeats(int $roomId, ?string $type = null): Collection
    public function assignSeat(int $seatId, ?int $bookingId, ?int $walkInId, int $assignedBy): int
    public function releaseSeat(int $assignmentId): bool
    public function markSeatOccupied(int $seatId): bool
    public function getSeatAssignment(int $seatId): ?object
    public function autoAssignSeat(int $roomId, int $bookingId): ?int

    // Occupancy
    public function getRoomOccupancy(int $roomId): array
    public function getSeatMapData(int $roomId): array
    public function getSeatStatistics(int $roomId, ?DateTime $from, ?DateTime $to): array
}
```

### EquipmentService (v2.0)

```php
namespace ahgResearchPlugin\Service;

class EquipmentService
{
    // Equipment Management
    public function getEquipmentForRoom(int $roomId): Collection
    public function getEquipment(int $equipmentId): ?object
    public function createEquipment(int $roomId, array $data): int
    public function updateEquipment(int $equipmentId, array $data): bool
    public function deleteEquipment(int $equipmentId): bool
    public function getEquipmentTypeCounts(int $roomId): Collection

    // Equipment Booking
    public function getAvailableEquipment(int $roomId, ?string $type = null): Collection
    public function createBooking(int $equipmentId, int $roomBookingId): int
    public function getBooking(int $bookingId): ?object
    public function getResearcherBookings(int $roomBookingId): Collection
    public function getBookingsForRoomBooking(int $roomBookingId): Collection
    public function checkOut(int $bookingId, int $userId): bool
    public function returnEquipment(int $bookingId, int $userId, ?string $condition): bool
    public function cancelBooking(int $bookingId): bool
    public function markNoShow(int $bookingId): bool

    // Maintenance
    public function getEquipmentNeedingMaintenance(): Collection
    public function logMaintenance(int $equipmentId, string $description, string $newCondition, ?DateTime $nextDate): bool
    public function getUsageStatistics(int $roomId, ?DateTime $from, ?DateTime $to): array
    public function getDailySchedule(int $roomId, DateTime $date): Collection
}
```

### RetrievalService (v2.0)

```php
namespace ahgResearchPlugin\Service;

class RetrievalService
{
    // Queue Management
    public function getQueues(): Collection
    public function getQueue(int $queueId): ?object
    public function getQueueByCode(string $code): ?object
    public function getQueueRequests(int $queueId): Collection
    public function getQueueCounts(): array
    public function moveToQueue(int $requestId, int $queueId, ?string $notes): bool
    public function batchUpdateStatus(array $requestIds, string $status, ?string $notes): int

    // Retrieval Schedules
    public function getRetrievalSchedules(int $roomId): Collection
    public function createRetrievalSchedule(int $roomId, array $data): int
    public function getNextRetrievalRun(int $roomId): ?DateTime
    public function getRequestsForRetrieval(int $roomId): Collection
    public function updateRequestStatus(int $requestId, string $status, ?string $notes): bool

    // Call Slips
    public function getPrintTemplate(string $code): ?object
    public function getCallSlipData(int $requestId): array
    public function renderCallSlip(int $requestId): string
    public function renderBatchCallSlips(array $requestIds): string
    public function markCallSlipPrinted(int $requestId, int $userId): bool

    // Walk-In Visitors
    public function registerWalkIn(int $roomId, array $data, int $registeredBy): int
    public function checkOutWalkIn(int $walkInId): bool
    public function getCurrentWalkIns(int $roomId): Collection
    public function convertWalkInToResearcher(int $walkInId): int

    // Statistics
    public function getRetrievalStatistics(?DateTime $from, ?DateTime $to): array
}
```

---

## Equipment Types

| Type | Description |
|------|-------------|
| microfilm_reader | Microfilm viewing equipment |
| microfiche_reader | Microfiche viewing equipment |
| scanner | Document/photo scanner |
| computer | Computer workstation |
| magnifier | Magnifying glass/lamp |
| book_cradle | Support for fragile books |
| light_box | Backlit viewing surface |
| camera_stand | For photography |
| gloves | Cotton gloves for handling |
| weights | Page weights |

## Equipment Condition Statuses

| Status | Description |
|--------|-------------|
| excellent | Perfect working condition |
| good | Normal wear, fully functional |
| fair | Minor issues, still usable |
| needs_repair | Requires maintenance |
| out_of_service | Not available for use |

## Seat Types

| Type | Description |
|------|-------------|
| standard | Regular desk/table |
| accessible | Wheelchair accessible |
| computer | With computer workstation |
| microfilm | Microfilm reader station |
| oversize | For large format materials |
| quiet | Silent study zone |
| group | Collaborative/group table |

---

## Citation Styles Supported

| Style | Format |
|-------|--------|
| APA | American Psychological Association |
| MLA | Modern Language Association |
| Chicago | Chicago Manual of Style |
| Harvard | Harvard Referencing |
| Turabian | Turabian Style |
| MHRA | Modern Humanities Research Association |

---

## Routes (v2.0)

### Reading Room Operations Routes

| Route Name | URL | Action |
|------------|-----|--------|
| research_retrieval_queue | /research/retrieval-queue | retrievalQueue |
| research_print_call_slips | /research/call-slips/print | printCallSlips |
| research_seats | /research/seats | seats |
| research_seat_assign | /research/seats/assign | assignSeat |
| research_seat_map | /research/seats/map | seatMap |
| research_equipment | /research/equipment | equipment |
| research_equipment_book | /research/equipment/book | bookEquipment |
| research_walk_in | /research/walk-in | walkIn |
| research_activities | /research/activities | activities |
| research_activity_view | /research/activities/:id | viewActivity |

---

## Migration File

The Reading Room features require the migration:
```
database/migrations/2025_01_31_reading_room_enhancements.sql
```

This migration:
- Creates 11 new tables for seats, equipment, queues, walk-ins, activities
- Adds columns to research_researcher (photo_path, card fields)
- Adds columns to research_booking (is_walk_in, seat_id, rules fields)
- Adds columns to research_material_request (location, queue, call slip fields)
- Inserts default queue definitions and print templates

---

*Last updated: February 2026*
*Part of the AtoM AHG Framework v2.0*
