# AtoM AHG Framework - User Workflows

**Version:** 2.1.17
**Last Updated:** January 2026

---

## 1. Plugin Management Workflow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         PLUGIN MANAGEMENT WORKFLOW                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────┐                                                            │
│  │ Administrator   │                                                            │
│  │ wants to enable │                                                            │
│  │ new plugin      │                                                            │
│  └────────┬────────┘                                                            │
│           │                                                                      │
│           ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Step 1: Discover Available Plugins                                       │   │
│  │                                                                          │   │
│  │   $ php bin/atom extension:discover                                      │   │
│  │                                                                          │   │
│  │   Output:                                                                │   │
│  │   ┌──────────────────────────────────────────────────────────────────┐  │   │
│  │   │ AVAILABLE PLUGINS                                                 │  │   │
│  │   ├──────────────────────────────────────────────────────────────────┤  │   │
│  │   │ Name                    │ Status   │ Category    │ Version      │  │   │
│  │   ├─────────────────────────┼──────────┼─────────────┼──────────────┤  │   │
│  │   │ ahgThemeB5Plugin        │ enabled  │ theme       │ 2.1.17  ✓    │  │   │
│  │   │ ahgPrivacyPlugin        │ disabled │ compliance  │ 1.2.0        │  │   │
│  │   │ ahgIiifPlugin           │ disabled │ media       │ 1.0.0        │  │   │
│  │   └──────────────────────────────────────────────────────────────────┘  │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│           │                                                                      │
│           ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Step 2: Enable Plugin                                                    │   │
│  │                                                                          │   │
│  │   $ php bin/atom extension:enable ahgPrivacyPlugin                       │   │
│  │                                                                          │   │
│  │   ┌──────────────────────────────────────────────────────────────────┐  │   │
│  │   │ ✓ Plugin ahgPrivacyPlugin enabled                                 │  │   │
│  │   │ ✓ Database migrations applied (3 tables created)                  │  │   │
│  │   │ ✓ Cache cleared                                                   │  │   │
│  │   │                                                                    │  │   │
│  │   │ Plugin is now active. Restart PHP-FPM for changes to take effect.│  │   │
│  │   └──────────────────────────────────────────────────────────────────┘  │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│           │                                                                      │
│           ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Step 3: Restart Services                                                 │   │
│  │                                                                          │   │
│  │   $ sudo systemctl restart php8.3-fpm                                    │   │
│  │   $ php symfony cc                                                       │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│           │                                                                      │
│           ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Step 4: Configure Plugin (Admin → Settings → [Plugin Name])             │   │
│  │                                                                          │   │
│  │   Navigate to: Admin → Settings → Privacy Settings                      │   │
│  │   Configure: Retention periods, consent types, breach workflows         │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│           │                                                                      │
│           ▼                                                                      │
│       ┌───────┐                                                                 │
│       │ Done! │                                                                 │
│       └───────┘                                                                 │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Sector Configuration Workflow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                       SECTOR CONFIGURATION WORKFLOW                              │
│                  (Museum / Library / Gallery / DAM / Archive)                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Question: What type of institution are you?                              │   │
│  │                                                                          │   │
│  │   ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐    │   │
│  │   │   Museum    │  │   Library   │  │   Gallery   │  │     DAM     │    │   │
│  │   │             │  │             │  │             │  │             │    │   │
│  │   │ Spectrum 5.0│  │ MARC/RDA    │  │  Artwork    │  │   Media     │    │   │
│  │   │ CCO/CIDOC   │  │ ISBN lookup │  │  Cataloging │  │   Assets    │    │   │
│  │   └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘    │   │
│  │          │                │                │                │            │   │
│  └──────────┼────────────────┼────────────────┼────────────────┼────────────┘   │
│             │                │                │                │                 │
│             ▼                ▼                ▼                ▼                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Step 1: Enable appropriate sector plugin                                 │   │
│  │                                                                          │   │
│  │   $ php bin/atom extension:enable ahgMuseumPlugin                        │   │
│  │                      OR                                                  │   │
│  │   $ php bin/atom extension:enable ahgLibraryPlugin                       │   │
│  │                      OR                                                  │   │
│  │   $ php bin/atom extension:enable ahgGalleryPlugin                       │   │
│  │                      OR                                                  │   │
│  │   $ php bin/atom extension:enable ahgDAMPlugin                           │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│             │                                                                    │
│             ▼                                                                    │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Step 2: Sector plugin automatically configures:                          │   │
│  │                                                                          │   │
│  │   ┌────────────────────────────────────────────────────────────────┐    │   │
│  │   │ MUSEUM EXAMPLE                                                  │    │   │
│  │   ├────────────────────────────────────────────────────────────────┤    │   │
│  │   │ Field Labels:                                                   │    │   │
│  │   │   • "Extent" → "Dimensions"                                     │    │   │
│  │   │   • "Scope and Content" → "Object Description"                  │    │   │
│  │   │   • "Archival History" → "Provenance"                           │    │   │
│  │   │                                                                 │    │   │
│  │   │ Vocabularies:                                                   │    │   │
│  │   │   • Object Type: Painting, Sculpture, Textile, Ceramic...      │    │   │
│  │   │   • Material: Oil paint, Bronze, Marble, Wood...               │    │   │
│  │   │                                                                 │    │   │
│  │   │ Standards:                                                      │    │   │
│  │   │   • Spectrum 5.0 procedures enabled                            │    │   │
│  │   │   • CCO metadata fields available                              │    │   │
│  │   └────────────────────────────────────────────────────────────────┘    │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│             │                                                                    │
│             ▼                                                                    │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Step 3: Enable capability plugins as needed                              │   │
│  │                                                                          │   │
│  │   Same capabilities work across ALL sectors:                             │   │
│  │                                                                          │   │
│  │   ┌─────────────────────────────────────────────────────────────────┐   │   │
│  │   │ Capability        │ Plugin              │ Works With            │   │   │
│  │   ├───────────────────┼─────────────────────┼───────────────────────┤   │   │
│  │   │ IIIF Viewing      │ ahgIiifPlugin       │ All sectors           │   │   │
│  │   │ 3D Models         │ ahg3DModelPlugin    │ All sectors           │   │   │
│  │   │ Privacy/GDPR      │ ahgPrivacyPlugin    │ All sectors           │   │   │
│  │   │ AI/NER            │ ahgAIPlugin         │ All sectors           │   │   │
│  │   │ Loans             │ ahgLoanPlugin       │ All sectors           │   │   │
│  │   │ Condition         │ ahgConditionPlugin  │ All sectors           │   │   │
│  │   │ Rights            │ ahgExtendedRights   │ All sectors           │   │   │
│  │   └─────────────────────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│             │                                                                    │
│             ▼                                                                    │
│       ┌─────────────────────────────────────────────────────────────────┐       │
│       │ Result: Sector-specific terminology + Universal capabilities    │       │
│       └─────────────────────────────────────────────────────────────────┘       │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Record Lifecycle Workflow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          RECORD LIFECYCLE WORKFLOW                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│    ┌──────────────┐                                                             │
│    │   CREATION   │                                                             │
│    └──────┬───────┘                                                             │
│           │                                                                      │
│           ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 1. Create Record                                                         │   │
│  │    • Add description (ISAD(G) / Dublin Core / sector-specific)          │   │
│  │    • Upload digital objects                                              │   │
│  │    • Link to related records                                             │   │
│  │                                                                          │   │
│  │    Plugins triggered:                                                    │   │
│  │    ├── AhgHooks::trigger('record.created', $record)                      │   │
│  │    ├── Audit Trail: logs creation                                        │   │
│  │    └── IIIF: generates manifest (if images)                              │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│           │                                                                      │
│           ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 2. Accessioning / Cataloging                                             │   │
│  │                                                                          │   │
│  │    ┌───────────────────────────────────────────────────────────────┐    │   │
│  │    │ Optional Plugin Actions                                        │    │   │
│  │    ├───────────────────────────────────────────────────────────────┤    │   │
│  │    │ ahgConditionPlugin  → Create condition assessment              │    │   │
│  │    │ ahgHeritagePlugin   → Register as heritage asset               │    │   │
│  │    │ ahgSecurityPlugin   → Apply security classification            │    │   │
│  │    │ ahgPrivacyPlugin    → Flag PII for protection                  │    │   │
│  │    │ ahgAIPlugin         → Extract named entities                   │    │   │
│  │    └───────────────────────────────────────────────────────────────┘    │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│           │                                                                      │
│           ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 3. Publication / Access                                                  │   │
│  │                                                                          │   │
│  │    Record visibility determined by:                                      │   │
│  │    ├── Publication status (draft / published)                            │   │
│  │    ├── Security classification (if ahgSecurityPlugin enabled)            │   │
│  │    ├── Embargo dates (if ahgEmbargoPlugin enabled)                       │   │
│  │    └── User permissions (AtoM ACL + clearance level)                     │   │
│  │                                                                          │   │
│  │    Public users see:                                                     │   │
│  │    ├── Published, unclassified records                                   │   │
│  │    ├── IIIF viewers for images                                           │   │
│  │    ├── 3D model viewers                                                  │   │
│  │    └── Audio/video players                                               │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│           │                                                                      │
│           ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 4. Use / Research                                                        │   │
│  │                                                                          │   │
│  │    ┌───────────────────────────────────────────────────────────────┐    │   │
│  │    │ Researcher Actions                                             │    │   │
│  │    ├───────────────────────────────────────────────────────────────┤    │   │
│  │    │ ahgResearchPlugin   → Submit access request                    │    │   │
│  │    │ ahgResearchPlugin   → Book reading room visit                  │    │   │
│  │    │ ahgCartPlugin       → Add to cart for ordering                 │    │   │
│  │    │ ahgFavoritesPlugin  → Save to favorites                        │    │   │
│  │    │ ahgFeedbackPlugin   → Submit feedback/correction               │    │   │
│  │    └───────────────────────────────────────────────────────────────┘    │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│           │                                                                      │
│           ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 5. Loans / Exhibitions                                                   │   │
│  │                                                                          │   │
│  │    ahgLoanPlugin workflow:                                               │   │
│  │    ├── Create loan request                                               │   │
│  │    ├── Add items to loan                                                 │   │
│  │    ├── Condition check (outgoing)                                        │   │
│  │    ├── Track location during loan                                        │   │
│  │    ├── Condition check (incoming)                                        │   │
│  │    └── Close loan                                                        │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│           │                                                                      │
│           ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 6. Preservation                                                          │   │
│  │                                                                          │   │
│  │    ahgPreservationPlugin:                                                │   │
│  │    ├── Format migration (obsolete → current)                             │   │
│  │    ├── Fixity checking (checksum validation)                             │   │
│  │    ├── PREMIS metadata generation                                        │   │
│  │    └── AIP/DIP packaging                                                 │   │
│  │                                                                          │   │
│  │    ahgBackupPlugin:                                                      │   │
│  │    ├── Scheduled backups                                                 │   │
│  │    ├── Off-site replication                                              │   │
│  │    └── Disaster recovery                                                 │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│           │                                                                      │
│           ▼                                                                      │
│    ┌──────────────┐                                                             │
│    │ DISPOSITION  │                                                             │
│    │  (if needed) │                                                             │
│    └──────────────┘                                                             │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Privacy Compliance Workflow (POPIA/GDPR)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        PRIVACY COMPLIANCE WORKFLOW                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                      DATA SUBJECT ACCESS REQUEST                         │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│    ┌────────────────┐                                                           │
│    │ Data Subject   │                                                           │
│    │ submits SAR    │                                                           │
│    └───────┬────────┘                                                           │
│            │                                                                     │
│            ▼                                                                     │
│  ┌──────────────────────────────────────┐                                       │
│  │ Request received via:                │                                       │
│  │ • Email                              │                                       │
│  │ • Web form                           │                                       │
│  │ • In person                          │                                       │
│  └──────────────────┬───────────────────┘                                       │
│                     │                                                            │
│                     ▼                                                            │
│  ┌──────────────────────────────────────┐                                       │
│  │ Step 1: Log request in system        │                                       │
│  │                                      │                                       │
│  │ Admin → Privacy → SAR Requests → New │                                       │
│  │                                      │                                       │
│  │ ┌──────────────────────────────────┐ │                                       │
│  │ │ Request Type: [Access/Erasure]  │ │                                       │
│  │ │ Requester: [Name]               │ │                                       │
│  │ │ Email: [email@example.com]      │ │                                       │
│  │ │ Deadline: [auto: 30 days]       │ │                                       │
│  │ └──────────────────────────────────┘ │                                       │
│  └──────────────────┬───────────────────┘                                       │
│                     │                                                            │
│                     ▼                                                            │
│  ┌──────────────────────────────────────┐                                       │
│  │ Step 2: Verify identity              │                                       │
│  │                                      │                                       │
│  │ □ ID document verified               │                                       │
│  │ □ Proof of address verified          │                                       │
│  │ □ Identity confirmed                 │                                       │
│  └──────────────────┬───────────────────┘                                       │
│                     │                                                            │
│        ┌────────────┴────────────┐                                              │
│        ▼                         ▼                                              │
│  ┌───────────────┐        ┌───────────────┐                                     │
│  │ ACCESS        │        │ ERASURE       │                                     │
│  │ Request       │        │ Request       │                                     │
│  └───────┬───────┘        └───────┬───────┘                                     │
│          │                        │                                              │
│          ▼                        ▼                                              │
│  ┌────────────────┐       ┌────────────────┐                                    │
│  │ Search for all │       │ Search for all │                                    │
│  │ personal data: │       │ personal data: │                                    │
│  │                │       │                │                                    │
│  │ • Actor records│       │ Check if legal │                                    │
│  │ • User accounts│       │ basis allows   │                                    │
│  │ • Audit logs   │       │ deletion       │                                    │
│  │ • Research req │       │                │                                    │
│  │ • Loan records │       │ □ Archival     │                                    │
│  └────────┬───────┘       │ □ Legal hold   │                                    │
│           │               │ □ Research     │                                    │
│           │               └───────┬────────┘                                    │
│           ▼                       │                                              │
│  ┌────────────────┐               ▼                                             │
│  │ Export data in │       ┌────────────────┐                                    │
│  │ portable format│       │ Pseudonymize   │                                    │
│  │ (CSV/JSON)     │       │ or delete      │                                    │
│  └────────┬───────┘       │ records        │                                    │
│           │               └───────┬────────┘                                    │
│           │                       │                                              │
│           └───────────┬───────────┘                                             │
│                       ▼                                                          │
│  ┌──────────────────────────────────────┐                                       │
│  │ Step 3: Respond within deadline      │                                       │
│  │                                      │                                       │
│  │ POPIA: 30 days                       │                                       │
│  │ GDPR: 30 days (extendable to 90)     │                                       │
│  │                                      │                                       │
│  │ Send response with:                  │                                       │
│  │ • Data export (if access)            │                                       │
│  │ • Confirmation (if erasure)          │                                       │
│  │ • Reason (if refused)                │                                       │
│  └──────────────────────────────────────┘                                       │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 5. Loan Request Workflow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           LOAN REQUEST WORKFLOW                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌────────────┐                                                                 │
│  │ Borrower   │                                                                 │
│  │ (Museum/   │                                                                 │
│  │ Gallery)   │                                                                 │
│  └─────┬──────┘                                                                 │
│        │                                                                         │
│        ▼                                                                         │
│  ┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐        │
│  │    REQUESTED     │────►│    REVIEWING     │────►│    APPROVED      │        │
│  │                  │     │                  │     │                  │        │
│  │ • Create request │     │ • Check avail.   │     │ • Insurance OK   │        │
│  │ • Add items      │     │ • Condition OK?  │     │ • Agreement sent │        │
│  │ • Set dates      │     │ • Security OK?   │     │ • Schedule pickup│        │
│  └──────────────────┘     └────────┬─────────┘     └────────┬─────────┘        │
│                                    │                         │                  │
│                                    │ Rejected                │                  │
│                                    ▼                         ▼                  │
│                           ┌──────────────────┐     ┌──────────────────┐        │
│                           │    DECLINED      │     │    DISPATCHED    │        │
│                           │                  │     │                  │        │
│                           │ • Notify borrower│     │ • Condition check│        │
│                           │ • Close request  │     │ • Pack items     │        │
│                           └──────────────────┘     │ • Ship/deliver   │        │
│                                                    │ • Update location│        │
│                                                    └────────┬─────────┘        │
│                                                             │                   │
│                                                             ▼                   │
│  ┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐        │
│  │     CLOSED       │◄────│    RETURNED      │◄────│     ON LOAN      │        │
│  │                  │     │                  │     │                  │        │
│  │ • Final report   │     │ • Condition check│     │ • At borrower    │        │
│  │ • Archive docs   │     │ • Inspect damage │     │ • Track status   │        │
│  │ • Update records │     │ • Back to storage│     │ • Insurance valid│        │
│  └──────────────────┘     └──────────────────┘     └──────────────────┘        │
│                                                                                  │
│  ═══════════════════════════════════════════════════════════════════════════   │
│                                                                                  │
│  At Each Stage, the System:                                                     │
│  ├── Logs all actions to audit trail                                           │
│  ├── Sends notifications to stakeholders                                        │
│  ├── Updates loan status in database                                            │
│  └── Triggers condition assessment prompts                                      │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 6. Condition Assessment Workflow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                       CONDITION ASSESSMENT WORKFLOW                              │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Triggers:                                                                       │
│  ├── Scheduled review                                                           │
│  ├── Loan dispatch/return                                                       │
│  ├── Damage reported                                                            │
│  └── New acquisition                                                            │
│                                                                                  │
│        ┌─────────────────────────────────────────────────────────────┐          │
│        │                                                             │          │
│        ▼                                                             │          │
│  ┌──────────────────────────────────────────────────────────────────┐│          │
│  │ Step 1: Select Object                                            ││          │
│  │                                                                  ││          │
│  │ Browse → Select Record → Actions → New Condition Assessment      ││          │
│  └──────────────────────────────────────────────────────────────────┘│          │
│        │                                                             │          │
│        ▼                                                             │          │
│  ┌──────────────────────────────────────────────────────────────────┐│          │
│  │ Step 2: Complete Assessment Form                                 ││          │
│  │                                                                  ││          │
│  │ ┌────────────────────────────────────────────────────────────┐  ││          │
│  │ │ Overall Condition:  ○ Excellent ○ Good ● Fair ○ Poor       │  ││          │
│  │ │                                                             │  ││          │
│  │ │ Stability:          ○ Stable ● Unstable ○ Deteriorating    │  ││          │
│  │ │                                                             │  ││          │
│  │ │ Display Suitable:   ● Yes ○ With restrictions ○ No         │  ││          │
│  │ │                                                             │  ││          │
│  │ │ Loan Suitable:      ○ Yes ● With conditions ○ No           │  ││          │
│  │ │                                                             │  ││          │
│  │ │ Priority:           ○ Low ● Medium ○ High ○ Urgent         │  ││          │
│  │ └────────────────────────────────────────────────────────────┘  ││          │
│  └──────────────────────────────────────────────────────────────────┘│          │
│        │                                                             │          │
│        ▼                                                             │          │
│  ┌──────────────────────────────────────────────────────────────────┐│          │
│  │ Step 3: Document Damage/Issues                                   ││          │
│  │                                                                  ││          │
│  │ For each component:                                              ││          │
│  │ ┌──────────────────────────────────────────────────────────┐    ││          │
│  │ │ Component: Frame                                          │    ││          │
│  │ │ Condition: Fair                                           │    ││          │
│  │ │ Damage Type: Cracking                                     │    ││          │
│  │ │ Severity: Moderate                                        │    ││          │
│  │ │ Location: Lower right corner                              │    ││          │
│  │ │ Photo: [Upload]                                           │    ││          │
│  │ └──────────────────────────────────────────────────────────┘    ││          │
│  └──────────────────────────────────────────────────────────────────┘│          │
│        │                                                             │          │
│        ▼                                                             │          │
│  ┌──────────────────────────────────────────────────────────────────┐│          │
│  │ Step 4: Propose Treatment (if needed)                            ││          │
│  │                                                                  ││          │
│  │ ┌──────────────────────────────────────────────────────────┐    ││          │
│  │ │ Treatment Type: Conservation repair                      │    ││          │
│  │ │ Description: Stabilize cracking, consolidate paint       │    ││          │
│  │ │ Estimated Cost: R 15,000                                 │    ││          │
│  │ │ Priority: Medium                                          │    ││          │
│  │ │ Recommended By: [Conservator Name]                        │    ││          │
│  │ └──────────────────────────────────────────────────────────┘    ││          │
│  └──────────────────────────────────────────────────────────────────┘│          │
│        │                                                             │          │
│        ▼                                                             │          │
│  ┌──────────────────────────────────────────────────────────────────┐│          │
│  │ Step 5: Set Next Review Date                                     ││          │
│  │                                                                  ││          │
│  │ Based on condition:                                              ││          │
│  │ • Excellent/Good: 2-5 years                                      ││          │
│  │ • Fair: 1 year                                                   ││          │
│  │ • Poor: 6 months                                                 ││          │
│  │ • Critical: Immediate action                                     ││          │
│  └──────────────────────────────────────────────────────────────────┘│          │
│        │                                                             │          │
│        ▼                                                             │          │
│     ┌──────┐                                                         │          │
│     │ Save │                                                         │          │
│     └──────┘                                                         │          │
│                                                                      │          │
│  System automatically:                                               │          │
│  ├── Adds assessment to object's condition history                  │          │
│  ├── Updates loan suitability flags                                 │          │
│  ├── Schedules next review reminder                                 │          │
│  └── Logs to audit trail                                            │          │
│                                                                      │          │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 7. Security Classification Workflow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                      SECURITY CLASSIFICATION WORKFLOW                            │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Classification Levels (NARSSA-aligned):                                        │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Level │ Code │ Color   │ Access                                         │   │
│  ├───────┼──────┼─────────┼────────────────────────────────────────────────┤   │
│  │   0   │  U   │ Green   │ Unclassified - Public access                   │   │
│  │   1   │  R   │ Blue    │ Restricted - Staff only                        │   │
│  │   2   │  C   │ Yellow  │ Confidential - Need-to-know                    │   │
│  │   3   │  S   │ Orange  │ Secret - Cleared personnel                     │   │
│  │   4   │  TS  │ Red     │ Top Secret - Special clearance                 │   │
│  └───────┴──────┴─────────┴────────────────────────────────────────────────┘   │
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                    CLASSIFYING A RECORD                                  │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│        ┌────────────────┐                                                       │
│        │ Create/Edit    │                                                       │
│        │ Record         │                                                       │
│        └───────┬────────┘                                                       │
│                │                                                                 │
│                ▼                                                                 │
│  ┌──────────────────────────────────────────────────────────────────┐          │
│  │ Security Classification Panel (sidebar)                          │          │
│  │                                                                  │          │
│  │ ┌──────────────────────────────────────────────────────────┐    │          │
│  │ │ Classification: [Dropdown: U/R/C/S/TS]                   │    │          │
│  │ │                                                          │    │          │
│  │ │ Reason for classification:                               │    │          │
│  │ │ ┌────────────────────────────────────────────────────┐  │    │          │
│  │ │ │ Contains personal medical records                  │  │    │          │
│  │ │ └────────────────────────────────────────────────────┘  │    │          │
│  │ │                                                          │    │          │
│  │ │ Review Date: [Date picker]                               │    │          │
│  │ │                                                          │    │          │
│  │ │ Handling Instructions:                                   │    │          │
│  │ │ ┌────────────────────────────────────────────────────┐  │    │          │
│  │ │ │ Do not copy. Reading room access only.             │  │    │          │
│  │ │ └────────────────────────────────────────────────────┘  │    │          │
│  │ └──────────────────────────────────────────────────────────┘    │          │
│  └──────────────────────────────────────────────────────────────────┘          │
│                │                                                                 │
│                ▼                                                                 │
│  ┌──────────────────────────────────────────────────────────────────┐          │
│  │ ACCESS CONTROL (automatic)                                        │          │
│  │                                                                  │          │
│  │   User Clearance Level    Can Access                             │          │
│  │   ──────────────────────  ──────────────────────────────────     │          │
│  │   0 (Public)              U only                                 │          │
│  │   1 (Staff)               U, R                                   │          │
│  │   2 (Researcher+)         U, R, C                                │          │
│  │   3 (Manager)             U, R, C, S                             │          │
│  │   4 (Director)            U, R, C, S, TS                         │          │
│  │                                                                  │          │
│  │   Record classified as C will:                                   │          │
│  │   • Be hidden from public browse                                 │          │
│  │   • Require login to view                                        │          │
│  │   • Only show to users with clearance ≥ 2                       │          │
│  │   • Log all access attempts                                      │          │
│  └──────────────────────────────────────────────────────────────────┘          │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

*Part of the AtoM AHG Framework - v2.1.17*
