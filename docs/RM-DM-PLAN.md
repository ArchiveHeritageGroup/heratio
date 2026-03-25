# Heratio RM/DM — Functional Design

## Core Principle

**Dual classification, single governance.**

Do not make the business work according to the file plan. Make the system translate business activity into compliant file plan governance.

## Design Rule

The **file plan classification is always the authoritative classification** for:
- retention
- disposal
- legal hold
- transfer
- audit
- office of record
- compliance reporting

The **business structure is an operational overlay** for navigation, filing convenience, work context, and retrieval. It does not independently govern disposition unless it has been mapped through approved RM controls.

---

## Architecture: Three Layers

![Heratio architecture](rm_dm_assets/architecture.png)

### Layer 1 — Business Interaction
What business users see:
- departments
- projects
- processes
- programmes
- cases
- workspaces
- guided filing
- templates
- operational dashboards

### Layer 2 — RM Control
What governs the record:
- file plan
- record category / folder
- retention schedule
- disposal class
- legal hold
- vital record status
- security classification
- office of record
- copy status
- disposition audit trail

### Layer 3 — Archival / GLAM / Preservation
What preserves and contextualises records of continuing value:
- transfer workflows
- OAIS packaging
- ISAD(G)
- ISAAR-CPF
- RiC-O
- IIIF
- long-term preservation region

---

## Governance Rules

These are non-negotiable.

1. Every declared record must have **one validated primary file plan node**.
2. Every authoritative record copy must have **one office of record**.
3. Business structures are overlays and **cannot replace the file plan**.
4. Legal hold overrides disposal, destruction, and transfer execution until released.
5. Reference, duplicate, transitory, and working copies do not carry disposal authority.
6. A transfer to archives must preserve audit history, classification history, fixity, and chain of custody.
7. A record may appear in multiple business views, but it must remain **one governed record object**.
8. When a record is transferred to archives, the archival object becomes the preservation master; the business side may keep an access stub or reference link.

---

## The Real Implementation Problem

The practical problem is not creating a file plan. It is getting users to file naturally while ensuring the record lands in the correct RM structure.

That means Heratio should not force users to browse the NARSSA tree at capture time. Instead, it should use a **mapping layer** between:
- the business-facing DM structure
- the authoritative RM file plan structure

This is the same design logic as your SharePoint approach with a term store and folder mapping, but made more explicit and governable inside Heratio.

---

## DM to RM Mapping Model

![DM to RM mapping](rm_dm_assets/dm_rm_mapping.png)

### Simplified principle

In Heratio, the user should file into a **business context** such as:
- project
- department
- process
- case
- service line
- team workspace

The system should then resolve that context through a **classification profile** to:
- the correct file plan node
- the correct RM folder / record category
- the default retention schedule
- the default disposal action
- the default security classification
- the office-of-record rule
- the declaration behaviour

This is effectively the Heratio equivalent of a SharePoint term-store mapping, but stronger because it can also carry RM governance and archival transfer behaviour.

---

## Recommended Simplification: Classification Profiles

The easiest model is not to map every individual DM folder directly to every file plan node. That becomes brittle.

Instead, introduce a middle layer called **Classification Profiles**.

### What a classification profile does
A classification profile binds together:
- one or more business nodes
- an optional document type or record type
- the target file plan node
- the target RM record category / folder
- the retention schedule
- the disposal class
- the security default
- whether auto-declaration is allowed
- whether RM validation is required
- whether archival transfer is possible on disposition
- the target archival series / fonds / preservation route

### Why this is simpler
This gives you one reusable configuration object that can be attached to:
- a department workspace
- a case type
- a project template
- an upload form
- a record type
- a DM folder
- a business taxonomy node

So instead of saying:
“this folder maps to that RM folder, and this other folder maps to that RM folder...”

you say:
“this business area uses **Classification Profile X**.”

That profile then drives the correct RM behaviour.

---

## SharePoint-Style Auto-Declaration in Heratio

The SharePoint pattern you described can translate almost directly.

### SharePoint pattern
- User uploads into a business folder.
- User picks a file plan number from a term store.
- On declaration, the item is auto-filed into the correct RM folder.

### Heratio pattern
- User uploads into a business workspace, case, project, or DM folder.
- The system detects the attached business context and optional filing code.
- A classification profile resolves that context to the correct RM file plan node and record category.
- On declaration, the record is automatically governed in the right RM location.
- The business view remains intact, but the RM control is authoritative underneath.

### Better than the SharePoint pattern
Heratio can improve on that model by allowing:
- automatic classification from folder + document type
- wizard-driven classification where users never need to know the file plan number
- AI-assisted suggestions where mappings are unclear
- RM validation queues for exceptions
- direct transfer workflows from RM disposition into archival / GLAM preservation

---

## Record Flow

### Business user view
The business user sees:
- Projects → Solar Upgrade
- Procurement
- Supplier ABC
- Contract

### System action
The system resolves:
- business node = Solar Upgrade / Procurement
- document type = Contract
- classification profile = `CAPITAL_WORKS_CONTRACT`
- file plan node = `3/3 Contracts` or approved equivalent
- RM folder = `Contracts / Capital Works`
- retention = `contract expiry + 7 years`
- disposition = `review for destruction or transfer`
- office of record = `Supply Chain`
- declaration mode = `auto-declare`

### RM outcome
The Records Manager sees:
- authoritative file plan placement
- retention and trigger
- legal hold status
- copy status
- office of record
- classification confidence
- disposition path
- future transfer eligibility

---

## Record Flow Diagram

```text
User files in business context
        ↓
System identifies business node / case / project / document type
        ↓
Classification profile resolves correct RM file plan + record category
        ↓
Record declared automatically or with validation
        ↓
RM controls attach: retention, disposal, legal hold, office of record
        ↓
Disposition reached
        ↓
Destroy / retain / review / transfer
        ↓
If transfer: move through archival workflow to GLAM/DAM preservation region
```

---

## Data Model

### Core entities

```text
BusinessClassificationNode
  ↓
ClassificationProfile
  ↓
FilePlanNode
  ↓
RecordCategory
  ↓
RMRecord
  ↓
DispositionAction / TransferPackage / ArchivalObject
```

### Entity relationship overview

```text
BusinessClassificationNode
  - department / project / process / case / site / programme

ClassificationProfile
  - reusable mapping object
  - binds business context to RM outcome

FilePlanNode
  - authoritative NARSSA-aligned classification node

RecordCategory
  - RM folder / container under a file plan node

RMRecord
  - declaration metadata attached to information object

ArchivalTransferRoute
  - what happens when disposition = transfer

ArchivalObject
  - preservation-side master after transfer
```

---

## New Core Object: Classification Profile

This should be added to the design explicitly.

### Suggested purpose
A `ClassificationProfile` is the reusable mapping layer between DM and RM.

### Suggested fields
- `id`
- `code`
- `name`
- `description`
- `business_node_id` or `business_scope_json`
- `document_type`
- `record_type`
- `file_plan_node_id`
- `record_category_id`
- `retention_schedule_id`
- `disposal_class_id`
- `security_default`
- `copy_status_default`
- `office_of_record_unit_id`
- `declaration_mode` (`auto`, `suggest`, `require_validation`)
- `allow_auto_declare`
- `allow_archive_transfer`
- `archive_transfer_route_id`
- `requires_rm_validation`
- `priority`
- `start_date`
- `end_date`
- `active`

### Effect
This lets one profile drive many uploads across many business areas without hard-coding hundreds of folder-to-folder mappings.

---

## SA NARSSA File Plan (Built-In Seeder)

### Fixed Main Series (1-11)

These should be seeded as a **controlled baseline**, not as the full finished file plan for every institution.

| Series | English | Afrikaans | Sub-divisions |
|--------|---------|-----------|---------------|
| 1 | Administration | Administrasie | Policy, Circulars, Office admin, Forms, Reports |
| 2 | Committees & Meetings | Komitees en Vergaderings | Standing, Ad hoc, Minutes, External |
| 3 | Legal Matters | Regsaangeleenthede | Legislation, Opinions, Contracts, Litigation, IP |
| 4 | Personnel | Personeel | Recruitment, Appointments, Conditions, Leave, Training, Performance, Disciplinary, Termination, Labour |
| 5 | Finance | Finansies | Budget, Revenue, Procurement, Payments, Assets, Audit, Banking |
| 6 | Equipment & Stores | Toerusting en Voorrade | Acquisition, Maintenance, Disposal, Inventory |
| 7 | Housing | Behuising | Official, Subsidies, Maintenance |
| 8 | Transport | Vervoer | Vehicles, Travel, Subsidised |
| 9 | Communication & Liaison | Kommunikasie en Skakeling | Internal, External, Media, Public |
| 10 | Social & Cultural | Maatskaplike en Kulturele | Wellness, Social, Cultural |
| 11 | Land & Buildings | Grond en Geboue | Acquisition, Maintenance, Leasing, Security |
| 12+ | Business-specific | Per organisation | Core function series |

### Important control note
Series 1–11 should be seeded as a controlled baseline. Detailed subdivisions and series 12+ remain organisation-specific and should be subject to internal governance and, where applicable, approval by the relevant records authority.

---

## Interfaces by Role

### Business User
- department / project / case dashboards
- save-to-workspace buttons
- guided filing wizard
- no need to browse the full file plan tree
- simplified declaration badge
- minimal RM jargon

### Records Manager
- authoritative file plan tree
- classification profile management
- business-to-RM mapping editor
- unfiled and exception queues
- disposal eligibility and legal hold lists
- transfer-ready records
- compliance reports by file plan and office of record

### Registry / Power User
- split view: business context left, RM destination right
- bulk declaration
- bulk remapping
- override with reason
- validation queue handling

---

## Filing Wizard (Business User UI)

Instead of “choose from 800 file plan nodes”, ask:

```text
Step 1: What are you working on?
  [ ] Day-to-day admin
  [ ] A project
  [ ] A case / matter
  [ ] Staff matter
  [ ] Financial matter
  [ ] Contract / agreement

Step 2: Which department, case or project?
  [dropdown: from business taxonomy]

Step 3: What kind of document is this?
  [ ] Correspondence
  [ ] Report
  [ ] Contract / agreement
  [ ] Minutes / agenda
  [ ] Policy / procedure
  [ ] Financial record
  [ ] Personnel record
  [ ] Plan / drawing
  [ ] Photograph / media

Step 4: System resolves the classification profile and shows:
  Suggested file plan
  Suggested RM folder
  Retention rule
  Security rule
  Office of record
  Declaration mode
```

This keeps the capture experience business-friendly while still landing the record in the correct governed location.

---

## Copy Status Model

Every information object may exist in different operational contexts, but only one governed record copy should carry disposal authority.

| Status | Meaning | RM implications |
|--------|---------|-----------------|
| Record copy | The authoritative governed version | Full retention, hold, disposal, and transfer controls |
| Reference copy | For access or convenience | No independent disposal authority |
| Working copy | Draft / in progress | Not yet declared |
| Transitory copy | Temporary use | Destroy when no longer needed |
| Duplicate | Exact extra copy | Destroy under duplication rules |

---

## Office of Record

Each authoritative record copy must have one office of record:
- the business unit responsible for the governed copy
- the unit responsible for disposal actions and transfer preparation
- the unit that owns the classification profile unless RM overrides it centrally

This becomes especially important where HR, Legal, Finance, and Projects all hold copies of related records.

---

## Lifecycle, Disposition, and Archive Transfer

![Archive transfer workflow](rm_dm_assets/archive_transfer.png)

### Key principle
Disposition should not stop at “destroy or keep”. Where records have enduring value, disposition must be able to trigger a **controlled transfer into the archival / GLAM preservation region**.

### Recommended disposition outcomes
- destroy
- retain in place
- review again
- transfer to archives / GLAM region

### Recommended transfer workflow
1. Record reaches cutoff or event trigger.
2. Retention period expires or review point is reached.
3. RM review confirms archival value or scheduled transfer requirement.
4. Legal hold and freeze checks run.
5. Transfer approval is completed.
6. Heratio generates a transfer package with metadata, fixity, and chain of custody.
7. The package is ingested into the archival / preservation region.
8. The record becomes an archival object with ISAD(G) / RiC-O / preservation context.
9. The business / RM side keeps either:
   - a stub
   - a reference link
   - or a controlled metadata shell

This gives you an end-to-end lifecycle from active record to preserved archival object inside one platform.

---

## New Object: Archival Transfer Route

To support automatic movement from RM disposition into GLAM/DAM, add a transfer routing object.

### Suggested fields
- `id`
- `code`
- `name`
- `description`
- `source_file_plan_node_id`
- `source_record_category_id`
- `disposal_class_id`
- `target_repository_id`
- `target_fonds_id`
- `target_series_id`
- `target_preservation_workflow`
- `packaging_type` (`BagIt`, `OAIS-SIP`, custom)
- `metadata_mapping_profile_id`
- `requires_appraisal_confirmation`
- `requires_archivist_approval`
- `active`

### Why this matters
Not every transferred record goes to the same archival destination. Some should land in:
- a departmental archives region
- a central archives region
- a specific fonds / series
- a long-term digital preservation store

The transfer route allows the RM disposition outcome to land in the correct GLAM/DAM structure automatically and defensibly.

---

## Recommended Database Additions

Add these tables or equivalents:

```sql
CREATE TABLE rm_classification_profile (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    file_plan_node_id INT NOT NULL,
    record_category_id INT,
    retention_schedule_id INT,
    disposal_class_id INT,
    office_of_record_unit_id INT,
    security_default VARCHAR(50),
    copy_status_default VARCHAR(50),
    declaration_mode ENUM('auto','suggest','require_validation') DEFAULT 'suggest',
    allow_auto_declare BOOLEAN DEFAULT TRUE,
    allow_archive_transfer BOOLEAN DEFAULT FALSE,
    archive_transfer_route_id INT,
    requires_rm_validation BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    active BOOLEAN DEFAULT TRUE,
    created_at DATETIME,
    updated_at DATETIME
);

CREATE TABLE rm_business_profile_map (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_node_id INT NOT NULL,
    classification_profile_id INT NOT NULL,
    document_type VARCHAR(100),
    record_type VARCHAR(100),
    is_default BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    created_at DATETIME
);

CREATE TABLE rm_archive_transfer_route (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    source_file_plan_node_id INT,
    source_record_category_id INT,
    disposal_class_id INT,
    target_repository_id INT,
    target_fonds_id INT,
    target_series_id INT,
    target_preservation_workflow VARCHAR(100),
    packaging_type VARCHAR(50),
    metadata_mapping_profile_id INT,
    requires_appraisal_confirmation BOOLEAN DEFAULT TRUE,
    requires_archivist_approval BOOLEAN DEFAULT TRUE,
    active BOOLEAN DEFAULT TRUE,
    created_at DATETIME,
    updated_at DATETIME
);

CREATE TABLE rm_archive_transfer (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rm_record_id INT NOT NULL,
    archive_transfer_route_id INT NOT NULL,
    package_path VARCHAR(500),
    transfer_status ENUM('pending','approved','packaged','transferred','ingested','failed') DEFAULT 'pending',
    transfer_reference VARCHAR(255),
    approved_by INT,
    transferred_at DATETIME,
    ingested_at DATETIME,
    created_at DATETIME
);
```

---

## Package Structure Additions

Add to the package:

```text
Controllers/
  ClassificationProfileController.php
  ArchiveTransferRouteController.php
  ArchiveTransferController.php

Services/
  ClassificationProfileResolver.php
  ArchiveTransferService.php
  MetadataMappingService.php
```

---

## Revised Execution Phases

### Phase 1 — Foundation and governance
- package skeleton and migrations
- NARSSA baseline seeder
- file plan tree
- business taxonomy tree
- classification profiles
- business-to-profile mapping
- declaration service
- office of record
- copy status model
- role-based views

### Phase 2 — Filing and auto-classification
- filing wizard
- profile resolver
- rule engine
- AI-assisted suggestion
- validation queue
- unfiled / exception handling
- record categories / RM folders

### Phase 3 — Retention, disposition, and transfer
- retention schedules
- disposal classes
- cutoff handling
- event-based triggers
- disposition workflow
- archive transfer routes
- archive transfer packaging
- stub / shell back-linking into DM

### Phase 4 — Legal hold, vital records, integrity
- legal hold
- freeze / override prevention
- vital records registry
- fixity and hash chaining
- RM search
- integrity monitoring

### Phase 5 — Compliance and reporting
- ISO 15489 reporting
- MoReq2010 reporting
- DoD 5015.2 reporting
- NARSSA reporting
- office-of-record audit
- classification confidence analytics
- transfer and disposition audit packs

---

## Practical Recommendation for Your Specific Need

For Heratio, I would implement this exact simplification:

### 1. Import or build the file plan
Create the authoritative NARSSA-aligned file plan and RM record categories.

### 2. Build business structures separately
Departments, projects, cases, programmes, sites, and workspaces remain on the DM side.

### 3. Create reusable classification profiles
Each profile maps business activity to:
- RM file plan node
- RM folder
- retention
- disposal
- transfer route

### 4. Attach profiles to business contexts
A project template, case type, business folder, or workspace uses one or more profiles.

### 5. Declare automatically
When a user uploads or declares from that business context, Heratio assigns the correct RM destination automatically.

### 6. Route disposition automatically
When a record reaches archival transfer as its disposition outcome, Heratio sends it through a controlled archival-transfer workflow into the GLAM/DAM region.

That gives you the same simplicity as the SharePoint term-store approach, but with stronger RM governance and a built-in path into preservation.

---

## SA Disposition Process (NARSSA / Provincial Archives)

South African government bodies may not destroy or transfer records without authorisation from the National Archivist (NARSSA) or the relevant Provincial Archivist. The legislated process is:

### Standard Disposal Authorization

```text
1. Department compiles a disposal list
   - Lists all records due for disposition (retention expired, cutoff reached)
   - Grouped by file plan series and record category
   - Includes volume, date range, and proposed action

2. Department submits disposal list to NARSSA or Provincial Archives
   - National government bodies → NARSSA (National Archives and Records Service of SA)
   - Provincial government bodies → relevant Provincial Archives

3. NARSSA / Provincial Archives reviews and appraises
   - Archivists assess each item or group for enduring value
   - Items with archival value are marked for transfer
   - Items without archival value are marked for destruction

4. NARSSA issues a Disposal Authority Number
   - The official written authorisation to proceed
   - Specifies exactly which records may be destroyed
   - Specifies which records must be transferred to the archives
   - No destruction may occur without this number

5. Department executes the disposal
   - Transfers items marked for archival preservation to NARSSA / Provincial Archives
   - Destroys the remainder under the issued authority number
   - Records the disposal authority number against each action in the audit trail
```

### Standing Authority Numbers

For routine, recurring record types (e.g., duplicates, transitory correspondence, routine financial records after audit clearance), NARSSA may issue **standing disposal authorities**. These are pre-approved, ongoing authorisations that allow departments to destroy specified record categories without submitting a disposal list each time, provided they meet the standing conditions (e.g., retention period expired, audit cleared).

Standing authorities are linked to specific file plan nodes and record categories in Heratio via the `rm_classification_profile.standing_authority_number` field.

### A20 Form (Appraisal Decision)

The **A20** is the NARSSA appraisal decision form. When a disposal list is submitted:

1. NARSSA appraises each record group on the list.
2. NARSSA completes the A20, marking each group as either:
   - **Transfer** — records of enduring value that the department must transfer to the archives
   - **Destroy** — records with no enduring value that the department may destroy
3. The department receives the A20 with the disposal authority number.
4. The department **transfers** all items marked for preservation.
5. The department **destroys** the remainder.
6. Both actions are recorded against the disposal authority number.

### Heratio Implementation

In Heratio, this process maps to:

| Step | Heratio Feature |
|------|-----------------|
| Compile disposal list | RM dashboard → generate disposal list from expired retention records |
| Submit to NARSSA | Export disposal list as PDF/CSV with file plan references; record submission date |
| Receive authority | Capture disposal authority number + A20 decisions against the disposal batch |
| Execute transfer | Archive transfer route triggers OAIS packaging for transfer-marked records |
| Execute destruction | Destruction workflow with authority number, certificate of destruction, audit trail |
| Standing authorities | Linked to classification profiles; auto-disposition without per-batch submission |

### Additional SQL field

Add to `rm_classification_profile`:
```sql
standing_authority_number VARCHAR(100) COMMENT 'NARSSA standing disposal authority number, if applicable'
```

Add new table for disposal batches:
```sql
CREATE TABLE rm_disposal_batch (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_reference VARCHAR(100) NOT NULL,
    description TEXT,
    submitted_to VARCHAR(100) COMMENT 'NARSSA or Provincial Archives name',
    submitted_at DATE,
    authority_number VARCHAR(100) COMMENT 'Disposal authority number issued by NARSSA',
    authority_received_at DATE,
    a20_document_path VARCHAR(500),
    status VARCHAR(50) DEFAULT 'draft' COMMENT 'draft, submitted, authorised, executing, completed',
    completed_at DATE,
    created_by INT,
    created_at DATETIME,
    updated_at DATETIME
);

CREATE TABLE rm_disposal_batch_item (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disposal_batch_id INT NOT NULL,
    rm_record_id INT,
    record_category_id INT,
    file_plan_node_id INT,
    volume_description TEXT,
    date_range_start DATE,
    date_range_end DATE,
    proposed_action VARCHAR(50) COMMENT 'destroy, transfer',
    narssa_decision VARCHAR(50) COMMENT 'destroy, transfer — set after A20 received',
    executed_at DATETIME,
    execution_certificate_path VARCHAR(500),
    created_at DATETIME
);
```

---

## Final Position

The best Heratio pattern is:

**Business-facing filing, profile-based mapping, authoritative RM governance, and controlled archival transfer.**

That will let business users work naturally while giving the Records Manager exactly what they need: the official file plan, disposal control, and a defensible path from active record to preserved archival asset.
