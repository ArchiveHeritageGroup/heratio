# ahgRightsPlugin - Technical Documentation

**Version:** 1.1.2
**Category:** Rights Management
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Comprehensive rights management system for archival materials implementing PREMIS rights vocabulary, RightsStatements.org standards, Creative Commons licensing, Traditional Knowledge Labels (Local Contexts), embargo management, and orphan works due diligence tracking.

---

## Architecture

```
+---------------------------------------------------------------------+
|                       ahgRightsPlugin                                |
+---------------------------------------------------------------------+
|                                                                      |
|  +---------------------------------------------------------------+  |
|  |                    RightsService                               |  |
|  |  - Rights record CRUD                                          |  |
|  |  - Embargo management                                          |  |
|  |  - TK Label assignment                                         |  |
|  |  - Orphan work tracking                                        |  |
|  |  - Access checking                                             |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Database Layer                              |  |
|  |  +------------------+  +------------------+  +---------------+  |  |
|  |  | rights_record    |  | rights_embargo   |  | rights_tk_*   |  |  |
|  |  | rights_grant     |  | rights_orphan_*  |  | rights_cc_*   |  |  |
|  |  | rights_statement |  | rights_territory |  | rights_deriv* |  |  |
|  |  +------------------+  +------------------+  +---------------+  |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    UI Components                               |  |
|  |  +------------------+  +------------------+  +---------------+  |  |
|  |  | rightsAdmin      |  | rights module    |  | Display       |  |  |
|  |  | (admin pages)    |  | (record views)   |  | Integration   |  |  |
|  |  +------------------+  +------------------+  +---------------+  |  |
|  +---------------------------------------------------------------+  |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+----------------------------------+       +----------------------------------+
|         rights_record            |       |        rights_statement          |
+----------------------------------+       +----------------------------------+
| PK id INT AUTO_INCREMENT         |       | PK id BIGINT AUTO_INCREMENT      |
|    object_id INT (FK)            |       |    uri VARCHAR(255) UNIQUE       |
|    basis ENUM                    |       |    code VARCHAR(50) UNIQUE       |
| FK rights_statement_id INT       |<------+    category ENUM                 |
| FK cc_license_id INT             |       |    icon_filename VARCHAR(100)    |
|    copyright_status ENUM         |       |    is_active TINYINT             |
|    copyright_holder VARCHAR(255) |       |    sort_order INT                |
|    copyright_holder_actor_id INT |       +----------------------------------+
|    copyright_jurisdiction VARCHAR|                      |
|    copyright_determination_date  |                      v
|    license_identifier VARCHAR    |       +----------------------------------+
|    license_terms TEXT            |       |     rights_statement_i18n        |
|    statute_citation VARCHAR      |       +----------------------------------+
|    statute_jurisdiction VARCHAR  |       | PK id BIGINT AUTO_INCREMENT      |
|    donor_name VARCHAR            |       | FK rights_statement_id BIGINT    |
|    policy_identifier VARCHAR     |       |    culture VARCHAR(10)           |
|    start_date DATE               |       |    name VARCHAR(255)             |
|    end_date DATE                 |       |    definition TEXT               |
|    created_by INT                |       |    scope_note TEXT               |
|    created_at DATETIME           |       +----------------------------------+
+----------------------------------+
         |                    |
         |                    |
         v                    v
+------------------+   +----------------------------------+
|  rights_grant    |   |     rights_cc_license            |
+------------------+   +----------------------------------+
| PK id INT        |   | PK id INT AUTO_INCREMENT         |
| FK rights_record |   |    code VARCHAR(20) UNIQUE       |
|    _id INT       |   |    version VARCHAR(10)           |
|    act ENUM      |   |    uri VARCHAR(255)              |
|    restriction   |   |    allows_commercial TINYINT     |
|     ENUM         |   |    allows_derivatives TINYINT    |
|    start_date    |   |    requires_share_alike TINYINT  |
|    end_date      |   |    requires_attribution TINYINT  |
|    condition_type|   |    badge_url VARCHAR(255)        |
|    condition_val |   |    is_active TINYINT             |
+------------------+   +----------------------------------+

+----------------------------------+       +----------------------------------+
|         rights_embargo           |       |        rights_tk_label           |
+----------------------------------+       +----------------------------------+
| PK id INT AUTO_INCREMENT         |       | PK id INT AUTO_INCREMENT         |
|    object_id INT (FK)            |       |    code VARCHAR(20) UNIQUE       |
|    embargo_type ENUM             |       |    category ENUM (tk/bc/attr)    |
|    reason ENUM                   |       |    uri VARCHAR(255)              |
|    start_date DATE               |       |    color VARCHAR(7)              |
|    end_date DATE                 |       |    icon_path VARCHAR(255)        |
|    auto_release TINYINT          |       |    sort_order INT                |
|    review_date DATE              |       |    is_active TINYINT             |
|    review_interval_months INT    |       +----------------------------------+
|    status ENUM                   |                      |
|    lifted_at DATETIME            |                      v
|    lifted_by INT                 |       +----------------------------------+
|    notify_before_days INT        |       |     rights_object_tk_label       |
|    notification_sent TINYINT     |       +----------------------------------+
|    notify_emails TEXT (JSON)     |       | PK id INT AUTO_INCREMENT         |
|    created_by INT                |       |    object_id INT (FK)            |
|    created_at DATETIME           |       | FK tk_label_id INT               |
+----------------------------------+       |    community_name VARCHAR        |
         |                                 |    community_contact TEXT        |
         v                                 |    custom_text TEXT              |
+----------------------------------+       |    verified TINYINT              |
|      rights_embargo_log          |       |    verified_by VARCHAR           |
+----------------------------------+       |    verified_date DATE            |
| PK id INT AUTO_INCREMENT         |       +----------------------------------+
| FK embargo_id INT                |
|    action ENUM                   |
|    old_status VARCHAR            |
|    new_status VARCHAR            |       +----------------------------------+
|    old_end_date DATE             |       |       rights_orphan_work         |
|    new_end_date DATE             |       +----------------------------------+
|    notes TEXT                    |       | PK id INT AUTO_INCREMENT         |
|    performed_by INT              |       |    object_id INT (FK)            |
|    performed_at DATETIME         |       |    status ENUM                   |
+----------------------------------+       |    work_type ENUM                |
                                           |    search_started_date DATE      |
+----------------------------------+       |    search_completed_date DATE    |
|      rights_territory            |       |    search_jurisdiction VARCHAR   |
+----------------------------------+       |    rights_holder_found TINYINT   |
| PK id INT AUTO_INCREMENT         |       |    rights_holder_name VARCHAR    |
| FK rights_record_id INT          |       |    contact_attempted TINYINT     |
|    territory_type ENUM           |       |    intended_use TEXT             |
|    country_code VARCHAR(10)      |       |    proposed_fee DECIMAL(10,2)    |
|    is_gdpr_territory TINYINT     |       +----------------------------------+
|    gdpr_legal_basis VARCHAR      |                      |
+----------------------------------+                      v
                                           +----------------------------------+
+----------------------------------+       |   rights_orphan_search_step      |
|    rights_derivative_rule        |       +----------------------------------+
+----------------------------------+       | PK id INT AUTO_INCREMENT         |
| PK id INT AUTO_INCREMENT         |       | FK orphan_work_id INT            |
|    object_id INT                 |       |    source_type ENUM              |
|    collection_id INT             |       |    source_name VARCHAR           |
|    is_global TINYINT             |       |    source_url VARCHAR            |
|    rule_type ENUM                |       |    search_date DATE              |
|    priority INT                  |       |    search_terms TEXT             |
|    applies_to_roles JSON         |       |    results_found TINYINT         |
|    applies_to_clearance JSON     |       |    results_description TEXT      |
|    watermark_text VARCHAR        |       |    evidence_file_path VARCHAR    |
|    watermark_image_path VARCHAR  |       |    screenshot_path VARCHAR       |
|    watermark_position ENUM       |       |    performed_by INT              |
|    watermark_opacity INT         |       +----------------------------------+
|    max_width INT                 |
|    max_height INT                |
+----------------------------------+
```

---

## ENUM Values

### Rights Basis
```sql
ENUM('copyright', 'license', 'statute', 'donor', 'policy', 'other')
```

### Copyright Status
```sql
ENUM('copyrighted', 'public_domain', 'unknown')
```

### PREMIS Acts
```sql
ENUM('render', 'disseminate', 'replicate', 'migrate', 'modify',
     'delete', 'print', 'use', 'publish', 'excerpt', 'annotate',
     'move', 'sell')
```

### Restriction Types
```sql
ENUM('allow', 'disallow', 'conditional')
```

### Embargo Types
```sql
ENUM('full', 'metadata_only', 'digital_only', 'partial')
```

### Embargo Reasons
```sql
ENUM('donor_restriction', 'copyright', 'privacy', 'legal',
     'commercial', 'research', 'cultural', 'security', 'other')
```

### Embargo Status
```sql
ENUM('active', 'pending', 'lifted', 'expired', 'extended')
```

### Work Types (Orphan Works)
```sql
ENUM('literary', 'dramatic', 'musical', 'artistic', 'film',
     'sound_recording', 'broadcast', 'typographical', 'database',
     'photograph', 'other')
```

### Search Source Types
```sql
ENUM('database', 'registry', 'publisher', 'author_society',
     'archive', 'library', 'internet', 'newspaper', 'other')
```

### TK Label Categories
```sql
ENUM('tk', 'bc', 'attribution')
```

### Rights Statement Categories
```sql
ENUM('in-copyright', 'no-copyright', 'other')
```

---

## Service Methods

### RightsService

```php
namespace Plugins\ahgRightsPlugin\Services;

class RightsService
{
    // Singleton
    public static function getInstance(?string $culture = null): self

    // =================================================================
    // RIGHTS RECORDS
    // =================================================================

    public function getRightsForObject(int $objectId): Collection
    public function getRightsRecord(int $id): ?object
    public function createRightsRecord(array $data): int
    public function updateRightsRecord(int $id, array $data): bool
    public function deleteRightsRecord(int $id): bool

    // =================================================================
    // RIGHTS GRANTS (PREMIS Acts)
    // =================================================================

    public function getGrantsForRecord(int $recordId): Collection
    public function createGrant(int $recordId, array $data): int

    // =================================================================
    // EMBARGO MANAGEMENT
    // =================================================================

    public function getEmbargo(int $objectId): ?object
    public function getActiveEmbargoes(): Collection
    public function getExpiringEmbargoes(int $days = 30): Collection
    public function getEmbargoesForReview(): Collection
    public function createEmbargo(array $data): int
    public function liftEmbargo(int $id, ?string $reason = null, ?int $userId = null): bool
    public function extendEmbargo(int $id, string $newEndDate, ?string $reason = null, ?int $userId = null): bool
    public function processExpiredEmbargoes(): int

    // =================================================================
    // ORPHAN WORKS
    // =================================================================

    public function getOrphanWork(int $objectId): ?object
    public function getOrphanWorkSearchSteps(int $orphanWorkId): Collection
    public function createOrphanWork(array $data): int
    public function addOrphanWorkSearchStep(int $orphanWorkId, array $data): int
    public function completeOrphanWorkSearch(int $id, bool $rightsHolderFound = false): bool

    // =================================================================
    // TK LABELS
    // =================================================================

    public function getTkLabelsForObject(int $objectId): Collection
    public function assignTkLabel(int $objectId, int $labelId, array $data = []): int
    public function removeTkLabel(int $objectId, int $labelId): bool

    // =================================================================
    // TERRITORY RESTRICTIONS
    // =================================================================

    public function getTerritoriesForRecord(int $recordId): Collection
    public function addTerritory(int $recordId, string $countryCode, string $type = 'include', bool $isGdpr = false): int

    // =================================================================
    // ACCESS CHECKS
    // =================================================================

    public function checkAccess(int $objectId, ?int $userId = null, ?string $purpose = null): array

    // =================================================================
    // REFERENCE DATA
    // =================================================================

    public function getRightsStatements(): Collection
    public function getCcLicenses(): Collection
    public function getTkLabels(): Collection
    public function getFormOptions(): array

    // =================================================================
    // STATISTICS
    // =================================================================

    public function getStatistics(): array
}
```

---

## Usage Examples

### Creating a Rights Record

```php
use Plugins\ahgRightsPlugin\Services\RightsService;

$service = RightsService::getInstance();

$rightsId = $service->createRightsRecord([
    'object_id' => 12345,
    'basis' => 'copyright',
    'copyright_status' => 'copyrighted',
    'copyright_holder' => 'John Smith Estate',
    'copyright_jurisdiction' => 'ZA',
    'copyright_determination_date' => '2026-01-15',
    'rights_statement_id' => 1, // InC
    'start_date' => '1990-01-01',
    'end_date' => '2060-12-31',
    'rights_note' => 'Copyright held by family estate.',
    'created_by' => $userId,
    'grants' => [
        ['act' => 'render', 'restriction' => 'allow'],
        ['act' => 'disseminate', 'restriction' => 'conditional', 'condition_value' => 'Attribution required'],
        ['act' => 'modify', 'restriction' => 'disallow'],
    ],
]);
```

### Managing Embargoes

```php
// Create embargo
$embargoId = $service->createEmbargo([
    'object_id' => 12345,
    'embargo_type' => 'full',
    'reason' => 'donor_restriction',
    'start_date' => '2026-01-30',
    'end_date' => '2030-12-31',
    'auto_release' => true,
    'review_date' => '2028-01-30',
    'notify_before_days' => 30,
    'notify_emails' => ['archivist@example.org'],
    'reason_note' => 'Donor requested restriction for 5 years.',
    'created_by' => $userId,
]);

// Extend embargo
$service->extendEmbargo($embargoId, '2035-12-31', 'Donor requested extension', $userId);

// Lift embargo
$service->liftEmbargo($embargoId, 'Restriction period completed', $userId);

// Process all expired embargoes (for cron)
$count = $service->processExpiredEmbargoes();
```

### Assigning TK Labels

```php
// Get available TK labels
$labels = $service->getTkLabels();

// Assign label to object
$service->assignTkLabel(12345, $labelId, [
    'community_name' => 'Example Community',
    'community_contact' => 'elder@community.org',
    'custom_text' => 'Traditional knowledge protocols apply.',
    'verified' => false,
    'created_by' => $userId,
]);

// Get labels for object
$objectLabels = $service->getTkLabelsForObject(12345);

// Remove label
$service->removeTkLabel(12345, $labelId);
```

### Orphan Works Due Diligence

```php
// Start orphan work search
$orphanId = $service->createOrphanWork([
    'object_id' => 12345,
    'work_type' => 'photograph',
    'search_jurisdiction' => 'ZA',
    'intended_use' => 'Digitization and online access',
    'created_by' => $userId,
]);

// Add search steps
$service->addOrphanWorkSearchStep($orphanId, [
    'source_type' => 'registry',
    'source_name' => 'SAMRO',
    'source_url' => 'https://www.samro.org.za',
    'search_date' => '2026-01-30',
    'search_terms' => 'photographer name, date range',
    'results_found' => false,
    'results_description' => 'No matching records found.',
    'performed_by' => $userId,
]);

// Complete search
$service->completeOrphanWorkSearch($orphanId, false);
```

### Access Checking

```php
$accessCheck = $service->checkAccess(12345, $userId);

// Returns:
[
    'accessible' => false,
    'restrictions' => [
        ['type' => 'embargo', 'reason' => 'donor_restriction', 'until' => '2030-12-31'],
    ],
    'embargo' => {...},
    'rights_statement' => ['code' => 'InC', 'name' => 'In Copyright', 'uri' => '...'],
    'cc_license' => null,
    'tk_labels' => [...],
    'required_actions' => [
        ['type' => 'tk_consultation', 'label' => 'TK-C', 'community' => 'Example Community'],
    ],
]
```

---

## Seed Data

### Rights Statements (12 standard)

| Code | Category | Name |
|------|----------|------|
| InC | in-copyright | In Copyright |
| InC-OW-EU | in-copyright | In Copyright - EU Orphan Work |
| InC-EDU | in-copyright | In Copyright - Educational Use Permitted |
| InC-NC | in-copyright | In Copyright - Non-Commercial Use Permitted |
| InC-RUU | in-copyright | In Copyright - Rights-holder Unlocatable |
| NoC-CR | no-copyright | No Copyright - Contractual Restrictions |
| NoC-NC | no-copyright | No Copyright - Non-Commercial Use Only |
| NoC-OKLR | no-copyright | No Copyright - Other Known Legal Restrictions |
| NoC-US | no-copyright | No Copyright - United States |
| CNE | other | Copyright Not Evaluated |
| UND | other | Copyright Undetermined |
| NKC | other | No Known Copyright |

### Creative Commons Licenses (8 standard)

| Code | Commercial | Derivatives | Share Alike |
|------|------------|-------------|-------------|
| CC0-1.0 | Yes | Yes | No |
| CC-BY-4.0 | Yes | Yes | No |
| CC-BY-SA-4.0 | Yes | Yes | Yes |
| CC-BY-NC-4.0 | No | Yes | No |
| CC-BY-NC-SA-4.0 | No | Yes | Yes |
| CC-BY-ND-4.0 | Yes | No | No |
| CC-BY-NC-ND-4.0 | No | No | No |
| PDM-1.0 | Yes | Yes | No |

### TK Labels (19 standard)

| Code | Category | Name |
|------|----------|------|
| TK-A | attribution | TK Attribution |
| TK-NC | tk | TK Non-Commercial |
| TK-C | tk | TK Community Voice |
| TK-CV | tk | TK Culturally Sensitive |
| TK-SS | tk | TK Secret/Sacred |
| TK-MC | tk | TK Multiple Communities |
| TK-MR | tk | TK Men Restricted |
| TK-WR | tk | TK Women Restricted |
| TK-SR | tk | TK Seasonal |
| TK-F | tk | TK Family |
| TK-O | tk | TK Outreach |
| TK-V | tk | TK Verified |
| TK-NV | tk | TK Non-Verified |
| BC-R | bc | BC Research Use |
| BC-CB | bc | BC Consent Before |
| BC-P | bc | BC Provenance |
| BC-MC | bc | BC Multiple Communities |
| BC-CL | bc | BC Clan |
| BC-O | bc | BC Outreach |

---

## Routes

### Rights Admin Module

| Route | Action | Description |
|-------|--------|-------------|
| `/rightsAdmin` | index | Dashboard |
| `/rightsAdmin/embargoes` | embargoes | List embargoes |
| `/rightsAdmin/embargoEdit/:id` | embargoEdit | Edit embargo |
| `/rightsAdmin/embargoLift/:id` | embargoLift | Lift embargo |
| `/rightsAdmin/embargoExtend/:id` | embargoExtend | Extend embargo |
| `/rightsAdmin/processExpired` | processExpired | Auto-release expired |
| `/rightsAdmin/orphanWorks` | orphanWorks | List orphan works |
| `/rightsAdmin/orphanWorkEdit/:id` | orphanWorkEdit | Edit orphan work |
| `/rightsAdmin/addSearchStep` | addSearchStep | Add search step |
| `/rightsAdmin/tkLabels` | tkLabels | TK label management |
| `/rightsAdmin/assignTkLabel` | assignTkLabel | Assign TK label |
| `/rightsAdmin/removeTkLabel` | removeTkLabel | Remove TK label |
| `/rightsAdmin/statements` | statements | View statements/licenses |
| `/rightsAdmin/report/:type` | report | Generate reports |

### Rights Module (Record-Level)

| Route | Action | Description |
|-------|--------|-------------|
| `/rights/:slug` | index | View rights for record |
| `/rights/:slug/add` | add | Add rights record |
| `/rights/:slug/edit/:id` | edit | Edit rights record |
| `/rights/:slug/delete/:id` | delete | Delete rights record |
| `/rights/:slug/embargo` | editEmbargo | Edit embargo |
| `/rights/:slug/releaseEmbargo/:id` | releaseEmbargo | Release embargo |
| `/rights/:slug/tkLabels` | tkLabels | Manage TK labels |
| `/rights/:slug/orphanWork` | orphanWork | Orphan work management |

### API Endpoints

| Route | Action | Description |
|-------|--------|-------------|
| `/api/rights/check/:id` | apiCheck | Check access rights |
| `/api/rights/embargo/:id` | apiEmbargo | Get embargo status |

---

## Display Integration

### extension.json Configuration

```json
{
    "display_actions": [
        {
            "id": "rights_action",
            "label": "Rights",
            "icon": "bi-shield-lock",
            "template": "ahgRightsPlugin/templates/display/_rights.php",
            "contexts": ["informationobject", "actor"],
            "permission": "read",
            "weight": 30
        }
    ],
    "display_panels": [
        {
            "id": "rights_panel",
            "label": "Rights & Restrictions",
            "template": "ahgRightsPlugin/templates/display/_rights_section.php",
            "contexts": ["informationobject"],
            "position": "sidebar",
            "weight": 50
        }
    ]
}
```

### Template Integration

Include in detail templates:

```php
<?php include_partial('rights/rightsPanel', ['resource' => $resource]); ?>
```

---

## Cron Jobs

### Process Expired Embargoes

Add to crontab for automatic embargo release:

```bash
# Daily at 2:00 AM
0 2 * * * cd /usr/share/nginx/archive && php symfony rights:process-expired
```

### Embargo Notifications

```bash
# Daily at 8:00 AM - send expiring embargo notifications
0 8 * * * cd /usr/share/nginx/archive && php symfony rights:notify-expiring
```

---

## Configuration

| Setting | Default | Description |
|---------|---------|-------------|
| rights_default_jurisdiction | ZA | Default copyright jurisdiction |
| embargo_notify_days | 30 | Days before end to notify |
| orphan_search_minimum_steps | 5 | Minimum search steps required |
| tk_require_verification | false | Require TK label verification |

---

## Compliance Mapping

| Standard | Requirement | Implementation |
|----------|-------------|----------------|
| PREMIS | Rights basis vocabulary | basis ENUM field |
| PREMIS | Granted rights (acts) | rights_grant table |
| RightsStatements.org | 12 standardized statements | rights_statement table |
| Creative Commons | License metadata | rights_cc_license table |
| Local Contexts | TK/BC Labels | rights_tk_label tables |
| GDPR | Territory restrictions | rights_territory table |
| POPIA (SA) | Access controls | embargo + access check |
| PAIA (SA) | Public access | access check integration |

---

## Tables Summary

| Table | Purpose | i18n |
|-------|---------|------|
| rights | Base AtoM rights (legacy) | rights_i18n |
| rights_record | Extended rights records | rights_record_i18n |
| rights_grant | PREMIS acts/restrictions | rights_grant_i18n |
| rights_statement | RightsStatements.org vocab | rights_statement_i18n |
| rights_cc_license | Creative Commons licenses | rights_cc_license_i18n |
| rights_tk_label | TK/BC label definitions | rights_tk_label_i18n |
| rights_object_tk_label | Label assignments | - |
| rights_embargo | Embargo records | rights_embargo_i18n |
| rights_embargo_log | Embargo audit trail | - |
| rights_orphan_work | Orphan work records | rights_orphan_work_i18n |
| rights_orphan_search_step | Search documentation | - |
| rights_territory | Geographic restrictions | - |
| rights_derivative_rule | Watermark/derivative rules | - |
| rights_derivative_log | Derivative generation log | - |
| creative_commons_license | CC license definitions | creative_commons_license_i18n |

---

## Security

### Permissions

| Action | Required Role |
|--------|---------------|
| View rights | Any authenticated |
| Add/edit rights | Editor or Administrator |
| Delete rights | Administrator |
| Manage embargoes | Administrator |
| Assign TK labels | Editor or Administrator |
| View admin dashboard | Administrator |

### Access Control

Rights checks are performed via `checkAccess()` which evaluates:

1. Active embargoes
2. Rights grants (disallow restrictions)
3. TK label restrictions
4. Territory restrictions
5. User role/clearance level

---

## Installation

```bash
# 1. Ensure plugin symlink exists
ln -s /usr/share/nginx/archive/atom-ahg-plugins/ahgRightsPlugin \
      /usr/share/nginx/archive/plugins/ahgRightsPlugin

# 2. Install database schema
mysql -u root archive < /usr/share/nginx/archive/plugins/ahgRightsPlugin/database/install.sql

# 3. Clear cache
php symfony cc

# 4. Enable plugin via Admin UI or CLI
php bin/atom extension:enable ahgRightsPlugin
```

---

*Part of the AtoM AHG Framework*
