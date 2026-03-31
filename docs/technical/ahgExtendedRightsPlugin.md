# ahgExtendedRightsPlugin - Technical Documentation

**Version:** 1.2.9
**Category:** Rights Management
**Dependencies:** atom-framework, ahgCorePlugin, ahgRightsPlugin

---

## Overview

Comprehensive extended rights management system providing RightsStatements.org integration, Creative Commons licensing, Traditional Knowledge (TK) Labels support, embargo management, and batch rights assignment capabilities for GLAM institutions.

---

## Architecture

```
+---------------------------------------------------------------------+
|                     ahgExtendedRightsPlugin                          |
+---------------------------------------------------------------------+
|                                                                      |
|  +---------------------------------------------------------------+  |
|  |              Rights Standards Integration                      |  |
|  |  * RightsStatements.org (12 statements)                       |  |
|  |  * Creative Commons Licenses (CC BY, CC BY-SA, etc.)          |  |
|  |  * Traditional Knowledge Labels (TK Notices)                  |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                   ExtendedRightsService                        |  |
|  |  * Rights assignment (single/batch)                           |  |
|  |  * Rights holder management                                   |  |
|  |  * TK Label assignment                                        |  |
|  |  * Statistics and reporting                                   |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                     EmbargoService                             |  |
|  |  * Embargo creation/modification/lifting                      |  |
|  |  * Access control (full/metadata/digital/partial)             |  |
|  |  * Exception management (user/group/IP range)                 |  |
|  |  * Auto-release on expiry                                     |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |              EmbargoNotificationService                        |  |
|  |  * Expiry warning notifications (30/7/1 days)                 |  |
|  |  * Lifted notifications                                       |  |
|  |  * Access granted notifications                               |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |     Helper Classes (Template Integration)                      |  |
|  |  * EmbargoHelper (static access checks)                       |  |
|  |  * DigitalObjectEmbargoFilter (download blocking)             |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +----------------------------+  +--------------------------------+  |
|  |      extended_rights       |  |        rights_embargo          |  |
|  |    extended_rights_i18n    |  |      embargo_exception         |  |
|  |  extended_rights_tk_label  |  |       embargo_audit            |  |
|  +----------------------------+  +--------------------------------+  |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+-----------------------------------+     +-----------------------------------+
|         extended_rights           |     |         rights_embargo            |
+-----------------------------------+     +-----------------------------------+
| PK id BIGINT                      |     | PK id BIGINT                      |
| FK object_id INT                  |     | FK object_id INT                  |
| FK rights_statement_id BIGINT     |     |    embargo_type ENUM              |
| FK creative_commons_license_id    |     |    reason ENUM                    |
|    BIGINT                         |     |    start_date DATE                |
|    rights_date DATE               |     |    end_date DATE                  |
|    expiry_date DATE               |     |    auto_release TINYINT           |
|    rights_holder VARCHAR(255)     |     |    status ENUM                    |
|    rights_holder_uri VARCHAR(255) |     | FK created_by INT                 |
|    is_primary TINYINT             |     | FK lifted_by INT                  |
| FK created_by INT                 |     |    lifted_at TIMESTAMP            |
| FK updated_by INT                 |     |    lift_reason TEXT               |
|    created_at TIMESTAMP           |     |    notify_before_days INT         |
|    updated_at TIMESTAMP           |     |    notification_sent TINYINT      |
+-----------------------------------+     |    created_at TIMESTAMP           |
         |                                |    updated_at TIMESTAMP           |
         | 1:N                            +-----------------------------------+
         v                                         |
+-----------------------------------+              | 1:N
|      extended_rights_i18n         |              v
+-----------------------------------+     +-----------------------------------+
| PK id BIGINT                      |     |       embargo_exception           |
| FK extended_rights_id BIGINT      |     +-----------------------------------+
|    culture VARCHAR(10)            |     | PK id BIGINT                      |
|    rights_note TEXT               |     | FK embargo_id BIGINT              |
|    usage_conditions TEXT          |     |    exception_type ENUM            |
|    copyright_notice TEXT          |     |    exception_id INT               |
+-----------------------------------+     |    ip_range_start VARCHAR(45)     |
                                          |    ip_range_end VARCHAR(45)       |
+-----------------------------------+     |    valid_from DATE                |
|    extended_rights_tk_label       |     |    valid_until DATE               |
+-----------------------------------+     |    notes TEXT                     |
| PK id BIGINT                      |     | FK granted_by INT                 |
| FK extended_rights_id BIGINT      |     |    created_at TIMESTAMP           |
| FK tk_label_id BIGINT             |     |    updated_at TIMESTAMP           |
|    community_id INT               |     +-----------------------------------+
|    community_note TEXT            |
|    assigned_date DATE             |     +-----------------------------------+
|    created_at TIMESTAMP           |     |         embargo_audit             |
|    updated_at TIMESTAMP           |     +-----------------------------------+
+-----------------------------------+     | PK id BIGINT                      |
                                          | FK embargo_id BIGINT              |
+-----------------------------------+     |    action ENUM                    |
|   extended_rights_batch_log       |     | FK user_id INT                    |
+-----------------------------------+     |    old_values JSON                |
| PK id INT                         |     |    new_values JSON                |
|    action VARCHAR(50)             |     |    ip_address VARCHAR(45)         |
|    object_count INT               |     |    created_at TIMESTAMP           |
|    object_ids JSON                |     +-----------------------------------+
|    data JSON                      |
|    results JSON                   |
| FK performed_by INT               |
|    performed_at DATETIME          |
+-----------------------------------+
```

### SQL Schema

```sql
-- Extended Rights Table
CREATE TABLE extended_rights (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    rights_statement_id BIGINT UNSIGNED DEFAULT NULL,
    creative_commons_license_id BIGINT UNSIGNED DEFAULT NULL,
    rights_date DATE DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    rights_holder VARCHAR(255),
    rights_holder_uri VARCHAR(255),
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_ext_rights_object (object_id),
    INDEX idx_ext_rights_rs (rights_statement_id),
    INDEX idx_ext_rights_cc (creative_commons_license_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extended Rights i18n Table
CREATE TABLE extended_rights_i18n (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    extended_rights_id BIGINT UNSIGNED NOT NULL,
    culture VARCHAR(10) NOT NULL DEFAULT 'en',
    rights_note TEXT,
    usage_conditions TEXT,
    copyright_notice TEXT,

    UNIQUE KEY uq_ext_rights_i18n (extended_rights_id, culture),
    INDEX idx_ext_rights_i18n_parent (extended_rights_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extended Rights TK Label Junction Table
CREATE TABLE extended_rights_tk_label (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    extended_rights_id BIGINT UNSIGNED NOT NULL,
    tk_label_id BIGINT UNSIGNED NOT NULL,
    community_id INT DEFAULT NULL,
    community_note TEXT,
    assigned_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_ext_rights_tk (extended_rights_id, tk_label_id),
    INDEX idx_ext_rights_tk_label (tk_label_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Embargo Table (uses rights_embargo from ahgRightsPlugin)
-- This plugin extends the shared rights_embargo table

-- Embargo Exception Table
CREATE TABLE embargo_exception (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    embargo_id BIGINT UNSIGNED NOT NULL,
    exception_type ENUM('user', 'group', 'ip_range', 'repository') NOT NULL,
    exception_id INT DEFAULT NULL,
    ip_range_start VARCHAR(45),
    ip_range_end VARCHAR(45),
    valid_from DATE DEFAULT NULL,
    valid_until DATE DEFAULT NULL,
    notes TEXT,
    granted_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_emb_exc_embargo (embargo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Embargo Audit Table
CREATE TABLE embargo_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    embargo_id BIGINT UNSIGNED NOT NULL,
    action ENUM('created', 'modified', 'lifted', 'extended',
                'exception_added', 'exception_removed') NOT NULL,
    user_id INT DEFAULT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_emb_audit_embargo (embargo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Batch Log Table
CREATE TABLE extended_rights_batch_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    object_count INT NOT NULL DEFAULT 0,
    object_ids JSON,
    data JSON,
    results JSON,
    performed_by INT DEFAULT NULL,
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_action (action),
    INDEX idx_performed_at (performed_at),
    INDEX idx_performed_by (performed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Embargo Types

| Type | Constant | Description |
|------|----------|-------------|
| full | `TYPE_FULL` | Complete access restriction - record hidden |
| metadata_only | `TYPE_METADATA_ONLY` | Metadata visible, digital content hidden |
| digital_only | `TYPE_DIGITAL_ONLY` | All visible, downloads blocked |
| partial | `TYPE_PARTIAL` | Configurable partial restrictions |

### Access Matrix by Embargo Type

| Embargo Type | View Record | View Metadata | View Thumbnail | View Digital | Download |
|--------------|-------------|---------------|----------------|--------------|----------|
| None | Yes | Yes | Yes | Yes | Yes |
| full | No | No | No | No | No |
| metadata_only | Yes | Yes | No | No | No |
| digital_only | Yes | Yes | Yes | No | No |
| partial | Yes | Yes | Yes | Limited | No |

---

## Embargo Reason Codes

| Code | Description |
|------|-------------|
| donor_restriction | Material restricted by donor agreement |
| copyright | Copyright-related restriction |
| privacy | Privacy protection restriction |
| legal | Legal restriction |
| commercial | Commercial restriction |
| research | Ongoing research restriction |
| cultural | Cultural sensitivity restriction |
| security | Security-related restriction |
| other | Other restriction |

---

## Service Methods

### ExtendedRightsService

```php
namespace ahgExtendedRightsPlugin\Services;

class ExtendedRightsService
{
    // Culture management
    public function setCulture(string $culture): self

    // Rights Statements
    public function getRightsStatementsByCategory(): array
    public function getRightsStatements(): array  // alias

    // Creative Commons
    public function getCreativeCommonsLicenses(): Collection

    // TK Labels
    public function getTkLabelsByCategory(): array
    public function getTkLabels(): array  // alias

    // Object Rights Management
    public function getObjectRights(int $objectId): ?object
    public function getAllObjectRights(int $objectId): Collection
    public function assignRights(int $objectId, array $data, ?int $userId = null): int
    public function updateRights(int $rightsId, array $data, ?int $userId = null): void
    public function removeRights(int $rightsId): void
    public function clearRights(int $objectId): void

    // Rights Assignment Shortcuts
    public function assignRightsStatement(int $objectId, int $statementId): void
    public function assignCreativeCommons(int $objectId, int $licenseId): void
    public function assignTkLabel(int $objectId, int $labelId): void
    public function assignRightsHolder(int $objectId, int $holderId): void

    // Embargo (basic)
    public function getActiveEmbargo(int $objectId): ?object
    public function getActiveEmbargoes(): Collection
    public function createEmbargo(int $objectId, string $type, string $startDate,
                                   ?string $endDate = null): int

    // Statistics
    public function getDashboardStats(): array

    // Lookup Data
    public function getDonors(int $limit = 100): Collection
    public function getTopLevelRecords(int $limit = 100): Collection
}
```

### EmbargoService

```php
namespace ahgExtendedRightsPlugin\Services;

class EmbargoService
{
    // Constants
    public const TYPE_FULL = 'full';
    public const TYPE_METADATA_ONLY = 'metadata_only';
    public const TYPE_DIGITAL_ONLY = 'digital_only';
    public const TYPE_PARTIAL = 'partial';

    // CRUD Operations
    public function getEmbargo(int $embargoId): ?object
    public function getObjectEmbargoes(int $objectId): Collection
    public function getActiveEmbargo(int $objectId): ?object
    public function getActiveEmbargoes(): Collection
    public function getExpiringEmbargoes(int $days = 30): Collection

    public function createEmbargo(int $objectId, array $data, ?int $userId = null): int
    public function createEmbargoWithPropagation(int $objectId, array $data,
                                                  ?int $userId = null,
                                                  bool $applyToChildren = false): array
    public function updateEmbargo(int $embargoId, array $data, ?int $userId = null): bool
    public function liftEmbargo(int $embargoId, ?string $reason = null,
                                 ?int $userId = null): bool

    // Status Checks
    public function isEmbargoed(int $objectId): bool
    public function checkAccess(int $objectId, ?int $userId = null,
                                 ?string $ipAddress = null): bool

    // Access Control Methods
    public function canAccessRecord(int $objectId, ?object $user = null): bool
    public function canViewMetadata(int $objectId, ?object $user = null): bool
    public function canViewThumbnail(int $objectId, ?object $user = null): bool
    public function canViewDigitalObject(int $objectId, ?object $user = null): bool
    public function canDownload(int $objectId, ?object $user = null): bool

    // Bulk Operations
    public function filterAccessibleIds(array $objectIds, ?object $user = null): array

    // Display
    public function getEmbargoDisplayInfo(int $objectId): ?array

    // Statistics
    public function getStatistics(): array

    // Cache
    public static function clearCache(): void
}
```

### EmbargoNotificationService

```php
namespace ahgExtendedRightsPlugin\Services;

class EmbargoNotificationService
{
    // Notifications
    public function sendExpiryNotification(object $embargo, int $daysRemaining): bool
    public function sendLiftedNotification(object $embargo, ?string $reason = null): bool
    public function sendAccessGrantedNotification(object $embargo, object $exception,
                                                   object $user): bool
}
```

### ExtendedRightsExportService

```php
namespace ahgExtendedRightsPlugin\Services;

class ExtendedRightsExportService
{
    public function getRightsStatistics(): array
    public function getRightsStatementCounts(): array
    public function getCCLicenseCounts(): array
    public function getTkLabelCounts(): array
    public function exportObjectRights($objectId = null): Collection
}
```

---

## Helper Classes

### EmbargoHelper (Template Usage)

```php
// Static helper for use in templates
class EmbargoHelper
{
    // Access checks (returns bool)
    public static function canAccess(int $objectId): bool
    public static function canViewMetadata(int $objectId): bool
    public static function canViewThumbnail(int $objectId): bool
    public static function canViewDigitalObject(int $objectId): bool
    public static function canDownload(int $objectId): bool

    // Embargo info
    public static function getActiveEmbargo(int $objectId): ?object
    public static function getDisplayInfo(int $objectId): ?array
    public static function isEmbargoed(int $objectId): bool

    // Bulk filtering
    public static function filterAccessible(array $objectIds): array
}
```

**Template Example:**
```php
<?php if (EmbargoHelper::canAccess($resource->id)): ?>
    <?php echo $resource->title ?>
<?php else: ?>
    <span class="text-muted">[Restricted]</span>
<?php endif; ?>
```

### DigitalObjectEmbargoFilter

```php
// Download blocking filter
class DigitalObjectEmbargoFilter
{
    public static function canDownload(int $digitalObjectId, ?object $user = null): array
    public static function canDownloadByObjectId(int $objectId, ?object $user = null): array
    public static function canViewDigitalObject(int $objectId): bool
}
```

**Return Structure:**
```php
[
    'allowed' => bool,
    'reason' => string|null,
    'embargo_info' => [
        'id' => int,
        'type' => string,
        'type_label' => string,
        'end_date' => string|null,
        'is_perpetual' => bool,
        'reason' => string|null
    ]|null,
    'can_request_access' => bool
]
```

---

## Routes

### Extended Rights Module

| Route Name | Path | Action |
|------------|------|--------|
| extendedRights_dashboard | `/extendedRights/dashboard` | index |
| extendedRights_index | `/extendedRights` | index |
| extendedRights_edit | `/extendedRights/edit/:slug` | edit |
| extendedRights_batch | `/extendedRights/batch` | batch |
| extendedRights_browse | `/extendedRights/browse` | browse |
| extendedRights_embargoes | `/extendedRights/embargoes` | embargoes |
| extendedRights_liftEmbargo | `/extendedRights/liftEmbargo/:id` | liftEmbargo |
| extendedRights_admin | `/admin/rights` | index |
| extendedRights_admin_batch | `/admin/rights/batch` | batch |

### Embargo Module

| Route Name | Path | Action |
|------------|------|--------|
| ahg_rights_embargo_index | `/ahg/rights/embargo` | index |
| ahg_rights_embargo_add | `/ahg/rights/embargo/add` | add |
| ahg_rights_embargo_edit | `/ahg/rights/embargo/edit` | edit |
| ahg_rights_embargo_view | `/ahg/rights/embargo/view/:id` | view |
| ahg_rights_embargo_lift | `/ahg/rights/embargo/lift/:id` | lift |

---

## CLI Commands

### embargo:process

Automated embargo processing for cron execution.

```bash
# Process all operations (lift expired + send notifications)
php symfony embargo:process

# Preview without changes
php symfony embargo:process --dry-run

# Send notifications only
php symfony embargo:process --notify-only

# Lift expired embargoes only
php symfony embargo:process --lift-only

# Custom warning intervals
php symfony embargo:process --warn-days=14,7,3
```

**Cron Setup (recommended daily at 6am):**
```bash
0 6 * * * cd /usr/share/nginx/archive && php symfony embargo:process
```

### embargo:report

Generate embargo reports.

```bash
# Summary statistics
php symfony embargo:report

# List all active embargoes
php symfony embargo:report --active

# List embargoes expiring in N days
php symfony embargo:report --expiring=30

# List recently lifted embargoes
php symfony embargo:report --lifted --days=7

# List expired but not lifted
php symfony embargo:report --expired

# Export as CSV
php symfony embargo:report --active --format=csv --output=/tmp/report.csv
```

---

## Configuration

The plugin automatically registers two modules on initialization:

```yaml
# config/settings.yml
all:
  .settings:
    enabled_modules:
      - extendedRights
      - embargo
```

---

## Display Badges

The plugin provides an embargo badge for display on information object pages:

```json
{
    "id": "embargo_badge",
    "check_method": "ahgExtendedRightsPlugin\\Services\\EmbargoService::isObjectEmbargoed",
    "template": "ahgExtendedRightsPlugin/templates/display/_embargoBadge.php",
    "contexts": ["informationobject"],
    "weight": 10
}
```

---

## Integration with AtoM

### Event Hooks

The plugin connects to Symfony events:

```php
// Plugin initialization
$this->dispatcher->connect('context.load_factories', [$this, 'contextLoadFactories']);
$this->dispatcher->connect('routing.load_configuration', [$this, 'loadRoutes']);
```

### CSS Assets

Automatically loaded:
```
/plugins/ahgExtendedRightsPlugin/web/css/extended-rights.css
```

### Image Assets

**Rights Statements Icons:**
```
/plugins/ahgExtendedRightsPlugin/web/images/rights-statements/
  InC.png, InC-EDU.png, InC-NC.png, InC-OW-EU.png, InC-RUU.png,
  NoC-CR.png, NoC-NC.png, NoC-OKLR.png, NoC-US.png,
  CNE.png, NKC.png, UND.png
```

**Creative Commons Icons:**
```
/plugins/ahgExtendedRightsPlugin/web/images/creative-commons/
  cc.png, by.png, sa.png, nc.png, nd.png, zero.png
```

---

## Shared Tables (from ahgRightsPlugin)

This plugin extends tables defined in `ahgRightsPlugin`:

| Table | Purpose |
|-------|---------|
| rights_embargo | Core embargo records |
| rights_embargo_i18n | Embargo i18n (reason_note, internal_note, public_message) |
| rights_statement | RightsStatements.org definitions |
| rights_cc_license | Creative Commons license definitions |
| rights_tk_label | Traditional Knowledge Label definitions |
| object_rights_statement | Object-to-rights-statement junction |
| rights_object_tk_label | Object-to-TK-label junction |

---

## Audit Trail Integration

When `ahgAuditTrailPlugin` is enabled, the following actions are logged:

| Action | Entity Type | Details |
|--------|-------------|---------|
| create | embargo | New embargo created |
| update | embargo | Embargo modified |
| lift | embargo | Embargo lifted |
| create | extended_rights | Rights assigned |
| update | extended_rights | Rights modified |
| delete | extended_rights | Rights removed |

---

## Security Considerations

1. **Embargo Bypass** - Only users with `update` permission on the object can bypass embargo restrictions
2. **Admin Override** - Administrators (group_id=100) bypass all embargo checks
3. **IP Range Exceptions** - Can grant access to specific IP ranges (e.g., reading room terminals)
4. **Time-Limited Exceptions** - Exceptions can have `valid_from` and `valid_until` dates

---

## Related Plugins

| Plugin | Relationship |
|--------|--------------|
| ahgRightsPlugin | **Required** - Provides base rights tables and definitions |
| ahgCorePlugin | **Required** - Provides EmailService for notifications |
| ahgAuditTrailPlugin | **Optional** - Provides audit logging |
| ahgAccessRequestPlugin | **Optional** - Enables access request functionality |

---

*Part of the AtoM AHG Framework*
