# Heratio - Database ERD

**Version:** 2.8.2
**Last Updated:** March 2026

---

## 1. Core Extension Tables (Heratio)

**2 tables** — Plugin registry and centralized settings.

### Tables

```json
["atom_plugin","ahg_settings"]
```

### ERD Diagram

```
┌──────────────────────────────────────────────────────────────────────────────────────────────┐
│                              CORE EXTENSION TABLES ERD                                        │
│                          Heratio Plugin & Settings Layer                                │
└──────────────────────────────────────────────────────────────────────────────────────────────┘

  ┌───────────────────────────────────────────────┐
  │               atom_plugin (116 rows)          │
  │───────────────────────────────────────────────│
  │ PK id               bigint unsigned           │
  │    name             varchar(255) UQ            │◄── e.g. "ahgThemeB5Plugin"
  │    class_name       varchar(255)               │◄── PHP class name
  │    version          varchar(50)                │
  │    description      text                       │
  │    author           varchar(255)               │
  │    category         varchar(100)               │◄── theme|security|sector|capability
  │    is_enabled       tinyint(1)                 │◄── 0=disabled, 1=enabled
  │    is_core          tinyint(1)                 │◄── 1=cannot be disabled
  │    is_locked        tinyint(1)                 │◄── 1=cannot be modified
  │    status           ENUM(installed/enabled/    │
  │                       disabled/pending_removal)│
  │    load_order       int                        │◄── Lower = loads first
  │    plugin_path      varchar(500)               │◄── Filesystem path
  │    settings         json                       │◄── Plugin configuration
  │    record_check_query text                     │◄── SQL to verify plugin data
  │    enabled_at       timestamp                  │
  │    disabled_at      timestamp                  │
  │    created_at       timestamp                  │
  │    updated_at       timestamp                  │
  └───────────────────────────────────────────────┘

  ┌───────────────────────────────────────────────┐
  │             ahg_settings (276 rows)           │
  │───────────────────────────────────────────────│
  │ PK id               int                       │
  │    setting_key      varchar(100) UQ            │◄── e.g. "iiif_viewer_enabled"
  │    setting_value    text                       │
  │    setting_type     ENUM(string/integer/       │
  │                       boolean/json/float)      │
  │    setting_group    varchar(50)                │◄── e.g. general, iiif, ingest, media
  │    description      varchar(500)               │
  │    is_sensitive     tinyint(1)                 │◄── 1=mask in UI
  │ FK updated_by       int ──────────────────────►│ user.id
  │    updated_at       datetime                   │
  │    created_at       datetime                   │
  └───────────────────────────────────────────────┘

  ══════════════════════════════════════════════════
   LOADING SEQUENCE:
   ProjectConfiguration.class.php
     → SELECT name FROM atom_plugin WHERE is_enabled = 1 ORDER BY load_order
     → enablePlugins($plugins)

   SETTINGS ACCESS:
     AhgSettingsService::get('key', 'default')
     AhgSettingsService::getBool('key')
     AhgSettingsService::getGroup('group_name')
  ══════════════════════════════════════════════════
```

---

## 2. Audit Trail ERD (ahgAuditTrailPlugin)

**6 tables** — Comprehensive audit logging with field-level change tracking, access logging, and authentication events.

### Tables

```json
["audit_log","ahg_audit_log","ahg_audit_access","ahg_audit_authentication","ahg_audit_retention_policy","ahg_audit_settings"]
```

### ERD Diagram

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                ahgAuditTrailPlugin — Audit Trail ERD                                  │
│                              Compliance/International Compliance Audit Logging                                    │
└──────────────────────────────────────────────────────────────────────────────────────────────────────┘

  ┌──────────────────────────────────────────────────┐
  │            audit_log (field-level)               │
  │──────────────────────────────────────────────────│
  │ PK id                bigint                      │
  │    table_name        varchar(100)                │◄── e.g. "information_object"
  │    record_id         int                         │◄── PK of changed record
  │    action            ENUM(create/update/delete)  │
  │    field_name        varchar(100)                │◄── specific field changed
  │    old_value         text                        │
  │    new_value         text                        │
  │    old_record        json                        │◄── full record snapshot (create/delete)
  │    new_record        json                        │
  │    user_id           int                         │
  │    username          varchar(255)                │
  │    ip_address        varchar(45)                 │
  │    user_agent        varchar(500)                │
  │    module            varchar(100)                │
  │    action_description varchar(255)               │
  │    created_at        datetime                    │
  └──────────────────────────────────────────────────┘

  ┌──────────────────────────────────────────────────┐
  │         ahg_audit_log (entity-level, 122 rows)   │
  │──────────────────────────────────────────────────│
  │ PK id                bigint unsigned             │
  │    uuid              char(36) UQ                 │◄── unique event identifier
  │ FK user_id           int ───────────────────────►│ user.id
  │    username          varchar(255)                │
  │    user_email        varchar(255)                │
  │    ip_address        varchar(45)                 │
  │    user_agent        varchar(500)                │
  │    session_id        varchar(128)                │
  │    action            varchar(50)                 │◄── view, edit, delete, download, etc.
  │    entity_type       varchar(100)                │◄── informationobject, actor, etc.
  │    entity_id         int                         │
  │    entity_slug       varchar(255)                │
  │    entity_title      varchar(500)                │
  │    module            varchar(100)                │
  │    action_name       varchar(100)                │
  │    request_method    varchar(10)                 │
  │    request_uri       varchar(2000)               │
  │    old_values        json                        │
  │    new_values        json                        │
  │    changed_fields    json                        │◄── list of field names changed
  │    metadata          json                        │◄── extra context data
  │    security_classification varchar(50)           │◄── classification at time of access
  │    status            varchar(20)                 │◄── success, denied, error
  │    error_message     text                        │
  │    created_at        timestamp                   │
  │    culture_id        int                         │
  └──────────────────────────────────────────────────┘

  ┌──────────────────────────────────────────────────┐
  │         ahg_audit_access (file/entity access)    │
  │──────────────────────────────────────────────────│
  │ PK id                bigint unsigned             │
  │    uuid              char(36) UQ                 │
  │ FK user_id           int ───────────────────────►│ user.id
  │    username          varchar(255)                │
  │    ip_address        varchar(45)                 │
  │    access_type       varchar(50)                 │◄── view, download, print, stream
  │    entity_type       varchar(100)                │
  │    entity_id         int                         │
  │    entity_slug       varchar(255)                │
  │    entity_title      varchar(500)                │
  │    security_classification varchar(50)           │
  │    security_clearance_level int unsigned         │
  │    clearance_verified tinyint(1)                 │◄── was clearance check passed?
  │    file_path         varchar(1000)               │
  │    file_name         varchar(255)                │
  │    file_mime_type    varchar(100)                │
  │    file_size         bigint unsigned             │
  │    status            varchar(20)                 │◄── success, denied
  │    denial_reason     varchar(255)                │
  │    metadata          json                        │
  │    created_at        timestamp                   │
  └──────────────────────────────────────────────────┘

  ┌──────────────────────────────────────────────────┐
  │   ahg_audit_authentication (31 rows)             │
  │──────────────────────────────────────────────────│
  │ PK id                bigint unsigned             │
  │    uuid              char(36) UQ                 │
  │    event_type        varchar(50)                 │◄── login, logout, login_failed, 2fa
  │ FK user_id           int ───────────────────────►│ user.id
  │    username          varchar(255)                │
  │    ip_address        varchar(45)                 │
  │    user_agent        varchar(500)                │
  │    session_id        varchar(128)                │
  │    status            varchar(20)                 │◄── success, failed
  │    failure_reason    varchar(255)                │
  │    failed_attempts   int unsigned                │
  │    metadata          json                        │
  │    created_at        timestamp                   │
  └──────────────────────────────────────────────────┘

  ┌──────────────────────────────────────────┐  ┌──────────────────────────────────────┐
  │   ahg_audit_retention_policy             │  │    ahg_audit_settings (4 rows)       │
  │──────────────────────────────────────────│  │──────────────────────────────────────│
  │ PK id              int unsigned          │  │ PK id              int unsigned      │
  │    log_type        varchar(50) UQ        │  │    setting_key     varchar(100) UQ   │
  │    retention_days  int unsigned           │  │    setting_value   text              │
  │    archive_before_delete tinyint(1)      │  │    setting_type    varchar(20)       │
  │    archive_path    varchar(500)           │  │    description     text              │
  │    last_cleanup_at timestamp             │  │    created_at      timestamp         │
  │    created_at      timestamp             │  │    updated_at      timestamp         │
  │    updated_at      timestamp             │  └──────────────────────────────────────┘
  └──────────────────────────────────────────┘

  ══════════════════════════════════════════════════════════════════════
   DUAL LOG PATTERN:
     audit_log         → field-level change tracking (old/new per field)
     ahg_audit_log     → entity-level event tracking (UUID, security classification)
     ahg_audit_access  → file/digital object access with clearance verification
     ahg_audit_authentication → login/logout/2FA events

   GLAM/DAM & INFORMATION OBJECT LINKS:
     audit_log.object_id ──────────────► object.id (AtoM core — any entity)
     ahg_audit_log.entity_type ────────► informationobject | actor | accession | repository | ...
     ahg_audit_log.entity_id ──────────► Polymorphic FK to any AtoM entity
     ahg_audit_access.object_id ───────► digital_object / information_object (file access)
     ahg_audit_access.security_classification_id ► security_classification.id
     All tables: user_id ──────────────► user.id (extends actor.id in AtoM)
  ══════════════════════════════════════════════════════════════════════
```

---

## 3. Privacy & Compliance ERD (ahgPrivacyPlugin)

**33 tables** across 7 subsystems: Breach Management, Consent, DSAR/PAIA Requests, Jurisdiction/Governance, Data Inventory, Administration, Visual Redaction.

### Tables

```json
["privacy_breach","privacy_breach_i18n","privacy_breach_incident","privacy_breach_notification","privacy_consent","privacy_consent_i18n","privacy_consent_log","privacy_consent_record","privacy_dsar","privacy_dsar_i18n","privacy_dsar_log","privacy_dsar_request","privacy_paia_request","privacy_complaint","privacy_jurisdiction","privacy_jurisdiction_registry","privacy_lawful_basis","privacy_compliance_rule","privacy_data_inventory","privacy_processing_activity","privacy_processing_activity_i18n","privacy_retention_schedule","privacy_special_category","privacy_request_type","privacy_officer","privacy_institution_config","privacy_config","privacy_notification","privacy_approval_log","privacy_audit_log","privacy_template","privacy_redaction_cache","privacy_visual_redaction"]
```

### ERD Diagram

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                           ahgPrivacyPlugin — Privacy & Compliance ERD                                     │
│                        Compliance / GDPR / CCPA / PIPEDA / NDPA / DPA / CDPA (7 jurisdictions)                │
└──────────────────────────────────────────────────────────────────────────────────────────────────────────┘

  ═══════════════════════════════  BREACH MANAGEMENT  ══════════════════════════════════

  ┌──────────────────────────────────────────────────┐
  │          privacy_breach                          │
  │──────────────────────────────────────────────────│
  │ PK id               int unsigned                │
  │    reference_number  varchar(50) UQ              │◄── e.g. BREACH-2026-001
  │    jurisdiction      varchar(30)                 │◄── popia, gdpr, ccpa, etc.
  │    breach_type       ENUM(confidentiality/       │
  │                        integrity/availability)   │
  │    severity          ENUM(low/medium/high/       │
  │                        critical)                 │
  │    status            ENUM(detected/investigating/│
  │                        contained/resolved/closed)│
  │    detected_date     datetime                    │
  │    occurred_date     datetime                    │
  │    contained_date    datetime                    │
  │    resolved_date     datetime                    │
  │    data_subjects_affected int                    │
  │    data_categories_affected text                 │
  │    notification_required tinyint(1)              │
  │    regulator_notified    tinyint(1)              │
  │    regulator_notified_date datetime              │
  │    subjects_notified     tinyint(1)              │
  │    subjects_notified_date datetime               │
  │    risk_to_rights    ENUM(unlikely/possible/     │
  │                        likely/high)              │
  │    assigned_to       int                         │
  │    created_by        int                         │
  │    created_at / updated_at                       │
  └─────────────┬────────────────────────────────────┘
                │
     ┌──────────┼──────────┐
     ▼          ▼          ▼
  ┌─────────────────┐ ┌────────────────────────────┐ ┌─────────────────────────┐
  │ privacy_breach_  │ │ privacy_breach_notification│ │ privacy_breach_incident │
  │ i18n             │ │────────────────────────────│ │─────────────────────────│
  │─────────────────│ │ PK id         int unsigned │ │ PK id       int unsigned│
  │ PK id  (FK)     │ │ FK breach_id  int unsigned │ │    reference varchar(50)│
  │ PK culture      │ │    notification_type ENUM( │ │    incident_date        │
  │    title        │ │      regulator/data_subject│ │    discovered_date      │
  │    description  │ │      /internal/third_party)│ │    breach_type          │
  │    cause        │ │    recipient  varchar(255) │ │    description text     │
  │    impact_      │ │    method ENUM(email/letter│ │    individuals_affected │
  │      assessment │ │      /portal/phone/        │ │    severity varchar(50) │
  │    remedial_    │ │      in_person)            │ │    root_cause text      │
  │      actions    │ │    sent_date  datetime     │ │    containment_actions  │
  │    lessons_     │ │    acknowledged_date       │ │    status varchar(50)   │
  │      learned    │ │    content    text          │ │    created_by / at      │
  └─────────────────┘ │    created_by / created_at │ └─────────────────────────┘
                      └────────────────────────────┘

  ═══════════════════════════════  CONSENT MANAGEMENT  ═════════════════════════════════

  ┌──────────────────────────────────────────────────┐
  │          privacy_consent                         │
  │──────────────────────────────────────────────────│
  │ PK id               int unsigned                │
  │    consent_type      ENUM(processing/marketing/ │
  │      profiling/third_party/cookies/research/     │
  │      special_category)                           │
  │    purpose_code      varchar(50)                 │
  │    is_required       tinyint(1)                  │
  │    is_active         tinyint(1)                  │
  │    valid_from / valid_until date                 │
  │    created_at / updated_at                       │
  └─────────────┬────────────────────────────────────┘
                │
     ┌──────────┼──────────┐
     ▼          ▼          ▼
  ┌─────────────────┐ ┌──────────────────────────────┐ ┌──────────────────────────────┐
  │ privacy_consent_ │ │   privacy_consent_log        │ │   privacy_consent_record     │
  │ i18n             │ │──────────────────────────────│ │──────────────────────────────│
  │─────────────────│ │ PK id         int unsigned   │ │ PK id         int unsigned   │
  │ PK id  (FK)     │ │ FK consent_id int unsigned   │ │    data_subject_id varchar   │
  │ PK culture      │ │    user_id    int            │ │    subject_name   varchar    │
  │    name         │ │    subject_identifier varchar│ │    subject_email  varchar    │
  │    description  │ │    action ENUM(granted/      │ │    purpose        varchar    │
  │    purpose_     │ │      withdrawn/expired/      │ │    consent_given  tinyint(1) │
  │      description│ │      renewed)                │ │    consent_method varchar(50)│
  └─────────────────┘ │    consent_given tinyint(1)  │ │    consent_date   datetime   │
                      │    consent_date  datetime    │ │    withdrawal_date datetime  │
                      │    withdrawal_date datetime  │ │    source   varchar(100)     │
                      │    ip_address varchar(45)    │ │    jurisdiction varchar(20)  │
                      │    user_agent text            │ │    ip_address varchar(45)    │
                      │    consent_proof text         │ │    status    varchar(50)     │
                      │    created_at                │ │    created_by / created_at   │
                      └──────────────────────────────┘ └──────────────────────────────┘

  ═══════════════════════  DSAR & PAIA REQUESTS  ═══════════════════════════════════════

  ┌──────────────────────────────────────────────────┐  ┌──────────────────────────────────────────┐
  │          privacy_dsar                            │  │        privacy_paia_request              │
  │──────────────────────────────────────────────────│  │──────────────────────────────────────────│
  │ PK id               int unsigned                │  │ PK id               int unsigned         │
  │    reference_number  varchar(50) UQ              │  │    reference_number  varchar(50) UQ      │
  │    jurisdiction      varchar(30)                 │  │    paia_section  ENUM(section_18/22/23/  │
  │    request_type  ENUM(access/rectification/     │  │      50/77)                               │
  │      erasure/portability/restriction/objection/ │  │    requestor_name / email / phone         │
  │      withdraw_consent)                           │  │    requestor_id_number varchar(100)      │
  │    requestor_name / email / phone               │  │    requestor_address text                │
  │    requestor_id_type / id_number                │  │    record_description text               │
  │    requestor_address text                       │  │    access_form ENUM(inspect/copy/both)   │
  │    is_verified       tinyint(1)                 │  │    status ENUM(received/processing/      │
  │    verified_at / verified_by                    │  │      granted/partially_granted/refused/   │
  │    status ENUM(received/verified/in_progress/   │  │      transferred/appealed)               │
  │      pending_info/completed/rejected/withdrawn) │  │    outcome_reason / refusal_grounds      │
  │    priority ENUM(low/normal/high/urgent)        │  │    fee_deposit / fee_access decimal      │
  │    received_date / due_date / completed_date    │  │    fee_paid tinyint(1)                   │
  │    assigned_to       int                        │  │    received_date / due_date / completed  │
  │    outcome ENUM(granted/partially_granted/      │  │    assigned_to / created_by              │
  │      refused/not_applicable)                    │  │    created_at / updated_at               │
  │    refusal_reason    text                       │  └──────────────────────────────────────────┘
  │    fee_required decimal / fee_paid tinyint      │
  │    created_by / created_at / updated_at         │  ┌──────────────────────────────────────────┐
  └─────────────┬────────────────────────────────────┘  │        privacy_complaint                │
                │                                       │──────────────────────────────────────────│
     ┌──────────┼──────────┐                            │ PK id              int unsigned         │
     ▼          ▼          ▼                             │    reference_number varchar(50) UQ      │
  ┌─────────────────┐ ┌──────────────────┐              │    jurisdiction     varchar(20)         │
  │ privacy_dsar_   │ │ privacy_dsar_log │              │    complainant_name / email / phone     │
  │ i18n            │ │──────────────────│              │    complaint_type   varchar(100)        │
  │─────────────────│ │ PK id  int unsign│              │    description      text               │
  │ PK id  (FK)     │ │ FK dsar_id       │              │    date_of_incident date               │
  │ PK culture      │ │    action  v(100)│              │    status ENUM(received/investigating/  │
  │    description  │ │    details text  │              │      resolved/escalated/closed)         │
  │    notes        │ │    user_id       │              │    assigned_to / resolution / resolved  │
  │    response_    │ │    ip_address    │              │    created_at / updated_at              │
  │      summary    │ │    created_at    │              └──────────────────────────────────────────┘
  └─────────────────┘ └──────────────────┘
                                                        ┌──────────────────────────────────────────┐
  ┌──────────────────────────────────────────┐          │        privacy_dsar_request (legacy)     │
  │     privacy_request_type                 │          │──────────────────────────────────────────│
  │──────────────────────────────────────────│          │ PK id              int unsigned         │
  │ PK id              int unsigned          │          │    reference       varchar(50) UQ       │
  │    jurisdiction_code varchar(30)         │          │    request_type    varchar(50)          │
  │    code             varchar(50)          │          │    data_subject_name / email / id_type  │
  │    name             varchar(255)         │          │    received_date / deadline_date        │
  │    description      text                 │          │    completed_date / status / notes      │
  │    legal_reference  varchar(100)         │          │    assigned_to / created_by / created_at│
  │    response_days    int                  │          └──────────────────────────────────────────┘
  │    fee_allowed      tinyint(1)           │
  │    is_active / sort_order                │
  └──────────────────────────────────────────┘

  ═══════════════════════  JURISDICTION & GOVERNANCE  ══════════════════════════════════

  ┌──────────────────────────────────────────────────┐  ┌──────────────────────────────────────────┐
  │     privacy_jurisdiction                         │  │   privacy_jurisdiction_registry          │
  │──────────────────────────────────────────────────│  │──────────────────────────────────────────│
  │ PK id              int unsigned                  │  │ PK id              int unsigned          │
  │    code            varchar(30) UQ                │  │    code            varchar(30) UQ        │
  │    name            varchar(50)                   │  │    name / full_name / country / region   │
  │    full_name       varchar(255)                  │  │    regulator / regulator_url             │
  │    country         varchar(100)                  │  │    dsar_days / breach_hours              │
  │    region          varchar(50)                   │  │    effective_date / related_laws json     │
  │    regulator       varchar(255)                  │  │    icon / default_currency               │
  │    regulator_url   varchar(255)                  │  │    is_installed / installed_at            │
  │    dsar_days       int                           │  │    is_active / sort_order / config_data  │
  │    breach_hours    int                           │  │    created_at / updated_at               │
  │    effective_date  date                          │  └──────────────────────────────────────────┘
  │    related_laws    json                          │
  │    icon            varchar(10)                   │  ┌──────────────────────────────────────────┐
  │    is_active / sort_order                        │  │   privacy_lawful_basis                   │
  │    created_at / updated_at                       │  │──────────────────────────────────────────│
  └──────────────────────────────────────────────────┘  │ PK id              int unsigned          │
                                                        │    jurisdiction_code varchar(30)         │
  ┌──────────────────────────────────────────────────┐  │    code / name / description             │
  │     privacy_compliance_rule                      │  │    legal_reference  varchar(100)         │
  │──────────────────────────────────────────────────│  │    requires_consent / requires_lia       │
  │ PK id              int unsigned                  │  │    is_active / sort_order                │
  │    jurisdiction_code varchar(30)                 │  └──────────────────────────────────────────┘
  │    category        varchar(50)                   │
  │    code / name / description                     │  ┌──────────────────────────────────────────┐
  │    check_type      varchar(50)                   │  │   privacy_special_category               │
  │    field_name / condition / error_message        │  │──────────────────────────────────────────│
  │    legal_reference varchar(100)                  │  │ PK id              int unsigned          │
  │    severity ENUM(error/warning/info)             │  │    jurisdiction_code varchar(30)         │
  │    is_active / sort_order                        │  │    code / name / description             │
  └──────────────────────────────────────────────────┘  │    legal_reference  varchar(100)         │
                                                        │    requires_explicit_consent tinyint(1)  │
                                                        │    is_active / sort_order                │
                                                        └──────────────────────────────────────────┘

  ═══════════════════════  DATA INVENTORY & PROCESSING  ═══════════════════════════════

  ┌──────────────────────────────────────────────────┐  ┌──────────────────────────────────────────┐
  │     privacy_data_inventory                       │  │   privacy_processing_activity            │
  │──────────────────────────────────────────────────│  │──────────────────────────────────────────│
  │ PK id              int unsigned                  │  │ PK id              int unsigned          │
  │    name / description                            │  │    name / description                    │
  │    data_type ENUM(personal/special_category/     │  │    jurisdiction     varchar(20)          │
  │      children/criminal/financial/health/         │  │    purpose          text                 │
  │      biometric/genetic)                          │  │    lawful_basis / lawful_basis_code      │
  │    storage_location varchar(255)                 │  │    data_categories / data_subjects text  │
  │    storage_format ENUM(electronic/paper/both)    │  │    recipients / transfers text           │
  │    encryption      tinyint(1)                    │  │    third_countries  json                 │
  │    access_controls text                          │  │    retention_period varchar(100)         │
  │    retention_years / disposal_method             │  │    security_measures text                │
  │    is_active / created_at / updated_at           │  │    dpia_required / completed / date      │
  └──────────────────────────────────────────────────┘  │    status / owner / department           │
                                                        │    assigned_officer_id int               │
  ┌──────────────────────────────────────────────────┐  │    submitted_at/by / approved_at/by      │
  │     privacy_retention_schedule                   │  │    rejected_at/by / rejection_reason     │
  │──────────────────────────────────────────────────│  │    next_review_date / created_by         │
  │ PK id              int unsigned                  │  │    created_at / updated_at               │
  │    record_type     varchar(255)                  │  └───────────────┬──────────────────────────┘
  │    description     text                          │                  │
  │    retention_period varchar(100)                 │                  ▼
  │    retention_years  int                          │  ┌──────────────────────────────────────────┐
  │    legal_basis     varchar(255)                  │  │ privacy_processing_activity_i18n         │
  │    disposal_action ENUM(destroy/archive/         │  │──────────────────────────────────────────│
  │      anonymize/review)                           │  │ PK id (FK) / PK culture                 │
  │    jurisdiction    varchar(30)                   │  │    name / purpose / description          │
  │    is_active / created_at / updated_at           │  └──────────────────────────────────────────┘
  └──────────────────────────────────────────────────┘

  ═══════════════════════  ADMINISTRATION & SUPPORT  ══════════════════════════════════

  ┌────────────────────────────────────┐  ┌──────────────────────────────────────────┐
  │    privacy_officer                 │  │   privacy_institution_config             │
  │────────────────────────────────────│  │──────────────────────────────────────────│
  │ PK id           int unsigned      │  │ PK id               int unsigned         │
  │ FK user_id      int               │  │    repository_id     int UQ              │
  │    name / email / phone / title   │  │    jurisdiction_code varchar(30)         │
  │    jurisdiction  varchar(30)      │  │    organization_name varchar(255)        │
  │    registration_number varchar    │  │    registration_number / privacy_officer │
  │    appointed_date date            │  │    data_protection_email                 │
  │    is_active / created_at / upd   │  │    dsar_response_days / breach_notif_hrs │
  └────────────────────────────────────┘  │    retention_default_years / settings   │
                                          │    created_at / updated_at              │
  ┌────────────────────────────────────┐  └──────────────────────────────────────────┘
  │    privacy_config                  │
  │────────────────────────────────────│  ┌──────────────────────────────────────────┐
  │ PK id           int unsigned      │  │   privacy_notification                   │
  │    jurisdiction  varchar(50)      │  │──────────────────────────────────────────│
  │    organization_name varchar      │  │ PK id              int unsigned          │
  │    registration_number varchar    │  │ FK user_id          int                  │
  │    privacy_officer_id int unsign  │  │    entity_type / entity_id               │
  │    data_protection_email          │  │    notification_type / subject / message │
  │    dsar_response_days int         │  │    link varchar(500)                     │
  │    breach_notification_hours int  │  │    is_read / read_at                     │
  │    retention_default_years int    │  │    email_sent / email_sent_at            │
  │    is_active / settings json      │  │    created_by / created_at               │
  │    created_at / updated_at        │  └──────────────────────────────────────────┘
  └────────────────────────────────────┘
                                          ┌──────────────────────────────────────────┐
  ┌────────────────────────────────────┐  │   privacy_audit_log                     │
  │    privacy_approval_log            │  │──────────────────────────────────────────│
  │────────────────────────────────────│  │ PK id              int unsigned         │
  │ PK id           int unsigned      │  │    entity_type / entity_id               │
  │    entity_type / entity_id        │  │    action          varchar(50)           │
  │    action        varchar(50)      │  │    user_id         int                   │
  │    old_status / new_status        │  │    ip_address      varchar(45)           │
  │    comment       text             │  │    old_values / new_values json          │
  │    user_id / created_at           │  │    notes           text                  │
  └────────────────────────────────────┘  │    created_at                           │
                                          └──────────────────────────────────────────┘
  ┌──────────────────────────────────────────┐
  │    privacy_template                      │
  │──────────────────────────────────────────│
  │ PK id           int unsigned             │
  │    category     varchar(50)              │◄── breach_notification, consent_form, etc.
  │    name         varchar(255)             │
  │    content      text                     │
  │    file_path / file_name / file_size     │
  │    mime_type    varchar(100)             │
  │    is_active / created_at               │
  └──────────────────────────────────────────┘

  ═══════════════════════  VISUAL REDACTION  ═══════════════════════════════════════════

  ┌──────────────────────────────────────────────────┐  ┌──────────────────────────────────────────┐
  │     privacy_visual_redaction                     │  │   privacy_redaction_cache                │
  │──────────────────────────────────────────────────│  │──────────────────────────────────────────│
  │ PK id              bigint unsigned               │  │ PK id              bigint unsigned       │
  │    object_id       int                           │  │    object_id       int                   │
  │    digital_object_id int                         │  │    digital_object_id int                 │
  │    page_number     int                           │  │    original_path   varchar(500)          │
  │    region_type ENUM(rectangle/polygon/freehand)  │  │    redacted_path   varchar(500)          │
  │    coordinates     json                          │  │    file_type ENUM(pdf/image)             │
  │    normalized      tinyint(1)                    │  │    regions_hash    varchar(64)            │
  │    source ENUM(manual/auto_ner/auto_pii/imported)│  │    region_count    int                   │
  │    linked_entity_id bigint unsigned              │  │    file_size       bigint unsigned       │
  │    label           varchar(255)                  │  │    generated_at    datetime              │
  │    color           varchar(7)                    │  │    expires_at      datetime              │
  │    status ENUM(pending/approved/applied/rejected)│  └──────────────────────────────────────────┘
  │    created_by / reviewed_by / reviewed_at        │
  │    applied_at / created_at / updated_at          │
  └──────────────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
   GLAM/DAM & INFORMATION OBJECT LINKS:
     privacy_redaction_cache.object_id ──► information_object.id (redacted record)
     privacy_visual_redaction.object_id ─► information_object.id (visual redaction target)
     privacy_institution_config.repository_id ► repository.id (per-institution config)
     privacy_officer.user_id ────────────► user.id (extends actor.id in AtoM)
     All audit/log tables: user_id ──────► user.id

   EXTERNAL REFERENCES:  user.id (user_id, assigned_to, created_by, verified_by)
                         information_object.id (object_id via redaction tables)
                         repository.id (privacy_institution_config)
                         privacy_jurisdiction.code (jurisdiction_code)
  ════════════════════════════════════════════════════════════════════════════════════════
```

### Subsystem Summary

| Subsystem | Tables | Purpose |
|-----------|--------|---------|
| Breach Management | `privacy_breach`, `_i18n`, `_incident`, `_notification` | Data breach tracking, notifications, regulatory reporting |
| Consent | `privacy_consent`, `_i18n`, `_log`, `_record` | Consent definitions, individual consent records, withdrawal tracking |
| DSAR/PAIA Requests | `privacy_dsar`, `_i18n`, `_log`, `_request`, `privacy_paia_request`, `privacy_complaint` | Data subject access requests (DSAR), SA PAIA requests, complaints |
| Jurisdiction & Governance | `privacy_jurisdiction`, `_registry`, `_lawful_basis`, `_compliance_rule`, `_special_category`, `_request_type` | Multi-jurisdiction config, lawful bases, compliance rules |
| Data Inventory | `privacy_data_inventory`, `privacy_processing_activity`, `_i18n`, `privacy_retention_schedule` | Data mapping, processing register (ROPA), retention schedules |
| Administration | `privacy_officer`, `privacy_config`, `_institution_config`, `_notification`, `_approval_log`, `_audit_log`, `_template` | Officers, config, notifications, audit trail, document templates |
| Visual Redaction | `privacy_visual_redaction`, `privacy_redaction_cache` | Region-based PII redaction on digital objects with caching |

---

## 4. Security Classification & Embargo ERD (ahgSecurityClearancePlugin)

**20 tables** across 5 subsystems: Classification Core, User Clearance, Object Classification, Embargo, Access & Audit.

### Tables

```json
["security_classification","security_compartment","object_security_classification","object_classification_history","user_security_clearance","user_security_clearance_log","security_clearance_history","security_2fa_session","embargo","embargo_audit","embargo_exception","embargo_i18n","security_access_log","security_access_request","security_access_condition_link","security_audit_log","security_compliance_log","security_declassification_schedule","security_retention_schedule","security_watermark_log"]
```

### ERD Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                        ahgSecurityClearancePlugin — Entity Relationship Diagram                         │
│                                     Security Classification                                      │
└─────────────────────────────────────────────────────────────────────────────────────────────────────────┘

  ┌──────────────────────────────────┐          ┌──────────────────────────────────────┐
  │     security_classification      │          │       security_compartment           │
  │──────────────────────────────────│          │──────────────────────────────────────│
  │ PK id            int unsigned    │◄─┐       │ PK id             int unsigned       │
  │    code          varchar(20) UQ  │  │   ┌──►│    code           varchar(50) UQ     │
  │    level         tinyint UQ      │  │   │   │    name           varchar(255)       │
  │    name          varchar(100)    │  │   │   │    description    text               │
  │    description   text            │  │   │   │ FK min_clearance_id int unsigned ────┤──► security_classification.id
  │    color         varchar(20)     │  │   │   │    requires_need_to_know tinyint(1)  │
  │    icon          varchar(100)    │  │   │   │    requires_briefing    tinyint(1)   │
  │    requires_justification  t(1)  │  │   │   │    active         tinyint(1)         │
  │    requires_approval       t(1)  │  │   │   │    created_at     timestamp          │
  │    requires_2fa            t(1)  │  │   │   │    updated_at     timestamp          │
  │    max_session_hours       int   │  │   │   └──────────────────────────────────────┘
  │    watermark_required      t(1)  │  │   │
  │    watermark_image  varchar(255) │  │   │
  │    download_allowed        t(1)  │  │   │
  │    print_allowed           t(1)  │  │   │
  │    copy_allowed            t(1)  │  │   │
  │    active          tinyint(1)    │  │   │
  │    created_at      datetime      │  │   │
  │    updated_at      datetime      │  │   │
  └──────────────────────────────────┘  │   │
       ▲    ▲    ▲    ▲                 │   │
       │    │    │    │                 │   │
       │    │    │    │                 │   │
  ┌────┤    │    │    └─────────────────┤───┤──────────────────────────────────────────────────┐
  │    │    │    │                      │   │                                                  │
  │    │    │    │                      │   │                                                  │
  │    │    │    │   ┌──────────────────────────────────────────────┐                          │
  │    │    │    │   │      object_security_classification          │                          │
  │    │    │    │   │──────────────────────────────────────────────│                          │
  │    │    │    │   │ PK id                 int unsigned           │                          │
  │    │    │    │   │ FK object_id           int UQ ───────────────┤──► information_object.id │
  │    │    │    └───┤ FK classification_id   int unsigned          │                          │
  │    │    │        │ FK classified_by       int ─────────────────►│    user.id               │
  │    │    │        │    classified_at       timestamp             │                          │
  │    │    │        │ FK assigned_by         int unsigned ────────►│    user.id               │
  │    │    │        │    assigned_at         datetime              │                          │
  │    │    │        │    review_date         date                  │                          │
  │    │    │        │    declassify_date     date                  │                          │
  │    │    │        │    declassify_to_id    int unsigned          │                          │
  │    │    │        │    reason              text                  │                          │
  │    │    │        │    handling_instructions text                │                          │
  │    │    │        │    inherit_to_children  tinyint(1)           │                          │
  │    │    │        │    justification       text                  │                          │
  │    │    │        │    active              tinyint(1)            │                          │
  │    │    │        │    created_at / updated_at                   │                          │
  │    │    │        └──────────────────────────────────────────────┘                          │
  │    │    │                                                                                  │
  │    │    │        ┌──────────────────────────────────────────────┐                          │
  │    │    │        │   object_classification_history              │                          │
  │    │    │        │──────────────────────────────────────────────│                          │
  │    │    │        │ PK id                  int unsigned          │                          │
  │    │    │        │ FK object_id           int ─────────────────►│ information_object.id    │
  │    │    └────────┤ FK previous_classification_id int unsigned   │                          │
  │    └─────────────┤ FK new_classification_id      int unsigned   │                          │
  │                  │    action              varchar(50)           │                          │
  │                  │ FK changed_by          int ────────────────►│ user.id                   │
  │                  │    reason              text                  │                          │
  │                  │    created_at          timestamp             │                          │
  │                  └──────────────────────────────────────────────┘                          │
  │                                                                                            │
  │                  ┌──────────────────────────────────────────────┐                          │
  │                  │     user_security_clearance                  │                          │
  │                  │──────────────────────────────────────────────│                          │
  │                  │ PK id              int unsigned              │                          │
  │                  │ FK user_id         int unsigned UQ ─────────►│ user.id                  │
  │                  │ FK classification_id int unsigned ───────────┤──► security_classif.id    │
  │                  │ FK granted_by      int unsigned ────────────►│ user.id                  │
  │                  │    granted_at      datetime                  │                          │
  │                  │    expires_at      datetime                  │                          │
  │                  │    notes           text                      │                          │
  │                  └──────────────────────────────────────────────┘                          │
  │                                                                                            │
  │                  ┌──────────────────────────────────────────────┐                          │
  │                  │     user_security_clearance_log              │                          │
  │                  │──────────────────────────────────────────────│                          │
  │                  │ PK id              int unsigned              │                          │
  │                  │ FK user_id         int unsigned ────────────►│ user.id                  │
  │                  │    classification_id int unsigned            │                          │
  │                  │    action  ENUM(granted/revoked/updated/     │                          │
  │                  │                   expired)                   │                          │
  │                  │ FK changed_by      int unsigned ────────────►│ user.id                  │
  │                  │    notes           text                      │                          │
  │                  │    created_at      timestamp                 │                          │
  │                  └──────────────────────────────────────────────┘                          │
  │                                                                                            │
  │                  ┌──────────────────────────────────────────────┐                          │
  │                  │     security_clearance_history               │                          │
  │                  │──────────────────────────────────────────────│                          │
  │                  │ PK id                  int unsigned          │                          │
  │                  │ FK user_id             int unsigned ────────►│ user.id                  │
  │                  │ FK previous_classification_id int unsigned ──┤──► security_classif.id    │
  │                  │ FK new_classification_id int unsigned ───────┤──► security_classif.id    │
  │                  │    action  ENUM(granted/upgraded/downgraded/ │                          │
  │                  │       revoked/renewed/expired/2fa_*)         │                          │
  │                  │    changed_by          int unsigned          │                          │
  │                  │    reason              text                  │                          │
  │                  │    created_at          timestamp             │                          │
  │                  └──────────────────────────────────────────────┘                          │
  │                                                                                            │
  │                  ┌──────────────────────────────────────────────┐                          │
  │                  │     security_2fa_session                     │                          │
  │                  │──────────────────────────────────────────────│                          │
  │                  │ PK id              int unsigned              │                          │
  │                  │ FK user_id         int unsigned ────────────►│ user.id                  │
  │                  │    session_id      varchar(100) UQ           │                          │
  │                  │    verified_at     timestamp                 │                          │
  │                  │    expires_at      timestamp                 │                          │
  │                  │    ip_address      varchar(45)               │                          │
  │                  │    device_fingerprint varchar(255)           │                          │
  │                  │    created_at      timestamp                 │                          │
  │                  └──────────────────────────────────────────────┘                          │
  │                                                                                            │
  │                                                                                            │
  │    ═══════════════════════════════  EMBARGO SUBSYSTEM  ═══════════════════════════════      │
  │                                                                                            │
  │                  ┌──────────────────────────────────────────────┐                          │
  │                  │              embargo                         │                          │
  │                  │──────────────────────────────────────────────│                          │
  │                  │ PK id              bigint unsigned           │                          │
  │                  │ FK object_id       int ─────────────────────►│ information_object.id    │
  │                  │    embargo_type  ENUM(full/metadata_only/    │                          │
  │                  │                   digital_object/custom)     │                          │
  │                  │    start_date      date                      │                          │
  │                  │    end_date        date                      │                          │
  │                  │    reason          text                      │                          │
  │                  │    is_perpetual    tinyint(1)                │                          │
  │                  │    status ENUM(active/expired/lifted/pending)│                          │
  │                  │ FK created_by      int ─────────────────────►│ user.id                  │
  │                  │    lifted_by / lifted_at / lift_reason       │                          │
  │                  │    notify_on_expiry    tinyint(1)            │                          │
  │                  │    notify_days_before  int                   │                          │
  │                  │    created_at / updated_at / is_active       │                          │
  │                  └─────────────┬────────────────────────────────┘                          │
  │                                │                                                           │
  │                    ┌───────────┼───────────┐                                               │
  │                    ▼           ▼           ▼                                                │
  │   ┌────────────────────┐ ┌──────────────────────┐ ┌───────────────────────────┐            │
  │   │   embargo_audit    │ │  embargo_exception   │ │      embargo_i18n         │            │
  │   │────────────────────│ │──────────────────────│ │───────────────────────────│            │
  │   │ PK id       bigint │ │ PK id       bigint   │ │ PK id         bigint      │            │
  │   │ FK embargo_id      │ │ FK embargo_id        │ │ FK embargo_id             │            │
  │   │    action ENUM(    │ │    exception_type     │ │    culture     varchar(10)│            │
  │   │      created/      │ │      ENUM(user/group/ │ │    reason      varchar    │            │
  │   │      modified/     │ │      ip_range/        │ │    notes       text       │            │
  │   │      lifted/       │ │      repository)      │ │    public_message text    │            │
  │   │      extended/     │ │    exception_id       │ └───────────────────────────┘            │
  │   │      exception_*)  │ │    ip_range_start/end │                                         │
  │   │    user_id         │ │    valid_from/until   │                                         │
  │   │    old_values json │ │    notes    text      │                                         │
  │   │    new_values json │ │    granted_by int     │                                         │
  │   │    ip_address      │ │    created_at         │                                         │
  │   │    created_at      │ │    updated_at         │                                         │
  │   └────────────────────┘ └──────────────────────┘                                         │
  │                                                                                            │
  │                                                                                            │
  │    ═══════════════════════════  ACCESS & AUDIT SUBSYSTEM  ══════════════════════════        │
  │                                                                                            │
  │   ┌────────────────────────────────┐  ┌────────────────────────────────────────┐           │
  │   │     security_access_log        │  │     security_access_request            │           │
  │   │────────────────────────────────│  │────────────────────────────────────────│           │
  │   │ PK id           bigint unsigned│  │ PK id              int unsigned       │           │
  │   │ FK user_id      int unsigned   │  │ FK user_id          int unsigned ────►│ user      │
  │   │ FK object_id    int            │  │ FK object_id        int unsigned ────►│ io        │
  │   │ FK classification_id int unsign│  │ FK classification_id int unsigned     │           │
  │   │    action        varchar(50)   │  │ FK compartment_id   int unsigned ────►│ compartm. │
  │   │    access_granted tinyint(1)   │  │    request_type ENUM(view/download/   │           │
  │   │    denial_reason varchar(255)  │  │      print/clearance_upgrade/         │           │
  │   │    justification text          │  │      compartment_access/renewal)      │           │
  │   │    ip_address    varchar(45)   │  │    justification    text              │           │
  │   │    user_agent    varchar(255)  │  │    duration_hours   int               │           │
  │   │    created_at    datetime      │  │    priority ENUM(normal/urgent/immed.)│           │
  │   └────────────────────────────────┘  │    status ENUM(pending/approved/      │           │
  │                                       │      denied/expired/cancelled)        │           │
  │   ┌────────────────────────────────┐  │    reviewed_by / reviewed_at          │           │
  │   │   security_audit_log           │  │    review_notes / access_granted_until│           │
  │   │────────────────────────────────│  │    created_at / updated_at            │           │
  │   │ PK id            int           │  └────────────────────────────────────────┘           │
  │   │    object_id     int           │                                                       │
  │   │    object_type   varchar(50)   │  ┌────────────────────────────────────────┐           │
  │   │    user_id       int           │  │  security_access_condition_link        │           │
  │   │    user_name     varchar(255)  │  │────────────────────────────────────────│           │
  │   │    action        varchar(100)  │  │ PK id              int unsigned       │           │
  │   │    action_category varchar(50) │  │ FK object_id        int ──────────────►│ io       │
  │   │    details       json          │  │ FK classification_id int unsigned      │           │
  │   │    ip_address    varchar(45)   │  │    access_conditions  text             │           │
  │   │    user_agent    text          │  │    reproduction_conditions text        │           │
  │   │    created_at    datetime      │  │    narssa_ref        varchar(100)      │           │
  │   └────────────────────────────────┘  │    retention_period  varchar(50)       │           │
  │                                       │    updated_by / updated_at             │           │
  │   ┌────────────────────────────────┐  └────────────────────────────────────────┘           │
  │   │  security_compliance_log       │                                                       │
  │   │────────────────────────────────│  ┌────────────────────────────────────────┐           │
  │   │ PK id            int unsigned  │  │  security_watermark_log               │           │
  │   │    action        varchar(100)  │  │────────────────────────────────────────│           │
  │   │    object_id     int           │  │ PK id              bigint unsigned    │           │
  │   │    user_id       int           │  │ FK user_id          int unsigned      │           │
  │   │    username      varchar(255)  │  │ FK object_id        int unsigned      │           │
  │   │    details       text          │  │    digital_object_id int unsigned     │           │
  │   │    ip_address    varchar(45)   │  │    watermark_type ENUM(visible/       │           │
  │   │    hash          varchar(64)   │  │      invisible/both)                  │           │
  │   │    created_at    datetime      │  │    watermark_text   varchar(500)      │           │
  │   └────────────────────────────────┘  │    watermark_code   varchar(100)      │           │
  │                                       │    file_hash        varchar(64)       │           │
  │   ┌────────────────────────────────┐  │    file_name        varchar(255)      │           │
  │   │  security_declassification_    │  │    ip_address       varchar(45)       │           │
  │   │  schedule                      │  │    created_at       timestamp         │           │
  │   │────────────────────────────────│  └────────────────────────────────────────┘           │
  │   │ PK id            int unsigned  │                                                       │
  │   │ FK object_id     int unsigned  │  ┌────────────────────────────────────────┐           │
  │   │    scheduled_date date         │  │  security_retention_schedule           │           │
  │   │ FK from_classification_id      │  │────────────────────────────────────────│           │
  │   │ FK to_classification_id        │  │ PK id              int unsigned       │           │
  │   │    trigger_type ENUM(date/     │  │    narssa_ref       varchar(100) UQ   │           │
  │   │      event/retention)          │  │    record_type      varchar(255)      │           │
  │   │    trigger_event varchar(255)  │  │    retention_period varchar(100)      │           │
  │   │    processed     tinyint(1)    │  │    disposal_action  varchar(100)      │           │
  │   │    processed_at / processed_by │  │    legal_reference  text              │           │
  │   │    notes         text          │  │    notes            text              │           │
  │   │    created_at    timestamp     │  │    created_at       datetime          │           │
  │   └────────────────────────────────┘  └────────────────────────────────────────┘           │
  │                                                                                            │
  │  ══════════════════════════════════════════════════════════════════════════════════════      │
  │   GLAM/DAM & INFORMATION OBJECT LINKS:                                                      │
  │     object_security_classification.object_id ► information_object.id (classified record)   │
  │     object_compartment.object_id ────────────► information_object.id (compartmented record) │
  │     object_access_grant.object_id ───────────► information_object.id (granted access)      │
  │     object_classification_history.object_id ─► information_object.id (classification audit)│
  │     object_declassification_schedule.object_id ► information_object.id                     │
  │     security_access_log.object_id ───────────► information_object.id (access event)        │
  │     security_access_request.object_id ───────► information_object.id (access request)      │
  │     security_watermark_log.object_id ────────► information_object.id (watermark applied)   │
  │     user_security_clearance.user_id ─────────► user.id (extends actor.id in AtoM)         │
  │     user_compartment_access.user_id ─────────► user.id                                     │
  │     security_2fa_session.user_id ────────────► user.id                                     │
  │   INTERNAL: security_classification.id, security_compartment.id (referenced by object_*)   │
  └────────────────────────────────────────────────────────────────────────────────────────────┘
```

### Subsystem Summary

| Subsystem | Tables | Purpose |
|-----------|--------|---------|
| Classification Core | `security_classification`, `security_compartment` | Define classification levels (U/R/C/S/TS) and compartments |
| User Clearance | `user_security_clearance`, `user_security_clearance_log`, `security_clearance_history`, `security_2fa_session` | User clearance assignment, history, 2FA sessions |
| Object Classification | `object_security_classification`, `object_classification_history` | Classify archival records, track changes |
| Embargo | `embargo`, `embargo_audit`, `embargo_exception`, `embargo_i18n` | Time-based access restrictions with exceptions and i18n |
| Access & Audit | `security_access_log`, `security_access_request`, `security_access_condition_link`, `security_audit_log`, `security_compliance_log`, `security_declassification_schedule`, `security_retention_schedule`, `security_watermark_log` | Access logging, requests, compliance, watermarks, International retention |

### Access Control Logic

```
User can access a record when:
  user_security_clearance.classification.level >= object_security_classification.classification.level
  AND (no active embargo on object OR user has embargo_exception)
  AND (no compartment restriction OR user has compartment access)
  AND clearance has not expired (user_security_clearance.expires_at)
```

---

## 5. Condition Assessment ERD (ahgConditionPlugin)

**8 tables** — Condition reporting, damage tracking, conservation, scheduling, and controlled vocabularies. Spectrum 5.1 compliant.

### Tables

```json
["condition_report","condition_damage","condition_image","condition_event","condition_assessment_schedule","condition_conservation_link","condition_vocabulary","condition_vocabulary_term"]
```

### ERD Diagram

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                          ahgConditionPlugin — Condition Assessment ERD                                │
│                                   Spectrum 5.1 Compliance                                            │
└──────────────────────────────────────────────────────────────────────────────────────────────────────┘

  ┌──────────────────────────────────────────────────┐
  │          condition_report                        │
  │──────────────────────────────────────────────────│
  │ PK id                bigint unsigned             │
  │ FK information_object_id int unsigned ──────────►│ information_object.id
  │    assessor_user_id  int unsigned                │
  │    assessment_date   date                        │
  │    context           ENUM(acquisition/loan_out/  │
  │      loan_in/loan_return/exhibition/storage/     │
  │      conservation/routine/incident/insurance/    │
  │      deaccession)                                │
  │    overall_rating    ENUM(excellent/good/fair/   │
  │                        poor/unacceptable)        │
  │    summary           text                        │
  │    recommendations   text                        │
  │    priority          ENUM(low/normal/high/urgent)│
  │    next_check_date   date                        │
  │    environmental_notes text                      │
  │    handling_notes    text                        │
  │    display_notes     text                        │
  │    storage_notes     text                        │
  │    created_at / updated_at timestamp             │
  └─────────────┬──────────────────────────┬─────────┘
                │ 1:N                      │ 1:N
                ▼                          ▼
  ┌────────────────────────────┐  ┌────────────────────────────────┐
  │     condition_damage       │  │      condition_image           │
  │────────────────────────────│  │────────────────────────────────│
  │ PK id       bigint unsigned│  │ PK id         bigint unsigned  │
  │ FK condition_report_id     │  │ FK condition_report_id         │
  │    damage_type varchar(50) │  │    digital_object_id int unsign│
  │    location   varchar(50)  │  │    file_path   varchar(500)    │
  │    severity ENUM(minor/    │  │    caption     varchar(500)    │
  │      moderate/severe)      │  │    image_type ENUM(general/    │
  │    description text        │  │      detail/damage/before/     │
  │    dimensions varchar(100) │  │      after/raking/uv)          │
  │    is_active  tinyint(1)   │  │    annotations json            │
  │    treatment_required t(1) │  │    created_at  timestamp       │
  │    treatment_notes text    │  └────────────────────────────────┘
  │    created_at timestamp    │
  └────────────────────────────┘

  ┌──────────────────────────────────────────────────┐
  │          condition_event                         │
  │──────────────────────────────────────────────────│
  │ PK id               int unsigned                │
  │ FK object_id        int ────────────────────────►│ information_object.id
  │    event_type       varchar(50)                  │◄── assessment, treatment, incident
  │    event_date       date                         │
  │    assessor         varchar(255)                 │
  │    condition_status varchar(50)                  │
  │    damage_types     json                         │
  │    severity         varchar(50)                  │
  │    notes            text                         │
  │    risk_score       decimal(5,2)                 │
  │    created_by       int                          │
  │    created_at / updated_at                       │
  └─────────────┬────────────────────────────────────┘
                │ 1:N
                ▼
  ┌──────────────────────────────────┐
  │   condition_conservation_link    │
  │──────────────────────────────────│
  │ PK id            int unsigned    │
  │ FK condition_event_id int unsign │
  │ FK treatment_id  int unsigned    │
  │    link_type     varchar(50)     │◄── treatment
  │    created_at    datetime        │
  └──────────────────────────────────┘

  ┌──────────────────────────────────────┐  ┌──────────────────────────────────────┐
  │  condition_assessment_schedule       │  │    condition_vocabulary (43 rows)     │
  │──────────────────────────────────────│  │──────────────────────────────────────│
  │ PK id            int unsigned        │  │ PK id            int unsigned        │
  │ FK object_id     int                 │  │    vocabulary_type ENUM(damage_type/ │
  │    frequency_months int              │  │      severity/condition/priority/    │
  │    last_assessment_date date         │  │      material/location_zone)         │
  │    next_due_date  date               │  │    code / display_name / description│
  │    priority       varchar(20)        │  │    color / icon                      │
  │    notes          text               │  │    sort_order / is_active            │
  │    is_active      tinyint(1)         │  │    created_at / updated_at           │
  └──────────────────────────────────────┘  └──────────────────────────────────────┘

                                            ┌──────────────────────────────────────┐
                                            │  condition_vocabulary_term           │
                                            │──────────────────────────────────────│
                                            │ PK id            int unsigned        │
                                            │    vocabulary_type varchar(50)       │
                                            │    term_code / term_label            │
                                            │    term_description text             │
                                            │    sort_order / is_active            │
                                            └──────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════
   GLAM/DAM & INFORMATION OBJECT LINKS:
     condition_report.information_object_id ► information_object.id (assessed record)
     condition_event.object_id ─────────────► information_object.id (event target)
     condition_assessment_schedule.object_id ► information_object.id (scheduled check)
     condition_report.assessor_user_id ─────► user.id (extends actor.id in AtoM)
     condition_event.created_by ────────────► user.id
     condition_image.digital_object_id ─────► digital_object.id (photo documentation)

   CROSS-PLUGIN: condition_conservation_link.treatment_id ► spectrum_conservation.id
                 (links to ahgSpectrumPlugin for Spectrum 5.1 conservation treatments)
                 ahg_loan_condition_report (ahgLoanPlugin) references condition data
                 ahg_ai_condition_assessment (ahgAIPlugin) AI-powered assessments
  ════════════════════════════════════════════════════════════════════════
```

---

## 6. Loan Management ERD (ahgLoanPlugin)

**20 tables** (4 base + 16 ahg_loan_*) — Full GLAM loan lifecycle: requests, objects, condition reports, facility reports, shipments, couriers, costs, notifications.

### Tables

```json
["loan","loan_object","loan_document","loan_extension","ahg_loan","ahg_loan_object","ahg_loan_document","ahg_loan_extension","ahg_loan_condition_report","ahg_loan_condition_image","ahg_loan_cost","ahg_loan_courier","ahg_loan_facility_report","ahg_loan_facility_image","ahg_loan_history","ahg_loan_notification_log","ahg_loan_notification_template","ahg_loan_shipment","ahg_loan_shipment_event","ahg_loan_status_history"]
```

### ERD Diagram

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                             ahgLoanPlugin — Loan Management ERD                                      │
│                       Shared Loan System for GLAM Institutions                                       │
└──────────────────────────────────────────────────────────────────────────────────────────────────────┘

  ═══════════════════════════  CORE LOAN (base + ahg)  ════════════════════════════════

  ┌──────────────────────────────────────────────────┐  ┌──────────────────────────────────────────────────┐
  │          loan (base)                             │  │          ahg_loan (extended, 3 rows)              │
  │──────────────────────────────────────────────────│  │──────────────────────────────────────────────────│
  │ PK id               bigint unsigned              │  │ PK id               bigint unsigned              │
  │    loan_number      varchar(50) UQ               │  │    loan_number      varchar(50) UQ               │
  │    loan_type        ENUM(out/in)                 │  │    loan_type        ENUM(out/in)                 │
  │    status           varchar(50)                  │  │    sector ENUM(museum/gallery/archive/           │
  │    purpose          ENUM(exhibition/research/    │  │      library/dam)                                │
  │      conservation/photography/education/         │  │    title / description / purpose varchar(100)    │
  │      filming/long_term/other)                    │  │    partner_institution / contact_name/email/phone│
  │    title / description                           │  │    partner_address text                          │
  │    partner_institution / contact_name/email/phone│  │    request_date / start_date / end_date / return │
  │    partner_address text                          │  │    insurance_type ENUM(6 values) / value /       │
  │    request_date / start_date / end_date / return │  │      currency / policy_number / provider         │
  │    insurance_type ENUM(5 values) / value /       │  │    loan_fee / loan_fee_currency                  │
  │      currency / policy_number / provider         │  │    status           varchar(50)                  │
  │    loan_fee / loan_fee_currency                  │  │    internal_approver_id / approved_date          │
  │    internal_approver_id / approved_date          │  │    exhibition_id / repository_id / sector_data   │
  │    notes / created_by / created_at / updated_at  │  │    notes / created_by / updated_by / timestamps │
  └─────────────┬────────────────────────────────────┘  └─────────────┬────────────────────────────────────┘
                │                                                     │
     ┌──────────┼──────────┐                           ┌──────────────┼──────────────────┐
     ▼          ▼          ▼                           ▼              ▼                  ▼
  ┌──────────┐ ┌─────────┐ ┌─────────┐  ┌──────────────────┐ ┌──────────────┐ ┌──────────────────┐
  │loan_     │ │loan_    │ │loan_    │  │ahg_loan_object   │ │ahg_loan_     │ │ahg_loan_         │
  │object    │ │document │ │extension│  │  (1 row)         │ │document      │ │extension         │
  │──────────│ │─────────│ │─────────│  │──────────────────│ │──────────────│ │──────────────────│
  │ id       │ │ id      │ │ id      │  │ id               │ │ id           │ │ id               │
  │ loan_id  │ │ loan_id │ │ loan_id │  │ loan_id          │ │ loan_id      │ │ loan_id          │
  │ info_obj │ │ doc_type│ │ prev_end│  │ info_object_id   │ │ document_type│ │ prev/new end_date│
  │ title    │ │ file_*  │ │ new_end │  │ external_obj_id  │ │ file_*       │ │ reason / approved│
  │ identifier││ descript│ │ reason  │  │ title/identifier │ │ description  │ │ created_at       │
  │ ins_value│ │ uploaded│ │ approved│  │ object_type      │ │ uploaded_by  │ └──────────────────┘
  │ cond_rpt │ │ created │ │ created │  │ ins_value / cond │ │ created_at   │
  │ spec_req │ └─────────┘ └─────────┘  │ departure/return │ └──────────────┘
  │ display  │                          │ special/display  │
  │ created  │                          │ status ENUM(8)   │
  └──────────┘                          │ dispatch/receive │
                                        │ return dates     │
                                        │ created/updated  │
                                        └──────────────────┘

  ═══════════════════════  CONDITION & FACILITY REPORTS  ═══════════════════════════════

  ┌──────────────────────────────────────────────────┐  ┌──────────────────────────────────────────┐
  │  ahg_loan_condition_report (35 columns)          │  │  ahg_loan_facility_report (36 columns)   │
  │──────────────────────────────────────────────────│  │──────────────────────────────────────────│
  │ PK id               bigint unsigned              │  │ PK id               bigint unsigned      │
  │ FK loan_id / loan_object_id / info_object_id     │  │ FK loan_id                               │
  │    report_type ENUM(pre_loan/post_loan/          │  │    venue_name / address / contact_*       │
  │      in_transit/periodic)                        │  │    assessment_date / assessed_by          │
  │    examination_date / examiner_id / examiner_name│  │    has_climate_control / temp_min/max     │
  │    location          varchar(255)                │  │    humidity_min/max / has_uv_filtering    │
  │    overall_condition ENUM(5 values)              │  │    light_levels_lux                       │
  │    condition_stable  tinyint(1)                  │  │    has_24hr_security / cctv / alarm       │
  │    structural/surface_condition text             │  │    has_fire_suppression / type             │
  │    has_damage / damage_description               │  │    security_notes                         │
  │    has_previous_repairs / repair_description     │  │    display_case_type / mounting_method     │
  │    has_active_deterioration / description        │  │    barrier_distance / storage_type        │
  │    height/width/depth_cm / weight_kg             │  │    public_access_hours / staff_supervision│
  │    handling/mounting/environmental requirements  │  │    photography_allowed                    │
  │    treatment/display_recommendations             │  │    overall_rating ENUM(5 values)          │
  │    signed_by_lender/borrower + signature_dates   │  │    recommendations / conditions_required  │
  │    pdf_generated / pdf_path                      │  │    approved / approved_by / approved_date │
  │    created_at / updated_at                       │  │    created_at / updated_at                │
  └─────────────┬────────────────────────────────────┘  └─────────────┬────────────────────────────┘
                ▼                                                     ▼
  ┌──────────────────────────────┐              ┌──────────────────────────────────┐
  │ ahg_loan_condition_image     │              │ ahg_loan_facility_image          │
  │──────────────────────────────│              │──────────────────────────────────│
  │ id / condition_report_id     │              │ id / facility_report_id          │
  │ file_path/name/mime_type     │              │ file_path/name/mime_type         │
  │ image_type / caption         │              │ caption / image_type             │
  │ annotation_data json         │              │ created_at                       │
  │ view_position / sort_order   │              └──────────────────────────────────┘
  └──────────────────────────────┘

  ═══════════════════════  SHIPPING & LOGISTICS  ══════════════════════════════════════

  ┌──────────────────────────────────────────────────┐  ┌──────────────────────────────────┐
  │  ahg_loan_shipment                               │  │  ahg_loan_courier (10 rows)      │
  │──────────────────────────────────────────────────│  │──────────────────────────────────│
  │ PK id               bigint unsigned              │  │ PK id            bigint unsigned  │
  │ FK loan_id / courier_id                          │  │    company_name  varchar(255)     │
  │    shipment_type ENUM(outbound/return)           │  │    contact_name / email / phone   │
  │    shipment_number / tracking_number / waybill   │  │    address text / website         │
  │    origin_address / destination_address          │  │    is_art_specialist tinyint(1)   │
  │    scheduled_pickup / actual_pickup              │  │    has_climate_control tinyint(1) │
  │    scheduled_delivery / actual_delivery          │  │    has_gps_tracking tinyint(1)    │
  │    status ENUM(planned/picked_up/in_transit/     │  │    insurance_coverage decimal     │
  │      customs/out_for_delivery/delivered/         │  │    insurance_currency varchar(3)  │
  │      failed/returned)                            │  │    quality_rating decimal(3,2)    │
  │    handling_instructions / special_requirements  │  │    notes / is_active              │
  │    shipping/insurance/customs/total_cost decimal │  │    created_at / updated_at        │
  │    cost_currency / notes / created_by            │  └──────────────────────────────────┘
  │    created_at / updated_at                       │
  └─────────────┬────────────────────────────────────┘
                ▼
  ┌──────────────────────────────────┐
  │  ahg_loan_shipment_event        │
  │──────────────────────────────────│
  │ id / shipment_id                 │
  │ event_time / event_type          │
  │ location / description           │
  │ created_at                       │
  └──────────────────────────────────┘

  ═══════════════════════  COSTS & TRACKING  ═══════════════════════════════════════════

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐  ┌──────────────────────────────┐
  │  ahg_loan_cost                   │  │  ahg_loan_history (2 rows)       │  │ ahg_loan_status_history      │
  │──────────────────────────────────│  │──────────────────────────────────│  │──────────────────────────────│
  │ id / loan_id                     │  │ id / loan_id                     │  │ id / loan_id                 │
  │ cost_type varchar(50)            │  │ action varchar(100)              │  │ from_status / to_status      │
  │ description / amount decimal     │  │ details json                     │  │ changed_by / comment         │
  │ currency / vendor / invoice_*    │  │ user_id / created_at             │  │ created_at                   │
  │ paid tinyint / paid_date         │  └──────────────────────────────────┘  └──────────────────────────────┘
  │ paid_by ENUM(lender/borrower/    │
  │   shared)                        │  ┌──────────────────────────────────┐  ┌──────────────────────────────┐
  │ notes / created_by               │  │ ahg_loan_notification_template   │  │ ahg_loan_notification_log    │
  │ created_at / updated_at          │  │        (5 rows)                  │  │──────────────────────────────│
  └──────────────────────────────────┘  │──────────────────────────────────│  │ id / loan_id / template_id   │
                                        │ id / code UQ / name / description│  │ notification_type            │
                                        │ sector / subject_template        │  │ recipient_email / name       │
                                        │ body_template / trigger_event    │  │ subject / body               │
                                        │ trigger_days_before / is_active  │  │ status ENUM(pending/sent/    │
                                        │ created_at / updated_at          │  │   failed/bounced)            │
                                        └──────────────────────────────────┘  │ sent_at / error_message      │
                                                                              │ created_at                   │
                                                                              └──────────────────────────────┘

  ═══════════════════════  GLAM/DAM & INFORMATION OBJECT LINKS  ═══════════════════════

  ┌──────────────────────────────────────────────────────────────────────────────────┐
  │  ahg_loan                                                                        │
  │    sector ENUM ─────────► museum | gallery | archive | library | dam             │
  │    exhibition_id ───────► exhibition.id (ahgExhibitionPlugin)                    │
  │    repository_id ───────► repository.id (AtoM core — archival institution)       │
  │    sector_data JSON ────► Sector-specific metadata (e.g. gallery provenance)     │
  │    internal_approver_id ► user.id                                                │
  │    created_by / updated_by ► user.id                                             │
  │                                                                                  │
  │  ahg_loan_object                                                                 │
  │    information_object_id ► information_object.id (AtoM core — archival record)   │
  │    external_object_id ──► External ref for non-AtoM objects (gallery/museum)     │
  │    object_type ─────────► archive | museum_object | gallery_artwork | dam_asset  │
  │    condition_report_id ─► ahg_loan_condition_report.id                           │
  │                                                                                  │
  │  ahg_loan_condition_report                                                       │
  │    information_object_id ► information_object.id                                 │
  │    examiner_id ─────────► user.id                                                │
  │                                                                                  │
  │  loan_object (base)                                                              │
  │    information_object_id ► information_object.id                                 │
  │                                                                                  │
  │  loan (base)                                                                     │
  │    partner_institution ─► Free text (borrower/lender institution name)           │
  │    created_by ──────────► user.id                                                │
  └──────────────────────────────────────────────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
   DUAL TABLE PATTERN: `loan` (base) + `ahg_loan` (extended with sector, exhibition link)
   Base tables: loan, loan_object, loan_document, loan_extension
   Extended: ahg_loan + 15 ahg_loan_* tables for full lifecycle management
   GLAM SECTOR AWARE: sector ENUM drives UI/workflow per institution type
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 7. Heritage Accounting ERD (Heritage Assets / IPSAS 45)

**Plugins:** ahgHeritageAccountingPlugin (12 tables) + ahgIPSASPlugin (10 tables)
**Standards:** Heritage Assets (South Africa), IPSAS 45 (International)
**Total: 22 tables** (+ 1 legacy view: `grap_heritage_asset`)

```
  ════════════════════════════════════════════════════════════════════════════════════════
  HERITAGE ACCOUNTING ERD — ahgHeritageAccountingPlugin (12 tables)
  Heritage Assets heritage asset accounting: recognition, valuation, depreciation, impairment
  ════════════════════════════════════════════════════════════════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  heritage_accounting_standard (10 rows)                                           │
  │───────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       int unsigned AUTO_INCREMENT                           │
  │ UQ code                     varchar(20)                                           │
  │    name                     varchar(100)                                          │
  │    country                  varchar(50)                                           │
  │ IX region_code              varchar(30)                                           │
  │    description              text                                                 │
  │    capitalisation_required  tinyint(1)                                            │
  │    valuation_methods        json                                                 │
  │    disclosure_requirements  json                                                 │
  │    is_active                tinyint(1)                                            │
  │    sort_order               int                                                  │
  │    created_at               datetime                                             │
  └───────────────────┬─────────────────────────────────────────────────────────────────┘
                      │ 1:N
  ┌───────────────────▼─────────────────────────────────────────────────────────────────┐
  │  heritage_compliance_rule (233 rows)                                                │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       int unsigned AUTO_INCREMENT                             │
  │ FK standard_id              int unsigned → heritage_accounting_standard.id          │
  │ IX category                 ENUM(recognition, measurement, disclosure)              │
  │    code                     varchar(50)                                             │
  │    name                     varchar(255)                                            │
  │    description              text                                                   │
  │    check_type               ENUM(required_field, value_check, date_check, custom)   │
  │    field_name               varchar(100)                                            │
  │    condition                varchar(255)                                            │
  │    error_message            varchar(255) NOT NULL                                   │
  │    reference                varchar(100)                                            │
  │    severity                 ENUM(error, warning, info)                              │
  │    is_active                tinyint(1)                                              │
  │    sort_order               int                                                    │
  │    created_at               datetime                                               │
  └─────────────────────────────────────────────────────────────────────────────────────┘

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  heritage_asset_class (17 rows, self-referencing hierarchy)                       │
  │───────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       int unsigned AUTO_INCREMENT                           │
  │ UQ code                     varchar(50)                                           │
  │    name                     varchar(100) NOT NULL                                 │
  │    description              text                                                 │
  │ FK parent_id                int unsigned → heritage_asset_class.id                │
  │    default_useful_life      int                                                  │
  │    default_depreciation_method varchar(50)                                        │
  │    is_depreciable           tinyint(1)                                            │
  │    is_active                tinyint(1)                                            │
  │    sort_order               int                                                  │
  │    created_at               datetime                                             │
  └───────────────────┬─────────────────────────────────────────────────────────────────┘
                      │
                      │ 1:N
  ┌───────────────────▼─────────────────────────────────────────────────────────────────┐
  │  heritage_asset (62 columns, 5 rows) — Central asset register                       │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       int unsigned AUTO_INCREMENT                             │
  │ IX information_object_id    int → information_object.id                             │
  │ UQ object_id                int                                                    │
  │ FK accounting_standard_id   int unsigned → heritage_accounting_standard.id          │
  │ FK asset_class_id           int unsigned → heritage_asset_class.id                  │
  │                                                                                     │
  │ ── Recognition ──                                                                   │
  │ IX recognition_status       ENUM(recognised, not_recognised, pending, derecognised) │
  │    recognition_status_reason varchar(255)                                            │
  │    recognition_date         date                                                    │
  │    asset_sub_class          varchar(100)                                             │
  │                                                                                     │
  │ ── Measurement / Valuation ──                                                       │
  │    measurement_basis        ENUM(cost, fair_value, nominal, not_practicable)         │
  │    acquisition_method       ENUM(purchase, donation, bequest, transfer, found,       │
  │                             exchange, other)                                        │
  │ IX acquisition_date         date                                                    │
  │    acquisition_cost         decimal(18,2)                                            │
  │    fair_value_at_acquisition decimal(18,2)                                           │
  │    nominal_value            decimal(18,2) DEFAULT 1.00                               │
  │    donor_name / donor_restrictions                                                  │
  │    initial_carrying_amount  decimal(18,2)                                            │
  │    current_carrying_amount  decimal(18,2)                                            │
  │    accumulated_depreciation decimal(18,2)                                            │
  │    revaluation_surplus      decimal(18,2)                                            │
  │    impairment_loss          decimal(18,2)                                            │
  │                                                                                     │
  │ ── Valuation Detail ──                                                              │
  │ IX last_valuation_date      date                                                    │
  │    last_valuation_amount    decimal(18,2)                                            │
  │    valuation_method         ENUM(market, cost, income, expert, insurance, other)     │
  │    valuer_name / valuer_credentials / valuation_report_reference                    │
  │    revaluation_frequency    ENUM(annual, triennial, quinquennial, as_needed,         │
  │                             not_applicable)                                         │
  │                                                                                     │
  │ ── Depreciation ──                                                                  │
  │    depreciation_policy      ENUM(not_depreciated, straight_line, reducing_balance,   │
  │                             units_of_production)                                    │
  │    useful_life_years        int                                                     │
  │    residual_value           decimal(18,2)                                            │
  │    annual_depreciation      decimal(18,2)                                            │
  │                                                                                     │
  │ ── Impairment ──                                                                    │
  │    last_impairment_date     date                                                    │
  │    impairment_indicators    tinyint(1)                                               │
  │    impairment_indicators_details text                                                │
  │    recoverable_amount       decimal(18,2)                                            │
  │                                                                                     │
  │ ── Derecognition ──                                                                 │
  │    derecognition_date       date                                                    │
  │    derecognition_reason     ENUM(disposal, destruction, loss, transfer,              │
  │                             write_off, other)                                       │
  │    derecognition_proceeds   decimal(18,2)                                            │
  │    gain_loss_on_derecognition decimal(18,2)                                          │
  │                                                                                     │
  │ ── Heritage Significance ──                                                         │
  │    heritage_significance    ENUM(exceptional, high, medium, low)                     │
  │    significance_statement   text                                                    │
  │    restrictions_on_use / restrictions_on_disposal text                               │
  │    conservation_requirements text                                                   │
  │                                                                                     │
  │ ── Insurance ──                                                                     │
  │    insurance_required       tinyint(1)                                               │
  │    insurance_value          decimal(18,2)                                            │
  │    insurance_policy_number  varchar(100)                                             │
  │    insurance_provider       varchar(255)                                             │
  │    insurance_expiry_date    date                                                    │
  │                                                                                     │
  │ ── Physical ──                                                                      │
  │    current_location         varchar(255)                                             │
  │    storage_conditions       text                                                    │
  │    condition_rating         ENUM(excellent, good, fair, poor, critical)              │
  │    last_condition_assessment date                                                    │
  │                                                                                     │
  │ ── Audit ──                                                                         │
  │    created_by / updated_by / approved_by (int → user.id)                            │
  │    approved_date            date                                                    │
  │    notes                    text                                                    │
  │    created_at / updated_at  datetime                                                │
  └──────────┬─────────────────┬──────────────────┬──────────────────┬──────────────────┘
             │                 │                  │                  │
             │ 1:N             │ 1:N              │ 1:N              │ 1:N
             ▼                 ▼                  ▼                  ▼
  ┌──────────────────────┐ ┌──────────────────────┐ ┌──────────────────────┐ ┌──────────────────────┐
  │heritage_valuation_   │ │heritage_movement_    │ │heritage_depreciation_│ │heritage_impairment_  │
  │history (2 rows)      │ │register (2 rows)     │ │schedule              │ │assessment (2 rows)   │
  │──────────────────────│ │──────────────────────│ │──────────────────────│ │──────────────────────│
  │PK id  int unsigned   │ │PK id  int unsigned   │ │PK id  int unsigned   │ │PK id  int unsigned   │
  │FK heritage_asset_id  │ │FK heritage_asset_id  │ │FK heritage_asset_id  │ │FK heritage_asset_id  │
  │IX valuation_date     │ │IX movement_date      │ │IX fiscal_year        │ │IX assessment_date    │
  │   previous_value     │ │IX movement_type ENUM │ │   fiscal_period      │ │   physical_damage    │
  │   new_value          │ │   (loan_out,         │ │   opening_value      │ │   + _details         │
  │   valuation_change   │ │    loan_return,      │ │   depreciation_amount│ │   obsolescence       │
  │   valuation_method   │ │    transfer,         │ │   closing_value      │ │   + _details         │
  │   ENUM(6 values)     │ │    exhibition,       │ │   calculated_at      │ │   change_in_use      │
  │   valuer_name        │ │    conservation,     │ │   notes              │ │   + _details         │
  │   valuer_credentials │ │    storage_change,   │ │   created_at         │ │   external_factors   │
  │   valuer_organization│ │    other)            │ └──────────────────────┘ │   + _details         │
  │   valuation_report_  │ │   from/to_location   │                          │   impairment_identfied│
  │    reference         │ │   reason text        │                          │   carrying_amount_   │
  │   revaluation_       │ │   authorized_by      │                          │    before / after    │
  │    surplus_change    │ │   authorization_date │                          │   recoverable_amount │
  │   notes              │ │   expected/actual    │                          │   impairment_loss    │
  │   created_by         │ │    _return_date      │                          │   reversal_applicable│
  │   created_at         │ │   condition_on_      │                          │   reversal_amount    │
  └──────────────────────┘ │    departure/return  │                          │   reversal_date      │
                           │   ENUM(4 values)     │                          │   assessor_name      │
                           │   condition_notes    │                          │   notes / created_by │
                           │   insurance_confirmed│                          │   created_at         │
                           │   insurance_value    │                          └──────────────────────┘
                           │   created_by         │
                           │   created_at         │
                           └──────────────────────┘

  ═══════════════════════  JOURNAL & FINANCIAL TRACKING  ═════════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  heritage_journal_entry (3 rows) — Double-entry accounting journal                 │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       int unsigned AUTO_INCREMENT                             │
  │ FK heritage_asset_id        int unsigned → heritage_asset.id                        │
  │ IX journal_date             date                                                    │
  │    journal_number           varchar(50)                                             │
  │ IX journal_type             ENUM(recognition, revaluation, depreciation,            │
  │                             impairment, impairment_reversal, derecognition,         │
  │                             adjustment, transfer)                                   │
  │    debit_account            varchar(50) NOT NULL                                    │
  │    debit_amount             decimal(18,2) NOT NULL                                  │
  │    credit_account           varchar(50) NOT NULL                                    │
  │    credit_amount            decimal(18,2) NOT NULL                                  │
  │    description              text                                                   │
  │    reference_document       varchar(255)                                            │
  │ IX fiscal_year              int                                                    │
  │    fiscal_period            int                                                    │
  │ IX posted                   tinyint(1)                                              │
  │    posted_by / posted_at    int / datetime                                          │
  │    reversed                 tinyint(1)                                              │
  │    reversal_journal_id      int unsigned                                            │
  │    reversal_date / reversal_reason                                                  │
  │    created_by               int                                                    │
  │    created_at               datetime                                               │
  └─────────────────────────────────────────────────────────────────────────────────────┘

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  heritage_financial_year_snapshot (25 columns)                                      │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       int unsigned AUTO_INCREMENT                             │
  │ IX repository_id            int → repository.id                                    │
  │ FK accounting_standard_id   int unsigned → heritage_accounting_standard.id          │
  │    financial_year_start     date NOT NULL                                           │
  │ IX financial_year_end       date                                                    │
  │ FK asset_class_id           int unsigned → heritage_asset_class.id                  │
  │    total_assets / recognised_assets / not_recognised_assets int                     │
  │    total_carrying_amount    decimal(18,2)                                            │
  │    total_accumulated_depreciation decimal(18,2)                                      │
  │    total_impairment / total_revaluation_surplus decimal(18,2)                        │
  │    additions_count / additions_value                                                │
  │    disposals_count / disposals_value                                                │
  │    impairments_count / impairments_value                                            │
  │    revaluations_count / revaluations_value                                          │
  │    snapshot_data            json                                                   │
  │    notes / created_by / created_at                                                 │
  └─────────────────────────────────────────────────────────────────────────────────────┘

  ┌───────────────────────────────────────────────┐
  │  heritage_transaction_log (22 rows)            │
  │───────────────────────────────────────────────│
  │ PK id              int unsigned AUTO_INCREMENT │
  │ FK heritage_asset_id int unsigned               │
  │ IX object_id        int                         │
  │ IX transaction_type varchar(50) NOT NULL         │
  │    transaction_date date                         │
  │    amount           decimal(18,2)                │
  │    transaction_data json                         │
  │    user_id          int                          │
  │ IX created_at       datetime                     │
  └───────────────────────────────────────────────┘

  ┌───────────────────────────────────────────────┐
  │  grap_heritage_asset (30 cols, legacy view)    │
  │───────────────────────────────────────────────│
  │    id / object_id / repository_id              │
  │    recognition_status / asset_class            │
  │    asset_subclass / acquisition_date           │
  │    acquisition_method ENUM(7 values)           │
  │    donor_source / cost_of_acquisition          │
  │    current_carrying_amount / impairment_loss   │
  │    accumulated_depreciation / residual_value   │
  │    measurement_basis ENUM(4 values)            │
  │    valuation_date / valuer / valuation_method  │
  │    physical_location / condition_description   │
  │    insurance_value / policy / expiry           │
  │    compliance_score / compliance_notes         │
  │    last_compliance_check / notes               │
  │    created_at / updated_at                     │
  └───────────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  IPSAS 45 ERD — ahgIPSASPlugin (10 tables)
  International Public Sector Accounting Standards heritage asset management
  ════════════════════════════════════════════════════════════════════════════════════════

  ┌───────────────────────────────────────────────┐
  │  ipsas_asset_category (9 rows)                 │
  │───────────────────────────────────────────────│
  │ PK id              bigint unsigned             │
  │ UQ code            varchar(20)                 │
  │    name            varchar(255) NOT NULL        │
  │    description     text                         │
  │    asset_type      ENUM(heritage, operational,  │
  │                    mixed)                       │
  │    depreciation_policy ENUM(none, straight_line,│
  │                        reducing_balance)        │
  │    useful_life_years int                        │
  │    account_code    varchar(50)                  │
  │    is_active       tinyint(1)                   │
  │    created_at      timestamp                    │
  └──────────────────────┬────────────────────────┘
                         │ 1:N
  ┌──────────────────────▼─────────────────────────────────────────────────────────────┐
  │  ipsas_heritage_asset (31 columns)                                                  │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       bigint unsigned AUTO_INCREMENT                          │
  │ UQ asset_number             varchar(50)                                             │
  │    information_object_id    int                                                    │
  │ FK category_id              bigint unsigned → ipsas_asset_category.id               │
  │    title                    varchar(500) NOT NULL                                   │
  │    description              text                                                   │
  │    location                 varchar(255)                                            │
  │ IX repository_id            int → repository.id                                    │
  │    acquisition_date         date                                                    │
  │    acquisition_method       ENUM(purchase, donation, bequest, transfer, found,      │
  │                             exchange, unknown)                                     │
  │    acquisition_source / acquisition_cost / acquisition_currency                    │
  │ IX valuation_basis          ENUM(historical_cost, fair_value, nominal,              │
  │                             not_recognized)                                        │
  │    current_value / current_value_currency / current_value_date                     │
  │    depreciation_policy      ENUM(none, straight_line, reducing_balance)             │
  │    useful_life_years / residual_value / accumulated_depreciation                   │
  │    insured_value / insurance_policy / insurance_expiry                              │
  │ IX status                   ENUM(active, on_loan, in_storage,                      │
  │                             under_conservation, disposed, lost, destroyed)          │
  │    condition_rating         ENUM(excellent, good, fair, poor, critical)             │
  │    risk_level               ENUM(low, medium, high, critical)                      │
  │    risk_notes               text                                                   │
  │    created_by               int NOT NULL                                           │
  │    created_at / updated_at  timestamp                                              │
  └──────────┬─────────────────┬──────────────────┬──────────────────┬─────────────────┘
             │ 1:N             │ 1:N              │ 1:N              │ 1:N
             ▼                 ▼                  ▼                  ▼
  ┌──────────────────────┐ ┌──────────────────────┐ ┌──────────────────────┐ ┌─────────────────────┐
  │ipsas_valuation       │ │ipsas_depreciation    │ │ipsas_disposal        │ │ipsas_impairment     │
  │  (20 cols)           │ │  (13 cols)           │ │  (15 cols)           │ │  (19 cols)          │
  │──────────────────────│ │──────────────────────│ │──────────────────────│ │─────────────────────│
  │PK id bigint unsigned │ │PK id bigint unsigned │ │PK id bigint unsigned │ │PK id bigint unsigned│
  │FK asset_id → ipsas_  │ │FK asset_id → ipsas_  │ │FK asset_id → ipsas_  │ │FK asset_id → ipsas_ │
  │  heritage_asset.id   │ │  heritage_asset.id   │ │  heritage_asset.id   │ │  heritage_asset.id  │
  │IX valuation_date     │ │IX financial_year     │ │IX disposal_date      │ │IX assessment_date   │
  │IX valuation_type ENUM│ │   period_start/end   │ │IX disposal_method    │ │   physical_damage   │
  │  (initial,revaluation│ │   opening_value      │ │   ENUM(sale,donation,│ │   obsolescence      │
  │   impairment,reversal│ │   depreciation_amount│ │   destruction,loss,  │ │   decline_in_demand │
  │   disposal)          │ │   closing_value      │ │   theft,transfer,    │ │   market_value_     │
  │   valuation_basis    │ │   accumulated_       │ │   deaccession)       │ │    decline          │
  │   ENUM(4 values)     │ │    depreciation      │ │   carrying_value     │ │   other_indicator   │
  │   previous/new_value │ │   calculation_method │ │   disposal_proceeds  │ │   indicator_descr   │
  │   currency           │ │   rate_percent       │ │   gain_loss          │ │   carrying_amount   │
  │   change_amount/%    │ │   notes              │ │   recipient          │ │   recoverable_amount│
  │   valuer_name/       │ │   calculated_at      │ │   authorization_ref  │ │   impairment_loss   │
  │    qualification/type│ └──────────────────────┘ │   authorized_by/date │ │   impairment_       │
  │   valuation_method   │                          │   reason / doc_ref   │ │    recognized       │
  │   market_evidence    │                          │   created_by         │ │   recognition_date  │
  │   comparable_sales   │                          │   created_at         │ │   is_reversal       │
  │   documentation_ref  │                          └──────────────────────┘ │   reversal_amount   │
  │   notes              │                                                   │   notes / assessed_by│
  │   created_by         │                                                   │   created_at        │
  │   created_at         │                                                   └─────────────────────┘
  └──────────────────────┘

  ┌───────────────────────────────────────────────────────┐
  │  ipsas_insurance (20 cols)                             │
  │───────────────────────────────────────────────────────│
  │ PK id                  bigint unsigned                 │
  │ FK asset_id            bigint unsigned → ipsas_heritage_asset.id │
  │ IX policy_number       varchar(100)                    │
  │    policy_type         ENUM(all_risks, named_perils,   │
  │                        blanket, transit, exhibition)   │
  │    insurer             varchar(255) NOT NULL            │
  │    coverage_start/end  date                             │
  │    sum_insured         decimal(15,2) NOT NULL           │
  │    currency / premium / deductible                     │
  │    coverage_details / exclusions text                   │
  │ IX status              ENUM(active, expired, cancelled, │
  │                        pending_renewal)                 │
  │    renewal_reminder_sent tinyint(1)                     │
  │    broker_name / broker_contact                        │
  │    created_by / created_at / updated_at                │
  └───────────────────────────────────────────────────────┘

  ┌───────────────────────────────────────────────────────┐  ┌────────────────────────────────┐
  │  ipsas_financial_year_summary (22 cols)                │  │  ipsas_config (9 rows)          │
  │───────────────────────────────────────────────────────│  │────────────────────────────────│
  │ PK id                  bigint unsigned                 │  │ PK id         bigint unsigned   │
  │ UQ financial_year      varchar(10)                     │  │ UQ config_key varchar(100)      │
  │    year_start / year_end date                          │  │    config_value text             │
  │    opening_total_assets / opening_total_value          │  │    description  text             │
  │    additions_count / additions_value                   │  │    created_at / updated_at       │
  │    disposals_count / disposals_value                   │  └────────────────────────────────┘
  │    revaluations_increase / revaluations_decrease       │
  │    impairments / depreciation                          │  ┌────────────────────────────────┐
  │    closing_total_assets / closing_total_value          │  │  ipsas_audit_log               │
  │ IX status              ENUM(open, closed, audited)     │  │────────────────────────────────│
  │    closed_by / closed_at                               │  │ PK id         bigint unsigned   │
  │    notes / created_at / updated_at                     │  │ IX action_type varchar(50)      │
  └───────────────────────────────────────────────────────┘  │ IX entity_type varchar(50)      │
                                                              │    entity_id    bigint unsigned  │
                                                              │    user_id      int              │
                                                              │    ip_address   varchar(45)      │
                                                              │    old_value / new_value json    │
                                                              │    notes        text             │
                                                              │ IX created_at   timestamp        │
                                                              └────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
   Heritage Assets SUBSYSTEM: heritage_asset → heritage_accounting_standard + heritage_asset_class
   heritage_asset has 5 child tables: valuation_history, movement_register,
     depreciation_schedule, impairment_assessment, journal_entry
   heritage_transaction_log + heritage_financial_year_snapshot for reporting
   heritage_compliance_rule linked to heritage_accounting_standard

   IPSAS 45 SUBSYSTEM: ipsas_heritage_asset → ipsas_asset_category
   ipsas_heritage_asset has 5 child tables: valuation, depreciation, disposal,
     impairment, insurance
   ipsas_financial_year_summary for annual reporting
   ipsas_config for system settings, ipsas_audit_log for change tracking

   GLAM/DAM & INFORMATION OBJECT LINKS:
     heritage_asset.information_object_id ──► information_object.id (heritage record)
     heritage_asset.object_id ──────────────► object.id (AtoM core entity)
     ipsas_heritage_asset.information_object_id ► information_object.id
     ipsas_heritage_asset.repository_id ────► repository.id (owning institution)
     heritage_financial_year_snapshot.repository_id ► repository.id
     heritage_transaction_log.object_id ────► information_object.id (transaction target)
     heritage_batch_item.object_id ─────────► information_object.id (batch processing)
     heritage_popia_flag.object_id ─────────► information_object.id (Compliance-flagged)
     heritage_audit_log.user_id ────────────► user.id (extends actor.id in AtoM)
     heritage_batch_job.user_id ────────────► user.id

   CROSS-PLUGIN: heritage_depreciation_schedule → Heritage Assets annual depreciation
                 ipsas_valuation / ipsas_impairment → IPSAS 45 valuation standards
                 heritage_popia_flag links to ahgPrivacyPlugin compliance
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 8. IIIF Integration ERD

**Plugin:** ahgIiifPlugin (16 tables)
**Standards:** IIIF Presentation API 3.0, IIIF Auth API 1.0
**Subsystems:** Annotations, OCR, Collections, Auth, Manifest Cache, 3D, Validation

```
  ════════════════════════════════════════════════════════════════════════════════════════
  IIIF INTEGRATION ERD — ahgIiifPlugin (16 tables)
  IIIF manifests, annotations, OCR, collections, auth services, 3D, validation
  ════════════════════════════════════════════════════════════════════════════════════════

  ═══════════════════════  ANNOTATIONS & OCR  ═════════════════════════════════════════

  ┌──────────────────────────────────────────────────────────────────────────────────┐
  │  iiif_annotation (9 columns)                                                      │
  │──────────────────────────────────────────────────────────────────────────────────│
  │ PK id                  int AUTO_INCREMENT                                         │
  │ IX object_id           int NOT NULL → information_object.id                       │
  │    canvas_id           int                                                        │
  │ IX target_canvas       varchar(500) NOT NULL                                      │
  │    target_selector     json                                                       │
  │ IX motivation          ENUM(commenting, tagging, describing, linking,             │
  │                        transcribing, identifying, supplementing)                  │
  │ IX created_by          int → user.id                                              │
  │    created_at / updated_at datetime                                               │
  └──────────────────┬───────────────────────────────────────────────────────────────┘
                     │ 1:N
  ┌──────────────────▼───────────────────────────────────────────────────────────────┐
  │  iiif_annotation_body (7 columns)                                                 │
  │──────────────────────────────────────────────────────────────────────────────────│
  │ PK id                  int AUTO_INCREMENT                                         │
  │ FK annotation_id       int NOT NULL → iiif_annotation.id                          │
  │    body_type           varchar(50)                                                │
  │    body_value          text                                                       │
  │    body_format         varchar(50)                                                │
  │    body_language       varchar(10)                                                │
  │    body_purpose        varchar(50)                                                │
  └──────────────────────────────────────────────────────────────────────────────────┘

  ┌──────────────────────────────────────────────────────────────────────────────────┐
  │  iiif_ocr_text (9 columns)                                                        │
  │──────────────────────────────────────────────────────────────────────────────────│
  │ PK id                  int AUTO_INCREMENT                                         │
  │ IX digital_object_id   int NOT NULL → digital_object.id                           │
  │ IX object_id           int → information_object.id                                │
  │ FT full_text           longtext (FULLTEXT indexed)                                │
  │    format              ENUM(plain, alto, hocr)                                    │
  │    language            varchar(10)                                                │
  │    confidence          decimal(5,2)                                               │
  │    created_at / updated_at datetime                                               │
  └──────────────────┬───────────────────────────────────────────────────────────────┘
                     │ 1:N
  ┌──────────────────▼───────────────────────────────────────────────────────────────┐
  │  iiif_ocr_block (11 columns) — Word/line/paragraph coordinate blocks              │
  │──────────────────────────────────────────────────────────────────────────────────│
  │ PK id                  int AUTO_INCREMENT                                         │
  │ FK ocr_id              int NOT NULL → iiif_ocr_text.id                            │
  │ IX page_number         int                                                        │
  │ IX block_type          ENUM(word, line, paragraph, region)                        │
  │ IX text                varchar(1000)                                              │
  │    x / y / width / height int NOT NULL                                            │
  │    confidence          decimal(5,2)                                               │
  │    block_order         int                                                        │
  └──────────────────────────────────────────────────────────────────────────────────┘

  ═══════════════════════  COLLECTIONS  ═════════════════════════════════════════════════

  ┌──────────────────────────────────────────────────────────────────────────────────┐
  │  iiif_collection (15 columns, 2 rows, self-referencing hierarchy)                 │
  │──────────────────────────────────────────────────────────────────────────────────│
  │ PK id                  int AUTO_INCREMENT                                         │
  │    name                varchar(255) NOT NULL                                      │
  │ UQ slug                varchar(255)                                               │
  │    description         text                                                       │
  │    attribution         varchar(500)                                               │
  │    logo_url / thumbnail_url varchar(500)                                          │
  │    viewing_hint        ENUM(individuals, paged, continuous, multi-part, top)       │
  │    nav_date            date                                                       │
  │ FK parent_id           int → iiif_collection.id (self-ref)                        │
  │    sort_order          int                                                        │
  │ IX is_public           tinyint(1)                                                 │
  │    created_by          int                                                        │
  │    created_at / updated_at timestamp                                              │
  └──────────┬─────────────┬─────────────────────────────────────────────────────────┘
             │ 1:N         │ 1:N
             ▼             ▼
  ┌─────────────────────────┐  ┌──────────────────────────────────────────────────────┐
  │iiif_collection_i18n     │  │iiif_collection_item (10 cols, 9 rows)                │
  │  (5 cols)               │  │──────────────────────────────────────────────────────│
  │─────────────────────────│  │ PK id              int AUTO_INCREMENT                │
  │PK id  int AUTO_INCREMENT│  │ FK collection_id   int → iiif_collection.id          │
  │FK collection_id → iiif_ │  │ FK object_id       int → information_object.id       │
  │  collection.id          │  │    manifest_uri    varchar(1000)                     │
  │   culture varchar(10)   │  │    item_type       ENUM(manifest, collection)        │
  │   name    varchar(255)  │  │    label           varchar(500)                      │
  │   description text      │  │    description     text                              │
  └─────────────────────────┘  │    thumbnail_url   varchar(500)                      │
                               │    sort_order      int                               │
                               │    added_at        timestamp                         │
                               └──────────────────────────────────────────────────────┘

  ═══════════════════════  AUTH (IIIF Auth API 1.0)  ═══════════════════════════════════

  ┌──────────────────────────────────────────────────────────────────────────────────┐
  │  iiif_auth_service (14 columns, 3 rows) — Auth service definitions                │
  │──────────────────────────────────────────────────────────────────────────────────│
  │ PK id                  int unsigned AUTO_INCREMENT                                │
  │ UQ name                varchar(100) NOT NULL                                      │
  │ IX profile             ENUM(login, clickthrough, kiosk, external) NOT NULL         │
  │    label               varchar(255) NOT NULL                                      │
  │    description         text                                                       │
  │    confirm_label       varchar(100)                                               │
  │    failure_header / failure_description                                            │
  │    login_url / logout_url varchar(500)                                             │
  │    token_ttl           int                                                        │
  │ IX is_active           tinyint(1)                                                 │
  │    created_at / updated_at timestamp                                              │
  └──────────┬──────────────────┬──────────────────┬──────────────────────────────────┘
             │ 1:N              │ 1:N              │ 1:N
             ▼                  ▼                  ▼
  ┌────────────────────────┐ ┌──────────────────────┐ ┌──────────────────────────────┐
  │iiif_auth_repository    │ │iiif_auth_resource    │ │iiif_auth_token (11 cols)     │
  │  (7 cols)              │ │  (9 cols)            │ │──────────────────────────────│
  │────────────────────────│ │──────────────────────│ │PK id        int unsigned     │
  │PK id  int unsigned     │ │PK id  int unsigned   │ │UQ token_hash char(64)        │
  │IX repository_id → repo │ │IX object_id → info_  │ │IX user_id   int              │
  │FK service_id → iiif_   │ │  object.id           │ │FK service_id → iiif_auth_    │
  │  auth_service.id       │ │FK service_id → iiif_ │ │  service.id                  │
  │   degraded_access      │ │  auth_service.id     │ │IX session_id varchar(128)    │
  │   degraded_width       │ │   apply_to_children  │ │   ip_address / user_agent    │
  │   notes / created_at   │ │   degraded_access    │ │   issued_at timestamp        │
  └────────────────────────┘ │   degraded_width     │ │IX expires_at timestamp       │
                             │   notes              │ │   last_used_at timestamp     │
                             │   created/updated_at │ │IX is_revoked tinyint(1)      │
                             └──────────────────────┘ └──────────────────────────────┘

                                                       ┌──────────────────────────────┐
                                                       │iiif_auth_access_log (9 cols) │
                                                       │──────────────────────────────│
                                                       │PK id   bigint unsigned       │
                                                       │IX object_id / user_id        │
                                                       │   token_id  int unsigned     │
                                                       │IX action ENUM(view, download,│
                                                       │   token_request, token_grant,│
                                                       │   token_deny, logout)        │
                                                       │   ip_address / user_agent    │
                                                       │   details json               │
                                                       │IX created_at timestamp       │
                                                       └──────────────────────────────┘

  ═══════════════════════  CACHE, 3D, VALIDATION, SETTINGS  ═══════════════════════════

  ┌──────────────────────────────────────┐  ┌──────────────────────────────────────┐
  │iiif_manifest_cache (34 rows)         │  │iiif_3d_manifest                      │
  │──────────────────────────────────────│  │──────────────────────────────────────│
  │PK id         bigint unsigned         │  │PK id         int AUTO_INCREMENT      │
  │IX object_id  int NOT NULL            │  │UQ model_id   int → object_3d_model.id│
  │   culture    varchar(10) NOT NULL    │  │   manifest_json longtext             │
  │   manifest_json longtext NOT NULL    │  │   manifest_hash varchar(64)          │
  │UQ cache_key  varchar(64)             │  │   generated_at  timestamp            │
  │   page_count int                     │  └──────────────────────────────────────┘
  │   created_at / expires_at timestamp  │
  └──────────────────────────────────────┘  ┌──────────────────────────────────────┐
                                            │iiif_validation_result                │
  ┌──────────────────────────────────────┐  │──────────────────────────────────────│
  │iiif_viewer_settings (19 rows)        │  │PK id         int AUTO_INCREMENT      │
  │──────────────────────────────────────│  │IX object_id  int NOT NULL             │
  │PK id         int AUTO_INCREMENT      │  │IX validation_type varchar(100)        │
  │UQ setting_key varchar(100)           │  │IX status     varchar(50)              │
  │   setting_value text                 │  │   details    text                     │
  │   description varchar(255)           │  │IX validated_at datetime               │
  │   created_at / updated_at timestamp  │  │   validated_by int                    │
  └──────────────────────────────────────┘  └──────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
   No iiif_manifest or iiif_canvas tables exist — manifests are generated on-the-fly
   from information_object/digital_object data, cached in iiif_manifest_cache.
   iiif_annotation links to info objects by object_id (not manifest/canvas tables).
   iiif_ocr_text → iiif_ocr_block for coordinate-level OCR word/line positions.
   Auth subsystem: iiif_auth_service → repository bindings + resource bindings + tokens.
   GLAM/DAM & INFORMATION OBJECT LINKS:
     iiif_annotation.object_id ─────────► information_object.id (annotated record)
     iiif_ocr_text.object_id ──────────► information_object.id (OCR source)
     iiif_manifest_cache.object_id ────► information_object.id (cached manifest)
     iiif_collection_item.object_id ───► information_object.id (collection member)
     iiif_auth_resource.object_id ─────► information_object.id (auth-protected)
     iiif_auth_access_log.object_id ───► information_object.id (access event)
     iiif_validation_result.object_id ─► information_object.id (validation target)
     iiif_auth_repository.repository_id ► repository.id (per-institution auth config)
     iiif_auth_token.user_id ──────────► user.id (extends actor.id in AtoM)
     iiif_auth_access_log.user_id ─────► user.id
     object_3d_model.object_id ────────► information_object.id (3D model link)
     object_3d_audit_log.object_id ────► information_object.id
     iiif_3d_manifest → object_3d_model (3D IIIF manifest generation)

   CROSS-PLUGIN: object_3d_model / object_3d_hotspot → ahg3DModelPlugin (3D viewer)
                 triposr_jobs → AI-powered 3D model generation
                 Manifests generated on-the-fly from information_object + digital_object
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 9. Research Portal ERD

**Plugin:** ahgResearchPlugin (83 tables + 4 researcher_submission tables)
**Subsystems:** Researcher Registration, Reading Room, Material Requests, Reproductions,
Projects, Collections, Annotations, Bibliography, Evidence, Reports, API, Submissions
**Total: 87 tables** (largest plugin in the system)

```
  ════════════════════════════════════════════════════════════════════════════════════════
  RESEARCH PORTAL ERD — ahgResearchPlugin (87 tables)
  Comprehensive research portal: registration, reading rooms, material requests,
  reproductions, projects, workspaces, annotations, bibliography, evidence, reports
  ════════════════════════════════════════════════════════════════════════════════════════

  ═══════════════════════  1. RESEARCHER REGISTRATION & IDENTITY  ═════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  research_researcher (46 columns, 20 rows)                                         │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       int AUTO_INCREMENT                                     │
  │ IX user_id                  int → user.id                                          │
  │    title / first_name / last_name / email / phone                                  │
  │    affiliation_type         ENUM(academic, government, private, independent,        │
  │                             student, other)                                        │
  │    institution / institution_id / department / position / student_id                │
  │    research_interests / current_project text                                       │
  │    orcid_id / orcid_verified / orcid_access_token / orcid_refresh_token            │
  │    orcid_token_expires_at / researcher_id_wos / scopus_id / isni                   │
  │    researcher_type_id       int → research_researcher_type.id                      │
  │    timezone / preferred_language / api_key / api_key_expires_at                     │
  │    id_type ENUM(5) / id_number / id_verified / id_verified_by / id_verified_at     │
  │ IX status                   ENUM(pending, approved, suspended, expired, rejected)   │
  │    rejection_reason / approved_by / approved_at / expires_at                        │
  │    renewal_reminder_sent / notes / photo_path                                      │
  │    card_number / card_barcode / card_issued_at                                     │
  │    created_at / updated_at                                                         │
  └─────────────────────────────────────────────────────────────────────────────────────┘

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │research_researcher_type (10 rows)│  │research_researcher_type_i18n     │
  │──────────────────────────────────│  │──────────────────────────────────│
  │PK id / UQ code / name           │  │PK id → research_researcher_type  │
  │   description                   │  │PK culture varchar(10)            │
  │   max_booking_days_advance      │  │   name / description             │
  │   max_booking_hours_per_day     │  └──────────────────────────────────┘
  │   max_materials_per_booking     │
  │   can_remote_access tinyint(1)  │  ┌──────────────────────────────────┐
  │   can_request_reproductions     │  │research_verification (15 cols)   │
  │   can_export_data / auto_approve│  │──────────────────────────────────│
  │   requires_id_verification      │  │PK id / IX researcher_id          │
  │   expiry_months / priority_level│  │IX verification_type ENUM(7 vals) │
  │IX is_active / IX sort_order     │  │   document_type/reference/path   │
  │   created_at / updated_at      │  │   verification_data json         │
  └──────────────────────────────────┘  │IX status ENUM(pending,verified, │
                                        │  rejected,expired)              │
  ┌──────────────────────────────────┐  │   verified_by/at / expires_at   │
  │research_researcher_audit (23cols)│  │   rejection_reason / notes      │
  │──────────────────────────────────│  └──────────────────────────────────┘
  │PK id / original_id / user_id    │
  │   title/first/last/email/phone  │  ┌──────────────────────────────────┐
  │   affiliation_type/institution  │  │research_password_reset (5 cols)  │
  │   department/position/interests │  │──────────────────────────────────│
  │   current_project/orcid_id      │  │PK id / IX user_id               │
  │   id_type/id_number/status      │  │IX token varchar(64)             │
  │   rejection_reason              │  │IX expires_at / created_at       │
  │   archived_by/at / original_*   │  └──────────────────────────────────┘
  └──────────────────────────────────┘

  ═══════════════════════  2. READING ROOM & BOOKING  ═════════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  research_reading_room (22 columns, 2 rows)                                        │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       int AUTO_INCREMENT                                     │
  │    name / code / description / amenities / location / operating_hours / rules       │
  │    capacity / has_seat_management tinyint(1)                                       │
  │    walk_ins_allowed / walk_in_capacity / floor_plan_path                            │
  │    advance_booking_days / max_booking_hours / cancellation_hours                    │
  │    opening_time / closing_time / days_open                                         │
  │    is_active / created_at / updated_at                                             │
  └──────────┬───────────────────────┬─────────────────────────────────────────────────┘
             │ 1:N                   │ 1:N
             ▼                       ▼
  ┌────────────────────────────┐  ┌──────────────────────────────────────────────────────┐
  │research_reading_room_seat  │  │research_booking (22 cols, 8 rows)                    │
  │  (17 cols)                 │  │──────────────────────────────────────────────────────│
  │────────────────────────────│  │PK id / IX researcher_id / IX reading_room_id         │
  │PK id / IX reading_room_id │  │   project_id / IX booking_date / start/end_time       │
  │   seat_number / seat_label│  │   purpose text                                       │
  │IX seat_type ENUM(standard,│  │IX status ENUM(pending, confirmed, cancelled,          │
  │  accessible, computer,    │  │   completed, no_show)                                │
  │  microfilm, oversize,     │  │   confirmed_by/at / cancelled_at/reason               │
  │  quiet, group)            │  │   checked_in_at / checked_out_at                     │
  │   has_power/lamp/computer │  │   notes / is_walk_in / rules_acknowledged/at          │
  │   has_magnifier           │  │IX seat_id / created_at / updated_at                  │
  │   position_x/y / IX zone │  └──────────────────────────────────────────────────────┘
  │   notes / IX is_active    │
  │   sort_order / created_at │  ┌──────────────────────────────────────────────────────┐
  │   updated_at              │  │research_seat_assignment (9 cols)                      │
  └────────────────────────────┘  │──────────────────────────────────────────────────────│
                                  │PK id / IX booking_id / IX seat_id                    │
  ┌────────────────────────────┐  │IX assigned_at / assigned_by                          │
  │research_walk_in_visitor    │  │   released_at / released_by                          │
  │  (23 cols)                 │  │IX status ENUM(assigned, occupied, released, no_show)  │
  │────────────────────────────│  │   notes                                              │
  │PK id / IX reading_room_id │  └──────────────────────────────────────────────────────┘
  │IX visit_date / first/last  │
  │IX email / phone            │  ┌──────────────────────────────────────────────────────┐
  │   id_type ENUM(5) / id_no │  │research_equipment (20 cols)                           │
  │   id_verified / affiliation│  │──────────────────────────────────────────────────────│
  │   institution / purpose    │  │PK id / IX reading_room_id / UQ code / name           │
  │   materials_requested text │  │IX equipment_type ENUM(microfilm_reader, microfiche,   │
  │   checked_in_at/out_at     │  │  scanner, computer, magnifier, book_cradle, light_box,│
  │   rules_acknowledged/at    │  │  camera_stand, gloves, weights, other)               │
  │   staff_member / notes     │  │   brand/model/serial_number/description/location     │
  │   registered_researcher_id │  │   requires_training / max_booking_hours               │
  │   created_at               │  │   booking_increment_minutes                          │
  └────────────────────────────┘  │   condition_status ENUM(5) / last/next_maintenance   │
                                  │IX is_available / notes / created_at / updated_at      │
  ┌────────────────────────────┐  └──────────────────────────────────────────────────────┘
  │research_equipment_booking  │
  │  (18 cols)                 │  ┌──────────────────────────────────────────────────────┐
  │────────────────────────────│  │research_retrieval_schedule (12 cols)                  │
  │PK id / IX booking/research │  │──────────────────────────────────────────────────────│
  │IX equipment_id/booking_date│  │PK id / IX reading_room_id / name                     │
  │   start/end_time / purpose │  │IX day_of_week / IX retrieval_time                    │
  │IX status ENUM(reserved,    │  │   cutoff_minutes_before / max_items_per_run           │
  │  in_use, returned,         │  │   storage_location                                   │
  │  cancelled, no_show)       │  │IX is_active / notes / created_at / updated_at        │
  │   checked_out_at/by        │  └──────────────────────────────────────────────────────┘
  │   returned_at/by           │
  │   condition_on_return ENUM │
  │   return_notes / notes     │
  │   created_at / updated_at  │
  └────────────────────────────┘

  ═══════════════════════  3. MATERIAL REQUESTS & CUSTODY  ════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  research_material_request (38 columns) — Paging / retrieval / return tracking     │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       int AUTO_INCREMENT                                     │
  │ IX booking_id / IX object_id int → information_object.id                           │
  │    quantity / notes / request_type ENUM(reading_room, reproduction, loan,           │
  │                                    remote_access)                                  │
  │    priority ENUM(normal, high, rush) / handling_instructions                       │
  │    location_code / shelf_location / location_current                               │
  │ IX retrieval_scheduled_for  datetime / IX queue_id                                 │
  │    box_number / folder_number                                                      │
  │    curatorial_approval_required / curatorial_approved_by/at                         │
  │    paging_slip_printed / call_slip_printed_at/by                                   │
  │    status ENUM(requested, retrieved, delivered, in_use, returned, unavailable)      │
  │    retrieved_by/at / returned_at / condition_notes                                 │
  │    sla_due_date / assigned_to                                                      │
  │    triage_status / triage_by / triage_at                                           │
  │    checkout_confirmed_at/by / return_condition / return_verified_by/at              │
  │    created_at / updated_at                                                         │
  └─────────────────────────────────────────────────────────────────────────────────────┘

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │research_request_queue (19 cols,  │  │research_custody_handoff (17 cols)│
  │  7 rows)                         │  │──────────────────────────────────│
  │──────────────────────────────────│  │PK id / IX material_request_id   │
  │PK id / name / UQ code            │  │IX handoff_type varchar(50)      │
  │   description                    │  │   from/to_handler_id            │
  │IX queue_type ENUM(retrieval,     │  │   from/to_location              │
  │  paging, return, curatorial,     │  │   condition_at_handoff / notes  │
  │  reproduction)                   │  │   signature_confirmed / by / at │
  │   filter_status/room_id/priority │  │   barcode_scanned               │
  │   sort_field / sort_direction    │  │IX spectrum_movement_id          │
  │   auto_assign / assigned_staff_id│  │   notes / IX created_at         │
  │   color / icon / is_default      │  │   created_by                    │
  │IX is_active / sort_order         │  └──────────────────────────────────┘
  │   created_at / updated_at        │
  └──────────────────────────────────┘  ┌──────────────────────────────────┐
                                        │research_request_correspondence   │
  ┌──────────────────────────────────┐  │  (11 cols)                       │
  │research_request_status_history   │  │──────────────────────────────────│
  │  (8 cols)                        │  │PK id / IX request_id             │
  │──────────────────────────────────│  │   request_type / sender_type     │
  │PK id / IX request_id             │  │IX sender_id / subject / body     │
  │   request_type ENUM(material,    │  │   is_internal / attachment_*     │
  │   reproduction)                  │  │IX created_at                     │
  │   old_status / new_status        │  └──────────────────────────────────┘
  │   changed_by / notes             │
  │IX created_at                     │  ┌──────────────────────────────────┐
  └──────────────────────────────────┘  │research_access_decision (7 cols) │
                                        │──────────────────────────────────│
  ┌──────────────────────────────────┐  │PK id / IX policy_id / IX researchr│
  │research_rights_policy (10 cols)  │  │   action_requested               │
  │──────────────────────────────────│  │IX decision ENUM(permitted,denied)│
  │PK id / IX target_type/target_id │  │   rationale / evaluated_at       │
  │IX policy_type ENUM(permission,   │  └──────────────────────────────────┘
  │  prohibition, obligation)        │
  │IX action_type ENUM(use,reproduce,│
  │  distribute,modify,archive,      │
  │  display)                        │
  │   constraints_json / policy_json │
  │   created_by / created_at / upd  │
  └──────────────────────────────────┘

  ═══════════════════════  4. REPRODUCTION REQUESTS  ══════════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  research_reproduction_request (33 columns, 7 rows)                                │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id / IX researcher_id / UQ reference_number                                     │
  │    purpose / intended_use / publication_details                                    │
  │ IX status ENUM(draft, submitted, processing, awaiting_payment,                     │
  │               in_production, completed, cancelled)                                 │
  │    estimated_cost / final_cost decimal(10,2) / currency                            │
  │    payment_reference / payment_date / payment_method                               │
  │    invoice_number / invoice_date                                                   │
  │    delivery_method ENUM(email, download, post, collect, digital,                   │
  │                    pickup, courier, physical)                                      │
  │    delivery_address / delivery_email                                               │
  │    completed_at / processed_by / notes / admin_notes                               │
  │    triage_status/by/at/notes / sla_due_date / assigned_to                          │
  │    closed_at/by/closure_reason / IX created_at / updated_at                        │
  └──────────────────┬─────────────────────────────────────────────────────────────────┘
                     │ 1:N
  ┌──────────────────▼──────────────────────┐  ┌──────────────────────────────────┐
  │research_reproduction_item (17 cols)      │  │research_reproduction_file(11 cols)│
  │──────────────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX request_id / IX object_id      │  │PK id / IX item_id               │
  │   digital_object_id                      │  │   file_name/path/size/mime_type  │
  │   reproduction_type ENUM(photocopy, scan,│  │   checksum varchar(64)           │
  │     photograph, digital_copy, digital_   │  │   download_count / expires_at    │
  │     scan, transcription, certification,  │  │IX download_token varchar(64)     │
  │     certified_copy, microfilm, other)    │  │   created_at                     │
  │   format / resolution / color_mode ENUM  │  └──────────────────────────────────┘
  │   quantity / page_range / special_instr  │
  │   unit_price / total_price decimal(10,2) │
  │IX status ENUM(pending, in_progress,      │
  │   completed, cancelled)                  │
  │   completed_at / notes / created_at      │
  └──────────────────────────────────────────┘

  ═══════════════════════  5. PROJECTS & COLLABORATION  ═══════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  research_project (19 columns, 2 rows)                                             │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id / IX owner_id (→ researcher) / title / description                           │
  │ IX project_type      ENUM(thesis, dissertation, publication, exhibition,           │
  │                      documentary, genealogy, institutional, personal, other)       │
  │    institution / supervisor / funding_source / grant_number / ethics_approval      │
  │    start_date / expected_end_date / actual_end_date                                │
  │ IX status            ENUM(planning, active, on_hold, completed, archived)          │
  │    visibility ENUM(private, collaborators, public) / IX share_token                │
  │    metadata json / created_at / updated_at                                         │
  └──────────┬──────────────────┬──────────────────┬───────────────────────────────────┘
             │ 1:N              │ 1:N              │ 1:N
             ▼                  ▼                  ▼
  ┌──────────────────────┐ ┌────────────────────────┐ ┌──────────────────────────────┐
  │research_project_     │ │research_project_       │ │research_project_resource     │
  │collaborator (10 cols,│ │milestone (10 cols)     │ │  (15 cols)                   │
  │  4 rows)             │ │────────────────────────│ │──────────────────────────────│
  │──────────────────────│ │PK id / IX project_id   │ │PK id / IX project_id         │
  │PK id / IX project_id│ │   title / description  │ │IX resource_type ENUM(8 vals) │
  │IX researcher_id      │ │IX due_date             │ │   resource_id / IX object_id │
  │   role ENUM(owner,   │ │   completed_at/by      │ │   external_url / link_type   │
  │   editor, contributor│ │IX status ENUM(pending,  │ │   link_metadata json         │
  │   viewer)            │ │  in_progress, completed,│ │   title/description/notes    │
  │   permissions json   │ │  cancelled)            │ │   tags / added_by / sort_order│
  │   invited_by/at      │ │   sort_order / created │ │   added_at                   │
  │   accepted_at        │ └────────────────────────┘ └──────────────────────────────┘
  │IX status ENUM(4 vals)│
  │   notes              │
  └──────────────────────┘

  ┌───────────────────────────────────┐  ┌──────────────────────────────────┐
  │research_workspace (9 cols, 2 rows)│  │research_institution (11 cols)    │
  │───────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX owner_id / name / descr│  │PK id / name / UQ code            │
  │IX visibility ENUM(private,members,│  │   description / url              │
  │  public)                          │  │   contact_name / contact_email   │
  │IX share_token / settings json     │  │   logo_path / is_active          │
  │   created_at / updated_at         │  │   created_at / updated_at        │
  └──────────┬────────────────────────┘  └──────────────────────────────────┘
             │ 1:N                       ┌──────────────────────────────────┐
  ┌──────────▼────────────────────────┐  │research_institutional_share      │
  │research_workspace_member (8 cols) │  │  (13 cols)                       │
  │───────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX workspace_id            │  │PK id / IX project_id            │
  │IX researcher_id                    │  │IX institution_id                │
  │   role ENUM(owner, admin, editor,  │  │UQ share_token / share_type ENUM │
  │   viewer, member, contributor)     │  │   shared_by / accepted_by       │
  │   invited_by / invited_at          │  │IX status ENUM(pending, active,  │
  │   accepted_at                      │  │   revoked, expired)             │
  │IX status ENUM(4 vals)              │  │   message / permissions json    │
  └───────────────────────────────────┘  │   expires_at / created/updated   │
  ┌───────────────────────────────────┐  └──────────────────────────────────┘
  │research_workspace_resource(10cols)│  ┌──────────────────────────────────┐
  │───────────────────────────────────│  │research_external_collaborator    │
  │PK id / IX workspace_id            │  │  (10 cols)                       │
  │IX resource_type ENUM(6 vals)      │  │──────────────────────────────────│
  │   resource_id / external_url      │  │PK id / IX share_id / name       │
  │   title / description / added_by  │  │IX email / institution / orcid_id │
  │   sort_order / added_at           │  │UQ access_token / role ENUM       │
  └───────────────────────────────────┘  │   last_accessed_at / created_at  │
                                         └──────────────────────────────────┘

  ═══════════════════════  6. COLLECTIONS & ANNOTATIONS  ══════════════════════════════

  ┌──────────────────────────────────────┐  ┌──────────────────────────────────┐
  │research_collection (9 cols, 10 rows) │  │research_clipboard_project(7 cols)│
  │──────────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX researcher_id / project_id │  │PK id / IX researcher_id          │
  │   name / description                 │  │IX project_id / object_id         │
  │   is_public / IX share_token         │  │   is_pinned / notes / created_at │
  │   created_at / updated_at            │  └──────────────────────────────────┘
  └──────────┬───────────────────────────┘
             │ 1:N                          ┌──────────────────────────────────┐
  ┌──────────▼───────────────────────────┐  │research_annotation (19 cols)     │
  │research_collection_item (11 cols,    │  │──────────────────────────────────│
  │  40 rows)                            │  │PK id / IX researcher_id          │
  │──────────────────────────────────────│  │   project_id / IX object_id      │
  │PK id / IX collection_id / IX obj_id  │  │   entity_type / IX collection_id │
  │   object_type / culture              │  │   digital_object_id              │
  │   external_uri / tags / ref_code     │  │   annotation_type ENUM(note,     │
  │   notes / sort_order / created_at    │  │   highlight, bookmark, tag,      │
  └──────────────────────────────────────┘  │   transcription)                 │
                                            │IX title / content / content_format│
  ┌──────────────────────────────────────┐  │   target_selector / canvas_id    │
  │research_annotation_v2 (11 cols,6 row)│  │   iiif_annotation_id / tags      │
  │──────────────────────────────────────│  │   is_private / visibility ENUM   │
  │PK id / IX researcher_id / IX project │  │   created_at / updated_at        │
  │IX motivation ENUM(commenting,        │  └──────────────────────────────────┘
  │  describing, classifying, linking,   │
  │  questioning, tagging, highlighting) │  ┌──────────────────────────────────┐
  │   body_json / creator_json /         │  │research_annotation_target(8 cols)│
  │   generated_json                     │  │──────────────────────────────────│
  │IX status ENUM(active,archived,deleted│  │PK id / IX annotation_id          │
  │IX visibility ENUM(private,shared,pub)│  │IX source_type / source_id        │
  │   created_at / updated_at            │  │IX selector_type ENUM(6 W3C types)│
  └──────────────────────────────────────┘  │   selector_json / source_url     │
                                            │   created_at                     │
                                            └──────────────────────────────────┘

  ═══════════════════════  7. RESEARCH TOOLS  ═════════════════════════════════════════

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │research_bibliography (10 cols)   │  │research_saved_search (21 cols)   │
  │──────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX researcher_id         │  │PK id / IX researcher_id          │
  │IX project_id / name / descr     │  │   project_id / name / description│
  │   citation_style                │  │   search_query / search_filters  │
  │   is_public / IX share_token    │  │   query_ast_json / result_snap   │
  │   created_at / updated_at       │  │   citation_id / last_result_count│
  └──────────┬──────────────────────┘  │   search_type / total_results    │
             │ 1:N                     │   facets json                    │
  ┌──────────▼──────────────────────┐  │   alert_enabled / alert_frequency│
  │research_bibliography_entry      │  │   ENUM(daily,weekly,monthly)     │
  │  (25 cols, 2 rows)              │  │   last_alert_at / new_results    │
  │──────────────────────────────────│ │   is_public / created/updated    │
  │PK id / IX bibliography_id       │  └──────────────────────────────────┘
  │IX object_id / IX entry_type ENUM│
  │   csl_data json / title / authors│  ┌──────────────────────────────────┐
  │   date / publisher / container   │  │research_citation_log (8929 rows) │
  │   volume / issue / pages / doi   │  │──────────────────────────────────│
  │   url / accessed_date            │  │PK id / IX researcher_id          │
  │   archive_name/location/         │  │IX object_id / citation_style     │
  │    collection_title/box/folder   │  │   citation_text / created_at     │
  │   notes / sort_order / created   │  └──────────────────────────────────┘
  │   updated_at                     │
  └──────────────────────────────────┘  ┌──────────────────────────────────┐
                                        │research_journal_entry (15 cols)  │
  ┌──────────────────────────────────┐  │──────────────────────────────────│
  │research_map_point (13 cols)      │  │PK id / IX researcher_id          │
  │──────────────────────────────────│  │IX project_id / IX entry_date     │
  │PK id / IX project_id/researcher │  │IX title / content / content_format│
  │   label / description            │  │   entry_type ENUM(8 types)       │
  │IX latitude/longitude decimal     │  │   time_spent_minutes / tags      │
  │   place_name / date_valid_*      │  │   is_private / related_entity_*  │
  │IX source_type / source_id        │  │   created_at / updated_at        │
  │   created_at                     │  └──────────────────────────────────┘
  └──────────────────────────────────┘
                                        ┌──────────────────────────────────┐
  ┌──────────────────────────────────┐  │research_comment (11 cols)        │
  │research_timeline_event (13 cols) │  │──────────────────────────────────│
  │──────────────────────────────────│  │PK id / IX researcher_id          │
  │PK id / IX project_id/researcher │  │IX entity_type ENUM(5 types)      │
  │   label / description            │  │   entity_id / IX parent_id       │
  │IX date_start / date_end          │  │   content / is_resolved          │
  │   date_type ENUM(event, creation,│  │   resolved_by/at / created/upd   │
  │   accession, publication)        │  └──────────────────────────────────┘
  │IX source_type / source_id        │
  │   position / color               │  ┌──────────────────────────────────┐
  │   created_at                     │  │research_discussion (13 cols)     │
  └──────────────────────────────────┘  │──────────────────────────────────│
                                        │PK id / IX workspace/project_id   │
  ┌──────────────────────────────────┐  │IX parent_id / IX researcher_id   │
  │research_search_alert_log (9 cols)│  │   subject / content              │
  │──────────────────────────────────│  │   is_pinned / is_resolved        │
  │PK id / IX saved_search_id        │  │   resolved_by/at / created/upd   │
  │IX researcher_id                   │  └──────────────────────────────────┘
  │   previous/new/new_items_count   │
  │   notification_sent / method     │
  │IX created_at                     │
  └──────────────────────────────────┘

  ═══════════════════════  8. EVIDENCE & ANALYSIS  ════════════════════════════════════

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │research_assertion (17 cols)      │  │research_hypothesis (9 cols)      │
  │──────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX researcher/project_id │  │PK id / IX project/researcher_id │
  │IX subject_type / subject_id      │  │   statement text                 │
  │   subject_label                  │  │IX status ENUM(proposed, testing, │
  │IX predicate varchar(255)         │  │   supported, refuted)            │
  │   object_value / object_type     │  │   evidence_count / tags          │
  │   object_id / object_label       │  │   created_at / updated_at        │
  │IX assertion_type ENUM(5 types)   │  └──────────┬───────────────────────┘
  │IX status ENUM(proposed, verified,│              │ 1:N
  │   disputed, retracted)           │  ┌──────────▼───────────────────────┐
  │   confidence / version           │  │research_hypothesis_evidence      │
  │   created_at / updated_at        │  │  (9 cols)                        │
  └──────────┬───────────────────────┘  │──────────────────────────────────│
             │ 1:N                      │PK id / IX hypothesis_id          │
  ┌──────────▼───────────────────────┐  │IX source_type / source_id        │
  │research_assertion_evidence       │  │IX relationship ENUM(supports,    │
  │  (9 cols)                        │  │   refutes, neutral)              │
  │──────────────────────────────────│  │   confidence / note / added_by   │
  │PK id / IX assertion_id           │  │   created_at                     │
  │IX source_type / source_id        │  └──────────────────────────────────┘
  │   selector_json                  │
  │IX relationship ENUM(supports,    │  ┌──────────────────────────────────┐
  │   refutes)                       │  │research_source_assessment(10cols)│
  │   note / added_by / created_at   │  │──────────────────────────────────│
  └──────────────────────────────────┘  │PK id / IX object/researcher_id  │
                                        │IX source_type ENUM(primary,      │
  ┌──────────────────────────────────┐  │  secondary, tertiary)            │
  │research_entity_resolution(15cols)│  │   source_form ENUM(5 values)     │
  │──────────────────────────────────│  │   completeness ENUM(5 values)    │
  │PK id / IX entity_a_type/id       │  │IX trust_score / rationale        │
  │IX entity_b_type / entity_b_id    │  │   bias_context / assessed_at     │
  │IX confidence decimal(5,4)         │  └──────────────────────────────────┘
  │   match_method                   │
  │IX status ENUM(proposed,accepted, │  ┌──────────────────────────────────┐
  │   rejected)                      │  │research_quality_metric (20 rows) │
  │   resolver_id / resolved_at      │  │──────────────────────────────────│
  │   notes / evidence_json          │  │PK id / IX object_id              │
  │   relationship_type              │  │IX metric_type ENUM(4 types)      │
  │   proposer_id / created_at       │  │   metric_value / source_service  │
  └──────────────────────────────────┘  │   raw_data_json / created_at     │
                                        └──────────────────────────────────┘

  ═══════════════════════  9. SNAPSHOTS, EXTRACTION & VALIDATION  ═════════════════════

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │research_snapshot (14 cols)       │  │research_extraction_job (13 cols) │
  │──────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX project/researcher_id │  │PK id / IX project/collection/    │
  │   title / description            │  │   researcher_id                  │
  │IX hash_sha256 varchar(64)        │  │IX extraction_type ENUM(7 types)  │
  │   query_state_json               │  │   parameters_json                │
  │   rights_state_json              │  │IX status ENUM(queued, running,   │
  │   metadata_json / item_count     │  │   completed, failed)             │
  │IX status ENUM(active, frozen,    │  │   progress / total/processed     │
  │   archived)                      │  │   error_log / created_at         │
  │   frozen_at / citation_id        │  │   completed_at                   │
  │   created_at                     │  └──────────┬───────────────────────┘
  └──────────┬───────────────────────┘              │ 1:N
             │ 1:N                      ┌──────────▼───────────────────────┐
  ┌──────────▼───────────────────────┐  │research_extraction_result(9 cols)│
  │research_snapshot_item (10 cols)   │  │──────────────────────────────────│
  │──────────────────────────────────│  │PK id / IX job_id / IX object_id  │
  │PK id / IX snapshot_id            │  │IX result_type ENUM(6 types)      │
  │IX object_id / object_type        │  │   data_json                      │
  │   culture / slug                 │  │IX confidence decimal(5,4)         │
  │   metadata_version_json          │  │   model_version / input_hash     │
  │   rights_snapshot_json           │  │   created_at                     │
  │   sort_order / created_at        │  └──────────────────────────────────┘
  └──────────────────────────────────┘
                                        ┌──────────────────────────────────┐
                                        │research_validation_queue (9 cols)│
                                        │──────────────────────────────────│
                                        │PK id / IX result_id              │
                                        │IX researcher_id                  │
                                        │IX status ENUM(pending, accepted, │
                                        │   rejected, modified)            │
                                        │   modified_data_json             │
                                        │IX reviewer_id / reviewed_at      │
                                        │   notes / created_at             │
                                        └──────────────────────────────────┘

  ═══════════════════════  10. ACTIVITIES & EVENTS  ═══════════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  research_activity (31 columns) — Classes, tours, filming, events, meetings        │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id / IX activity_type ENUM(class, tour, exhibit, loan, conservation,            │
  │                          photography, filming, event, meeting, other)              │
  │    title / description / IX organizer_id / organizer_name/email/phone              │
  │    organization / expected_attendees / IX reading_room_id                          │
  │ IX start_date / end_date / start_time / end_time                                   │
  │    recurring / recurrence_pattern json                                              │
  │    setup_requirements / av_requirements / catering_notes / special_instructions     │
  │ IX status ENUM(requested, tentative, confirmed, in_progress, completed, cancelled) │
  │    confirmed_by/at / cancelled_by/at/reason / notes / admin_notes                  │
  │    created_at / updated_at                                                         │
  └──────────┬──────────────────┬──────────────────────────────────────────────────────┘
             │ 1:N              │ 1:N
             ▼                  ▼
  ┌────────────────────────────┐  ┌────────────────────────────────────────────────────┐
  │research_activity_material  │  │research_activity_participant (17 cols)              │
  │  (18 cols)                 │  │────────────────────────────────────────────────────│
  │────────────────────────────│  │PK id / IX activity/researcher_id                   │
  │PK id / IX activity_id      │  │   name/email/phone/organization                   │
  │IX object_id / purpose      │  │IX role ENUM(organizer, instructor, presenter,      │
  │   handling/display_notes   │  │   student, visitor, assistant, staff, other)       │
  │   insurance_value          │  │   dietary_requirements / accessibility_needs       │
  │   loan_agreement_signed    │  │IX registration_status ENUM(6 values)               │
  │   condition_before/after   │  │   registered/confirmed/checked_in/checked_out_at   │
  │IX status ENUM(7 values)    │  │   feedback / notes                                │
  │   approved_by/at           │  └────────────────────────────────────────────────────┘
  │   retrieved_at / returned  │
  │   notes / created/updated  │  ┌────────────────────────────────────────────────────┐
  └────────────────────────────┘  │research_activity_log (12 cols, 4 rows)              │
                                  │────────────────────────────────────────────────────│
                                  │PK id / IX researcher/project_id                    │
                                  │IX activity_type ENUM(26+ action types)             │
                                  │IX entity_type / entity_id / entity_title           │
                                  │   details json / session_id / ip_address           │
                                  │   user_agent / IX created_at                       │
                                  └────────────────────────────────────────────────────┘

  ═══════════════════════  11. REPORTS & TEMPLATES  ═══════════════════════════════════

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │research_report (10 cols, 2 rows) │  │research_report_template (7 cols) │
  │──────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX researcher/project_id │  │PK id / name / UQ code            │
  │   title                          │  │   description / sections_config  │
  │   template_type ENUM(6 types)    │  │   is_system / created_at         │
  │   description                    │  └──────────────────────────────────┘
  │IX status ENUM(draft, in_progress,│
  │   review, completed, archived)   │  ┌──────────────────────────────────┐
  │   metadata json                  │  │research_peer_review (9 cols)     │
  │   created_at / updated_at        │  │──────────────────────────────────│
  └──────────┬───────────────────────┘  │PK id / IX report_id              │
             │ 1:N                      │   requested_by / IX reviewer_id  │
  ┌──────────▼───────────────────────┐  │IX status ENUM(pending,in_progress│
  │research_report_section (12 cols) │  │   completed, declined)           │
  │──────────────────────────────────│  │   feedback / rating              │
  │PK id / IX report_id              │  │   requested_at / completed_at    │
  │   section_type ENUM(9 types)     │  └──────────────────────────────────┘
  │   title / content / content_fmt  │
  │   bibliography/collection_id     │  ┌──────────────────────────────────┐
  │   settings json / IX sort_order  │  │research_print_template (20 cols) │
  │   created_at / updated_at        │  │──────────────────────────────────│
  └──────────────────────────────────┘  │PK id / name / UQ code            │
                                        │IX template_type ENUM(7 types)    │
  ┌──────────────────────────────────┐  │   description / template_html    │
  │research_document_template(8 cols)│  │   css_styles / page_size ENUM    │
  │──────────────────────────────────│  │   orientation / margin_*         │
  │PK id / name / IX document_type   │  │   copies_default / variables json│
  │   description / fields_json      │  │   is_default / IX is_active      │
  │IX created_by / created/updated   │  │   created_by / created/updated   │
  └──────────────────────────────────┘  └──────────────────────────────────┘

  ═══════════════════════  12. NOTIFICATIONS, API & STATISTICS  ═══════════════════════

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │research_notification (11 cols)   │  │research_api_key (11 cols)        │
  │──────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX researcher_id          │  │PK id / IX researcher_id / name   │
  │   type ENUM(7 notification types)│  │UQ api_key varchar(64)            │
  │   title / message / link         │  │   permissions json / rate_limit  │
  │   related_entity_type/id         │  │   last_used_at / request_count   │
  │IX is_read / read_at              │  │   expires_at / IX is_active      │
  │IX created_at                     │  │   created_at                     │
  └──────────────────────────────────┘  └──────────────────────────────────┘

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │research_notification_preference  │  │research_api_log (11 cols)        │
  │  (6 cols)                        │  │──────────────────────────────────│
  │──────────────────────────────────│  │PK id / IX api_key_id             │
  │PK id / IX researcher_id          │  │IX researcher_id / IX endpoint    │
  │   notification_type              │  │   method / request_params json   │
  │   email_enabled / in_app_enabled │  │   response_code / response_time  │
  │   digest_frequency ENUM          │  │   ip_address / user_agent        │
  └──────────────────────────────────┘  │IX created_at                     │
                                        └──────────────────────────────────┘
  ┌──────────────────────────────────┐
  │research_statistics_daily (9 cols)│
  │──────────────────────────────────│
  │PK id / IX stat_date / IX stat_type│
  │   dimension / dimension_value    │
  │   count_value / sum_value        │
  │   metadata json / created_at     │
  └──────────────────────────────────┘

  ═══════════════════════  13. VIRTUAL RESEARCH ROOMS  ════════════════════════════════

  ┌──────────────────────────────────┐
  │research_room (9 cols)            │
  │──────────────────────────────────│
  │PK id bigint unsigned             │  ┌──────────────────────────────────┐
  │IX project_id / name / descr      │  │research_room_manifest (6 cols)   │
  │IX status ENUM(draft, active,     │  │──────────────────────────────────│
  │   archived)                      │  │PK id / FK room_id → research_room│
  │   created_by / max_participants  │  │   object_id / manifest_json      │
  │   created_at / updated_at        │  │   derivative_type ENUM(full,     │
  └──────────┬──────────────────────┘  │   subset, annotated) / created_at│
             │ 1:N                      └──────────────────────────────────┘
  ┌──────────▼──────────────────────┐
  │research_room_participant(5 cols)│
  │──────────────────────────────────│
  │PK id / FK room_id → research_room│
  │   user_id                        │
  │   role ENUM(owner, editor, viewer│
  │   joined_at                      │
  └──────────────────────────────────┘

  ═══════════════════════  14. RESEARCHER SUBMISSIONS  ════════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  researcher_submission (22 columns, 1 row) — User-contributed descriptions         │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id bigint unsigned / IX researcher_id / IX user_id                              │
  │    title / description / repository_id / parent_object_id / IX project_id          │
  │    source_type ENUM(online, offline) / source_file / include_images                │
  │ IX status ENUM(draft, submitted, under_review, approved, published,                │
  │                returned, rejected)                                                 │
  │    workflow_task_id / total_items / total_files / total_file_size                   │
  │    return_comment / reject_comment / published_at / submitted_at                   │
  │    created_at / updated_at                                                         │
  └──────────┬─────────────────────────────────────────────────────────────────────────┘
             │ 1:N
  ┌──────────▼────────────────────────────────┐  ┌──────────────────────────────────┐
  │researcher_submission_item (28 cols)        │  │researcher_submission_review      │
  │────────────────────────────────────────────│  │  (6 cols)                        │
  │PK id bigint unsigned                       │  │──────────────────────────────────│
  │FK submission_id → researcher_submission.id │  │PK id / FK submission_id          │
  │FK parent_item_id → self (hierarchy)        │  │   reviewer_id                    │
  │IX item_type ENUM(description, note,        │  │   action ENUM(comment, return,   │
  │   repository, creator)                     │  │   approve, reject, publish)      │
  │   title / identifier / level_of_description│  │   comment / created_at           │
  │   scope_and_content / extent_and_medium    │  └──────────────────────────────────┘
  │   date_display / date_start / date_end     │
  │   creators / subjects / places / genres    │  ┌──────────────────────────────────┐
  │   access/reproduction_conditions / notes   │  │researcher_submission_file        │
  │   repository_name / address / contact      │  │  (12 cols)                       │
  │   reference_object_id / reference_slug     │  │──────────────────────────────────│
  │   sort_order / published_object_id         │  │PK id / FK item_id → _item.id     │
  │   created_at / updated_at                  │  │   original/stored_name / path    │
  └────────────────────────────────────────────┘  │   mime_type / file_size          │
                                                  │   checksum / caption / sort_order│
                                                  │   published_do_id / created_at   │
                                                  └──────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
   NOTE: research_request and research_request_item tables DO NOT EXIST.
   Material requests use research_material_request (not a separate request entity).
   The old ERD showed these non-existent tables — corrected in this version.

   KEY RELATIONSHIPS:
   research_researcher → user.id (1:1 optional)
   research_researcher → research_researcher_type (N:1)
   research_booking → research_reading_room + research_researcher (N:1 each)
   research_material_request → research_booking + information_object (N:1 each)
   research_project → research_researcher (owner, N:1)
   research_collection/annotation/saved_search → research_researcher (N:1)
   researcher_submission → researcher_submission_item → researcher_submission_file

   GLAM/DAM & INFORMATION OBJECT LINKS:
     research_material_request.object_id ──► information_object.id (requested record)
     research_activity_material.object_id ─► information_object.id (studied material)
     research_annotation.object_id ────────► information_object.id (annotated record)
     research_assertion.object_id ─────────► information_object.id (research claim)
     research_bibliography_entry.object_id ► information_object.id (cited record)
     research_citation_log.object_id ──────► information_object.id (citation event)
     research_clipboard_project.object_id ─► information_object.id (saved to clipboard)
     research_collection_item.object_id ───► information_object.id (in collection)
     research_extraction_result.object_id ─► information_object.id (extracted data)
     research_project_resource.object_id ──► information_object.id (project resource)
     research_quality_metric.object_id ────► information_object.id (quality score)
     research_reproduction_item.object_id ─► information_object.id (reproduction)
     research_room_manifest.object_id ─────► information_object.id (room manifest)
     research_snapshot_item.object_id ─────► information_object.id (snapshot)
     research_source_assessment.object_id ─► information_object.id (source eval)
     researcher_submission.repository_id ──► repository.id (submission target)
     research_researcher.user_id ──────────► user.id (extends actor.id in AtoM)

   CROSS-PLUGIN: research_reproduction_request → ahgCartPlugin (reproduction orders)
                 research_material_request → links to physical storage for retrieval
                 research_reading_room → research_booking → research_researcher
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 10. Registry Standards & Conformance ERD

**Plugin:** ahgRegistryPlugin (32 tables)
**Subsystems:** Standards & Extensions, Software & Components, Institutions,
Vendors, Instances, ERD Documentation, Discussions, Blog, Newsletter, User Groups, OAuth
**Total: 32 tables**

```
  ════════════════════════════════════════════════════════════════════════════════════════
  REGISTRY STANDARDS & CONFORMANCE ERD — ahgRegistryPlugin (32 tables)
  Global GLAM software registry: standards, software, institutions, vendors, instances
  ════════════════════════════════════════════════════════════════════════════════════════

  ═══════════════════════  1. STANDARDS & EXTENSIONS  ═════════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  registry_standard (17 columns, 27 rows)                                           │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       bigint unsigned AUTO_INCREMENT                         │
  │ IX name                     varchar(255)                                           │
  │    acronym                  varchar(50)                                            │
  │ UQ slug                     varchar(255)                                           │
  │ IX category                 varchar(50)                                            │
  │    description / short_description text                                            │
  │    website_url              varchar(500)                                            │
  │    issuing_body             varchar(255)                                            │
  │    current_version          varchar(50)                                             │
  │    publication_year         int                                                    │
  │    sector_applicability     json                                                   │
  │    is_featured / IX is_active / sort_order                                         │
  │    created_at / updated_at  datetime                                               │
  └──────────┬─────────────────────────────────────────────────────────────────────────┘
             │ 1:N
  ┌──────────▼─────────────────────────────────────────────────────────────────────────┐
  │  registry_standard_extension (14 columns, 14 rows)                                  │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       bigint unsigned                                        │
  │ FK standard_id              bigint unsigned → registry_standard.id                 │
  │ IX extension_type           varchar(30)                                            │
  │    title                    varchar(255)                                            │
  │    description / rationale  text                                                   │
  │ IX plugin_name              varchar(100)                                            │
  │    api_endpoint / db_tables varchar(255/500)                                        │
  │    is_active / sort_order / created_by / created_at / updated_at                   │
  └─────────────────────────────────────────────────────────────────────────────────────┘

  ═══════════════════════  2. SOFTWARE, COMPONENTS & RELEASES  ════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  registry_software (46 columns, 6 rows) — Software products in the registry        │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       bigint unsigned AUTO_INCREMENT                         │
  │ IX name / UQ slug / IX vendor_id                                                   │
  │ IX category                 ENUM(ams, ims, dam, dams, cms, preservation,            │
  │                             digitization, discovery, utility, plugin,               │
  │                             integration, theme, glam, other)                       │
  │    description / short_description / logo_path / screenshot_path                   │
  │    website / documentation_url / install_url                                       │
  │    git_provider ENUM(github, gitlab, bitbucket, self_hosted, none)                  │
  │    git_url / git_default_branch / git_latest_tag / git_latest_commit               │
  │    git_is_public / git_api_token_encrypted                                         │
  │    is_internal / upload_path / upload_filename / upload_size / upload_checksum      │
  │    license / license_url / latest_version                                          │
  │    supported_platforms / glam_sectors / standards_supported / languages json        │
  │    min_php_version / min_mysql_version / requirements                               │
  │    pricing_model ENUM(free, open_source, freemium, subscription, one_time, contact)│
  │    pricing_details / is_verified / is_featured / IX is_active                      │
  │    institution_count / average_rating / rating_count / download_count              │
  │    created_by / created_at / updated_at                                            │
  └──────────┬─────────────────┬──────────────────┬────────────────────────────────────┘
             │ 1:N             │ 1:N              │ 1:N
             ▼                 ▼                  ▼
  ┌──────────────────────┐ ┌──────────────────────┐ ┌──────────────────────────────────┐
  │registry_software_    │ │registry_software_    │ │registry_setup_guide (15 cols)    │
  │component (17 cols,   │ │release (17 cols,     │ │──────────────────────────────────│
  │  84 rows)            │ │  10 rows)            │ │PK id / FK software_id →          │
  │──────────────────────│ │──────────────────────│ │  registry_software.id            │
  │PK id / IX software_id│ │PK id / IX software_id│ │IX title / slug / IX category     │
  │   name / slug        │ │IX version / release_ │ │   content text / short_descr     │
  │IX component_type ENUM│ │  type ENUM(major,    │ │   author_name / author_user_id   │
  │  (plugin, module,    │ │  minor, patch, beta, │ │   is_featured / IX is_active      │
  │  extension, theme,   │ │  rc, alpha)          │ │   view_count / sort_order        │
  │  integration, library│ │   release_notes      │ │   created_at / updated_at        │
  │  other)              │ │   git_tag / commit   │ └──────────────────────────────────┘
  │IX category / descr   │ │   git_compare_url    │
  │   short_descr/version│ │   file_path/name/    │ ┌──────────────────────────────────┐
  │   is_required/active │ │    size/checksum     │ │registry_software_standard(7 cols)│
  │   git/doc_url / icon │ │   download_count     │ │──────────────────────────────────│
  │   sort_order         │ │   is_stable /        │ │PK id / FK software_id →          │
  │   created/updated    │ │IX is_latest          │ │  registry_software.id            │
  └──────────────────────┘ │   released_at        │ │FK standard_id →                  │
                           │   created_at         │ │  registry_standard.id            │
                           └──────────────────────┘ │   conformance_level / notes      │
                                                    │   created_at / updated_at        │
                                                    └──────────────────────────────────┘

  ═══════════════════════  3. INSTITUTIONS  ════════════════════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  registry_institution (43 columns, 184 rows) — GLAM institutions directory         │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       bigint unsigned AUTO_INCREMENT                         │
  │ IX name / UQ slug                                                                  │
  │ IX institution_type         ENUM(archive, library, museum, gallery, dam,            │
  │                             heritage_site, research_centre, government,             │
  │                             university, other)                                     │
  │    glam_sectors json / description / short_description                             │
  │    logo_path / banner_path / website / email / phone / fax                         │
  │    street_address / city / province_state / postal_code / IX country               │
  │    latitude / longitude                                                            │
  │    size ENUM(small, medium, large, national)                                       │
  │    governance ENUM(public, private, ngo, academic, government, tribal, community)  │
  │    parent_body / established_year / accreditation                                  │
  │    collection_summary / collection_strengths json / total_holdings                 │
  │    digitization_percentage / descriptive_standards json                             │
  │    management_system / uses_atom / open_to_public / institution_url                │
  │    is_verified / is_featured / IX is_active                                        │
  │    verification_notes / verified_at / verified_by                                  │
  │    created_by / created_at / updated_at                                            │
  └──────────┬─────────────────────────────────────────────────────────────────────────┘
             │ 1:N
  ┌──────────▼─────────────────────────────────────┐
  │registry_institution_software (8 cols, 2 rows)   │
  │─────────────────────────────────────────────────│
  │PK id / IX institution_id / IX software_id        │
  │   instance_id / version_in_use / deployment_date │
  │   notes / created_at                             │
  └─────────────────────────────────────────────────┘

  ═══════════════════════  4. VENDORS  ════════════════════════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  registry_vendor (39 columns, 2 rows) — Service providers & developers             │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       bigint unsigned AUTO_INCREMENT                         │
  │ IX name / UQ slug / vendor_type json / specializations json                        │
  │    description / short_description / logo_path / banner_path                       │
  │    website / email / phone / street_address / city / province_state                │
  │    postal_code / IX country / company_registration / vat_number                    │
  │    established_year / team_size ENUM(solo, 2-5, 6-20, 21-50, 50+)                 │
  │    service_regions / languages / certifications json                                │
  │    github_url / gitlab_url / linkedin_url                                          │
  │    is_verified / is_featured / IX is_active                                        │
  │    verification_notes / verified_at / verified_by                                  │
  │    client_count / average_rating / rating_count                                    │
  │    created_by / created_at / updated_at                                            │
  └──────────┬──────────────────┬──────────────────────────────────────────────────────┘
             │ 1:N              │ 1:N
             ▼                  ▼
  ┌────────────────────────────┐  ┌──────────────────────────────────────────────────┐
  │registry_vendor_institution │  │registry_vendor_call_log (23 cols)                │
  │  (10 cols, 1 row)          │  │──────────────────────────────────────────────────│
  │────────────────────────────│  │PK id / IX vendor_id / IX institution_id          │
  │PK id / IX vendor_id        │  │   logged_by_user_id/name/email                  │
  │IX institution_id           │  │IX interaction_type ENUM(call, email, meeting,    │
  │   relationship_type ENUM   │  │   support_ticket, site_visit, video_call, other)│
  │   (developer, hosting,     │  │   direction ENUM(inbound, outbound) / subject   │
  │    maintenance, consulting,│  │   description text                               │
  │    digitization, training, │  │IX status ENUM(open, in_progress, resolved,       │
  │    integration)            │  │   closed, escalated) / IX priority ENUM(4 vals) │
  │   service_descr / dates    │  │   contact_name/email/phone / resolution          │
  │   is_active / is_public    │  │   resolved_at/by / IX follow_up_date / notes     │
  │   created_at               │  │   duration_minutes / IX created_at / updated_at  │
  └────────────────────────────┘  └──────────────────────────────────────────────────┘

  ═══════════════════════  5. INSTANCES & SYNC  ═══════════════════════════════════════

  ┌───────────────────────────────────────────────────────────────────────────────────┐
  │  registry_instance (28 columns, 168 rows) — Deployed software instances            │
  │─────────────────────────────────────────────────────────────────────────────────────│
  │ PK id                       bigint unsigned AUTO_INCREMENT                         │
  │ IX institution_id / name / url                                                     │
  │    instance_type            ENUM(production, staging, development, demo, offline)   │
  │    software / software_version                                                     │
  │    hosting ENUM(self_hosted, cloud, vendor_hosted, saas)                            │
  │ IX hosting_vendor_id / IX maintained_by_vendor_id                                  │
  │ IX sync_token / sync_enabled / last_sync_at / last_heartbeat_at / sync_data json   │
  │ IX status ENUM(online, offline, maintenance, decommissioned)                       │
  │    is_public / description / record_count / digital_object_count                   │
  │    storage_gb / os_environment / languages json / descriptive_standard              │
  │    feature_usage json / feature_notes json / created_at / updated_at               │
  └──────────┬─────────────────────────────────────────────────────────────────────────┘
             │ 1:N
  ┌──────────▼─────────────────────┐  ┌──────────────────────────────────┐
  │registry_instance_feature(6 cols)│  │registry_sync_log (8 cols)        │
  │─────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX instance_id           │  │PK id / IX instance_id            │
  │   feature_name / is_in_use      │  │IX event_type ENUM(register,      │
  │   comments / created_at         │  │  heartbeat, sync, update, error) │
  └─────────────────────────────────┘  │   payload json / ip_address      │
                                       │   status ENUM(success, error)    │
                                       │   error_message / IX created_at  │
                                       └──────────────────────────────────┘

  ═══════════════════════  6. COMMUNITY & CONTENT  ════════════════════════════════════

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │registry_discussion (20 cols,     │  │registry_blog_post (20 cols,      │
  │  24 rows)                        │  │  12 rows)                        │
  │──────────────────────────────────│  │──────────────────────────────────│
  │PK id / IX group_id / IX blog_id │  │PK id / IX title / UQ slug        │
  │   author_email/name/user_id     │  │   content / excerpt              │
  │IX title / content               │  │   featured_image_path            │
  │   topic_type ENUM(6 types)      │  │IX author_type ENUM(admin,vendor, │
  │   tags json / IX is_pinned      │  │  institution, user_group)        │
  │   is_locked / is_resolved       │  │   author_id / author_name        │
  │IX status ENUM(active, closed,   │  │IX category ENUM(8 types)         │
  │  hidden, spam)                   │  │   tags json / IX status ENUM     │
  │   reply_count / view_count      │  │   is_featured / is_pinned        │
  │IX last_reply_at / last_reply_by │  │   view_count / comment_count     │
  │   created_at / updated_at       │  │   comments_enabled               │
  └──────────┬──────────────────────┘  │IX published_at / created/updated │
             │ 1:N                     └──────────────────────────────────┘
  ┌──────────▼──────────────────────┐
  │registry_discussion_reply        │  ┌──────────────────────────────────┐
  │  (11 cols, 23 rows)             │  │registry_user_group (32 cols,     │
  │──────────────────────────────────│ │  5 rows)                         │
  │PK id / IX discussion_id         │  │──────────────────────────────────│
  │IX parent_reply_id (threaded)    │  │PK id / IX name / UQ slug / descr│
  │   author_email/name/user_id     │  │   logo/banner_path               │
  │   content                       │  │IX group_type ENUM(regional,      │
  │   is_accepted_answer            │  │  topic, software, institutional, │
  │   status ENUM(active,hidden,spam│  │  other)                          │
  │IX created_at / updated_at       │  │   focus_areas json               │
  └──────────────────────────────────┘ │   website / email / city         │
                                       │IX country / region / is_virtual  │
  ┌──────────────────────────────────┐ │   meeting_frequency/format/      │
  │registry_user_group_member        │ │    platform / next_meeting_*     │
  │  (10 cols, 80 rows)              │ │   mailing_list/slack/discord/    │
  │──────────────────────────────────│ │    forum urls                    │
  │PK id / IX group_id / user_id    │  │   member_count / IX is_active    │
  │   name / IX email / institution │  │   is_featured / is_verified      │
  │   role ENUM(organizer,co_org,   │  │   created_by / organizer_*       │
  │   member, speaker, sponsor)     │  │   created_at / updated_at        │
  │   is_active / email_notifications│ └──────────────────────────────────┘
  │   joined_at                     │
  └──────────────────────────────────┘

  ═══════════════════════  7. NEWSLETTER & OAUTH  ═════════════════════════════════════

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │registry_newsletter (15 cols)     │  │registry_newsletter_subscriber    │
  │──────────────────────────────────│  │  (13 cols, 51 rows)              │
  │PK id / subject / content/excerpt│  │──────────────────────────────────│
  │   author_name / author_user_id  │  │PK id / UQ email / name           │
  │IX status ENUM(draft, scheduled, │  │   user_id / institution_id       │
  │  sent, cancelled)               │  │   vendor_id                      │
  │   recipient/sent/open/click_cnt │  │IX status ENUM(active,            │
  │   scheduled_at / IX sent_at     │  │  unsubscribed, bounced)          │
  │   created_at / updated_at       │  │   subscribed/unsubscribed_at     │
  └──────────┬──────────────────────┘  │IX unsubscribe_token / confirm_*  │
             │ 1:N                     │   is_confirmed / created_at      │
  ┌──────────▼──────────────────────┐  └──────────────────────────────────┘
  │registry_newsletter_send_log     │
  │  (7 cols)                       │  ┌──────────────────────────────────┐
  │──────────────────────────────────│ │registry_oauth_account (14 cols)  │
  │PK id / IX newsletter_id         │  │──────────────────────────────────│
  │IX subscriber_id                  │  │PK id / IX user_id               │
  │   status ENUM(queued, sent,     │  │IX provider ENUM(google, facebook,│
  │   failed, bounced, opened,      │  │  github, linkedin, microsoft)   │
  │   clicked)                      │  │   provider_user_id / IX email    │
  │   sent_at / opened_at / error   │  │   name / avatar_url             │
  └──────────────────────────────────┘ │   access/refresh_token_encrypted│
                                       │   token_expires_at / profile_data│
                                       │   last_login_at / created/updated│
                                       └──────────────────────────────────┘

  ═══════════════════════  8. POLYMORPHIC TABLES  ═════════════════════════════════════

  ┌──────────────────────────────────┐  ┌──────────────────────────────────┐
  │registry_erd (17 cols, 31 rows)   │  │registry_contact (16 cols, 4 rows)│
  │──────────────────────────────────│  │──────────────────────────────────│
  │PK id / UQ plugin_name / UQ slug │  │PK id / IX entity_type ENUM       │
  │   vendor_id / display_name      │  │  (institution, vendor) / entity_id│
  │   category / description        │  │   first/last_name / email / phone│
  │   tables_json / diagram longtext│  │   mobile / job_title / department│
  │   diagram_image / notes         │  │   roles json / is_primary        │
  │   icon / color / is_active      │  │   is_public / notes / created/upd│
  │   sort_order / created/updated  │  └──────────────────────────────────┘
  └──────────────────────────────────┘
                                        ┌──────────────────────────────────┐
  ┌──────────────────────────────────┐  │registry_review (12 cols)         │
  │registry_note (10 cols)           │  │──────────────────────────────────│
  │──────────────────────────────────│  │PK id / IX entity_type ENUM       │
  │PK id / IX entity_type / entity_id│ │  (vendor, software) / entity_id  │
  │IX user_id / user_name / content │  │   reviewer_institution_id        │
  │   is_pinned / is_active         │  │   reviewer_name / reviewer_email │
  │   created_at / updated_at       │  │IX rating / title / comment       │
  └──────────────────────────────────┘  │   is_visible / is_verified       │
                                        │   created_at                     │
  ┌──────────────────────────────────┐  └──────────────────────────────────┘
  │registry_attachment (14 cols)     │
  │──────────────────────────────────│  ┌──────────────────────────────────┐
  │PK id / IX entity_type ENUM       │  │registry_tag (4 cols, 13 rows)    │
  │  (discussion, reply, blog_post,  │  │──────────────────────────────────│
  │   institution, vendor, software) │  │PK id / IX entity_type ENUM       │
  │   entity_id / file_path/name    │  │  (institution, vendor, software) │
  │   file_size / mime_type          │  │   entity_id / IX tag varchar(100)│
  │IX file_type ENUM(6 types)       │  └──────────────────────────────────┘
  │   caption / is_inline           │
  │   download_count                │  ┌──────────────────────────────────┐
  │   uploaded_by_email / user_id   │  │registry_favorite (5 cols, 2 rows)│
  │   created_at                    │  │──────────────────────────────────│
  └──────────────────────────────────┘  │PK id / IX user_id               │
                                        │IX entity_type ENUM(institution,  │
  ┌──────────────────────────────────┐  │  vendor, software, group)        │
  │registry_settings (45 rows)       │  │   entity_id / created_at         │
  │──────────────────────────────────│  └──────────────────────────────────┘
  │PK id / UQ setting_key            │
  │   setting_value / setting_type   │
  │   ENUM(text, number, boolean,    │
  │   json) / description            │
  │   created_at / updated_at        │
  └──────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
   FOREIGN KEY RELATIONSHIPS:
   registry_standard_extension.standard_id → registry_standard.id
   registry_software_standard.software_id → registry_software.id
   registry_software_standard.standard_id → registry_standard.id
   registry_setup_guide.software_id → registry_software.id

   POLYMORPHIC TABLES: registry_contact, registry_attachment, registry_note,
     registry_tag, registry_review, registry_favorite all use entity_type + entity_id

   GLAM/DAM & INFORMATION OBJECT LINKS:
     Registry is a STANDALONE subsystem — no direct information_object references.
     registry_favorite.user_id ────────► user.id (AtoM user)
     registry_note.user_id ────────────► user.id
     registry_newsletter_subscriber.user_id ► user.id
     registry_oauth_account.user_id ───► user.id
     registry_user_group_member.user_id ► user.id
     registry_erd.tables_json ─────────► References table names from ALL plugins
                                          (live schema introspection via information_schema)

   EXTERNAL REFERENCES: user.id (for created_by, user_id fields)
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 11. Digital Preservation (ahgPreservationPlugin)

**21 tables** | `tables_json`: `["preservation_event","preservation_checksum","preservation_format","preservation_package","preservation_virus_scan","preservation_fixity_check","preservation_object_format","preservation_format_conversion","preservation_format_obsolescence","preservation_migration_pathway","preservation_migration_plan","preservation_migration_plan_object","preservation_package_event","preservation_package_object","preservation_policy","preservation_replication_target","preservation_replication_log","preservation_backup_verification","preservation_stats","preservation_workflow_run","preservation_workflow_schedule"]`

```
┌─────────────────────────────────────────┐       ┌──────────────────────────────────────────┐
│         preservation_event              │       │        preservation_checksum              │
├─────────────────────────────────────────┤       ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK    │       │ id               BIGINT UNSIGNED  PK     │
│ digital_object_id INT   FK→digital_object│      │ digital_object_id INT   FK→digital_object │
│ information_object_id INT FK→info_object │       │ algorithm        ENUM(md5,sha1,sha256,512)│
│ event_type       ENUM(16 types)         │       │ checksum_value   VARCHAR(128)             │
│ event_datetime   DATETIME               │       │ file_size        BIGINT UNSIGNED           │
│ event_outcome    ENUM(success,fail,warn) │       │ verification_status ENUM(pending,valid,..)│
│ linking_agent_type ENUM(user,system,..) │       │ created_at       DATETIME                  │
│ linking_agent_value VARCHAR(255)         │       └──────────────────────────────────────────┘
│ created_at       DATETIME               │
└─────────────────────────────────────────┘       ┌──────────────────────────────────────────┐
                                                  │        preservation_virus_scan            │
┌─────────────────────────────────────────┐       ├──────────────────────────────────────────┤
│         preservation_format             │       │ id               BIGINT UNSIGNED  PK     │
├─────────────────────────────────────────┤       │ digital_object_id INT   FK→digital_object │
│ id               BIGINT UNSIGNED  PK    │       │ scan_engine      VARCHAR(50)              │
│ puid             VARCHAR(50)  PRONOM ID │       │ status           ENUM(clean,infected,err) │
│ mime_type        VARCHAR(255)           │       │ threat_name      VARCHAR(255)              │
│ format_name      VARCHAR(255)           │       │ quarantined      TINYINT(1)                │
│ risk_level       ENUM(low,med,high,crit)│       │ scanned_at       DATETIME                  │
│ preservation_action ENUM(none,monitor,..)│      └──────────────────────────────────────────┘
│ migration_target_id BIGINT FK→self      │
│ is_preservation_format TINYINT(1)       │       ┌──────────────────────────────────────────┐
└─────────────────────────────────────────┘       │     preservation_object_format            │
         │                                        ├──────────────────────────────────────────┤
         │ FK                                     │ id               BIGINT UNSIGNED  PK     │
         ▼                                        │ digital_object_id INT   FK→digital_object │
┌─────────────────────────────────────────┐       │ format_id        BIGINT FK→pres_format    │
│   preservation_format_conversion        │       │ identified_by    VARCHAR(100)              │
├─────────────────────────────────────────┤       └──────────────────────────────────────────┘
│ id               BIGINT UNSIGNED  PK    │
│ source_format_id BIGINT FK→pres_format  │       ┌──────────────────────────────────────────┐
│ target_format_id BIGINT FK→pres_format  │       │     preservation_fixity_check             │
│ tool             VARCHAR(255)           │       ├──────────────────────────────────────────┤
│ command_template TEXT                    │       │ id               BIGINT UNSIGNED  PK     │
└─────────────────────────────────────────┘       │ digital_object_id INT   FK→digital_object │
                                                  │ algorithm        VARCHAR(20)               │
┌─────────────────────────────────────────┐       │ expected_checksum VARCHAR(128)             │
│  preservation_format_obsolescence       │       │ actual_checksum  VARCHAR(128)              │
├─────────────────────────────────────────┤       │ status           VARCHAR(20)               │
│ id               BIGINT UNSIGNED  PK    │       │ checked_at       DATETIME                  │
│ format_id        BIGINT FK→pres_format  │       └──────────────────────────────────────────┘
│ risk_assessment  TEXT                    │
│ recommended_action VARCHAR(100)         │
│ review_date      DATE                   │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐       ┌──────────────────────────────────────────┐
│         preservation_package            │       │    preservation_package_object            │
├─────────────────────────────────────────┤       ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK    │──┐    │ id               BIGINT UNSIGNED  PK     │
│ uuid             CHAR(36)               │  │    │ package_id       BIGINT FK→pres_package   │
│ name             VARCHAR(255)           │  │    │ digital_object_id INT  FK→digital_object  │
│ package_type     ENUM(sip,aip,dip)      │  │    │ information_object_id INT FK→info_object  │
│ status           ENUM(draft,building,..)│  │    │ file_path        VARCHAR(1024)             │
│ package_format   ENUM(bagit,zip,tar,..) │  │    └──────────────────────────────────────────┘
│ object_count     INT UNSIGNED           │  │
│ total_size       BIGINT UNSIGNED        │  │    ┌──────────────────────────────────────────┐
│ information_object_id INT FK→info_object│  ├───►│    preservation_package_event             │
│ parent_package_id BIGINT FK→self        │  │    ├──────────────────────────────────────────┤
│ metadata         JSON                   │       │ id               BIGINT UNSIGNED  PK     │
│ created_at       DATETIME               │       │ package_id       BIGINT FK→pres_package   │
└─────────────────────────────────────────┘       │ event_type       VARCHAR(50)               │
                                                  │ details          TEXT                       │
┌─────────────────────────────────────────┐       └──────────────────────────────────────────┘
│   preservation_migration_pathway        │
├─────────────────────────────────────────┤       ┌──────────────────────────────────────────┐
│ id               BIGINT UNSIGNED  PK    │       │    preservation_migration_plan            │
│ source_format_id BIGINT FK→pres_format  │       ├──────────────────────────────────────────┤
│ target_format_id BIGINT FK→pres_format  │       │ id               BIGINT UNSIGNED  PK     │
│ tool             VARCHAR(255)           │       │ name             VARCHAR(255)              │
│ priority         INT                    │       │ status           VARCHAR(50)               │
└─────────────────────────────────────────┘       │ description      TEXT                      │
                                                  └──────────────┬───────────────────────────┘
┌─────────────────────────────────────────┐                      │ FK
│  preservation_migration_plan_object     │◄─────────────────────┘
├─────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK    │       ┌──────────────────────────────────────────┐
│ plan_id          BIGINT FK→migration_plan│      │     preservation_replication_target       │
│ digital_object_id INT  FK→digital_object│       ├──────────────────────────────────────────┤
│ status           VARCHAR(50)            │       │ id               BIGINT UNSIGNED  PK     │
└─────────────────────────────────────────┘       │ name             VARCHAR(255)              │
                                                  │ storage_type     VARCHAR(50)               │
┌─────────────────────────────────────────┐       │ config_json      JSON                      │
│     preservation_replication_log        │       └──────────────────────────────────────────┘
├─────────────────────────────────────────┤                │ FK
│ id               BIGINT UNSIGNED  PK    │◄───────────────┘
│ target_id        BIGINT FK→repl_target  │
│ digital_object_id INT  FK→digital_object│       ┌──────────────────────────────────────────┐
│ status           VARCHAR(50)            │       │     preservation_policy                   │
└─────────────────────────────────────────┘       ├──────────────────────────────────────────┤
                                                  │ id               BIGINT UNSIGNED  PK     │
┌─────────────────────────────────────────┐       │ name             VARCHAR(255)              │
│   preservation_workflow_schedule        │       │ policy_type      VARCHAR(50)               │
├─────────────────────────────────────────┤       │ schedule_json    JSON                      │
│ id               BIGINT UNSIGNED  PK    │       └──────────────────────────────────────────┘
│ name             VARCHAR(255)           │
│ task_type        VARCHAR(50)            │       ┌──────────────────────────────────────────┐
│ cron_expression  VARCHAR(50)            │       │   preservation_backup_verification        │
│ is_active        TINYINT(1)             │       ├──────────────────────────────────────────┤
└──────────────┬──────────────────────────┘       │ id               BIGINT UNSIGNED  PK     │
               │ FK                               │ backup_path      VARCHAR(1024)             │
               ▼                                  │ status           VARCHAR(50)               │
┌─────────────────────────────────────────┐       │ verified_at      DATETIME                  │
│    preservation_workflow_run            │       └──────────────────────────────────────────┘
├─────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK    │       ┌──────────────────────────────────────────┐
│ schedule_id      BIGINT FK→wf_schedule  │       │       preservation_stats                  │
│ status           VARCHAR(50)            │       ├──────────────────────────────────────────┤
│ started_at       DATETIME               │       │ id               BIGINT UNSIGNED  PK     │
│ completed_at     DATETIME               │       │ stat_date        DATE                      │
└─────────────────────────────────────────┘       │ metric_name      VARCHAR(100)              │
                                                  │ metric_value     BIGINT                    │
                                                  └──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • preservation_event.information_object_id ──► information_object.id
   • preservation_event.digital_object_id ──► digital_object.id
   • preservation_checksum.digital_object_id ──► digital_object.id
   • preservation_virus_scan.digital_object_id ──► digital_object.id
   • preservation_fixity_check.digital_object_id ──► digital_object.id
   • preservation_object_format.digital_object_id ──► digital_object.id
   • preservation_package.information_object_id ──► information_object.id
   • preservation_package_object.information_object_id ──► information_object.id
   • preservation_package_object.digital_object_id ──► digital_object.id
   • preservation_migration_plan_object.digital_object_id ──► digital_object.id
   • preservation_replication_log.digital_object_id ──► digital_object.id
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 12. AI & NER (ahgAIPlugin)

**25 tables** | `tables_json`: `["ahg_ner_entity","ahg_ner_entity_link","ahg_ner_extraction","ahg_ner_authority_stub","ahg_ner_settings","ahg_ner_usage","ahg_ai_batch","ahg_ai_job","ahg_ai_job_log","ahg_ai_pending_extraction","ahg_ai_auto_trigger_log","ahg_ai_condition_assessment","ahg_ai_condition_damage","ahg_ai_condition_history","ahg_ai_service_client","ahg_ai_service_usage","ahg_ai_training_contribution","ahg_ai_usage","ahg_ai_settings","ahg_llm_config","ahg_spellcheck_result","ahg_translation_draft","ahg_translation_log","ahg_translation_queue","ahg_translation_settings"]`

```
┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│          ahg_ner_extraction              │      │          ahg_ner_entity                   │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │──┐   │ id               BIGINT UNSIGNED  PK     │
│ object_id        INT     FK→info_object  │  │   │ extraction_id    BIGINT FK→ner_extraction│
│ backend_used     VARCHAR(50)             │  │   │ object_id        INT    FK→info_object   │
│ status           VARCHAR(50)             │  │   │ entity_type      VARCHAR(50)              │
│ entity_count     INT                     │  │   │ entity_value     VARCHAR(500)             │
│ extracted_at     TIMESTAMP               │  │   │ confidence       DECIMAL(5,4)             │
└──────────────────────────────────────────┘  │   │ status           VARCHAR(50)              │
                                              │   │ linked_actor_id  INT    FK→actor          │
                                              │   │ reviewed_by      INT    FK→user           │
                                              └──►│ correction_type  VARCHAR(20)              │
                                                  └──────────┬───────────────────────────────┘
                                                             │
                    ┌────────────────────────────────────────┐│    ┌──────────────────────────────────────┐
                    │     ahg_ner_entity_link                │▼    │    ahg_ner_authority_stub             │
                    ├────────────────────────────────────────┤     ├──────────────────────────────────────┤
                    │ id             BIGINT UNSIGNED  PK     │     │ id             BIGINT UNSIGNED  PK   │
                    │ entity_id      BIGINT FK→ner_entity    │     │ ner_entity_id  BIGINT FK→ner_entity  │
                    │ actor_id       INT    FK→actor          │     │ actor_id       INT    FK→actor       │
                    │ link_type      ENUM(exact,fuzzy,manual) │     │ source_object_id INT  FK→info_object │
                    │ confidence     DECIMAL(5,4)             │     │ entity_type    VARCHAR(50)            │
                    │ created_by     INT    FK→user           │     │ status         VARCHAR(20)            │
                    └────────────────────────────────────────┘     └──────────────────────────────────────┘

┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│            ahg_ai_batch                  │      │            ahg_ai_job                    │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │──┐   │ id               BIGINT UNSIGNED  PK     │
│ name             VARCHAR(255)            │  │   │ batch_id         BIGINT FK→ai_batch      │
│ task_types       JSON                    │  │   │ object_id        INT    FK→info_object   │
│ status           ENUM(pending,running,..)│  │   │ task_type        VARCHAR(50)              │
│ total_items      INT                     │  │   │ status           ENUM(pending,running,..) │
│ completed_items  INT                     │  │   │ result_data      JSON                     │
│ progress_percent DECIMAL(5,2)            │  │   │ processing_time_ms INT                    │
│ created_by       INT    FK→user          │  │   └──────────────────────────────────────────┘
└──────────────────────────────────────────┘  │
                                              │   ┌──────────────────────────────────────────┐
                                              └──►│          ahg_ai_job_log                  │
                                                  ├──────────────────────────────────────────┤
                                                  │ id             BIGINT UNSIGNED  PK       │
                                                  │ batch_id       BIGINT FK→ai_batch        │
                                                  │ job_id         BIGINT FK→ai_job           │
                                                  │ event_type     VARCHAR(50)                │
                                                  │ details        JSON                       │
                                                  └──────────────────────────────────────────┘

┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│     ahg_ai_condition_assessment          │      │     ahg_ai_condition_damage               │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │──┐   │ id             BIGINT UNSIGNED  PK       │
│ information_object_id INT FK→info_object │  └──►│ assessment_id  BIGINT FK→condition_assess│
│ digital_object_id INT    FK→digital_obj  │      │ damage_type    VARCHAR(50)                │
│ overall_score    DECIMAL(5,2)            │      │ severity       VARCHAR(50)                │
│ condition_grade  VARCHAR(50)             │      │ confidence     DECIMAL(4,3)               │
│ damage_count     INT                     │      │ bbox_x/y/w/h   INT (bounding box)         │
│ api_client_id    BIGINT FK→service_client│      │ area_percent   DECIMAL(5,2)               │
│ confirmed_by     INT    FK→user          │      └──────────────────────────────────────────┘
│ created_by       INT    FK→user          │
└──────────────────────────────────────────┘      ┌──────────────────────────────────────────┐
                                                  │    ahg_ai_condition_history               │
┌──────────────────────────────────────────┐      ├──────────────────────────────────────────┤
│      ahg_ai_pending_extraction           │      │ id             BIGINT UNSIGNED  PK       │
├──────────────────────────────────────────┤      │ information_object_id INT FK→info_object │
│ id               BIGINT UNSIGNED  PK     │      │ assessment_id  BIGINT FK→condition_assess│
│ object_id        INT    FK→info_object   │      │ score          DECIMAL(5,2)               │
│ digital_object_id INT   FK→digital_obj   │      │ condition_grade VARCHAR(50)               │
│ task_type        VARCHAR(50)             │      │ assessed_at    DATETIME                   │
│ status           ENUM(pending,processing)│      └──────────────────────────────────────────┘
│ attempt_count    INT                     │
└──────────────────────────────────────────┘      ┌──────────────────────────────────────────┐
                                                  │    ahg_ai_auto_trigger_log                │
┌──────────────────────────────────────────┐      ├──────────────────────────────────────────┤
│       ahg_ai_service_client              │      │ id             BIGINT UNSIGNED  PK       │
├──────────────────────────────────────────┤      │ object_id      INT    FK→info_object     │
│ id               BIGINT UNSIGNED  PK     │──┐   │ digital_object_id INT FK→digital_obj     │
│ name             VARCHAR(255)            │  │   │ task_type      VARCHAR(50)                │
│ organization     VARCHAR(255)            │  │   │ status         VARCHAR(50)                │
│ api_key          VARCHAR(64)             │  │   └──────────────────────────────────────────┘
│ tier             VARCHAR(50)             │  │
│ monthly_limit    INT                     │  │   ┌──────────────────────────────────────────┐
│ is_active        TINYINT(1)              │  │   │    ahg_ai_training_contribution           │
└──────────────────────────────────────────┘  │   ├──────────────────────────────────────────┤
                                              │   │ id             BIGINT UNSIGNED  PK       │
┌──────────────────────────────────────────┐  │   │ source         VARCHAR(50)                │
│      ahg_ai_service_usage                │  │   │ object_id      INT    FK→info_object     │
├──────────────────────────────────────────┤  │   │ client_id      BIGINT FK→service_client  │
│ id               BIGINT UNSIGNED  PK     │  │   │ image_filename VARCHAR(255)               │
│ client_id        BIGINT FK→service_client│◄─┘   │ status         VARCHAR(20)                │
│ year_month       VARCHAR(7)              │      └──────────────────────────────────────────┘
│ scans_used       INT                     │
└──────────────────────────────────────────┘      ┌──────────────────────────────────────────┐
                                                  │         ahg_llm_config                    │
┌──────────────────────────────────────────┐      ├──────────────────────────────────────────┤
│       ahg_spellcheck_result              │      │ id             INT UNSIGNED  PK          │
├──────────────────────────────────────────┤      │ provider       VARCHAR(50)                │
│ id               BIGINT UNSIGNED  PK     │      │ name           VARCHAR(100)               │
│ object_id        INT    FK→info_object   │      │ model          VARCHAR(100)               │
│ errors_json      JSON                    │      │ endpoint_url   VARCHAR(500)               │
│ error_count      INT                     │      │ api_key_encrypted TEXT                    │
│ status           ENUM(pending,reviewed,..)│      │ max_tokens     INT                        │
│ reviewed_by      INT    FK→user          │      │ temperature    DECIMAL(3,2)               │
└──────────────────────────────────────────┘      │ is_active      TINYINT(1)                 │
                                                  │ is_default     TINYINT(1)                 │
┌──────────────────────────────────────────┐      └──────────────────────────────────────────┘
│       ahg_translation_queue              │
├──────────────────────────────────────────┤      ┌──────────────────────────────────────────┐
│ id               BIGINT UNSIGNED  PK     │      │       ahg_translation_log                │
│ object_id        INT    FK→info_object   │      ├──────────────────────────────────────────┤
│ source_culture   VARCHAR(10)             │      │ id             BIGINT UNSIGNED  PK       │
│ target_culture   VARCHAR(10)             │      │ object_id      INT    FK→info_object     │
│ fields           TEXT (JSON)             │      │ field_name     VARCHAR(100)               │
│ status           ENUM(pending,processing)│      │ source_culture VARCHAR(10)                │
│ created_by       INT    FK→user          │      │ target_culture VARCHAR(10)                │
└──────────────────────────────────────────┘      │ translation_engine VARCHAR(50)            │
                                                  │ created_by     INT    FK→user             │
┌──────────────────────────────────────────┐      └──────────────────────────────────────────┘
│       ahg_translation_draft              │
├──────────────────────────────────────────┤      ┌──────────────────────────────────────────┐
│ id               BIGINT UNSIGNED  PK     │      │       ahg_ai_usage                       │
│ object_id        BIGINT                  │      ├──────────────────────────────────────────┤
│ entity_type      VARCHAR(64)             │      │ id             BIGINT UNSIGNED  PK       │
│ field_name       VARCHAR(64)             │      │ feature        VARCHAR(50)                │
│ source_culture   VARCHAR(8)              │      │ user_id        INT    FK→user             │
│ target_culture   VARCHAR(8)              │      │ endpoint       VARCHAR(100)               │
│ source_text      LONGTEXT               │      │ response_time_ms INT                      │
│ translated_text  LONGTEXT               │      │ status_code    INT                        │
│ status           ENUM(draft,applied,rej) │      └──────────────────────────────────────────┘
│ created_by_user_id BIGINT FK→user        │
└──────────────────────────────────────────┘      Settings tables: ahg_ai_settings,
                                                  ahg_ner_settings, ahg_translation_settings,
                                                  ahg_ner_usage (key-value config stores)

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • ahg_ner_extraction.object_id ──► information_object.id
   • ahg_ner_entity.object_id ──► information_object.id
   • ahg_ner_entity.linked_actor_id ──► actor.id
   • ahg_ner_entity_link.actor_id ──► actor.id
   • ahg_ner_authority_stub.actor_id ──► actor.id (created stub)
   • ahg_ner_authority_stub.source_object_id ──► information_object.id
   • ahg_ai_job.object_id ──► information_object.id
   • ahg_ai_pending_extraction.object_id ──► information_object.id
   • ahg_ai_pending_extraction.digital_object_id ──► digital_object.id
   • ahg_ai_auto_trigger_log.object_id ──► information_object.id
   • ahg_ai_auto_trigger_log.digital_object_id ──► digital_object.id
   • ahg_ai_condition_assessment.information_object_id ──► information_object.id
   • ahg_ai_condition_assessment.digital_object_id ──► digital_object.id
   • ahg_ai_condition_history.information_object_id ──► information_object.id
   • ahg_ai_training_contribution.object_id ──► information_object.id
   • ahg_spellcheck_result.object_id ──► information_object.id
   • ahg_translation_queue.object_id ──► information_object.id
   • ahg_translation_log.object_id ──► information_object.id
   • ahg_ai_batch.created_by ──► user.id
   • ahg_ai_usage.user_id ──► user.id
   • ahg_ai_condition_assessment.confirmed_by / created_by ──► user.id
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 13. Extended Rights (ahgExtendedRightsPlugin)

**10 tables** | `tables_json`: `["extended_rights","extended_rights_i18n","extended_rights_tk_label","extended_rights_batch_log","embargo","embargo_i18n","embargo_audit","embargo_exception","rights_statement","rights_statement_i18n"]`

```
┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│          extended_rights                 │      │         rights_statement                  │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │      │ id             BIGINT UNSIGNED  PK       │
│ object_id        INT    FK→info_object   │      │ uri            VARCHAR(255)               │
│ rights_statement_id BIGINT FK→rights_stmt│─────►│ code           VARCHAR(50)                │
│ creative_commons_license_id BIGINT       │      │ category       ENUM(in-copyright,no-,..) │
│ rights_date      DATE                    │      │ icon_url       VARCHAR(255)               │
│ expiry_date      DATE                    │      │ is_active      TINYINT(1)                 │
│ rights_holder    VARCHAR(255)            │      │ sort_order     INT                        │
│ is_primary       TINYINT(1)              │      └──────────────────────────────────────────┘
│ created_by       INT    FK→user          │                │ FK
│ updated_by       INT    FK→user          │      ┌──────────────────────────────────────────┐
└──────────┬───────────────────────────────┘      │       rights_statement_i18n               │
           │                                      ├──────────────────────────────────────────┤
           │ FK                                   │ id             BIGINT FK→rights_statement│
           ▼                                      │ culture        VARCHAR(16)                │
┌──────────────────────────────────────────┐      │ label          VARCHAR(255)               │
│       extended_rights_i18n               │      │ description    TEXT                       │
├──────────────────────────────────────────┤      └──────────────────────────────────────────┘
│ id               BIGINT FK→extended_rights│
│ culture          VARCHAR(16)             │
│ notes            TEXT                    │
└──────────────────────────────────────────┘

┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│       extended_rights_tk_label           │      │     extended_rights_batch_log             │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │      │ id             BIGINT UNSIGNED  PK       │
│ extended_rights_id BIGINT FK→ext_rights  │      │ batch_operation VARCHAR(100)              │
│ tk_label_code    VARCHAR(50)             │      │ affected_count INT                        │
│ assigned_by      INT    FK→user          │      │ performed_by   INT    FK→user             │
└──────────────────────────────────────────┘      └──────────────────────────────────────────┘

┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│              embargo                     │      │          embargo_audit                    │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │──┐   │ id             BIGINT UNSIGNED  PK       │
│ object_id        INT    FK→info_object   │  └──►│ embargo_id     BIGINT FK→embargo          │
│ embargo_type     ENUM(full,metadata,..)  │      │ action         VARCHAR(50)                │
│ start_date       DATE                    │      │ performed_by   INT    FK→user             │
│ end_date         DATE                    │      └──────────────────────────────────────────┘
│ is_perpetual     TINYINT(1)              │
│ status           ENUM(active,expired,..) │      ┌──────────────────────────────────────────┐
│ created_by       INT    FK→user          │      │        embargo_exception                  │
│ lifted_by        INT    FK→user          │      ├──────────────────────────────────────────┤
│ notify_on_expiry TINYINT(1)              │      │ id             BIGINT UNSIGNED  PK       │
│ notify_days_before INT                   │      │ embargo_id     BIGINT FK→embargo          │
└──────────────────────────────────────────┘      │ user_id        INT    FK→user             │
           │ FK                                   │ reason         TEXT                       │
           ▼                                      │ granted_by     INT    FK→user             │
┌──────────────────────────────────────────┐      └──────────────────────────────────────────┘
│           embargo_i18n                   │
├──────────────────────────────────────────┤
│ id               BIGINT FK→embargo       │
│ culture          VARCHAR(16)             │
│ reason           TEXT                    │
└──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • extended_rights.object_id ──► information_object.id
   • embargo.object_id ──► information_object.id
   • extended_rights.created_by / updated_by ──► user.id
   • embargo.created_by / lifted_by ──► user.id
   • embargo_exception.user_id / granted_by ──► user.id
   • extended_rights_tk_label.assigned_by ──► user.id
   CROSS-PLUGIN: extended_rights_tk_label ↔ ahgICIPPlugin (TK Labels)
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 14. Exhibition Management (ahgExhibitionPlugin)

**13 tables** | `tables_json`: `["exhibition","exhibition_object","exhibition_venue","exhibition_section","exhibition_gallery","exhibition_media","exhibition_event","exhibition_storyline","exhibition_storyline_stop","exhibition_checklist","exhibition_checklist_item","exhibition_checklist_template","exhibition_status_history"]`

```
┌──────────────────────────────────────────┐
│              exhibition                  │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ title            VARCHAR(255)            │
│ slug             VARCHAR(255)            │
│ exhibition_type  VARCHAR(50)             │
│ sector           VARCHAR(50) GLAM/DAM    │
│ status           VARCHAR(50)             │
│ repository_id    INT    FK→repository    │
│ start_date       DATE                    │
│ end_date         DATE                    │
│ created_by       INT    FK→user          │
└──────┬──────────┬──────────┬─────────────┘
       │          │          │
       │ FK       │ FK       │ FK
       ▼          ▼          ▼
┌──────────────┐ ┌─────────────────┐ ┌───────────────────────────────────┐
│ exhibition   │ │ exhibition      │ │ exhibition_venue                  │
│ _object      │ │ _section        │ ├───────────────────────────────────┤
├──────────────┤ ├─────────────────┤ │ id           BIGINT UNSIGNED PK   │
│ id       PK  │ │ id          PK  │ │ exhibition_id BIGINT FK→exhib     │
│ exhibition_id│ │ exhibition_id   │ │ name         VARCHAR(255)         │
│  FK→exhib   │ │  FK→exhib       │ │ address      TEXT                  │
│ object_id    │ │ title           │ │ start_date   DATE                  │
│  FK→info_obj │ │ description     │ │ end_date     DATE                  │
│ display_order│ │ display_order   │ └───────────────────────────────────┘
└──────────────┘ └─────────────────┘
                                          ┌───────────────────────────────────┐
┌──────────────────────────────────────┐  │ exhibition_gallery                │
│ exhibition_media                     │  ├───────────────────────────────────┤
├──────────────────────────────────────┤  │ id           BIGINT UNSIGNED PK   │
│ id               BIGINT UNSIGNED PK  │  │ exhibition_id BIGINT FK→exhib     │
│ exhibition_id    BIGINT FK→exhib     │  │ name         VARCHAR(255)         │
│ media_type       VARCHAR(50)         │  │ floor_plan_image VARCHAR(500)     │
│ file_path        VARCHAR(1024)       │  └───────────────────────────────────┘
│ title            VARCHAR(255)        │
└──────────────────────────────────────┘  ┌───────────────────────────────────┐
                                          │ exhibition_event                  │
┌──────────────────────────────────────┐  ├───────────────────────────────────┤
│ exhibition_storyline                 │  │ id           BIGINT UNSIGNED PK   │
├──────────────────────────────────────┤  │ exhibition_id BIGINT FK→exhib     │
│ id               BIGINT UNSIGNED PK  │  │ event_type   VARCHAR(50)          │
│ exhibition_id    BIGINT FK→exhib     │  │ event_date   DATETIME             │
│ title            VARCHAR(255)        │  │ details      TEXT                  │
│ description      TEXT                │  └───────────────────────────────────┘
└──────────┬───────────────────────────┘
           │ FK                           ┌───────────────────────────────────┐
           ▼                              │ exhibition_status_history         │
┌──────────────────────────────────────┐  ├───────────────────────────────────┤
│ exhibition_storyline_stop            │  │ id           BIGINT UNSIGNED PK   │
├──────────────────────────────────────┤  │ exhibition_id BIGINT FK→exhib     │
│ id               BIGINT UNSIGNED PK  │  │ from_status  VARCHAR(50)          │
│ storyline_id     BIGINT FK→storyline │  │ to_status    VARCHAR(50)          │
│ object_id        INT   FK→info_object│  │ changed_by   INT    FK→user       │
│ narrative        TEXT                │  └───────────────────────────────────┘
│ display_order    INT                 │
└──────────────────────────────────────┘  ┌───────────────────────────────────┐
                                          │ exhibition_checklist              │
┌──────────────────────────────────────┐  ├───────────────────────────────────┤
│ exhibition_checklist_template        │  │ id           BIGINT UNSIGNED PK   │
├──────────────────────────────────────┤  │ exhibition_id BIGINT FK→exhib     │
│ id               BIGINT UNSIGNED PK  │  │ template_id  BIGINT FK→checklist_t│
│ name             VARCHAR(255)        │  │ status       VARCHAR(50)          │
│ category         VARCHAR(100)        │  └──────────┬────────────────────────┘
│ items_json       JSON                │             │ FK
└──────────────────────────────────────┘             ▼
                                          ┌───────────────────────────────────┐
                                          │ exhibition_checklist_item         │
                                          ├───────────────────────────────────┤
                                          │ id           BIGINT UNSIGNED PK   │
                                          │ checklist_id BIGINT FK→checklist  │
                                          │ item_text    TEXT                  │
                                          │ is_completed TINYINT(1)           │
                                          └───────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • exhibition.repository_id ──► repository.id
   • exhibition.sector ──► GLAM/DAM dispatch (museum|gallery|archive|library|dam)
   • exhibition_object.object_id ──► information_object.id
   • exhibition_storyline_stop.object_id ──► information_object.id
   • exhibition.created_by ──► user.id
   • exhibition_status_history.changed_by ──► user.id
   CROSS-PLUGIN: exhibition ↔ ahgLoanPlugin (exhibition_id on ahg_loan)
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 15. Donor Agreements (ahgDonorAgreementPlugin)

**12 tables** | `tables_json`: `["donor_agreement","donor_agreement_i18n","donor_agreement_accession","donor_agreement_classification","donor_agreement_document","donor_agreement_history","donor_agreement_record","donor_agreement_reminder","donor_agreement_reminder_log","donor_agreement_restriction","donor_agreement_right","donor_agreement_rights"]`

```
┌──────────────────────────────────────────┐
│            donor_agreement               │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ title            VARCHAR(255)            │
│ agreement_number VARCHAR(100)            │
│ agreement_type   VARCHAR(50)             │
│ donor_id         INT    FK→actor         │
│ repository_id    INT    FK→repository    │
│ status           VARCHAR(50)             │
│ signed_date      DATE                    │
│ expiry_date      DATE                    │
│ created_by       INT    FK→user          │
└──┬──────┬──────┬──────┬──────┬───────────┘
   │      │      │      │      │
   │FK    │FK    │FK    │FK    │FK
   ▼      ▼      ▼      ▼      ▼
┌────────┐┌────────────┐┌──────────────┐┌──────────────┐┌──────────────────┐
│donor_  ││donor_agree-││donor_agree-  ││donor_agree-  ││donor_agreement   │
│agree-  ││ment_       ││ment_record   ││ment_document ││_accession        │
│ment_   ││classifica- │├──────────────┤├──────────────┤├──────────────────┤
│i18n    ││tion        ││ id       PK  ││ id       PK  ││ id       PK      │
├────────┤├────────────┤│ agreement_id ││ agreement_id ││ agreement_id     │
│ id  FK ││ id     PK  ││  FK→donor_agr││  FK→donor_agr││  FK→donor_agr    │
│ culture││ agreement  ││ info_object  ││ file_path    ││ accession_id     │
│ title  ││  _id FK    ││  _id FK→IO   ││ document_type││  FK→accession    │
│ descr. ││ security_  │└──────────────┘└──────────────┘└──────────────────┘
└────────┘│ classif.   │
          └────────────┘
┌──────────────────────────────────────┐  ┌──────────────────────────────────────┐
│      donor_agreement_history         │  │    donor_agreement_restriction       │
├──────────────────────────────────────┤  ├──────────────────────────────────────┤
│ id               BIGINT UNSIGNED PK  │  │ id               BIGINT UNSIGNED PK │
│ agreement_id     BIGINT FK→donor_agr │  │ agreement_id     BIGINT FK→donor_agr│
│ action           VARCHAR(50)         │  │ restriction_type VARCHAR(50)         │
│ performed_by     INT    FK→user      │  │ details          TEXT                │
└──────────────────────────────────────┘  └──────────────────────────────────────┘

┌──────────────────────────────────────┐  ┌──────────────────────────────────────┐
│      donor_agreement_right           │  │    donor_agreement_rights            │
├──────────────────────────────────────┤  ├──────────────────────────────────────┤
│ id               BIGINT UNSIGNED PK  │  │ id               BIGINT UNSIGNED PK │
│ agreement_id     BIGINT FK→donor_agr │  │ agreement_id     BIGINT FK→donor_agr│
│ right_type       VARCHAR(50)         │  │ rights_type      VARCHAR(50)         │
│ description      TEXT                │  └──────────────────────────────────────┘
└──────────────────────────────────────┘

┌──────────────────────────────────────┐  ┌──────────────────────────────────────┐
│     donor_agreement_reminder         │  │   donor_agreement_reminder_log       │
├──────────────────────────────────────┤  ├──────────────────────────────────────┤
│ id               BIGINT UNSIGNED PK  │──►│ id             BIGINT UNSIGNED PK  │
│ agreement_id     BIGINT FK→donor_agr │  │ reminder_id    BIGINT FK→reminder   │
│ reminder_date    DATE                │  │ sent_at        DATETIME              │
│ reminder_type    VARCHAR(50)         │  │ status         VARCHAR(50)           │
└──────────────────────────────────────┘  └──────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • donor_agreement.donor_id ──► actor.id (donor is an actor)
   • donor_agreement.repository_id ──► repository.id
   • donor_agreement_record.information_object_id ──► information_object.id
   • donor_agreement_accession.accession_id ──► accession.id
   • donor_agreement.created_by ──► user.id
   • donor_agreement_history.performed_by ──► user.id
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 16. Report Builder (ahgReportBuilderPlugin)

**3 tables** | `tables_json`: `["report_template","report_section","report_schedule"]`

```
┌──────────────────────────────────────────┐
│           report_template                │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ name             VARCHAR(255)            │
│ slug             VARCHAR(255)            │
│ description      TEXT                    │
│ report_type      VARCHAR(50)             │
│ category         VARCHAR(100)            │
│ format           VARCHAR(50)             │
│ sql_query        TEXT                    │
│ is_active        TINYINT(1)              │
│ is_public        TINYINT(1)              │
│ created_by       INT    FK→user          │
│ repository_id    INT    FK→repository    │
└──────────┬──────────┬────────────────────┘
           │          │
           │ FK       │ FK
           ▼          ▼
┌─────────────────────────────┐  ┌─────────────────────────────────┐
│      report_section         │  │       report_schedule           │
├─────────────────────────────┤  ├─────────────────────────────────┤
│ id           BIGINT  PK     │  │ id           BIGINT  PK         │
│ template_id  BIGINT FK→tmpl │  │ template_id  BIGINT FK→tmpl     │
│ title        VARCHAR(255)   │  │ frequency    VARCHAR(50)        │
│ section_type VARCHAR(50)    │  │ cron_expression VARCHAR(100)    │
│ content      LONGTEXT       │  │ recipients_json JSON             │
│ sql_query    TEXT            │  │ last_run_at  DATETIME            │
│ display_order INT            │  │ next_run_at  DATETIME            │
└─────────────────────────────┘  │ is_active    TINYINT(1)          │
                                 └─────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • report_template.repository_id ──► repository.id
   • report_template.created_by ──► user.id
   • report_section.sql_query may reference information_object and other core tables
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 17. Provenance Tracking (ahgProvenancePlugin)

**8 tables** | `tables_json`: `["provenance_record","provenance_record_i18n","provenance_event","provenance_event_i18n","provenance_agent","provenance_agent_i18n","provenance_entry","provenance_document"]`

```
┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│          provenance_record               │      │       provenance_record_i18n              │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │──┐   │ id             BIGINT FK→prov_record     │
│ information_object_id INT FK→info_object │  │   │ culture        VARCHAR(16)                │
│ status           VARCHAR(50)             │  │   │ title          VARCHAR(255)               │
│ is_verified      TINYINT(1)              │  │   │ description    TEXT                       │
└──────────┬──────────┬────────────────────┘  │   └──────────────────────────────────────────┘
           │          │                       │
           │ FK       │ FK                    │
           ▼          ▼                       │
┌──────────────────────┐ ┌────────────────────┘
│ provenance_event     │ │ provenance_entry   │
├──────────────────────┤ ├────────────────────┤
│ id         PK        │ │ id         PK      │
│ record_id FK→record  │ │ record_id FK→rec   │
│ event_type VARCHAR   │ │ entry_date DATE    │
│ event_date DATE      │ │ description TEXT   │
│ agent_id FK→prov_agt │ │ source     VARCHAR │
└──────────────────────┘ └────────────────────┘
         │                     ┌──────────────────────────────────────────┐
         │ FK                  │       provenance_document               │
         ▼                     ├──────────────────────────────────────────┤
┌──────────────────────────┐   │ id             BIGINT UNSIGNED  PK      │
│   provenance_agent       │   │ record_id      BIGINT FK→prov_record    │
├──────────────────────────┤   │ file_path      VARCHAR(1024)            │
│ id           PK          │   │ document_type  VARCHAR(50)              │
│ actor_id     FK→actor    │   │ title          VARCHAR(255)             │
│ agent_type   VARCHAR(50) │   └──────────────────────────────────────────┘
└──────────────────────────┘
         │ FK                  ┌──────────────────────────────────────────┐
         ▼                     │     provenance_event_i18n                │
┌──────────────────────────┐   ├──────────────────────────────────────────┤
│ provenance_agent_i18n    │   │ id             BIGINT FK→prov_event     │
├──────────────────────────┤   │ culture        VARCHAR(16)               │
│ id       FK→prov_agent   │   │ description    TEXT                      │
│ culture  VARCHAR(16)     │   └──────────────────────────────────────────┘
│ name     VARCHAR(255)    │
│ description TEXT          │
└──────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • provenance_record.information_object_id ──► information_object.id
   • provenance_agent.actor_id ──► actor.id
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 18. Workflow Engine (ahgWorkflowPlugin)

**7 tables** | `tables_json`: `["ahg_workflow","ahg_workflow_step","ahg_workflow_task","ahg_workflow_history","ahg_workflow_notification","ahg_workflow_queue","ahg_workflow_sla_policy"]`

```
┌──────────────────────────────────────────┐
│            ahg_workflow                  │
├──────────────────────────────────────────┤
│ id               INT  PK                │
│ name             VARCHAR(255)            │
│ scope_type       VARCHAR(50)             │
│ scope_id         INT FK→repository/IO    │
│ trigger_event    VARCHAR(50)             │
│ applies_to       VARCHAR(50)             │
│ is_active        TINYINT(1)              │
│ is_default       TINYINT(1)              │
│ require_all_steps TINYINT(1)             │
│ allow_parallel   TINYINT(1)              │
│ notification_enabled TINYINT(1)          │
│ created_by       INT    FK→user          │
└──────────┬──────────┬────────────────────┘
           │          │
           │ FK       │ FK
           ▼          ▼
┌──────────────────────────┐  ┌──────────────────────────────────────────┐
│   ahg_workflow_step      │  │        ahg_workflow_task                 │
├──────────────────────────┤  ├──────────────────────────────────────────┤
│ id           INT  PK     │  │ id             INT  PK                  │
│ workflow_id  INT FK→wf   │  │ workflow_id    INT  FK→workflow          │
│ name         VARCHAR(255)│  │ step_id        INT  FK→wf_step          │
│ step_order   INT         │  │ object_id      INT  FK→info_object      │
│ step_type    VARCHAR(50) │  │ object_type    VARCHAR(50)               │
│ assignee_type VARCHAR(50)│  │ status         VARCHAR(50)               │
│ assignee_id  INT         │  │ assigned_to    INT  FK→user              │
│ auto_approve_days INT    │  │ priority       VARCHAR(20)               │
│ is_required  TINYINT(1)  │  │ due_date       DATETIME                  │
└──────────────────────────┘  │ started_at     DATETIME                  │
                              │ completed_at   DATETIME                  │
                              └──────────┬──────────┬────────────────────┘
                                         │          │
                                         │ FK       │ FK
                                         ▼          ▼
                              ┌──────────────────────┐ ┌─────────────────────────┐
                              │ ahg_workflow_history  │ │ahg_workflow_notification│
                              ├──────────────────────┤ ├─────────────────────────┤
                              │ id         INT PK    │ │ id         INT PK       │
                              │ task_id    FK→task   │ │ task_id    FK→task      │
                              │ workflow_id FK→wf    │ │ user_id    FK→user      │
                              │ object_id  FK→IO     │ │ notification_type       │
                              │ action     VARCHAR   │ │ subject    VARCHAR      │
                              │ from_status VARCHAR  │ │ body       TEXT         │
                              │ to_status  VARCHAR   │ │ status     VARCHAR      │
                              │ performed_by FK→user │ │ sent_at    DATETIME     │
                              │ comment    TEXT       │ └─────────────────────────┘
                              └──────────────────────┘

┌──────────────────────────────────────────┐  ┌──────────────────────────────────────────┐
│       ahg_workflow_queue                 │  │     ahg_workflow_sla_policy               │
├──────────────────────────────────────────┤  ├──────────────────────────────────────────┤
│ id               INT  PK                │  │ id             INT  PK                   │
│ task_id          INT  FK→wf_task         │  │ workflow_id    INT  FK→workflow           │
│ action           VARCHAR(50)             │  │ step_id        INT  FK→wf_step            │
│ scheduled_at     DATETIME               │  │ max_duration_hours INT                    │
│ status           VARCHAR(50)             │  │ escalation_user_id INT FK→user            │
│ processed_at     DATETIME               │  │ auto_action    VARCHAR(50)                │
└──────────────────────────────────────────┘  └──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • ahg_workflow_task.object_id ──► information_object.id (polymorphic via object_type)
   • ahg_workflow_history.object_id ──► information_object.id
   • ahg_workflow.scope_id ──► repository.id or information_object.id (via scope_type)
   • ahg_workflow_task.assigned_to ──► user.id
   • ahg_workflow_history.performed_by ──► user.id
   • ahg_workflow_notification.user_id ──► user.id
   • ahg_workflow_sla_policy.escalation_user_id ──► user.id
   • ahg_workflow.created_by ──► user.id
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 19. CDPA — Zimbabwe (ahgCDPAPlugin)

**9 tables** | `tables_json`: `["cdpa_controller_license","cdpa_dpo","cdpa_processing_activity","cdpa_consent","cdpa_data_subject_request","cdpa_breach","cdpa_dpia","cdpa_audit_log","cdpa_config"]`

```
┌──────────────────────────────────────────┐
│        cdpa_controller_license           │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ organization_name VARCHAR(255)           │
│ license_number   VARCHAR(100)            │
│ license_type     VARCHAR(50)             │
│ status           VARCHAR(50)             │
│ issued_date      DATE                    │
│ expiry_date      DATE                    │
│ repository_id    INT    FK→repository    │
└──────┬──────┬──────┬──────┬──────────────┘
       │      │      │      │
       │FK    │FK    │FK    │FK
       ▼      ▼      ▼      ▼
┌────────────┐┌────────────────┐┌──────────────────┐┌──────────────────┐
│ cdpa_dpo   ││cdpa_processing ││cdpa_data_subject ││ cdpa_breach      │
├────────────┤│_activity       ││_request          │├──────────────────┤
│ id     PK  │├────────────────┤├──────────────────┤│ id       PK      │
│ name       ││ id       PK    ││ id       PK      ││ license_id FK    │
│ email      ││ license_id FK  ││ license_id FK    ││ breach_date      │
│ phone      ││ activity_name  ││ request_type     ││ detected_date    │
│ license_id ││ purpose        ││ requester_name   ││ severity         │
│  FK→license││ legal_basis    ││ requester_email  ││ description      │
│ user_id    ││ data_categories││ status           ││ affected_count   │
│  FK→user   ││  JSON          ││ due_date         ││ notified_potraz  │
│ is_active  ││ retention_prd  ││ handled_by FK→usr││ reported_by      │
└────────────┘└──────┬─────────┘└──────────────────┘│  FK→user         │
                     │ FK                            └──────────────────┘
                     ▼
              ┌──────────────────┐  ┌──────────────────────────────────────┐
              │  cdpa_consent    │  │         cdpa_dpia                    │
              ├──────────────────┤  ├──────────────────────────────────────┤
              │ id       PK      │  │ id             BIGINT UNSIGNED PK   │
              │ activity_id FK   │  │ activity_id    BIGINT FK→proc_act   │
              │ data_subject     │  │ assessment_date DATE                │
              │ consent_type     │  │ risk_level     VARCHAR(50)          │
              │ status           │  │ mitigations    TEXT                  │
              │ obtained_at      │  │ approved_by    INT    FK→user       │
              │ withdrawn_at     │  └──────────────────────────────────────┘
              └──────────────────┘

┌──────────────────────────────────────┐  ┌──────────────────────────────────────┐
│         cdpa_audit_log               │  │          cdpa_config                 │
├──────────────────────────────────────┤  ├──────────────────────────────────────┤
│ id               BIGINT UNSIGNED PK  │  │ id             INT  PK              │
│ license_id       BIGINT FK→license   │  │ setting_key    VARCHAR(100)         │
│ action           VARCHAR(50)         │  │ setting_value  TEXT                  │
│ performed_by     INT    FK→user      │  └──────────────────────────────────────┘
│ details          TEXT                │
└──────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • cdpa_controller_license.repository_id ──► repository.id
   • cdpa_dpo.user_id ──► user.id
   • cdpa_data_subject_request.handled_by ──► user.id
   • cdpa_breach.reported_by ──► user.id
   • cdpa_dpia.approved_by ──► user.id
   • cdpa_audit_log.performed_by ──► user.id
   NOTE: CDPA tables do not directly reference information_object — they track
   organizational compliance (data controller licenses, processing activities)
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 20. Indigenous Cultural IP (ahgICIPPlugin)

**11 tables** | `tables_json`: `["icip_community","icip_tk_label_type","icip_tk_label","icip_cultural_notice_type","icip_cultural_notice","icip_notice_acknowledgement","icip_access_restriction","icip_consent","icip_consultation","icip_object_summary","icip_config"]`

```
┌──────────────────────────────────────┐      ┌──────────────────────────────────────┐
│         icip_community               │      │       icip_tk_label_type             │
├──────────────────────────────────────┤      ├──────────────────────────────────────┤
│ id             BIGINT UNSIGNED PK    │      │ id             BIGINT UNSIGNED PK   │
│ name           VARCHAR(255)          │      │ code           VARCHAR(50)          │
│ region         VARCHAR(255)          │      │ name           VARCHAR(255)         │
│ country        VARCHAR(100)          │      │ description    TEXT                  │
│ contact_name   VARCHAR(255)          │      │ icon           VARCHAR(500)         │
│ contact_email  VARCHAR(255)          │      │ category       VARCHAR(100)         │
│ is_active      TINYINT(1)            │      └──────────┬───────────────────────────┘
└──────────┬───────────────────────────┘                 │ FK
           │                                             ▼
           │                              ┌──────────────────────────────────────┐
           │                              │          icip_tk_label               │
           │                              ├──────────────────────────────────────┤
           │    FK                        │ id             BIGINT UNSIGNED PK   │
           ├─────────────────────────────►│ type_id        BIGINT FK→tk_type    │
           │                              │ object_id      INT    FK→info_object│
           │                              │ community_id   BIGINT FK→community  │
           │                              │ assigned_by    INT    FK→user       │
           │                              │ status         VARCHAR(50)          │
           │                              └──────────────────────────────────────┘
           │
           │      ┌──────────────────────────────────────┐
           │      │    icip_cultural_notice_type          │
           │      ├──────────────────────────────────────┤
           │      │ id             BIGINT UNSIGNED PK    │
           │      │ code           VARCHAR(50)           │
           │      │ name           VARCHAR(255)          │
           │      │ icon           VARCHAR(500)          │
           │      └──────────┬───────────────────────────┘
           │                 │ FK
           │                 ▼
           │      ┌──────────────────────────────────────┐
           ├─────►│       icip_cultural_notice            │
           │      ├──────────────────────────────────────┤
           │      │ id             BIGINT UNSIGNED PK    │──┐
           │      │ type_id        BIGINT FK→notice_type │  │
           │      │ object_id      INT    FK→info_object │  │
           │      │ community_id   BIGINT FK→community   │  │
           │      │ assigned_by    INT    FK→user         │  │
           │      └──────────────────────────────────────┘  │
           │                                                │ FK
           │      ┌──────────────────────────────────────┐  │
           │      │    icip_notice_acknowledgement        │◄─┘
           │      ├──────────────────────────────────────┤
           │      │ id             BIGINT UNSIGNED PK    │
           │      │ notice_id      BIGINT FK→cult_notice │
           │      │ user_id        INT    FK→user         │
           │      │ acknowledged_at DATETIME              │
           │      └──────────────────────────────────────┘
           │
           │      ┌──────────────────────────────────────┐
           ├─────►│     icip_access_restriction           │
           │      ├──────────────────────────────────────┤
           │      │ id             BIGINT UNSIGNED PK    │
           │      │ object_id      INT    FK→info_object │
           │      │ community_id   BIGINT FK→community   │
           │      │ restriction_type VARCHAR(50)          │
           │      │ reason         TEXT                   │
           │      │ is_active      TINYINT(1)             │
           │      └──────────────────────────────────────┘
           │
           │      ┌──────────────────────────────────────┐
           ├─────►│         icip_consent                  │
           │      ├──────────────────────────────────────┤
           │      │ id             BIGINT UNSIGNED PK    │
           │      │ community_id   BIGINT FK→community   │
           │      │ object_id      INT    FK→info_object │
           │      │ consent_type   VARCHAR(50)            │
           │      │ status         VARCHAR(50)            │
           │      │ given_by       VARCHAR(255)           │
           │      └──────────────────────────────────────┘
           │
           │      ┌──────────────────────────────────────┐
           └─────►│       icip_consultation               │
                  ├──────────────────────────────────────┤
                  │ id             BIGINT UNSIGNED PK    │
                  │ community_id   BIGINT FK→community   │
                  │ object_id      INT    FK→info_object │
                  │ purpose        TEXT                   │
                  │ status         VARCHAR(50)            │
                  │ conducted_by   INT    FK→user         │
                  │ scheduled_date DATE                   │
                  └──────────────────────────────────────┘

┌──────────────────────────────────────┐  ┌──────────────────────────────────────┐
│      icip_object_summary             │  │           icip_config                │
├──────────────────────────────────────┤  ├──────────────────────────────────────┤
│ id             BIGINT UNSIGNED PK    │  │ id             INT  PK              │
│ object_id      INT    FK→info_object │  │ setting_key    VARCHAR(100)         │
│ has_tk_labels  TINYINT(1)            │  │ setting_value  TEXT                  │
│ has_notices    TINYINT(1)            │  └──────────────────────────────────────┘
│ has_restrictions TINYINT(1)          │
│ community_count INT                  │
└──────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • icip_tk_label.object_id ──► information_object.id
   • icip_cultural_notice.object_id ──► information_object.id
   • icip_access_restriction.object_id ──► information_object.id
   • icip_consent.object_id ──► information_object.id
   • icip_consultation.object_id ──► information_object.id
   • icip_object_summary.object_id ──► information_object.id
   • icip_tk_label.assigned_by / icip_cultural_notice.assigned_by ──► user.id
   • icip_notice_acknowledgement.user_id ──► user.id
   • icip_consultation.conducted_by ──► user.id
   CROSS-PLUGIN: icip_tk_label ↔ ahgExtendedRightsPlugin (extended_rights_tk_label)
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 21. NAZ — Zimbabwe (ahgNAZPlugin)

**10 tables** | `tables_json`: `["naz_closure_period","naz_protected_record","naz_records_schedule","naz_transfer","naz_transfer_item","naz_researcher","naz_research_permit","naz_research_visit","naz_audit_log","naz_config"]`

```
┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│        naz_closure_period                │      │       naz_protected_record               │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │      │ id             BIGINT UNSIGNED  PK       │
│ information_object_id INT FK→info_object │      │ information_object_id INT FK→info_object │
│ closure_type     VARCHAR(50)             │      │ protection_type VARCHAR(50)               │
│ closure_years    INT                     │      │ declared_date  DATE                       │
│ start_date       DATE                    │      │ gazette_reference VARCHAR(255)            │
│ expiry_date      DATE                    │      │ is_active      TINYINT(1)                 │
│ reason           TEXT                    │      └──────────────────────────────────────────┘
│ status           VARCHAR(50)             │
│ extended_by      INT    FK→user          │      ┌──────────────────────────────────────────┐
└──────────────────────────────────────────┘      │       naz_records_schedule               │
                                                  ├──────────────────────────────────────────┤
┌──────────────────────────────────────────┐      │ id             BIGINT UNSIGNED  PK       │
│          naz_transfer                    │      │ name           VARCHAR(255)               │
├──────────────────────────────────────────┤      │ retention_period_years INT                │
│ id               BIGINT UNSIGNED  PK     │──┐   │ disposal_action VARCHAR(50)               │
│ repository_id    INT    FK→repository    │  │   │ authority_reference VARCHAR(255)          │
│ transfer_number  VARCHAR(100)            │  │   │ repository_id  INT    FK→repository       │
│ transfer_date    DATE                    │  │   └──────────────────────────────────────────┘
│ status           VARCHAR(50)             │  │
│ received_by      INT    FK→user          │  │
└──────────────────────────────────────────┘  │
                                              │ FK
                                              ▼
                              ┌──────────────────────────────────────────┐
                              │       naz_transfer_item                  │
                              ├──────────────────────────────────────────┤
                              │ id             BIGINT UNSIGNED  PK       │
                              │ transfer_id    BIGINT FK→naz_transfer    │
                              │ information_object_id INT FK→info_object │
                              │ accession_number VARCHAR(100)            │
                              └──────────────────────────────────────────┘

┌──────────────────────────────────────────┐
│          naz_researcher                  │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │──┐
│ user_id          INT    FK→user          │  │
│ registration_number VARCHAR(100)         │  │
│ institution      VARCHAR(255)            │  │
│ research_area    VARCHAR(255)            │  │
│ is_approved      TINYINT(1)              │  │
└──────────────────────────────────────────┘  │
                                              │ FK
                                              ▼
┌──────────────────────────────────────────┐
│       naz_research_permit                │──┐
├──────────────────────────────────────────┤  │
│ id               BIGINT UNSIGNED  PK     │  │
│ researcher_id    BIGINT FK→naz_researcher│  │
│ permit_number    VARCHAR(100)            │  │
│ purpose          TEXT                    │  │
│ valid_from       DATE                    │  │
│ valid_to         DATE                    │  │
│ status           VARCHAR(50)             │  │
│ approved_by      INT    FK→user          │  │
└──────────────────────────────────────────┘  │
                                              │ FK
                                              ▼
                              ┌──────────────────────────────────────────┐
                              │       naz_research_visit                 │
                              ├──────────────────────────────────────────┤
                              │ id             BIGINT UNSIGNED  PK       │
                              │ permit_id      BIGINT FK→naz_permit      │
                              │ visit_date     DATE                       │
                              │ sign_in        TIME                       │
                              │ sign_out       TIME                       │
                              │ materials_accessed TEXT                   │
                              └──────────────────────────────────────────┘

┌──────────────────────────────────────┐  ┌──────────────────────────────────────┐
│         naz_audit_log                │  │          naz_config                  │
├──────────────────────────────────────┤  ├──────────────────────────────────────┤
│ id               BIGINT UNSIGNED PK  │  │ id             INT  PK              │
│ action           VARCHAR(50)         │  │ setting_key    VARCHAR(100)         │
│ entity_type      VARCHAR(50)         │  │ setting_value  TEXT                  │
│ entity_id        BIGINT              │  └──────────────────────────────────────┘
│ performed_by     INT    FK→user      │
│ details          TEXT                │
└──────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • naz_closure_period.information_object_id ──► information_object.id
   • naz_protected_record.information_object_id ──► information_object.id
   • naz_transfer_item.information_object_id ──► information_object.id
   • naz_transfer.repository_id ──► repository.id
   • naz_records_schedule.repository_id ──► repository.id
   • naz_researcher.user_id ──► user.id
   • naz_transfer.received_by ──► user.id
   • naz_closure_period.extended_by ──► user.id
   • naz_research_permit.approved_by ──► user.id
   • naz_audit_log.performed_by ──► user.id
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 22. DOI Integration (ahgDoiPlugin)

**5 tables** | `tables_json`: `["ahg_doi","ahg_doi_config","ahg_doi_log","ahg_doi_mapping","ahg_doi_queue"]`

```
┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│              ahg_doi                     │      │          ahg_doi_config                   │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │      │ id             BIGINT UNSIGNED  PK       │
│ information_object_id INT FK→info_object │      │ repository_id  INT    FK→repository      │
│ doi              VARCHAR(255)            │      │ datacite_repo_id VARCHAR(100)             │
│ doi_url          VARCHAR(500)            │      │ datacite_prefix VARCHAR(50)               │
│ status           ENUM(draft,registered,..)│     │ datacite_password VARCHAR(255)            │
│ minted_at        DATETIME               │      │ datacite_url   VARCHAR(255)               │
│ minted_by        INT    FK→user          │      │ environment    ENUM(test,production)      │
│ datacite_response JSON                   │      │ auto_mint      TINYINT(1)                 │
│ metadata_json    JSON                    │      │ auto_mint_levels JSON                     │
│ last_sync_at     DATETIME               │      │ suffix_pattern VARCHAR(100)               │
└──────────────────────────────────────────┘      │ is_active      TINYINT(1)                 │
                                                  └──────────┬───────────────────────────────┘
┌──────────────────────────────────────────┐                 │ FK
│           ahg_doi_log                    │                 ▼
├──────────────────────────────────────────┤      ┌──────────────────────────────────────────┐
│ id               BIGINT UNSIGNED  PK     │      │         ahg_doi_mapping                   │
│ doi_id           BIGINT FK→ahg_doi       │      ├──────────────────────────────────────────┤
│ information_object_id INT FK→info_object │      │ id             BIGINT UNSIGNED  PK       │
│ action           VARCHAR(50)             │      │ config_id      BIGINT FK→doi_config      │
│ status_before    VARCHAR(50)             │      │ datacite_field VARCHAR(100)               │
│ status_after     VARCHAR(50)             │      │ atom_field     VARCHAR(100)               │
│ performed_by     INT    FK→user          │      │ transform_function VARCHAR(255)           │
│ details          JSON                    │      └──────────────────────────────────────────┘
└──────────────────────────────────────────┘

┌──────────────────────────────────────────┐
│           ahg_doi_queue                  │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ information_object_id INT FK→info_object │
│ action           VARCHAR(50)             │
│ status           ENUM(pending,processing)│
│ priority         INT                     │
│ error_message    TEXT                    │
│ created_by       INT    FK→user          │
└──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • ahg_doi.information_object_id ──► information_object.id
   • ahg_doi_log.information_object_id ──► information_object.id
   • ahg_doi_queue.information_object_id ──► information_object.id
   • ahg_doi_config.repository_id ──► repository.id
   • ahg_doi.minted_by ──► user.id
   • ahg_doi_log.performed_by ──► user.id
   • ahg_doi_queue.created_by ──► user.id
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 23. Data Ingest (ahgIngestPlugin)

**6 tables** | `tables_json`: `["ingest_session","ingest_file","ingest_mapping","ingest_row","ingest_validation","ingest_job"]`

```
┌──────────────────────────────────────────┐
│           ingest_session                 │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ name             VARCHAR(255)            │
│ status           VARCHAR(50)             │
│ sector           VARCHAR(50) GLAM/DAM    │
│ standard         VARCHAR(50)             │
│ repository_id    INT    FK→repository    │
│ parent_object_id INT    FK→info_object   │
│ user_id          INT    FK→user          │
│ settings_json    JSON                    │
└──┬──────┬──────┬─────────────────────────┘
   │      │      │
   │FK    │FK    │FK
   ▼      ▼      ▼
┌──────────────┐ ┌──────────────────┐ ┌──────────────────────────────────────┐
│ ingest_file  │ │ ingest_mapping   │ │          ingest_row                  │
├──────────────┤ ├──────────────────┤ ├──────────────────────────────────────┤
│ id       PK  │ │ id       PK      │ │ id             BIGINT UNSIGNED PK   │
│ session_id   │ │ session_id       │ │ session_id     BIGINT FK→session     │
│  FK→session  │ │  FK→session      │ │ row_number     INT                   │
│ original_name│ │ source_column    │ │ data_json      JSON                  │
│ stored_path  │ │ target_field     │ │ enriched_json  JSON                  │
│ file_type    │ │ transform        │ │ status         VARCHAR(50)           │
│ file_size    │ │ display_order    │ │ information_object_id INT FK→IO     │
│ status       │ └──────────────────┘ └──────────┬───────────────────────────┘
└──────────────┘                                  │ FK
                                                  ▼
                              ┌──────────────────────────────────────────┐
                              │        ingest_validation                 │
                              ├──────────────────────────────────────────┤
                              │ id             BIGINT UNSIGNED  PK       │
                              │ session_id     BIGINT FK→session          │
                              │ row_id         BIGINT FK→ingest_row      │
                              │ rule           VARCHAR(100)               │
                              │ severity       VARCHAR(20)                │
                              │ message        TEXT                       │
                              └──────────────────────────────────────────┘

┌──────────────────────────────────────────┐
│            ingest_job                    │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ session_id       BIGINT FK→session       │
│ status           VARCHAR(50)             │
│ progress_percent DECIMAL(5,2)            │
│ total_rows       INT                     │
│ processed_rows   INT                     │
│ error_count      INT                     │
│ started_at       DATETIME               │
│ completed_at     DATETIME               │
└──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • ingest_session.repository_id ──► repository.id
   • ingest_session.parent_object_id ──► information_object.id
   • ingest_session.sector ──► GLAM/DAM dispatch (museum|gallery|archive|library|dam)
   • ingest_row.information_object_id ──► information_object.id (created record)
   • ingest_session.user_id ──► user.id
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 24. RiC / Fuseki (ahgRicExplorerPlugin)

**5 tables** | `tables_json`: `["ric_sync_config","ric_sync_log","ric_sync_queue","ric_sync_status","ric_sync_summary"]`

```
┌──────────────────────────────────────────┐
│          ric_sync_config                 │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ setting_key      VARCHAR(100)            │
│ setting_value    TEXT                    │
│ setting_group    VARCHAR(50)             │
└──────────────────────────────────────────┘

┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│          ric_sync_queue                  │      │        ric_sync_status                    │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │      │ id             BIGINT UNSIGNED  PK       │
│ entity_type      VARCHAR(50)             │      │ entity_type    VARCHAR(50)                │
│ entity_id        INT                     │      │ entity_id      INT                        │
│ action           VARCHAR(50)             │      │ last_synced_at DATETIME                   │
│ priority         INT                     │      │ sync_status    VARCHAR(50)                │
│ status           VARCHAR(50)             │      │ triple_count   INT                        │
│ created_at       DATETIME               │      └──────────────────────────────────────────┘
│ processed_at     DATETIME               │
└──────────────────────────────────────────┘      ┌──────────────────────────────────────────┐
                                                  │       ric_sync_summary                    │
┌──────────────────────────────────────────┐      ├──────────────────────────────────────────┤
│          ric_sync_log                    │      │ id             BIGINT UNSIGNED  PK       │
├──────────────────────────────────────────┤      │ sync_date      DATE                       │
│ id               BIGINT UNSIGNED  PK     │      │ entities_synced INT                       │
│ action           VARCHAR(50)             │      │ triples_created INT                       │
│ entity_type      VARCHAR(50)             │      │ triples_deleted INT                       │
│ entity_id        INT                     │      │ duration_seconds INT                      │
│ status           VARCHAR(50)             │      └──────────────────────────────────────────┘
│ message          TEXT                    │
│ created_at       DATETIME               │
└──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • ric_sync_queue.entity_id ──► polymorphic (information_object.id, actor.id, etc.)
   • ric_sync_status.entity_id ──► polymorphic (via entity_type dispatch)
   • ric_sync_log.entity_id ──► polymorphic (via entity_type dispatch)
   NOTE: RiC sync tracks ANY AtoM entity type for triplestore synchronization
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 25. Library System — Full ILS (ahgLibraryPlugin)

**18 tables** | `tables_json`: `["library_item","library_item_creator","library_item_subject","library_copy","library_patron","library_checkout","library_hold","library_fine","library_loan_rule","library_budget","library_order","library_order_line","library_subscription","library_serial_issue","library_ill_request","library_settings","library_subject_authority","library_entity_subject_map"]`

### 25.1 Catalog Core

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                              library_item                                    │
├──────────────────────────────────────────────────────────────────────────────┤
│ id                    BIGINT UNSIGNED  PK AUTO_INCREMENT                     │
│ information_object_id INT UNSIGNED     FK→information_object.id              │
│                                                                              │
│ ── Bibliographic ──                                                          │
│ material_type         VARCHAR(50)      NOT NULL DEFAULT 'monograph'          │
│ subtitle              VARCHAR(500)                                           │
│ responsibility_statement VARCHAR(500)                                        │
│ edition               VARCHAR(255)                                           │
│ edition_statement     VARCHAR(500)                                           │
│ publisher             VARCHAR(255)                                           │
│ publication_place     VARCHAR(255)                                           │
│ publication_date      VARCHAR(100)                                           │
│ copyright_date        VARCHAR(50)                                            │
│ printing              VARCHAR(100)                                           │
│ language              VARCHAR(100)                                           │
│                                                                              │
│ ── Classification ──                                                         │
│ call_number           VARCHAR(100)                                           │
│ classification_scheme VARCHAR(50)                                            │
│ classification_number VARCHAR(100)                                           │
│ dewey_decimal         VARCHAR(50)                                            │
│ cutter_number         VARCHAR(50)                                            │
│ shelf_location        VARCHAR(100)                                           │
│                                                                              │
│ ── Identifiers ──                                                            │
│ isbn                  VARCHAR(17)                                            │
│ issn                  VARCHAR(9)                                             │
│ lccn                  VARCHAR(50)                                            │
│ oclc_number           VARCHAR(50)                                            │
│ doi                   VARCHAR(255)                                           │
│ barcode               VARCHAR(50)                                            │
│ openlibrary_id        VARCHAR(50)                                            │
│ goodreads_id          VARCHAR(50)                                            │
│ librarything_id       VARCHAR(50)                                            │
│                                                                              │
│ ── Physical ──                                                               │
│ pagination            VARCHAR(100)                                           │
│ dimensions            VARCHAR(100)                                           │
│ physical_details      TEXT                                                   │
│ accompanying_material TEXT                                                   │
│ copy_number           VARCHAR(20)                                            │
│ volume_designation    VARCHAR(100)                                           │
│                                                                              │
│ ── Series ──                                                                 │
│ series_title          VARCHAR(500)                                           │
│ series_number         VARCHAR(50)                                            │
│ series_issn           VARCHAR(9)                                             │
│ subseries_title       VARCHAR(500)                                           │
│                                                                              │
│ ── Notes ──                                                                  │
│ general_note          TEXT                                                   │
│ bibliography_note     TEXT                                                   │
│ contents_note         TEXT                                                   │
│ summary               TEXT                                                   │
│ target_audience       TEXT                                                   │
│ system_requirements   TEXT                                                   │
│ binding_note          TEXT                                                   │
│                                                                              │
│ ── Serials-specific ──                                                       │
│ frequency             VARCHAR(50)                                            │
│ former_frequency      VARCHAR(100)                                           │
│ numbering_peculiarities VARCHAR(255)                                         │
│ publication_start_date DATE                                                  │
│ publication_end_date  DATE                                                   │
│ publication_status    VARCHAR(20)                                            │
│                                                                              │
│ ── Links ──                                                                  │
│ cover_url             VARCHAR(500)                                           │
│ cover_url_original    VARCHAR(500)                                           │
│ openlibrary_url       VARCHAR(500)                                           │
│ ebook_preview_url     VARCHAR(500)                                           │
│                                                                              │
│ ── Circulation ──                                                            │
│ total_copies          SMALLINT UNSIGNED NOT NULL DEFAULT 1                   │
│ available_copies      SMALLINT UNSIGNED NOT NULL DEFAULT 1                   │
│ circulation_status    VARCHAR(30)      NOT NULL DEFAULT 'available'          │
│                                                                              │
│ ── Cataloging ──                                                             │
│ cataloging_source     VARCHAR(100)                                           │
│ cataloging_rules      VARCHAR(20)                                            │
│ encoding_level        VARCHAR(20)                                            │
│                                                                              │
│ ── Heritage Accounting (GRAP 103 / IPSAS 45) ──                             │
│ heritage_asset_id     INT UNSIGNED                                           │
│ acquisition_method    VARCHAR(50)                                            │
│ acquisition_date      DATE                                                   │
│ acquisition_cost      DECIMAL(15,2)                                          │
│ acquisition_currency  VARCHAR(3)       DEFAULT 'ZAR'                         │
│ replacement_value     DECIMAL(15,2)                                          │
│ insurance_value       DECIMAL(15,2)                                          │
│ insurance_policy      VARCHAR(100)                                           │
│ insurance_expiry      DATE                                                   │
│ asset_class_code      VARCHAR(20)                                            │
│ recognition_status    VARCHAR(30)      DEFAULT 'pending'                     │
│ valuation_date        DATE                                                   │
│ valuation_method      VARCHAR(50)                                            │
│ valuation_notes       TEXT                                                   │
│ donor_name            VARCHAR(255)                                           │
│ donor_restrictions    TEXT                                                   │
│ condition_grade       VARCHAR(30)                                            │
│ conservation_priority VARCHAR(20)                                            │
│                                                                              │
│ created_at            TIMESTAMP                                              │
│ updated_at            TIMESTAMP                                              │
└──────────┬───────────────────┬───────────────────────────────────────────────┘
           │                   │
           │ FK                │ FK
           ▼                   ▼
┌─────────────────────────────────┐  ┌─────────────────────────────────────────┐
│     library_item_creator        │  │       library_item_subject               │
├─────────────────────────────────┤  ├─────────────────────────────────────────┤
│ id              BIGINT PK       │  │ id              BIGINT PK               │
│ library_item_id BIGINT FK→item  │  │ library_item_id BIGINT FK→item          │
│ name            VARCHAR(500)    │  │ heading         VARCHAR(500)             │
│ role            VARCHAR(50)     │  │ subject_type    VARCHAR(50) def 'topic'  │
│ is_primary      TINYINT(1)      │  │ source          VARCHAR(100)             │
│ sort_order      INT def 0       │  │ uri             VARCHAR(500)             │
│ authority_uri   VARCHAR(500)    │  │ lcsh_id         VARCHAR(100)             │
│ created_at      TIMESTAMP       │  │ authority_id    BIGINT FK→subj_auth      │
└─────────────────────────────────┘  │ dewey_number    VARCHAR(50)              │
                                     │ lcc_number      VARCHAR(50)              │
                                     │ subdivisions    JSON                     │
                                     │ created_at      TIMESTAMP                │
                                     └─────────────────────────────────────────┘
```

### 25.2 Copy Management

```
┌─────────────────────────────────────────────────────────────┐
│                     library_copy                            │
├─────────────────────────────────────────────────────────────┤
│ id                BIGINT UNSIGNED  PK AUTO_INCREMENT        │
│ library_item_id   BIGINT UNSIGNED  FK→library_item.id       │
│ copy_number       SMALLINT UNSIGNED NOT NULL DEFAULT 1      │
│ barcode           VARCHAR(50)      UNIQUE                   │
│ accession_number  VARCHAR(50)      INDEX                    │
│ call_number_suffix VARCHAR(20)                              │
│ shelf_location    VARCHAR(100)                              │
│ branch            VARCHAR(100)     INDEX                    │
│ status            VARCHAR(30)      NOT NULL DEFAULT 'available' │
│ condition_grade   VARCHAR(30)                               │
│ condition_notes   TEXT                                      │
│ acquisition_method VARCHAR(50)                              │
│ acquisition_date  DATE                                      │
│ acquisition_cost  DECIMAL(15,2)                             │
│ acquisition_source VARCHAR(255)                             │
│ withdrawal_date   DATE                                      │
│ withdrawal_reason TEXT                                      │
│ notes             TEXT                                      │
│ created_at        TIMESTAMP                                 │
│ updated_at        TIMESTAMP                                 │
└─────────────────────────────────────────────────────────────┘
  Status values: available, checked_out, on_hold, in_transit,
                 in_repair, lost, missing, withdrawn
```

### 25.3 Patron Management

```
┌─────────────────────────────────────────────────────────────┐
│                    library_patron                           │
├─────────────────────────────────────────────────────────────┤
│ id                BIGINT UNSIGNED  PK AUTO_INCREMENT        │
│ actor_id          INT UNSIGNED     FK→actor.id              │
│ card_number       VARCHAR(50)      NOT NULL UNIQUE          │
│ patron_type       VARCHAR(30)      NOT NULL DEFAULT 'public'│
│ first_name        VARCHAR(100)     NOT NULL                 │
│ last_name         VARCHAR(100)     NOT NULL INDEX           │
│ email             VARCHAR(255)     INDEX                    │
│ phone             VARCHAR(50)                               │
│ address           TEXT                                      │
│ institution       VARCHAR(255)                              │
│ department        VARCHAR(100)                              │
│ id_number         VARCHAR(50)                               │
│ date_of_birth     DATE                                      │
│ membership_start  DATE             NOT NULL                 │
│ membership_expiry DATE             INDEX                    │
│ max_checkouts     SMALLINT UNSIGNED NOT NULL DEFAULT 5      │
│ max_renewals      SMALLINT UNSIGNED NOT NULL DEFAULT 2      │
│ max_holds         SMALLINT UNSIGNED NOT NULL DEFAULT 3      │
│ borrowing_status  VARCHAR(20)      NOT NULL DEFAULT 'active'│
│ suspension_reason TEXT                                      │
│ suspension_until  DATE                                      │
│ total_fines_owed  DECIMAL(10,2)    NOT NULL DEFAULT 0.00    │
│ total_fines_paid  DECIMAL(10,2)    NOT NULL DEFAULT 0.00    │
│ total_checkouts   INT UNSIGNED     NOT NULL DEFAULT 0       │
│ last_activity_date DATE                                     │
│ photo_url         VARCHAR(500)                              │
│ notes             TEXT                                      │
│ created_by        INT UNSIGNED                              │
│ created_at        TIMESTAMP                                 │
│ updated_at        TIMESTAMP                                 │
└─────────────────────────────────────────────────────────────┘
  Patron types: public, student, faculty, staff, researcher, institutional
  Borrowing status: active, suspended, expired, barred
```

### 25.4 Circulation

```
┌─────────────────────────────────────────────────────┐
│                library_checkout                     │
├─────────────────────────────────────────────────────┤
│ id              BIGINT UNSIGNED  PK AUTO_INCREMENT  │
│ copy_id         BIGINT UNSIGNED  FK→library_copy.id │
│ patron_id       BIGINT UNSIGNED  FK→library_patron  │
│ checkout_date   DATETIME         NOT NULL INDEX     │
│ due_date        DATE             NOT NULL INDEX     │
│ return_date     DATETIME                            │
│ renewed_count   SMALLINT UNSIGNED NOT NULL DEFAULT 0│
│ status          VARCHAR(30)      NOT NULL INDEX     │
│ checkout_notes  TEXT                                 │
│ return_notes    TEXT                                 │
│ return_condition VARCHAR(30)                        │
│ checked_out_by  INT UNSIGNED                        │
│ checked_in_by   INT UNSIGNED                        │
│ created_at      TIMESTAMP                           │
│ updated_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
  Status values: active, returned, lost, claimed_returned

┌─────────────────────────────────────────────────────┐
│                 library_hold                        │
├─────────────────────────────────────────────────────┤
│ id              BIGINT UNSIGNED  PK AUTO_INCREMENT  │
│ library_item_id BIGINT UNSIGNED  FK→library_item    │
│ patron_id       BIGINT UNSIGNED  FK→library_patron  │
│ hold_date       DATETIME         NOT NULL           │
│ expiry_date     DATE                                │
│ pickup_branch   VARCHAR(100)                        │
│ queue_position  SMALLINT UNSIGNED NOT NULL DEFAULT 1│
│ status          VARCHAR(30)      NOT NULL INDEX     │
│ notification_sent TINYINT(1)     NOT NULL DEFAULT 0 │
│ notification_date DATETIME                          │
│ fulfilled_date  DATETIME                            │
│ cancelled_date  DATETIME                            │
│ cancel_reason   TEXT                                 │
│ notes           TEXT                                 │
│ created_at      TIMESTAMP                           │
│ updated_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
  Hold status: pending, ready, fulfilled, cancelled, expired

┌─────────────────────────────────────────────────────┐
│                 library_fine                         │
├─────────────────────────────────────────────────────┤
│ id              BIGINT UNSIGNED  PK AUTO_INCREMENT  │
│ patron_id       BIGINT UNSIGNED  FK→library_patron  │
│ checkout_id     BIGINT UNSIGNED  FK→library_checkout│
│ fine_type       VARCHAR(30)      NOT NULL INDEX     │
│ amount          DECIMAL(10,2)    NOT NULL           │
│ paid_amount     DECIMAL(10,2)    NOT NULL DEFAULT 0 │
│ currency        VARCHAR(3)       NOT NULL DEFAULT 'ZAR' │
│ status          VARCHAR(20)      NOT NULL INDEX     │
│ description     TEXT                                 │
│ fine_date       DATE             NOT NULL INDEX     │
│ payment_date    DATETIME                            │
│ payment_method  VARCHAR(30)                         │
│ payment_reference VARCHAR(100)                      │
│ waived_by       INT UNSIGNED                        │
│ waived_date     DATETIME                            │
│ waive_reason    TEXT                                 │
│ notes           TEXT                                 │
│ created_at      TIMESTAMP                           │
│ updated_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
  Fine types: overdue, lost, damaged, processing, replacement
  Status: outstanding, paid, waived, partial

┌─────────────────────────────────────────────────────┐
│               library_loan_rule                     │
├─────────────────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK AUTO_INCREMENT │
│ material_type    VARCHAR(50)      NOT NULL INDEX    │
│ patron_type      VARCHAR(30)      NOT NULL DEF '*'  │
│ loan_period_days SMALLINT UNSIGNED NOT NULL DEF 14  │
│ renewal_period_days SMALLINT UNSIGNED NOT NULL DEF 14│
│ max_renewals     SMALLINT UNSIGNED NOT NULL DEF 2   │
│ fine_per_day     DECIMAL(10,2)    NOT NULL DEF 1.00 │
│ fine_cap         DECIMAL(10,2)                      │
│ grace_period_days SMALLINT UNSIGNED NOT NULL DEF 0  │
│ is_loanable      TINYINT(1)       NOT NULL DEF 1   │
│ notes            TEXT                                │
│ created_at       TIMESTAMP                          │
└─────────────────────────────────────────────────────┘
  Lookup fallback: exact match → material_type + '*' → global default
```

### 25.5 Acquisitions

```
┌─────────────────────────────────────────────────────┐
│                 library_order                       │
├─────────────────────────────────────────────────────┤
│ id              BIGINT UNSIGNED  PK AUTO_INCREMENT  │
│ order_number    VARCHAR(50)      NOT NULL UNIQUE    │
│ vendor_id       INT UNSIGNED     FK→actor.id INDEX  │
│ vendor_name     VARCHAR(255)                        │
│ order_date      DATE             NOT NULL INDEX     │
│ expected_date   DATE                                │
│ received_date   DATE                                │
│ status          VARCHAR(30)      NOT NULL INDEX     │
│ order_type      VARCHAR(30)      NOT NULL DEF 'purchase' │
│ budget_code     VARCHAR(50)      INDEX              │
│ subtotal        DECIMAL(15,2)    NOT NULL DEFAULT 0 │
│ tax             DECIMAL(15,2)    NOT NULL DEFAULT 0 │
│ shipping        DECIMAL(15,2)    NOT NULL DEFAULT 0 │
│ total           DECIMAL(15,2)    NOT NULL DEFAULT 0 │
│ currency        VARCHAR(3)       DEFAULT 'ZAR'      │
│ invoice_number  VARCHAR(100)                        │
│ invoice_date    DATE                                │
│ payment_status  VARCHAR(30)      DEFAULT 'unpaid'   │
│ shipping_address TEXT                                │
│ notes           TEXT                                 │
│ approved_by     INT UNSIGNED                        │
│ approved_date   DATETIME                            │
│ created_by      INT UNSIGNED                        │
│ created_at      TIMESTAMP                           │
│ updated_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
  Order status: draft, submitted, approved, ordered, partial, received, cancelled
  Order type: purchase, standing_order, gift, exchange
  Payment status: unpaid, partial, paid

┌─────────────────────────────────────────────────────┐
│              library_order_line                     │
├─────────────────────────────────────────────────────┤
│ id              BIGINT UNSIGNED  PK AUTO_INCREMENT  │
│ order_id        BIGINT UNSIGNED  FK→library_order   │
│ library_item_id BIGINT UNSIGNED  FK→library_item    │
│ title           VARCHAR(500)     NOT NULL           │
│ isbn            VARCHAR(17)      INDEX              │
│ issn            VARCHAR(9)                          │
│ author          VARCHAR(255)                        │
│ publisher       VARCHAR(255)                        │
│ edition         VARCHAR(100)                        │
│ material_type   VARCHAR(50)                         │
│ quantity        SMALLINT UNSIGNED NOT NULL DEFAULT 1│
│ unit_price      DECIMAL(15,2)    NOT NULL DEFAULT 0 │
│ discount_percent DECIMAL(5,2)    NOT NULL DEFAULT 0 │
│ line_total      DECIMAL(15,2)    NOT NULL DEFAULT 0 │
│ quantity_received SMALLINT UNSIGNED NOT NULL DEF 0  │
│ received_date   DATE                                │
│ status          VARCHAR(30)      NOT NULL INDEX     │
│ budget_code     VARCHAR(50)                         │
│ fund_code       VARCHAR(50)                         │
│ notes           TEXT                                 │
│ created_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
  Line status: ordered, partial, received, cancelled, backordered

┌─────────────────────────────────────────────────────┐
│                library_budget                       │
├─────────────────────────────────────────────────────┤
│ id              BIGINT UNSIGNED  PK AUTO_INCREMENT  │
│ budget_code     VARCHAR(50)      NOT NULL INDEX     │
│ fund_name       VARCHAR(255)     NOT NULL           │
│ fiscal_year     VARCHAR(9)       NOT NULL INDEX     │
│ allocated_amount DECIMAL(15,2)   NOT NULL DEFAULT 0 │
│ committed_amount DECIMAL(15,2)   NOT NULL DEFAULT 0 │
│ spent_amount    DECIMAL(15,2)    NOT NULL DEFAULT 0 │
│ currency        VARCHAR(3)       DEFAULT 'ZAR'      │
│ category        VARCHAR(50)      INDEX              │
│ department      VARCHAR(100)                        │
│ notes           TEXT                                 │
│ status          VARCHAR(20)      NOT NULL INDEX     │
│ created_by      INT UNSIGNED                        │
│ created_at      TIMESTAMP                           │
│ updated_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
  Budget status: active, frozen, closed
```

### 25.6 Serials

```
┌─────────────────────────────────────────────────────┐
│              library_subscription                   │
├─────────────────────────────────────────────────────┤
│ id              BIGINT UNSIGNED  PK AUTO_INCREMENT  │
│ library_item_id BIGINT UNSIGNED  FK→library_item    │
│ vendor_id       INT UNSIGNED                        │
│ subscription_number VARCHAR(100)                    │
│ status          VARCHAR(30)      NOT NULL INDEX     │
│ start_date      DATE             NOT NULL           │
│ end_date        DATE                                │
│ renewal_date    DATE             INDEX              │
│ frequency       VARCHAR(30)                         │
│ issues_per_year SMALLINT UNSIGNED                   │
│ cost_per_year   DECIMAL(10,2)                       │
│ currency        VARCHAR(3)       DEFAULT 'ZAR'      │
│ budget_code     VARCHAR(50)                         │
│ routing_list    JSON                                │
│ delivery_method VARCHAR(30)                         │
│ notes           TEXT                                 │
│ created_by      INT UNSIGNED                        │
│ created_at      TIMESTAMP                           │
│ updated_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
  Subscription status: active, suspended, cancelled, expired

┌─────────────────────────────────────────────────────┐
│              library_serial_issue                   │
├─────────────────────────────────────────────────────┤
│ id              BIGINT UNSIGNED  PK AUTO_INCREMENT  │
│ subscription_id BIGINT UNSIGNED  FK→subscription    │
│ library_item_id BIGINT UNSIGNED  FK→library_item    │
│ volume          VARCHAR(20)      INDEX              │
│ issue_number    VARCHAR(20)                         │
│ part            VARCHAR(20)                         │
│ supplement      VARCHAR(50)                         │
│ issue_date      DATE                                │
│ expected_date   DATE             INDEX              │
│ received_date   DATE                                │
│ status          VARCHAR(30)      NOT NULL INDEX     │
│ claim_date      DATE                                │
│ claim_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0│
│ barcode         VARCHAR(50)      UNIQUE             │
│ shelf_location  VARCHAR(100)                        │
│ bound_volume_id BIGINT UNSIGNED                     │
│ notes           TEXT                                 │
│ checked_in_by   INT UNSIGNED                        │
│ created_at      TIMESTAMP                           │
│ updated_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
  Issue status: expected, received, claimed, missing, bound
```

### 25.7 Interlibrary Loan

```
┌─────────────────────────────────────────────────────┐
│              library_ill_request                    │
├─────────────────────────────────────────────────────┤
│ id              BIGINT UNSIGNED  PK AUTO_INCREMENT  │
│ request_number  VARCHAR(50)      NOT NULL UNIQUE    │
│ direction       VARCHAR(20)      NOT NULL INDEX     │
│ patron_id       BIGINT UNSIGNED  FK→library_patron  │
│ partner_library VARCHAR(255)     NOT NULL INDEX     │
│ partner_contact VARCHAR(255)                        │
│ partner_email   VARCHAR(255)                        │
│ title           VARCHAR(500)     NOT NULL           │
│ author          VARCHAR(255)                        │
│ isbn            VARCHAR(17)                         │
│ issn            VARCHAR(9)                          │
│ publisher       VARCHAR(255)                        │
│ publication_year VARCHAR(10)                        │
│ volume_issue    VARCHAR(100)                        │
│ pages           VARCHAR(50)                         │
│ library_item_id BIGINT UNSIGNED  FK→library_item    │
│ copy_id         BIGINT UNSIGNED                     │
│ status          VARCHAR(30)      NOT NULL INDEX     │
│ request_date    DATE             NOT NULL INDEX     │
│ needed_by       DATE                                │
│ shipped_date    DATE                                │
│ received_date   DATE                                │
│ due_date        DATE                                │
│ return_date     DATE                                │
│ shipping_method VARCHAR(50)                         │
│ tracking_number VARCHAR(100)                        │
│ cost            DECIMAL(10,2)                       │
│ currency        VARCHAR(3)       DEFAULT 'ZAR'      │
│ notes           TEXT                                 │
│ created_by      INT UNSIGNED                        │
│ created_at      TIMESTAMP                           │
│ updated_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
  Direction: borrow, lend
  Status: requested, approved, shipped, received, in_use, returned, cancelled
```

### 25.8 Subject Authority & Settings

```
┌─────────────────────────────────────────────────────┐
│           library_subject_authority                 │
├─────────────────────────────────────────────────────┤
│ id              BIGINT UNSIGNED  PK AUTO_INCREMENT  │
│ heading         VARCHAR(500)     NOT NULL INDEX     │
│ heading_normalized VARCHAR(500)  NOT NULL INDEX     │
│ heading_type    VARCHAR(61)      INDEX              │
│ source          VARCHAR(50)      INDEX              │
│ lcsh_id         VARCHAR(100)                        │
│ lcsh_uri        VARCHAR(500)                        │
│ suggested_dewey VARCHAR(50)                         │
│ suggested_lcc   VARCHAR(50)                         │
│ broader_terms   JSON                                │
│ narrower_terms  JSON                                │
│ related_terms   JSON                                │
│ usage_count     INT UNSIGNED     INDEX DEFAULT 1    │
│ first_used_at   TIMESTAMP                           │
│ last_used_at    TIMESTAMP                           │
│ created_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
  Heading types: topical, geographic, personal_name, corporate_name, genre_form
  Sources: lcsh, mesh, fast, local

┌─────────────────────────────────────────────────────┐
│          library_entity_subject_map                 │
├─────────────────────────────────────────────────────┤
│ id                  BIGINT UNSIGNED PK AUTO_INC     │
│ entity_type         VARCHAR(50)     NOT NULL INDEX  │
│ entity_value        VARCHAR(500)    NOT NULL        │
│ entity_normalized   VARCHAR(500)    NOT NULL        │
│ subject_authority_id BIGINT UNSIGNED FK→subj_auth   │
│ co_occurrence_count INT UNSIGNED    DEFAULT 1       │
│ confidence          DECIMAL(5,4)    INDEX DEF 1.0   │
│ created_at          TIMESTAMP                       │
│ updated_at          TIMESTAMP                       │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│              library_settings                       │
├─────────────────────────────────────────────────────┤
│ id              INT UNSIGNED     PK AUTO_INCREMENT  │
│ setting_key     VARCHAR(100)     NOT NULL UNIQUE    │
│ setting_value   TEXT                                │
│ setting_type    VARCHAR(37)      DEFAULT 'string'   │
│ description     VARCHAR(255)                        │
│ created_at      TIMESTAMP                           │
│ updated_at      TIMESTAMP                           │
└─────────────────────────────────────────────────────┘
```

### 25.9 Relationships

```
  ════════════════════════════════════════════════════════════════════════════════════════
  CORE LINKS:
   • library_item.information_object_id ──► information_object.id
   • library_item_creator.library_item_id ──► library_item.id
   • library_item_subject.library_item_id ──► library_item.id
   • library_item_subject.authority_id ──► library_subject_authority.id
   • library_entity_subject_map.subject_authority_id ──► library_subject_authority.id

  COPY & CIRCULATION:
   • library_copy.library_item_id ──► library_item.id
   • library_checkout.copy_id ──► library_copy.id
   • library_checkout.patron_id ──► library_patron.id
   • library_hold.library_item_id ──► library_item.id
   • library_hold.patron_id ──► library_patron.id
   • library_fine.patron_id ──► library_patron.id
   • library_fine.checkout_id ──► library_checkout.id

  PATRON:
   • library_patron.actor_id ──► actor.id

  ACQUISITIONS:
   • library_order.vendor_id ──► actor.id (vendor as actor)
   • library_order_line.order_id ──► library_order.id
   • library_order_line.library_item_id ──► library_item.id

  SERIALS:
   • library_subscription.library_item_id ──► library_item.id
   • library_serial_issue.subscription_id ──► library_subscription.id
   • library_serial_issue.library_item_id ──► library_item.id

  INTERLIBRARY LOAN:
   • library_ill_request.patron_id ──► library_patron.id
   • library_ill_request.library_item_id ──► library_item.id

  PUBLICATION STATUS (via status table):
   • status.object_id ──► information_object.id
   • status.type_id = 158 (publication type)
   • status.status_id: 160 = Published, 159 = Draft
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 26. Gallery Management (ahgGalleryPlugin)

**9 tables** | `tables_json`: `["gallery_artist","gallery_artist_bibliography","gallery_artist_exhibition_history","gallery_loan","gallery_loan_object","gallery_valuation","gallery_insurance_policy","gallery_facility_report","gallery_space"]`

```
┌──────────────────────────────────────────┐
│           gallery_artist                 │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │──┐
│ actor_id         INT    FK→actor         │  │
│ birth_date       DATE                    │  │
│ death_date       DATE                    │  │
│ nationality      VARCHAR(100)            │  │
│ medium           VARCHAR(255)            │  │
│ biography        TEXT                    │  │
│ is_represented   TINYINT(1)              │  │
│ repository_id    INT    FK→repository    │  │
└──────────────────────────────────────────┘  │
                                              │ FK
┌──────────────────────────────────────────┐  │  ┌──────────────────────────────────────────┐
│   gallery_artist_bibliography            │◄─┤  │ gallery_artist_exhibition_history         │
├──────────────────────────────────────────┤  │  ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │  └─►│ id             BIGINT UNSIGNED  PK       │
│ artist_id        BIGINT FK→gallery_artist│     │ artist_id      BIGINT FK→gallery_artist  │
│ title            VARCHAR(500)            │     │ exhibition_title VARCHAR(500)             │
│ publication      VARCHAR(500)            │     │ venue          VARCHAR(255)                │
│ year             INT                     │     │ year           INT                         │
│ citation         TEXT                    │     │ is_solo        TINYINT(1)                  │
└──────────────────────────────────────────┘     └──────────────────────────────────────────┘

┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│           gallery_loan                   │      │       gallery_loan_object                 │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │──┐   │ id             BIGINT UNSIGNED  PK       │
│ loan_type        VARCHAR(50) in/out      │  └──►│ loan_id        BIGINT FK→gallery_loan    │
│ borrower_id      INT    FK→actor         │      │ object_id      INT    FK→info_object     │
│ lender_id        INT    FK→actor         │      │ condition_out  TEXT                       │
│ repository_id    INT    FK→repository    │      │ condition_in   TEXT                       │
│ status           VARCHAR(50)             │      └──────────────────────────────────────────┘
│ start_date       DATE                    │
│ end_date         DATE                    │      ┌──────────────────────────────────────────┐
│ insurance_value  DECIMAL(15,2)           │      │       gallery_valuation                  │
└──────────────────────────────────────────┘      ├──────────────────────────────────────────┤
                                                  │ id             BIGINT UNSIGNED  PK       │
┌──────────────────────────────────────────┐      │ object_id      INT    FK→info_object     │
│     gallery_insurance_policy             │      │ valuation_date DATE                       │
├──────────────────────────────────────────┤      │ value_amount   DECIMAL(15,2)              │
│ id               BIGINT UNSIGNED  PK     │      │ currency       VARCHAR(10)                │
│ policy_number    VARCHAR(100)            │      │ valuator       VARCHAR(255)                │
│ provider         VARCHAR(255)            │      │ purpose        VARCHAR(100)                │
│ coverage_amount  DECIMAL(15,2)           │      └──────────────────────────────────────────┘
│ start_date       DATE                    │
│ end_date         DATE                    │      ┌──────────────────────────────────────────┐
│ repository_id    INT    FK→repository    │      │         gallery_space                    │
└──────────────────────────────────────────┘      ├──────────────────────────────────────────┤
                                                  │ id             BIGINT UNSIGNED  PK       │
┌──────────────────────────────────────────┐      │ name           VARCHAR(255)               │
│     gallery_facility_report              │      │ repository_id  INT    FK→repository       │
├──────────────────────────────────────────┤      │ area_sqm       DECIMAL(10,2)              │
│ id               BIGINT UNSIGNED  PK     │      │ capacity       INT                        │
│ venue_name       VARCHAR(255)            │      │ space_type     VARCHAR(50)                 │
│ report_date      DATE                    │      └──────────────────────────────────────────┘
│ security_rating  VARCHAR(50)             │
│ climate_control  VARCHAR(50)             │
│ lighting         VARCHAR(50)             │
│ approved_by      INT    FK→user          │
└──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • gallery_artist.actor_id ──► actor.id
   • gallery_artist.repository_id ──► repository.id
   • gallery_loan.borrower_id / lender_id ──► actor.id
   • gallery_loan.repository_id ──► repository.id
   • gallery_loan_object.object_id ──► information_object.id
   • gallery_valuation.object_id ──► information_object.id
   • gallery_insurance_policy.repository_id ──► repository.id
   • gallery_space.repository_id ──► repository.id
   • gallery_facility_report.approved_by ──► user.id
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 27. Digital Asset Management (ahgDAMPlugin)

**4 tables** | `tables_json`: `["dam_iptc_metadata","dam_external_links","dam_format_holdings","dam_version_links"]`

```
┌──────────────────────────────────────────┐      ┌──────────────────────────────────────────┐
│         dam_iptc_metadata                │      │        dam_external_links                 │
├──────────────────────────────────────────┤      ├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │      │ id             BIGINT UNSIGNED  PK       │
│ information_object_id INT FK→info_object │      │ information_object_id INT FK→info_object │
│ headline         VARCHAR(255)            │      │ platform       VARCHAR(100)               │
│ caption          TEXT                    │      │ external_url   VARCHAR(1024)              │
│ keywords         JSON                    │      │ external_id    VARCHAR(255)               │
│ creator          VARCHAR(255)            │      │ sync_status    VARCHAR(50)                │
│ credit           VARCHAR(255)            │      └──────────────────────────────────────────┘
│ source           VARCHAR(255)            │
│ copyright_notice VARCHAR(500)            │      ┌──────────────────────────────────────────┐
│ city             VARCHAR(100)            │      │       dam_format_holdings                 │
│ country          VARCHAR(100)            │      ├──────────────────────────────────────────┤
│ category         VARCHAR(100)            │      │ id             BIGINT UNSIGNED  PK       │
└──────────────────────────────────────────┘      │ information_object_id INT FK→info_object │
                                                  │ format_type    VARCHAR(50)                │
┌──────────────────────────────────────────┐      │ file_path      VARCHAR(1024)              │
│         dam_version_links                │      │ file_size      BIGINT                     │
├──────────────────────────────────────────┤      │ mime_type      VARCHAR(100)               │
│ id               BIGINT UNSIGNED  PK     │      │ is_primary     TINYINT(1)                 │
│ information_object_id INT FK→info_object │      └──────────────────────────────────────────┘
│ version_number   INT                     │
│ parent_version_id BIGINT FK→self         │
│ change_description TEXT                  │
│ created_by       INT    FK→user          │
└──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • dam_iptc_metadata.information_object_id ──► information_object.id
   • dam_external_links.information_object_id ──► information_object.id
   • dam_format_holdings.information_object_id ──► information_object.id
   • dam_version_links.information_object_id ──► information_object.id
   • dam_version_links.created_by ──► user.id
   NOTE: All DAM tables attach directly to information_object — DAM is a sector overlay
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 28. Museum Cataloging (ahgMuseumPlugin)

**1 table** | `tables_json`: `["museum_metadata"]`

```
┌──────────────────────────────────────────┐
│          museum_metadata                 │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ information_object_id INT FK→info_object │
│ object_name      VARCHAR(255)            │
│ classification   VARCHAR(255)            │
│ materials        TEXT                    │
│ dimensions       VARCHAR(255)            │
│ inscription      TEXT                    │
│ marks            TEXT                    │
│ condition_status VARCHAR(50)             │
│ acquisition_method VARCHAR(100)          │
│ acquisition_date DATE                    │
│ provenance_summary TEXT                  │
│ cultural_context VARCHAR(255)            │
│ period           VARCHAR(100)            │
│ style            VARCHAR(100)            │
│ repository_id    INT    FK→repository    │
└──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • museum_metadata.information_object_id ──► information_object.id
   • museum_metadata.repository_id ──► repository.id
   NOTE: Museum metadata is a 1:1 sector extension on information_object
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 29. Extended Contacts (ahgContactPlugin)

**3 tables** | `tables_json`: `["contact_information","contact_information_extended","contact_information_i18n"]`

```
┌──────────────────────────────────────────┐
│        contact_information               │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ actor_id         INT    FK→actor         │
│ contact_type     VARCHAR(50)             │
│ primary_name     VARCHAR(255)            │
│ email            VARCHAR(255)            │
│ telephone        VARCHAR(50)             │
│ fax              VARCHAR(50)             │
│ website          VARCHAR(500)            │
│ street_address   TEXT                    │
│ city             VARCHAR(255)            │
│ region           VARCHAR(255)            │
│ country_code     VARCHAR(5)              │
│ postal_code      VARCHAR(20)             │
│ is_primary       TINYINT(1)              │
└──────────┬──────────┬────────────────────┘
           │          │
           │ FK       │ FK
           ▼          ▼
┌──────────────────────────┐  ┌──────────────────────────────────────┐
│ contact_information      │  │  contact_information_i18n             │
│ _extended                │  ├──────────────────────────────────────┤
├──────────────────────────┤  │ id           BIGINT FK→contact_info  │
│ id         PK            │  │ culture      VARCHAR(16)              │
│ contact_information_id   │  │ note         TEXT                     │
│  FK→contact_info         │  └──────────────────────────────────────┘
│ field_name VARCHAR(100)  │
│ field_value TEXT          │
└──────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • contact_information.actor_id ──► actor.id
   NOTE: Contacts are linked to actors (persons, organizations), not directly to IO
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 30. Custom Fields / EAV (ahgCustomFieldsPlugin)

**2 tables** | `tables_json`: `["custom_field_definition","custom_field_value"]`

```
┌──────────────────────────────────────────┐
│       custom_field_definition            │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │──┐
│ entity_type      VARCHAR(50) (IO/actor/..)│  │
│ field_name       VARCHAR(100)            │  │
│ field_label      VARCHAR(255)            │  │
│ field_type       VARCHAR(50)             │  │
│   (text/textarea/date/number/boolean/    │  │
│    dropdown/url)                         │  │
│ dropdown_taxonomy VARCHAR(100)           │  │
│ is_required      TINYINT(1)              │  │
│ is_repeatable    TINYINT(1)              │  │
│ is_searchable    TINYINT(1)              │  │
│ field_group      VARCHAR(100)            │  │
│ display_order    INT                     │  │
│ validation_rules JSON                    │  │
│ is_active        TINYINT(1)              │  │
│ help_text        TEXT                    │  │
└──────────────────────────────────────────┘  │
                                              │ FK
                                              ▼
┌──────────────────────────────────────────┐
│        custom_field_value                │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │
│ definition_id    BIGINT FK→field_def     │
│ entity_type      VARCHAR(50)             │
│ object_id        INT (polymorphic FK)    │
│   ──► information_object.id             │
│   ──► actor.id                          │
│   ──► accession.id                      │
│   ──► repository.id                     │
│   ──► donor.id                          │
│   ──► function.id                       │
│ value_text       TEXT                    │
│ value_date       DATE                    │
│ value_number     DECIMAL                 │
│ value_boolean    TINYINT(1)              │
│ repeat_index     INT                     │
└──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • custom_field_value.object_id ──► polymorphic:
     - information_object.id (entity_type='informationobject')
     - actor.id (entity_type='actor')
     - accession.id (entity_type='accession')
     - repository.id (entity_type='repository')
     - donor.id (entity_type='donor')
     - function.id (entity_type='function')
   NOTE: EAV pattern — any entity type can have custom fields without schema changes
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 31. User Feedback (ahgFeedbackPlugin)

**2 tables** | `tables_json`: `["feedback","feedback_i18n"]`

```
┌──────────────────────────────────────────┐
│              feedback                    │
├──────────────────────────────────────────┤
│ id               BIGINT UNSIGNED  PK     │──┐
│ user_id          INT    FK→user          │  │
│ object_id        INT    FK→info_object   │  │
│ feedback_type    VARCHAR(50)             │  │
│ status           VARCHAR(50)             │  │
│ rating           INT                     │  │
│ is_public        TINYINT(1)              │  │
│ created_at       DATETIME               │  │
│ resolved_at      DATETIME               │  │
│ resolved_by      INT    FK→user          │  │
└──────────────────────────────────────────┘  │
                                              │ FK
                                              ▼
┌──────────────────────────────────────────┐
│           feedback_i18n                  │
├──────────────────────────────────────────┤
│ id               BIGINT FK→feedback      │
│ culture          VARCHAR(16)             │
│ subject          VARCHAR(255)            │
│ message          TEXT                    │
│ response         TEXT                    │
└──────────────────────────────────────────┘

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • feedback.object_id ──► information_object.id
   • feedback.user_id ──► user.id
   • feedback.resolved_by ──► user.id
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 32. AHG Settings (ahgSettingsPlugin)

**1 table** | `tables_json`: `["ahg_settings"]`

```
┌──────────────────────────────────────────┐
│            ahg_settings                  │
├──────────────────────────────────────────┤
│ id               INT  PK                │
│ setting_key      VARCHAR(100) UNIQUE     │
│ setting_value    TEXT                    │
│ setting_group    VARCHAR(50)             │
│ created_at       DATETIME               │
│ updated_at       DATETIME               │
└──────────────────────────────────────────┘

  Setting groups: general, multi_tenant, metadata, iiif, spectrum,
  data_protection, faces, media, photos, jobs, fuseki, ingest

  ════════════════════════════════════════════════════════════════════════════════════════
  GLAM/DAM & INFORMATION OBJECT LINKS:
   • No direct FK references — stores global configuration key-value pairs
   • Used by ALL plugins via AhgSettingsService::get('key', 'default')
  ════════════════════════════════════════════════════════════════════════════════════════
```

---

## 33. Table Relationships Summary

### 33.1 Information Object Links (object_id / information_object_id)

All plugin tables that reference `information_object.id` — the central archival record in AtoM.

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│                    INFORMATION OBJECT — CROSS-PLUGIN RELATIONSHIPS                        │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                           │
│                          ┌────────────────────────┐                                      │
│                          │  information_object     │                                      │
│                          │  (AtoM Core)            │                                      │
│                          │  .id = PK               │                                      │
│                          └───────────┬─────────────┘                                      │
│                                      │                                                    │
│  ┌───────────────────────────────────┼────────────────────────────────────────┐           │
│  │               │                   │                    │                   │           │
│  ▼               ▼                   ▼                    ▼                   ▼           │
│                                                                                           │
│  CONDITION       LOAN                PRESERVATION         SECURITY            IIIF        │
│  ┌─────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌────────────────┐ ┌──────────┐ │
│  │condition_    │ │loan_object      │ │preservation_    │ │object_security_│ │iiif_     │ │
│  │  report      │ │  .info_obj_id   │ │  event          │ │  classification│ │annotation│ │
│  │condition_    │ │ahg_loan_object  │ │preservation_    │ │object_         │ │iiif_     │ │
│  │  event       │ │  .info_obj_id   │ │  checksum       │ │  compartment   │ │ocr_text  │ │
│  │condition_    │ │ahg_loan_        │ │preservation_    │ │object_access_  │ │iiif_     │ │
│  │  assessment_ │ │  condition_rpt  │ │  package        │ │  grant         │ │manifest_ │ │
│  │  schedule    │ │  .info_obj_id   │ │premis_object    │ │object_classif_ │ │cache     │ │
│  └─────────────┘ └─────────────────┘ │ahg_file_checksum│ │  history       │ │iiif_     │ │
│                                       └─────────────────┘ │object_declass_ │ │collection│ │
│  HERITAGE ACCT   RESEARCH             RIGHTS               │  schedule      │ │  _item   │ │
│  ┌─────────────┐ ┌─────────────────┐ ┌─────────────────┐ │security_access_│ │iiif_auth_│ │
│  │heritage_    │ │research_material│ │extended_rights   │ │  log/request   │ │resource  │ │
│  │  asset      │ │  _request       │ │embargo           │ │security_       │ │iiif_     │ │
│  │heritage_    │ │research_        │ │rights_record     │ │  watermark_log │ │validation│ │
│  │  transaction│ │  annotation     │ │rights_embargo    │ └────────────────┘ │  _result │ │
│  │  _log       │ │research_        │ │rights_orphan_    │                    │object_3d_│ │
│  │heritage_    │ │  collection_item│ │  work            │ PROVENANCE         │  model   │ │
│  │  batch_item │ │research_        │ │rights_object_    │ ┌────────────────┐ └──────────┘ │
│  │heritage_    │ │  bibliography_  │ │  tk_label        │ │provenance_entry│              │
│  │  popia_flag │ │  entry          │ │rights_derivative_│ │provenance_     │ DOI          │
│  │ipsas_       │ │research_        │ │  rule            │ │  record        │ ┌──────────┐ │
│  │  heritage_  │ │  assertion      │ │object_rights_    │ │object_         │ │ahg_doi   │ │
│  │  asset      │ │research_        │ │  holder          │ │  provenance    │ │ahg_doi_  │ │
│  └─────────────┘ │  extraction_    │ │object_rights_    │ └────────────────┘ │  queue   │ │
│                   │  result         │ │  statement       │                    │ahg_doi_  │ │
│  AI & NER        │research_        │ └─────────────────┘ DONOR AGREEMENT    │  log     │ │
│  ┌─────────────┐ │  clipboard_     │                     ┌────────────────┐ └──────────┘ │
│  │ahg_ner_     │ │  project        │ CUSTOM FIELDS       │donor_agreement │              │
│  │  entity     │ │research_project_│ ┌─────────────────┐ │donor_agreement_│ ICIP         │
│  │ahg_ner_     │ │  resource       │ │custom_field_    │ │  record        │ ┌──────────┐ │
│  │  extraction │ │research_quality_│ │  value           │ │donor_provenance│ │icip_     │ │
│  │ahg_ai_job   │ │  metric         │ │  .object_id     │ └────────────────┘ │  tk_label│ │
│  │ahg_ai_      │ │research_        │ │  (polymorphic — │                    │icip_     │ │
│  │  condition_ │ │  snapshot_item  │ │   any entity)    │ EXHIBITION        │  consent │ │
│  │  assessment │ │research_        │ └─────────────────┘ ┌────────────────┐ │icip_     │ │
│  │ahg_ai_      │ │  reproduction_  │                     │exhibition_     │ │  access_ │ │
│  │  pending_   │ │  item           │ NAZ (Zimbabwe)      │  object        │ │  restrict│ │
│  │  extraction │ │research_room_   │ ┌─────────────────┐ └────────────────┘ │icip_     │ │
│  │ahg_         │ │  manifest       │ │naz_closure_     │                    │  cultural│ │
│  │  spellcheck │ │research_source_ │ │  period          │ LIBRARY           │  _notice │ │
│  │ahg_         │ │  assessment     │ │naz_protected_   │ ┌────────────────┐ │icip_     │ │
│  │  translation│ └─────────────────┘ │  record          │ │library_item    │ │  object_ │ │
│  │  _draft/log │                     │naz_transfer_item│ │  .info_obj_id  │ │  summary │ │
│  │ahg_         │ DAM                 └─────────────────┘ └────────────────┘ └──────────┘ │
│  │  description│ ┌─────────────────┐                                                     │
│  │  _suggestion│ │dam_external_    │ MUSEUM              GALLERY            FEEDBACK      │
│  └─────────────┘ │  links          │ ┌────────────────┐ ┌────────────────┐ ┌──────────┐  │
│                   │dam_format_     │ │museum_metadata  │ │gallery_        │ │feedback  │  │
│                   │  holdings      │ │  .object_id     │ │  loan_object   │ │  _i18n   │  │
│                   │dam_iptc_       │ └────────────────┘ │gallery_        │ │.object_id│  │
│                   │  metadata      │                     │  valuation     │ └──────────┘  │
│                   │dam_version_    │                     │  .object_id    │               │
│                   │  links         │                     └────────────────┘               │
│                   │  .object_id    │                                                      │
│                   └─────────────────┘                                                     │
│                                                                                           │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

### 33.2 Repository Links (repository_id)

Tables that link to `repository.id` — the archival institution in AtoM.

| Plugin | Table | Column |
|--------|-------|--------|
| ahgLoanPlugin | `ahg_loan` | `repository_id` |
| ahgHeritageAccountingPlugin | `heritage_financial_year_snapshot` | `repository_id` |
| ahgHeritageAccountingPlugin | `ipsas_heritage_asset` | `repository_id` |
| ahgIiifPlugin | `iiif_auth_repository` | `repository_id` |
| ahgResearchPlugin | `researcher_submission` | `repository_id` |
| ahgPrivacyPlugin | `privacy_institution_config` | `repository_id` |
| ahgDoiPlugin | `ahg_doi_config` | `repository_id` |
| ahgDoiPlugin | `ahg_doi_mapping` | `repository_id` |
| ahgAIPlugin | `ahg_prompt_template` | `repository_id` |
| ahgReportBuilderPlugin | `report_template` | `repository_id` |
| ahgIngestPlugin | `ingest_session` | `repository_id` |

### 33.3 Actor Links (actor_id)

Tables that link to `actor.id` — persons, organizations, families in AtoM.

| Plugin | Table | Column |
|--------|-------|--------|
| ahgProvenancePlugin | `provenance_agent` | `actor_id` |
| ahgDonorAgreementPlugin | `donor_agreement` | `actor_id` |
| ahgDonorAgreementPlugin | `donor_agreement` | `donor_id` |
| ahgGalleryPlugin | `gallery_artist` | `actor_id` |
| ahgContactPlugin | `contact_information` | `actor_id` |
| ahgAIPlugin | `ahg_ner_authority_stub` | `actor_id` |
| ahgAIPlugin | `ahg_ner_entity_link` | `actor_id` |

### 33.4 User Links (user_id)

Tables that link to `user.id` (user extends actor in AtoM).

| Plugin | Tables |
|--------|--------|
| ahgAuditTrailPlugin | `audit_log`, `ahg_audit_log`, `ahg_audit_access`, `ahg_audit_authentication` |
| ahgSecurityClearancePlugin | `user_security_clearance`, `user_security_clearance_log`, `user_compartment_access`, `security_2fa_session`, `security_access_log`, `security_access_request`, `security_audit_log`, `security_clearance_history`, `security_compliance_log`, `security_watermark_log` |
| ahgPrivacyPlugin | `privacy_approval_log`, `privacy_audit_log`, `privacy_consent_log`, `privacy_dsar_log`, `privacy_notification`, `privacy_officer` |
| ahgLoanPlugin | `ahg_loan_history` |
| ahgHeritageAccountingPlugin | `heritage_audit_log`, `heritage_batch_job`, `heritage_transaction_log`, `ipsas_audit_log` |
| ahgIiifPlugin | `iiif_auth_token`, `iiif_auth_access_log`, `object_3d_audit_log` |
| ahgResearchPlugin | `research_researcher`, `research_password_reset`, `research_room_participant`, `researcher_submission` |
| ahgWorkflowPlugin | `ahg_workflow_notification`, `workflow_history` |
| ahgAIPlugin | `ahg_ai_usage`, `ahg_ner_usage` |
| ahgIngestPlugin | `ingest_session` |
| ahgReportBuilderPlugin | `report_comment` |
| ahgICIPPlugin | `icip_notice_acknowledgement` |
| ahgCDPAPlugin | `cdpa_audit_log` |
| ahgNAZPlugin | `naz_audit_log`, `naz_researcher` |

### 33.5 GLAM Sector Dispatch

The loan system uses `sector ENUM(museum, gallery, archive, library, dam)` to drive sector-specific behavior. Cross-plugin links:

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│                          GLAM/DAM SECTOR CROSS-REFERENCES                                 │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                           │
│  ahg_loan.sector ──────┬── museum  ──► museum_metadata.object_id                         │
│                         ├── gallery ──► gallery_artist, gallery_valuation, gallery_loan   │
│                         ├── archive ──► information_object (default AtoM)                 │
│                         ├── library ──► library_item.information_object_id                │
│                         └── dam     ──► dam_iptc_metadata, dam_format_holdings            │
│                                                                                           │
│  ahg_loan.exhibition_id ────────────► exhibition.id (ahgExhibitionPlugin)                │
│  ahg_loan_object.object_type ───────► archive | museum_object | gallery_artwork | dam    │
│                                                                                           │
│  exhibition_object.information_object_id ► information_object.id                         │
│  library_item.information_object_id ─────► information_object.id                         │
│  museum_metadata.object_id ──────────────► information_object.id                         │
│  gallery_loan_object.object_id ──────────► information_object.id                         │
│  gallery_valuation.object_id ────────────► information_object.id                         │
│  dam_iptc_metadata.object_id ────────────► information_object.id                         │
│  dam_format_holdings.object_id ──────────► information_object.id                         │
│  dam_external_links.object_id ───────────► information_object.id                         │
│  dam_version_links.object_id ────────────► information_object.id                         │
│                                                                                           │
│  ingest_session.sector ──► museum | gallery | archive | library | dam                    │
│  display_object_config.object_id ► information_object.id (GLAM display routing)          │
│                                                                                           │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

### 33.6 Audit Trail Coverage

The audit system tracks changes across ALL plugins via polymorphic entity references:

```
audit_log.object_id ──────────► any AtoM object.id (field-level changes)
ahg_audit_log.entity_type ────► informationobject | actor | accession | repository | ...
ahg_audit_log.entity_id ─────► polymorphic FK to any entity
ahg_audit_access.object_id ──► digital_object / information_object (file access tracking)
```

---

*Part of the Heratio - v2.8.2*
