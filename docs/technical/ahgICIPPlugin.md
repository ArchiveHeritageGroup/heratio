# ahgICIPPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Compliance / Cultural Protocols
**Dependencies:** atom-framework

---

## Overview

Indigenous Cultural and Intellectual Property (ICIP) management plugin for Australian GLAM institutions. Provides comprehensive support for ethical stewardship of First Nations cultural materials in accordance with UNDRIP Article 31, Creative Australia Protocols, and AIATSIS Code of Ethics.

---

## Architecture

```
+---------------------------------------------------------------------+
|                        ahgICIPPlugin                                 |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |                 Plugin Configuration                          |  |
|  |  ahgICIPPluginConfiguration.class.php                         |  |
|  |  - Registers CSS                                              |  |
|  |  - Enables 'icip' module                                      |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                    Service Layer                              |  |
|  |  lib/ahgICIPService.class.php                                 |  |
|  |  - Consent management                                         |  |
|  |  - Access control checks                                      |  |
|  |  - Summary calculations                                       |  |
|  |  - Reporting queries                                          |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                   Controller (Actions)                        |  |
|  |  modules/icip/actions/actions.class.php                       |  |
|  |  - Dashboard, CRUD operations                                 |  |
|  |  - Object-specific ICIP management                            |  |
|  |  - API endpoints                                              |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                      Templates                                |  |
|  |  modules/icip/templates/*.php                                 |  |
|  |  - Bootstrap 5 responsive UI                                  |  |
|  |  - Dashboard, lists, forms, reports                           |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                    Database Layer                             |  |
|  |  10 tables for ICIP data management                           |  |
|  |  Uses Laravel Query Builder (Illuminate\Database)             |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+---------------------------+        +---------------------------+
|      icip_community       |        |    icip_cultural_notice   |
+---------------------------+        +---------------------------+
| PK id INT                 |<---+   | PK id INT                 |
|    name VARCHAR(255)      |    |   |    information_object_id  |
|    alternate_names JSON   |    |   | FK notice_type_id INT     |
|    language_group         |    +---| FK community_id INT       |
|    region VARCHAR(255)    |    |   |    custom_text TEXT       |
|    state_territory ENUM   |    |   |    applies_to_descendants |
|    contact_name           |    |   |    start_date DATE        |
|    contact_email          |    |   |    end_date DATE          |
|    contact_phone          |    |   |    notes TEXT             |
|    contact_address TEXT   |    |   |    created_at             |
|    native_title_reference |    |   +---------------------------+
|    prescribed_body_corp   |    |              |
|    pbc_contact_email      |    |              v
|    notes TEXT             |    |   +---------------------------+
|    is_active TINYINT(1)   |    |   | icip_cultural_notice_type |
|    created_at             |    |   +---------------------------+
|    updated_at             |    |   | PK id INT                 |
+---------------------------+    |   |    code VARCHAR(50) UNIQUE|
        |                        |   |    name VARCHAR(255)      |
        |                        |   |    description TEXT       |
        v                        |   |    default_text TEXT      |
+---------------------------+    |   |    icon VARCHAR(100)      |
|      icip_consent         |    |   |    severity ENUM          |
+---------------------------+    |   |    requires_acknowledgement|
| PK id INT                 |    |   |    blocks_access TINYINT  |
|    information_object_id  |    |   |    display_public TINYINT |
| FK community_id INT ------+----+   |    display_staff TINYINT  |
|    consent_status ENUM    |        |    display_order INT      |
|    consent_scope JSON     |        |    is_active TINYINT(1)   |
|    consent_date DATE      |        +---------------------------+
|    consent_expiry_date    |
|    consent_granted_by     |
|    consent_document_path  |        +---------------------------+
|    conditions TEXT        |        | icip_notice_acknowledgement|
|    restrictions TEXT      |        +---------------------------+
|    notes TEXT             |        | PK id INT                 |
|    created_at             |        | FK notice_id INT          |
|    updated_at             |        | FK user_id INT            |
+---------------------------+        |    acknowledged_at        |
                                     |    ip_address VARCHAR(45) |
                                     |    user_agent VARCHAR(500)|
+---------------------------+        +---------------------------+
|    icip_tk_label_type     |
+---------------------------+        +---------------------------+
| PK id INT                 |<----+  |     icip_tk_label         |
|    code VARCHAR(50) UNIQUE|     |  +---------------------------+
|    category ENUM(TK,BC)   |     |  | PK id INT                 |
|    name VARCHAR(255)      |     |  |    information_object_id  |
|    description TEXT       |     +--| FK label_type_id INT      |
|    icon_path VARCHAR(255) |        | FK community_id INT       |
|    local_contexts_url     |        |    applied_by ENUM        |
|    display_order INT      |        |    local_contexts_proj_id |
|    is_active TINYINT(1)   |        |    notes TEXT             |
|    created_at             |        |    created_at             |
+---------------------------+        +---------------------------+

+---------------------------+        +---------------------------+
|    icip_consultation      |        | icip_access_restriction   |
+---------------------------+        +---------------------------+
| PK id INT                 |        | PK id INT                 |
|    information_object_id  |        |    information_object_id  |
| FK community_id INT       |        |    restriction_type ENUM  |
|    consultation_type ENUM |        | FK community_id INT       |
|    consultation_date DATE |        |    start_date DATE        |
|    consultation_method    |        |    end_date DATE          |
|    location VARCHAR(255)  |        |    applies_to_descendants |
|    attendees TEXT         |        |    override_security_clr  |
|    community_reps TEXT    |        |    custom_restriction_txt |
|    institution_reps TEXT  |        |    notes TEXT             |
|    summary TEXT           |        |    created_at             |
|    outcomes TEXT          |        +---------------------------+
|    action_items JSON      |
|    follow_up_date DATE    |        +---------------------------+
|    follow_up_notes TEXT   |        |   icip_object_summary     |
|    is_confidential TINYINT|        +---------------------------+
| FK linked_consent_id INT  |        | PK information_object_id  |
|    documents JSON         |        |    has_icip_content       |
|    status ENUM            |        |    consent_status         |
|    created_at             |        |    has_cultural_notices   |
+---------------------------+        |    cultural_notice_count  |
                                     |    has_tk_labels          |
+---------------------------+        |    tk_label_count         |
|      icip_config          |        |    has_restrictions       |
+---------------------------+        |    restriction_count      |
| PK id INT                 |        |    requires_acknowledgement|
|    config_key VARCHAR(100)|        |    blocks_access          |
|    config_value TEXT      |        |    community_ids JSON     |
|    description TEXT       |        |    last_consultation_date |
|    updated_at             |        |    consent_expiry_date    |
+---------------------------+        |    updated_at             |
                                     +---------------------------+
```

### Table Definitions

#### icip_community

Stores registered Aboriginal and Torres Strait Islander communities.

```sql
CREATE TABLE IF NOT EXISTS icip_community (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    alternate_names JSON COMMENT 'Array of alternate names/spellings',
    language_group VARCHAR(255),
    region VARCHAR(255),
    state_territory ENUM('NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'NT', 'ACT', 'External') NOT NULL,
    contact_name VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(100),
    contact_address TEXT,
    native_title_reference VARCHAR(255) COMMENT 'Reference to Native Title determination',
    prescribed_body_corporate VARCHAR(255) COMMENT 'PBC name if applicable',
    pbc_contact_email VARCHAR(255),
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_state (state_territory),
    INDEX idx_language (language_group),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### icip_consent

Tracks consent status for records containing Indigenous cultural materials.

```sql
CREATE TABLE IF NOT EXISTS icip_consent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    community_id INT COMMENT 'Link to icip_community',
    consent_status ENUM(
        'not_required',
        'pending_consultation',
        'consultation_in_progress',
        'conditional_consent',
        'full_consent',
        'restricted_consent',
        'denied',
        'unknown'
    ) NOT NULL DEFAULT 'unknown',
    consent_scope JSON COMMENT 'Array of scope values',
    consent_date DATE,
    consent_expiry_date DATE,
    consent_granted_by VARCHAR(255),
    consent_document_path VARCHAR(500),
    conditions TEXT,
    restrictions TEXT,
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_object (information_object_id),
    INDEX idx_community (community_id),
    INDEX idx_status (consent_status),
    INDEX idx_expiry (consent_expiry_date),
    FOREIGN KEY (community_id) REFERENCES icip_community(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### icip_cultural_notice_type

Defines available cultural notice types.

```sql
CREATE TABLE IF NOT EXISTS icip_cultural_notice_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    default_text TEXT COMMENT 'Default notice text',
    icon VARCHAR(100),
    severity ENUM('info', 'warning', 'critical') DEFAULT 'warning',
    requires_acknowledgement TINYINT(1) DEFAULT 0,
    blocks_access TINYINT(1) DEFAULT 0,
    display_public TINYINT(1) DEFAULT 1,
    display_staff TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Default Notice Types:**

| Code | Name | Severity | Requires Acknowledgement |
|------|------|----------|-------------------------|
| cultural_sensitivity | Cultural Sensitivity | info | No |
| aboriginal_torres_strait | Aboriginal and Torres Strait Islander | warning | Yes |
| deceased_person | Deceased Person | warning | Yes |
| sacred_secret | Sacred/Secret Material | critical | Yes |
| mens_business | Men's Business | critical | Yes |
| womens_business | Women's Business | critical | Yes |
| ceremonial | Ceremonial Material | critical | Yes |
| community_only | Community Only | critical | Yes |
| seasonal_restriction | Seasonal Restriction | warning | No |
| custom | Custom Notice | info | No |

#### icip_tk_label_type

Stores TK and BC label definitions from Local Contexts.

```sql
CREATE TABLE IF NOT EXISTS icip_tk_label_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    category ENUM('TK', 'BC') NOT NULL COMMENT 'TK = Traditional Knowledge, BC = Biocultural',
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon_path VARCHAR(255),
    local_contexts_url VARCHAR(500),
    display_order INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**TK Labels (16):** TK_A, TK_CL, TK_F, TK_MC, TK_NC, TK_O, TK_S, TK_V, TK_CS, TK_CV, TK_CO, TK_WR, TK_WG, TK_MR, TK_MG, TK_SS

**BC Labels (6):** BC_P, BC_MC, BC_CL, BC_CNC, BC_O, BC_R

#### icip_access_restriction

ICIP-specific access controls.

```sql
CREATE TABLE IF NOT EXISTS icip_access_restriction (
    id INT AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    restriction_type ENUM(
        'community_permission_required',
        'gender_restricted_male',
        'gender_restricted_female',
        'initiated_only',
        'seasonal',
        'mourning_period',
        'repatriation_pending',
        'under_consultation',
        'elder_approval_required',
        'custom'
    ) NOT NULL,
    community_id INT,
    start_date DATE,
    end_date DATE COMMENT 'NULL for indefinite restrictions',
    applies_to_descendants TINYINT(1) DEFAULT 1,
    override_security_clearance TINYINT(1) DEFAULT 1,
    custom_restriction_text TEXT,
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_object (information_object_id),
    INDEX idx_type (restriction_type),
    INDEX idx_community (community_id),
    INDEX idx_dates (start_date, end_date),
    FOREIGN KEY (community_id) REFERENCES icip_community(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### icip_object_summary

Materialized summary view for efficient ICIP status lookups.

```sql
CREATE TABLE IF NOT EXISTS icip_object_summary (
    information_object_id INT PRIMARY KEY,
    has_icip_content TINYINT(1) DEFAULT 0,
    consent_status VARCHAR(50),
    has_cultural_notices TINYINT(1) DEFAULT 0,
    cultural_notice_count INT DEFAULT 0,
    has_tk_labels TINYINT(1) DEFAULT 0,
    tk_label_count INT DEFAULT 0,
    has_restrictions TINYINT(1) DEFAULT 0,
    restriction_count INT DEFAULT 0,
    requires_acknowledgement TINYINT(1) DEFAULT 0,
    blocks_access TINYINT(1) DEFAULT 0,
    community_ids JSON,
    last_consultation_date DATE,
    consent_expiry_date DATE,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_has_icip (has_icip_content),
    INDEX idx_consent (consent_status),
    INDEX idx_blocks (blocks_access)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Service Methods

### ahgICIPService

Core service class for ICIP operations.

```php
class ahgICIPService
{
    // Constants
    const CONSENT_NOT_REQUIRED = 'not_required';
    const CONSENT_PENDING = 'pending_consultation';
    const CONSENT_IN_PROGRESS = 'consultation_in_progress';
    const CONSENT_CONDITIONAL = 'conditional_consent';
    const CONSENT_FULL = 'full_consent';
    const CONSENT_RESTRICTED = 'restricted_consent';
    const CONSENT_DENIED = 'denied';
    const CONSENT_UNKNOWN = 'unknown';

    const SCOPE_PRESERVATION_ONLY = 'preservation_only';
    const SCOPE_INTERNAL_ACCESS = 'internal_access';
    const SCOPE_PUBLIC_ACCESS = 'public_access';
    const SCOPE_REPRODUCTION = 'reproduction';
    const SCOPE_COMMERCIAL_USE = 'commercial_use';
    const SCOPE_EDUCATIONAL_USE = 'educational_use';
    const SCOPE_RESEARCH_USE = 'research_use';
    const SCOPE_FULL_RIGHTS = 'full_rights';

    // Option getters
    public static function getConsentStatusOptions(): array
    public static function getConsentScopeOptions(): array
    public static function getStateTerritories(): array
    public static function getRestrictionTypes(): array
    public static function getRestrictionLabel(string $type): string

    // Object data retrieval
    public static function getObjectSummary(int $objectId): ?object
    public static function hasICIPContent(int $objectId): bool
    public static function getObjectConsent(int $objectId): array
    public static function getObjectNotices(int $objectId): array
    public static function getObjectTKLabels(int $objectId): array
    public static function getObjectRestrictions(int $objectId): array
    public static function getObjectConsultations(int $objectId): array

    // Access control
    public static function checkAccess(int $objectId, ?int $userId = null): array
    public static function hasAcknowledged(int $noticeId, int $userId): bool
    public static function recordAcknowledgement(int $noticeId, int $userId): bool

    // Summary management
    public static function updateObjectSummary(int $objectId): void

    // Configuration
    public static function getConfig(string $key, $default = null)
    public static function setConfig(string $key, $value): void

    // Dashboard & Reporting
    public static function getDashboardStats(): array
    public static function getPendingConsultation(int $limit = 50): array
    public static function getExpiringConsents(int $days = 90): array
}
```

### Key Method Details

#### checkAccess()

Checks if a user can access an object based on ICIP restrictions.

```php
public static function checkAccess(int $objectId, ?int $userId = null): array
{
    $result = [
        'allowed' => true,
        'requires_acknowledgement' => false,
        'unacknowledged_notices' => [],
        'blocked_reason' => null,
        'restrictions' => [],
    ];

    // Check notices that block access or require acknowledgement
    $notices = self::getObjectNotices($objectId);
    foreach ($notices as $notice) {
        if ($notice->blocks_access) {
            if (!$userId || !self::hasAcknowledged($notice->id, $userId)) {
                $result['allowed'] = false;
                $result['blocked_reason'] = 'Cultural notice requires acknowledgement';
                $result['unacknowledged_notices'][] = $notice;
            }
        } elseif ($notice->requires_acknowledgement) {
            if (!$userId || !self::hasAcknowledged($notice->id, $userId)) {
                $result['requires_acknowledgement'] = true;
                $result['unacknowledged_notices'][] = $notice;
            }
        }
    }

    // Check restrictions that override security clearance
    $restrictions = self::getObjectRestrictions($objectId);
    foreach ($restrictions as $restriction) {
        $result['restrictions'][] = $restriction;
        if ($restriction->override_security_clearance) {
            if (in_array($restriction->restriction_type, [
                'community_permission_required',
                'initiated_only',
                'repatriation_pending',
            ])) {
                $result['allowed'] = false;
                $result['blocked_reason'] = 'ICIP restriction: ' .
                    self::getRestrictionLabel($restriction->restriction_type);
            }
        }
    }

    return $result;
}
```

#### updateObjectSummary()

Updates the materialized summary for an information object.

```php
public static function updateObjectSummary(int $objectId): void
{
    // Count related records
    $consent = DB::table('icip_consent')
        ->where('information_object_id', $objectId)
        ->orderBy('created_at', 'desc')
        ->first();

    $noticeCount = DB::table('icip_cultural_notice')
        ->where('information_object_id', $objectId)->count();

    $labelCount = DB::table('icip_tk_label')
        ->where('information_object_id', $objectId)->count();

    $restrictionCount = DB::table('icip_access_restriction')
        ->where('information_object_id', $objectId)->count();

    // Check for blocking notices
    $blockingNotice = DB::table('icip_cultural_notice as n')
        ->join('icip_cultural_notice_type as t', 'n.notice_type_id', '=', 't.id')
        ->where('n.information_object_id', $objectId)
        ->where(function ($query) {
            $query->where('t.requires_acknowledgement', 1)
                ->orWhere('t.blocks_access', 1);
        })
        ->first();

    // Aggregate community IDs
    $communityIds = array_unique(array_merge(
        DB::table('icip_consent')
            ->where('information_object_id', $objectId)
            ->whereNotNull('community_id')
            ->pluck('community_id')->toArray(),
        DB::table('icip_cultural_notice')
            ->where('information_object_id', $objectId)
            ->whereNotNull('community_id')
            ->pluck('community_id')->toArray()
    ));

    // Update or insert summary
    DB::table('icip_object_summary')->updateOrInsert(
        ['information_object_id' => $objectId],
        [
            'has_icip_content' => ($consent || $noticeCount || $labelCount || $restrictionCount) ? 1 : 0,
            'consent_status' => $consent ? $consent->consent_status : null,
            'has_cultural_notices' => $noticeCount > 0 ? 1 : 0,
            'cultural_notice_count' => $noticeCount,
            'has_tk_labels' => $labelCount > 0 ? 1 : 0,
            'tk_label_count' => $labelCount,
            'has_restrictions' => $restrictionCount > 0 ? 1 : 0,
            'restriction_count' => $restrictionCount,
            'requires_acknowledgement' => $blockingNotice && $blockingNotice->requires_acknowledgement ? 1 : 0,
            'blocks_access' => $blockingNotice && $blockingNotice->blocks_access ? 1 : 0,
            'community_ids' => !empty($communityIds) ? json_encode($communityIds) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]
    );
}
```

---

## Routes

### Dashboard & Management Routes

| Route | URL | Action |
|-------|-----|--------|
| icip_dashboard | /icip | Dashboard |
| icip_communities | /icip/communities | Community list |
| icip_community_add | /icip/community/add | Add community |
| icip_community_edit | /icip/community/:id/edit | Edit community |
| icip_community_view | /icip/community/:id | View community |
| icip_consent_list | /icip/consent | Consent list |
| icip_consent_add | /icip/consent/add | Add consent |
| icip_consent_edit | /icip/consent/:id/edit | Edit consent |
| icip_consent_view | /icip/consent/:id | View consent |
| icip_consultations | /icip/consultations | Consultation list |
| icip_consultation_add | /icip/consultation/add | Add consultation |
| icip_consultation_edit | /icip/consultation/:id/edit | Edit consultation |
| icip_tk_labels | /icip/tk-labels | TK Labels management |
| icip_notices | /icip/notices | Cultural notices |
| icip_notice_types | /icip/notice-types | Notice type management |
| icip_restrictions | /icip/restrictions | Access restrictions |
| icip_reports | /icip/reports | Reports overview |
| icip_report_pending | /icip/reports/pending-consultation | Pending report |
| icip_report_expiry | /icip/reports/consent-expiry | Expiry report |
| icip_report_community | /icip/reports/community/:id | Community report |

### Object-Specific Routes

| Route | URL | Action |
|-------|-----|--------|
| icip_object | /object/:slug/icip | Object ICIP overview |
| icip_object_consent | /object/:slug/icip/consent | Object consent |
| icip_object_notices | /object/:slug/icip/notices | Object notices |
| icip_object_labels | /object/:slug/icip/labels | Object TK labels |
| icip_object_restrictions | /object/:slug/icip/restrictions | Object restrictions |
| icip_object_consultations | /object/:slug/icip/consultations | Object consultations |

### API Routes

| Route | URL | Method | Response |
|-------|-----|--------|----------|
| icip_api_summary | /icip/api/summary/:object_id | GET | ICIP summary JSON |
| icip_api_check_access | /icip/api/check-access/:object_id | GET | Access check JSON |
| icip_acknowledge | /icip/acknowledge/:notice_id | POST | Acknowledgement result |

---

## API Endpoints

### GET /icip/api/summary/:object_id

Returns ICIP summary for an information object.

**Response:**
```json
{
    "object_id": 12345,
    "has_icip_content": true,
    "consent_status": "full_consent",
    "has_cultural_notices": true,
    "has_tk_labels": true,
    "has_restrictions": false,
    "requires_acknowledgement": true,
    "blocks_access": false
}
```

### GET /icip/api/check-access/:object_id

Checks if current user can access an object based on ICIP restrictions.

**Response:**
```json
{
    "allowed": true,
    "requires_acknowledgement": true,
    "unacknowledged_notices": [
        {
            "id": 5,
            "notice_code": "aboriginal_torres_strait",
            "notice_name": "Aboriginal and Torres Strait Islander"
        }
    ],
    "blocked_reason": null,
    "restrictions": []
}
```

### POST /icip/acknowledge/:notice_id

Records user acknowledgement of a cultural notice.

**Response:**
```json
{
    "success": true
}
```

---

## Configuration Options

Stored in `icip_config` table:

| Key | Default | Description |
|-----|---------|-------------|
| enable_public_notices | 1 | Display cultural notices on public view |
| enable_staff_notices | 1 | Display cultural notices on staff view |
| require_acknowledgement_default | 1 | Default acknowledgement requirement |
| consent_expiry_warning_days | 90 | Days before expiry to show warning |
| local_contexts_hub_enabled | 0 | Enable Local Contexts Hub API integration |
| local_contexts_api_key | '' | API key for Local Contexts Hub |
| default_consultation_follow_up_days | 30 | Default follow-up period |
| audit_all_icip_access | 1 | Log all access to ICIP-flagged records |

---

## File Structure

```
ahgICIPPlugin/
+-- ahgICIPPluginConfiguration.class.php
+-- ICIP.md
+-- config/
|   +-- routing.yml
+-- css/
|   +-- icip.css
+-- database/
|   +-- install.sql
+-- images/
|   +-- tk-labels/              (future: TK label icons)
+-- lib/
|   +-- ahgICIPService.class.php
+-- modules/
    +-- icip/
        +-- actions/
        |   +-- actions.class.php
        +-- templates/
            +-- dashboardSuccess.php
            +-- communitiesSuccess.php
            +-- communityEditSuccess.php
            +-- communityViewSuccess.php
            +-- consentListSuccess.php
            +-- consentEditSuccess.php
            +-- consentViewSuccess.php
            +-- consultationsSuccess.php
            +-- consultationEditSuccess.php
            +-- consultationViewSuccess.php
            +-- tkLabelsSuccess.php
            +-- noticesSuccess.php
            +-- noticeTypesSuccess.php
            +-- restrictionsSuccess.php
            +-- reportsSuccess.php
            +-- reportPendingSuccess.php
            +-- reportExpirySuccess.php
            +-- reportCommunitySuccess.php
            +-- objectIcipSuccess.php
            +-- objectConsentSuccess.php
            +-- objectNoticesSuccess.php
            +-- objectLabelsSuccess.php
            +-- objectRestrictionsSuccess.php
            +-- objectConsultationsSuccess.php
```

---

## Installation

```bash
# 1. Run database migration
mysql -u root archive < /usr/share/nginx/archive/atom-ahg-plugins/ahgICIPPlugin/database/install.sql

# 2. Create symlink
ln -sf /usr/share/nginx/archive/atom-ahg-plugins/ahgICIPPlugin /usr/share/nginx/archive/plugins/ahgICIPPlugin

# 3. Clear cache
rm -rf /usr/share/nginx/archive/cache/* && php /usr/share/nginx/archive/symfony cc

# 4. Enable plugin
php /usr/share/nginx/archive/bin/atom extension:enable ahgICIPPlugin

# 5. Restart PHP-FPM
sudo systemctl restart php8.3-fpm
```

---

## Security Considerations

### Authentication
- All management routes require authenticated users
- Public actions (acknowledge, API) allow authenticated access

### Access Control
- ICIP restrictions can override standard AtoM security clearance
- `override_security_clearance` flag determines precedence
- Restriction types that block access:
  - `community_permission_required`
  - `initiated_only`
  - `repatriation_pending`

### Acknowledgement Tracking
- User acknowledgements are logged with:
  - User ID
  - Timestamp
  - IP address
  - User agent
- Provides audit trail for compliance

### Confidential Consultations
- Consultations can be marked confidential
- Confidential consultations hidden from public queries
- Only visible to authenticated staff

---

## Integration Points

### ahgSecurityClearancePlugin
- ICIP restrictions can override security clearance
- Planned: Check ICIP status in clearance evaluation

### ahgAuditTrailPlugin
- Planned: Log all ICIP access when `audit_all_icip_access` enabled
- Track consent changes, consultation updates

### Local Contexts Hub
- Future: API integration with Local Contexts Hub
- Sync TK Labels from community projects
- `local_contexts_hub_enabled` configuration

### Theme Integration
- Add ICIP tab to information object view
- Display cultural notices in record headers
- Show TK Label badges on record listings

---

## Compliance Mapping

| Framework | Requirement | Implementation |
|-----------|-------------|----------------|
| UNDRIP Article 31 | Right to maintain cultural heritage | Community registry, consent tracking |
| Creative Australia Protocols | First Nations ICIP guidelines | Cultural notices, TK Labels |
| AIATSIS Code of Ethics | Research ethics | Consultation log, consent workflow |
| Local Contexts | TK/BC Label system | Full TK/BC label support |

---

## Future Enhancements

1. **Local Contexts Hub Integration**
   - API sync for TK Labels
   - Project linking

2. **Theme Integration**
   - ICIP tab in record edit forms
   - Notice display in record view partials

3. **Audit Trail Integration**
   - Log ICIP record access
   - Track consent changes

4. **TK Label Icons**
   - Download official icons from Local Contexts
   - Display in record views

5. **Repatriation Workflow**
   - Dedicated repatriation tracking
   - Community liaison integration

---

*Part of the AtoM AHG Framework - ahgICIPPlugin v1.0.0*
