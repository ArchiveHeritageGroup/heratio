# ahgAccessRequestPlugin - Technical Documentation

**Version:** 1.1.6
**Category:** Rights Management
**Dependencies:** atom-framework, ahgCorePlugin, ahgSecurityClearancePlugin

---

## Overview

The Access Request Plugin provides a comprehensive workflow system for managing researcher access requests to restricted archival materials. It supports two primary request types: security clearance upgrades and object-level access grants. The plugin integrates with the Security Clearance system and includes email notifications, audit logging, and approver management.

---

## Architecture

```
+---------------------------------------------------------------------+
|                      ahgAccessRequestPlugin                         |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |                    Request Types                              |  |
|  |  - Clearance Upgrade (user clearance level)                   |  |
|  |  - Object Access (information_object, repository, actor)      |  |
|  |  - Researcher Renewal                                         |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                   AccessRequestService                        |  |
|  |  - Request creation and validation                            |  |
|  |  - Approval/denial workflow                                   |  |
|  |  - Object access grant management                             |  |
|  |  - Email notifications                                        |  |
|  |  - Audit logging                                              |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                    Database Tables                            |  |
|  |  - access_request (main request record)                       |  |
|  |  - access_request_scope (object targets)                      |  |
|  |  - access_request_log (audit trail)                           |  |
|  |  - access_request_approver (approver configuration)           |  |
|  |  - object_access_grant (granted permissions)                  |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |              SecurityClearanceService                         |  |
|  |  (Integration for clearance upgrades)                         |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+---------------------------------------+       +----------------------------------+
|           access_request              |       |     access_request_scope         |
+---------------------------------------+       +----------------------------------+
| PK id INT UNSIGNED                    |<---+  | PK id INT UNSIGNED               |
|    request_type ENUM                  |    |  | FK request_id INT UNSIGNED       |
|    scope_type ENUM                    |    +--+    object_type ENUM              |
| FK user_id INT UNSIGNED               |       |    object_id INT UNSIGNED        |
| FK requested_classification_id        |       |    include_descendants TINYINT   |
| FK current_classification_id          |       |    object_title VARCHAR(500)     |
|    reason TEXT                        |       |    created_at TIMESTAMP          |
|    justification TEXT                 |       +----------------------------------+
|    urgency ENUM                       |
|    status ENUM                        |       +----------------------------------+
| FK reviewed_by INT UNSIGNED           |       |     access_request_log           |
|    reviewed_at DATETIME               |       +----------------------------------+
|    review_notes TEXT                  |       | PK id INT UNSIGNED               |
|    expires_at DATETIME                |<------+ FK request_id INT UNSIGNED       |
|    created_at TIMESTAMP               |       |    action ENUM                   |
|    updated_at TIMESTAMP               |       | FK actor_id INT UNSIGNED         |
+---------------------------------------+       |    details TEXT                  |
         |                                      |    ip_address VARCHAR(45)        |
         |                                      |    created_at TIMESTAMP          |
         v                                      +----------------------------------+
+---------------------------------------+
|     access_request_approver           |       +----------------------------------+
+---------------------------------------+       |      object_access_grant         |
| PK id INT UNSIGNED                    |       +----------------------------------+
| FK user_id INT UNSIGNED (UNIQUE)      |       | PK id INT UNSIGNED               |
|    min_classification_level INT       |       | FK user_id INT UNSIGNED          |
|    max_classification_level INT       |       | FK request_id INT UNSIGNED       |
|    email_notifications TINYINT        |       |    object_type ENUM              |
|    active TINYINT                     |       |    object_id INT UNSIGNED        |
|    created_at TIMESTAMP               |       |    include_descendants TINYINT   |
+---------------------------------------+       |    access_level VARCHAR(50)      |
                                                | FK granted_by INT UNSIGNED       |
                                                |    granted_at DATETIME           |
                                                |    expires_at DATETIME           |
                                                |    notes TEXT                    |
                                                |    active TINYINT                |
                                                |    revoked_at DATETIME           |
                                                | FK revoked_by INT UNSIGNED       |
                                                +----------------------------------+
```

### Table: access_request

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED PK | Primary key |
| request_type | ENUM | 'clearance', 'object', 'repository', 'authority', 'researcher' |
| scope_type | ENUM | 'single', 'with_children', 'collection', 'repository_all', 'renewal' |
| user_id | INT UNSIGNED FK | Requesting user |
| requested_classification_id | INT UNSIGNED FK | For clearance requests |
| current_classification_id | INT UNSIGNED FK | User's current clearance |
| reason | TEXT | Request reason (required) |
| justification | TEXT | Business justification (optional) |
| urgency | ENUM | 'low', 'normal', 'high', 'critical' |
| status | ENUM | 'pending', 'approved', 'denied', 'cancelled', 'expired' |
| reviewed_by | INT UNSIGNED FK | Approver user ID |
| reviewed_at | DATETIME | Review timestamp |
| review_notes | TEXT | Approver notes |
| expires_at | DATETIME | Access expiration |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

### Table: access_request_scope

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED PK | Primary key |
| request_id | INT UNSIGNED FK | Parent request |
| object_type | ENUM | 'information_object', 'repository', 'actor' |
| object_id | INT UNSIGNED | Target object ID |
| include_descendants | TINYINT | Include child records |
| object_title | VARCHAR(500) | Cached object title |
| created_at | TIMESTAMP | Creation timestamp |

### Table: access_request_log

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED PK | Primary key |
| request_id | INT UNSIGNED FK | Parent request |
| action | ENUM | 'created', 'updated', 'approved', 'denied', 'cancelled', 'expired', 'escalated' |
| actor_id | INT UNSIGNED FK | User who performed action |
| details | TEXT | Action details |
| ip_address | VARCHAR(45) | Client IP address |
| created_at | TIMESTAMP | Action timestamp |

### Table: access_request_approver

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED PK | Primary key |
| user_id | INT UNSIGNED FK | Approver user (unique) |
| min_classification_level | INT | Minimum level can approve |
| max_classification_level | INT | Maximum level can approve |
| email_notifications | TINYINT | Receive email alerts |
| active | TINYINT | Approver is active |
| created_at | TIMESTAMP | Creation timestamp |

### Table: object_access_grant

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED PK | Primary key |
| user_id | INT UNSIGNED FK | Granted user |
| request_id | INT UNSIGNED FK | Source request (optional) |
| object_type | ENUM | 'information_object', 'repository', 'actor' |
| object_id | INT UNSIGNED | Granted object ID |
| include_descendants | TINYINT | Grant includes children |
| access_level | VARCHAR(50) | 'view', 'download', 'edit' |
| granted_by | INT UNSIGNED FK | Approver who granted |
| granted_at | DATETIME | Grant timestamp |
| expires_at | DATETIME | Expiration (null = never) |
| notes | TEXT | Grant notes |
| active | TINYINT | Grant is active |
| revoked_at | DATETIME | Revocation timestamp |
| revoked_by | INT UNSIGNED FK | User who revoked |

---

## Request Workflow

### State Machine

```
                          +-------------+
                          |   PENDING   |
                          +-------------+
                                |
            +-------------------+-------------------+
            |                   |                   |
            v                   v                   v
     +------------+      +------------+      +------------+
     |  APPROVED  |      |   DENIED   |      | CANCELLED  |
     +------------+      +------------+      +------------+
            |
            v
     +------------+
     |  EXPIRED   |
     +------------+
```

### Workflow Steps

1. **Request Creation**
   - User submits request via form
   - System validates and creates `access_request` record
   - For object requests, creates `access_request_scope` entries
   - Logs 'created' action
   - Sends email to approvers

2. **Review Process**
   - Approver views pending requests (sorted by urgency)
   - Reviews request details and justification
   - Makes approval decision

3. **Approval**
   - Updates request status to 'approved'
   - For clearance requests: grants clearance via SecurityClearanceService
   - For object requests: creates `object_access_grant` entries
   - Logs 'approved' action
   - Notifies user via email

4. **Denial**
   - Updates request status to 'denied'
   - Requires denial reason
   - Logs 'denied' action
   - Notifies user via email

5. **Cancellation**
   - User can cancel own pending requests
   - Updates status to 'cancelled'
   - Logs 'cancelled' action

---

## Service Methods

### AccessRequestService

```php
namespace AtomExtensions\Services;

class AccessRequestService
{
    // Request Creation
    public static function createClearanceRequest(
        int $userId,
        int $requestedClassificationId,
        string $reason,
        ?string $justification = null,
        string $urgency = 'normal'
    ): ?int

    public static function createObjectAccessRequest(
        int $userId,
        array $scopes,
        string $reason,
        ?string $justification = null,
        string $urgency = 'normal',
        string $accessLevel = 'view'
    ): ?int

    public static function createRequest(int $userId, array $data): ?int

    // Request Actions
    public static function approveRequest(
        int $requestId,
        int $approverId,
        ?string $notes = null,
        ?string $expiresAt = null
    ): bool

    public static function denyRequest(
        int $requestId,
        int $approverId,
        ?string $notes = null
    ): bool

    public static function cancelRequest(int $requestId, int $userId): bool

    // Object Access
    public static function grantObjectAccess(
        int $userId,
        string $objectType,
        int $objectId,
        bool $includeDescendants,
        int $grantedBy,
        ?int $requestId = null,
        ?string $expiresAt = null,
        ?string $notes = null,
        string $accessLevel = 'view'
    ): ?int

    public static function hasObjectAccess(
        int $userId,
        string $objectType,
        int $objectId
    ): bool

    public static function getUserAccessGrants(int $userId): array

    public static function revokeObjectAccess(int $grantId, int $revokedBy): bool

    // Request Queries
    public static function getRequest(int $requestId): ?object
    public static function getPendingRequests(int $approverId): array
    public static function getUserRequests(int $userId): array
    public static function getRequestLog(int $requestId): array
    public static function hasPendingRequestForObject(
        int $userId,
        string $objectType,
        int $objectId
    ): bool

    // Approver Management
    public static function isApprover(int $userId): bool
    public static function canApprove(int $userId, ?int $classificationId): bool
    public static function getApprovers(): array
    public static function setApprover(
        int $userId,
        int $minLevel = 0,
        int $maxLevel = 5,
        bool $emailNotifications = true
    ): bool
    public static function removeApprover(int $userId): bool

    // Utility
    public static function getObjectTitle(string $objectType, int $objectId): ?string
    public static function getObjectPath(string $objectType, int $objectId): array
    public static function countDescendants(string $objectType, int $objectId): int
    public static function getStats(): array
}
```

---

## Routes

### User Routes

| Route Name | URL | Action | Description |
|------------|-----|--------|-------------|
| access_request_new | /security/request-access | new | Clearance request form |
| access_request_create | /security/request-access/create | create | Submit clearance request |
| access_request_object | /security/request-object | requestObject | Object access request form |
| access_request_object_create | /security/request-object/create | createObjectRequest | Submit object request |
| access_request_my | /security/my-requests | myRequests | User's request history |
| access_request_cancel | /security/request/:id/cancel | cancel | Cancel pending request |

### Approver Routes

| Route Name | URL | Action | Description |
|------------|-----|--------|-------------|
| access_request_pending | /security/access-requests | pending | View pending requests |
| access_request_view | /security/request/:id | view | View request details |
| access_request_approve | /security/request/:id/approve | approve | Approve request |
| access_request_deny | /security/request/:id/deny | deny | Deny request |

### Admin Routes

| Route Name | URL | Action | Description |
|------------|-----|--------|-------------|
| access_request_approvers | /security/approvers | approvers | Manage approvers |
| access_request_add_approver | /security/approvers/add | addApprover | Add new approver |
| access_request_remove_approver | /security/approvers/:id/remove | removeApprover | Remove approver |

---

## Controller Actions

### accessRequestActions

| Action | HTTP Method | Authentication | Description |
|--------|-------------|----------------|-------------|
| executeNew | GET | Required | Display clearance request form |
| executeRequestObject | GET | Required | Display object access request form |
| executeCreate | POST | Required | Process clearance request submission |
| executeCreateObjectRequest | POST | Required | Process object request submission |
| executeMyRequests | GET | Required | Display user's request history |
| executeCancel | GET | Required | Cancel user's pending request |
| executePending | GET | Approver | Display pending requests queue |
| executeView | GET | Owner/Approver | Display request details |
| executeApprove | POST | Approver | Approve pending request |
| executeDeny | POST | Approver | Deny pending request |
| executeApprovers | GET | Administrator | Display approver management |
| executeAddApprover | POST | Administrator | Add new approver |
| executeRemoveApprover | GET | Administrator | Remove approver |

---

## Email Notifications

### Notification Types

| Event | Recipients | Subject |
|-------|------------|---------|
| Request Created | Active Approvers | "New Access Request - [Type/Level]" |
| Request Approved | Requester | "Access Request Approved" |
| Request Denied | Requester | "Access Request Denied" |

### Email Templates

**Approver Notification:**
- Requester details (username, email)
- Request type and urgency
- Requested access (classification or objects)
- Reason summary
- Link to review request

**User Notification:**
- Status (approved/denied)
- Review notes (if provided)
- Link to view requests

### Email Configuration

Emails are sent using PHP's native `mail()` function with:
- HTML content type
- Site title from settings
- From address: `noreply@theahg.co.za`

---

## Access Control Logic

### Object Access Checking

The `hasObjectAccess()` method checks access in this order:

1. **Direct Grant** - User has explicit grant for the object
2. **Ancestor Grant** - User has grant for an ancestor with `include_descendants=1`
3. **Repository Grant** - User has grant for the object's repository with `include_descendants=1`

```php
// Check hierarchy for information_object
if ($objectType === 'information_object') {
    // Get object's nested set bounds (lft, rgt)
    // Find ancestors where lft < obj.lft AND rgt > obj.rgt
    // Check for grants on ancestors with include_descendants
    // Also check repository grants
}
```

### Approver Authorization

- **Administrators**: Always authorized as approvers (group_id = 100)
- **Designated Approvers**: Listed in `access_request_approver` with `active=1`
- **Classification Range**: Approvers can only approve within their min/max level range
- **Clearance Requirement**: Approver must have clearance >= requested classification level

---

## Logging

### Audit Log (access_request_log)

All actions are logged with:
- Request ID
- Action type
- Actor (user who performed action)
- Details/notes
- IP address
- Timestamp

### File Logging

Uses Monolog with RotatingFileHandler:
- Log file: `/var/log/atom/access_request.log`
- Rotation: 30 days
- Level: INFO

---

## Configuration

### Plugin Settings

The plugin uses database tables for configuration rather than app.yml settings.

### Dependencies

```json
{
  "requires": {
    "atom_framework": ">=1.0.0",
    "atom": ">=2.8",
    "php": ">=8.1"
  },
  "dependencies": [
    "ahgCorePlugin",
    "ahgSecurityClearancePlugin"
  ]
}
```

---

## UI Components

### Templates

| Template | Description |
|----------|-------------|
| newSuccess.php | Clearance request form |
| requestObjectSuccess.php | Object access request form |
| myRequestsSuccess.php | User's request dashboard |
| pendingSuccess.php | Approver's queue with stats |
| viewSuccess.php | Request detail view with approve/deny |
| approversSuccess.php | Approver management |
| _requestAccessButton.php | Embeddable request button partial |

### Statistics Dashboard

The pending requests view displays:
- Pending count
- Approved today
- Denied today
- Total this month

---

## Integration Points

### Security Clearance Plugin

When a clearance request is approved:
```php
SecurityClearanceService::grantClearance(
    $request->user_id,
    $request->requested_classification_id,
    $approverId,
    $expiresAt,
    "Approved via access request #{$requestId}"
);
```

### Research Plugin

Researcher renewal requests update the research_researcher table:
```php
DB::table('research_researcher')
    ->where('user_id', $request->user_id)
    ->update([
        'status' => 'approved',
        'expires_at' => $newExpiry
    ]);
```

### Embeddable Button

Include in record views:
```php
<?php include_partial('accessRequest/requestAccessButton', [
    'resource' => $resource,
    'type' => 'information_object'
]); ?>
```

---

## Error Handling

- All database operations wrapped in try-catch
- Transactions used for multi-table operations
- Errors logged to file
- User-friendly flash messages displayed

---

## Security Considerations

- All routes require authentication
- Approver routes verify approver status
- Admin routes require administrator credential
- Users can only view/cancel their own requests
- IP addresses logged for audit trail
- Denial requires reason to prevent arbitrary rejections

---

*Part of the AtoM AHG Framework*
