# ahgEmbargoPlugin - Technical Documentation

**Version:** 1.0.0  
**Category:** Access Control  
**Dependencies:** atom-framework

---

## Overview

Time-based access restrictions for archival materials with automatic expiration, notification system, and integration with AtoM's ACL.

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────────────────┐
│              embargo                    │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK object_id INT                       │──────┐
│    object_type VARCHAR                  │      │
│                                         │      │
│ -- EMBARGO DETAILS --                   │      │
│    embargo_type ENUM                    │      │
│    start_date DATE                      │      │
│    end_date DATE                        │      │
│    reason ENUM                          │      │
│    reason_detail TEXT                   │      │
│                                         │      │
│ -- RESTRICTIONS --                      │      │
│    restrict_discovery TINYINT           │      │
│    restrict_metadata TINYINT            │      │
│    restrict_digital_object TINYINT      │      │
│    restrict_download TINYINT            │      │
│                                         │      │
│ -- STATUS --                            │      │
│    status ENUM                          │      │
│    lifted_at TIMESTAMP                  │      │
│    lifted_by INT                        │      │
│    lift_reason TEXT                     │      │
│                                         │      │
│ -- AUDIT --                             │      │
│    created_by INT                       │      │
│    approved_by INT                      │      │
│    approved_at TIMESTAMP                │      │
│                                         │      │
│    notes TEXT                           │      │
│    created_at TIMESTAMP                 │      │
│    updated_at TIMESTAMP                 │      │
└─────────────────────────────────────────┘      │
              │                                   │
              │ 1:N                               │
              ▼                                   │
┌─────────────────────────────────────────┐      │
│         embargo_exception               │      │
├─────────────────────────────────────────┤      │
│ PK id INT                              │      │
│ FK embargo_id INT                      │──────┤
│ FK user_id INT                         │      │
│ FK group_id INT                        │      │
│    exception_type ENUM                  │      │
│    granted_by INT                       │      │
│    granted_at TIMESTAMP                 │      │
│    expires_at TIMESTAMP                 │      │
│    reason TEXT                          │      │
│    created_at TIMESTAMP                 │      │
└─────────────────────────────────────────┘      │
                                                  │
┌─────────────────────────────────────────┐      │
│         embargo_notification            │      │
├─────────────────────────────────────────┤      │
│ PK id INT                              │      │
│ FK embargo_id INT                      │──────┘
│    notification_type ENUM               │
│    days_before INT                      │
│    recipient_email VARCHAR              │
│    sent TINYINT                         │
│    sent_at TIMESTAMP                    │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘
```

---

## Embargo Types

| Type | Description |
|------|-------------|
| time_based | Fixed end date |
| event_based | Until specific event |
| permanent | Requires manual lift |
| rolling | Extends from creation |

---

## Embargo Reasons

| Reason | Description |
|--------|-------------|
| privacy | Personal data protection |
| security | Security concerns |
| legal | Legal restrictions |
| donor | Donor agreement |
| cultural | Cultural sensitivity |
| commercial | Commercial interests |
| government | Government restriction |

---

## Service Methods

### EmbargoService

```php
namespace ahgEmbargoPlugin\Service;

class EmbargoService
{
    // Embargoes
    public function createEmbargo(int $objectId, string $type, array $data): int
    public function updateEmbargo(int $id, array $data): bool
    public function liftEmbargo(int $id, int $userId, string $reason): bool
    public function extendEmbargo(int $id, DateTime $newEndDate, string $reason): bool
    public function getEmbargo(int $id): ?array
    public function getObjectEmbargo(int $objectId): ?array
    public function isEmbargoed(int $objectId): bool
    
    // Exceptions
    public function grantException(int $embargoId, int $userId, array $data): int
    public function revokeException(int $exceptionId): bool
    public function hasException(int $embargoId, int $userId): bool
    
    // Notifications
    public function scheduleNotification(int $embargoId, array $data): int
    public function sendDueNotifications(): int
    public function getExpiringEmbargoes(int $days = 30): Collection
    
    // Bulk
    public function bulkApplyEmbargo(array $objectIds, array $data): int
    public function processExpired(): int
    
    // Reports
    public function getEmbargoStats(): array
    public function getEmbargoedObjects(array $filters): Collection
}
```

---

## ACL Integration

```php
// Embargo check integrated into ACL
QubitAcl::checkEmbargo($object, $user)
{
    $embargo = EmbargoService::getObjectEmbargo($object->id);
    
    if (!$embargo || $embargo['status'] !== 'active') {
        return true; // No active embargo
    }
    
    // Check if user has exception
    if (EmbargoService::hasException($embargo['id'], $user->id)) {
        return true;
    }
    
    // Check restriction levels
    $action = $this->getRequestedAction();
    switch ($action) {
        case 'view_metadata':
            return !$embargo['restrict_metadata'];
        case 'view_digital_object':
            return !$embargo['restrict_digital_object'];
        case 'download':
            return !$embargo['restrict_download'];
    }
    
    return false;
}
```

---

*Part of the AtoM AHG Framework*
