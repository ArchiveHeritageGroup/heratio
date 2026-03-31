# Heratio Research Portal — Training Manual

**Plugin:** ahgResearchPlugin v3.1.0
**Platform:** Heratio (AtoM 2.10 + AHG Framework v2.8.2)
**Author:** The Archive and Heritage Group (Pty) Ltd
**Last Updated:** February 2026

---

## Table of Contents

1. [Overview](#1-overview)
2. [Getting Started](#2-getting-started)
3. [Dashboard](#3-dashboard)
4. [Researcher Registration & Profile](#4-researcher-registration--profile)
5. [Reading Room & Bookings](#5-reading-room--bookings)
6. [Collections](#6-collections)
7. [Saved Searches & Discovery](#7-saved-searches--discovery)
8. [Annotations & Notes](#8-annotations--notes)
9. [Annotation Studio (W3C Web Annotations)](#9-annotation-studio-w3c-web-annotations)
10. [Bibliographies & Citations](#10-bibliographies--citations)
11. [Research Projects](#11-research-projects)
12. [Collaboration & Workspaces](#12-collaboration--workspaces)
13. [Research Journal](#13-research-journal)
14. [Research Reports](#14-research-reports)
15. [Hypotheses](#15-hypotheses)
16. [Source Assessment & Trust Scoring](#16-source-assessment--trust-scoring)
17. [Knowledge Graph & Assertions](#17-knowledge-graph--assertions)
18. [Snapshots & Reproducibility](#18-snapshots--reproducibility)
19. [AI Extraction & Validation Queue](#19-ai-extraction--validation-queue)
20. [Entity Resolution](#20-entity-resolution)
21. [Visualization Tools](#21-visualization-tools)
22. [Reproduction Requests](#22-reproduction-requests)
23. [Request Lifecycle & SLA](#23-request-lifecycle--sla)
24. [Material Retrieval & Custody](#24-material-retrieval--custody)
25. [Accessibility](#25-accessibility)
26. [Rights & Access Policies (ODRL)](#26-rights--access-policies-odrl)
27. [RO-Crate & DOI Minting](#27-ro-crate--doi-minting)
28. [Notifications](#28-notifications)
29. [REST API & API Keys](#29-rest-api--api-keys)
30. [ORCID Integration](#30-orcid-integration)
31. [Institutional Sharing](#31-institutional-sharing)
32. [Audit Trail](#32-audit-trail)
33. [Administration](#33-administration)
34. [Database Reference](#34-database-reference)
35. [Troubleshooting](#35-troubleshooting)

---

## 1. Overview

The Heratio Research Portal is an enterprise-grade research support platform built into the AtoM archival management system. It provides a complete environment for researchers to discover, analyze, annotate, and publish findings from archival collections.

### Key Capabilities

| Area | Features |
|------|----------|
| **Discovery** | Saved searches with result diffing, citation IDs, full-text search |
| **Reading Room** | Booking, check-in/out, seat assignment, equipment, material retrieval |
| **Collections** | Personal research collections, finding aid export, snapshots |
| **Annotations** | W3C Web Annotations, IIIF import/export, multi-target selectors |
| **Knowledge** | Assertions (SPO triples), hypotheses, source assessment, trust scoring |
| **AI Extraction** | OCR, NER, summarization orchestration with validation queue |
| **Collaboration** | Projects, workspaces, peer review, comments, institutional sharing |
| **Publishing** | Bibliographies (RIS/BibTeX/Zotero), citations (6 styles), reports (PDF/DOCX) |
| **Reproducibility** | Immutable snapshots with hash verification, RO-Crate, DOI minting |
| **Visualization** | Timeline builder, geographic map, network graph, knowledge graph |
| **Compliance** | ODRL rights policies, audit trail, ORCID integration |
| **API** | Full REST API with API key authentication |

### User Roles

| Role | Access Level |
|------|-------------|
| **Public Visitor** | Can register, view public collections |
| **Pending Researcher** | Registered but awaiting admin approval |
| **Approved Researcher** | Full access to research features |
| **Project Owner** | Can create/manage projects, invite collaborators |
| **Project Collaborator** | Access to shared project resources |
| **Admin** | Researcher approval, reading room management, statistics |

---

## 2. Getting Started

### Accessing the Portal

Navigate to **`/research`** on your Heratio instance (e.g., `https://psis.theahg.co.za/research`).

### First-Time Setup

1. **Register** — Click "Register as Researcher" or navigate to `/research/register`
2. **Complete Profile** — Fill in personal details, institution, research interests
3. **Wait for Approval** — An admin must approve your registration
4. **Receive Confirmation** — Once approved, you can access all research features
5. **Connect ORCID** (optional) — Link your ORCID iD for scholarly identification

### Navigation Structure

```
Research Portal (/)
├── Dashboard (/research)
├── Profile (/research/profile)
│   └── API Keys (/research/profile/api-keys)
├── Bookings (/research/bookings)
│   └── Book (/research/book)
├── Collections (/research/collections)
│   └── View Collection (/research/collection/:id)
├── Saved Searches (/research/saved-searches)
├── Annotations (/research/annotations)
│   └── Annotation Studio (/research/annotation-studio/:object_id)
├── Bibliographies (/research/bibliographies)
│   └── View Bibliography (/research/bibliography/:id)
├── Projects (/research/projects)
│   └── View Project (/research/project/:id)
├── Workspaces (/research/workspaces)
│   └── View Workspace (/research/workspaces/:id)
├── Journal (/research/journal)
├── Reports (/research/reports)
├── Reproductions (/research/reproductions)
├── Notifications (/research/notifications)
└── Invitations (/research/invitations)
```

---

## 3. Dashboard

**URL:** `/research`

The dashboard is your home base. It displays:

- **Today's Bookings** — Upcoming reading room sessions
- **Pending Requests** — Material requests awaiting fulfillment
- **Recent Activity** — Your latest actions (annotations, collections, searches)
- **Recent Notes** — Last 5 annotations/notes
- **Recent Journal** — Last 5 journal entries
- **Search Alerts** — Saved searches with new results
- **Pending Invitations** — Project/workspace invitations requiring action
- **Active Projects** — Projects you're contributing to
- **Unread Notifications** — Count of unread notifications

### Quick Actions from Dashboard

- Create a booking
- Start a new project
- Browse collections
- View notifications

---

## 4. Researcher Registration & Profile

### Registration

**URL:** `/research/register` (authenticated) or `/research/register-researcher` (public)

**Required Fields:**
- First name, Last name
- Email address
- ID type (National ID, Passport, Driver's License, Student Card, Other)
- ID number

**Optional Fields:**
- Title (Mr, Mrs, Ms, Dr, Prof)
- Phone
- Affiliation type (Independent, Academic, Government, Corporate, NGO)
- Institution, Department, Position
- Student ID
- Research interests (free text)
- Current project description
- ORCID iD

**Process Flow:**
```
Registration → Pending Status → Admin Review → Approved/Rejected
                                                    ↓
                                              Access Request Created
                                              (Internal clearance level)
```

On registration, an access request is automatically created requesting "Internal" clearance level. The admin can approve or reject through the researcher management interface.

### Rejection with Audit Trail

When a researcher is rejected:
1. All data is copied to `research_researcher_audit` table
2. The main record is deleted from `research_researcher`
3. The user account is deactivated (`user.active = 0`)
4. The access request is set to "denied" with the rejection reason
5. The email/user can re-register in the future

### Profile Management

**URL:** `/research/profile`

Researchers can update their profile information at any time. Changes are audit-logged.

### Researcher Types

Admins can configure researcher types that control access limits:

| Type | Max Advance Booking | Hours/Day | Materials/Booking | Remote Access | Auto-Approve |
|------|--------------------:|----------:|-----------------:|:-------------:|:------------:|
| General Public | 7 days | 2 hrs | 5 | No | No |
| Registered Researcher | 14 days | 4 hrs | 10 | No | No |
| Academic Staff | 30 days | 8 hrs | 20 | Yes | Yes |
| Postgraduate Student | 21 days | 6 hrs | 15 | No | No |
| Heritage Professional | 30 days | 8 hrs | 25 | Yes | Yes |

### Verification System

Researchers may need to verify their identity:

**Verification Types:**
- ID document verification
- Institutional affiliation verification
- Student enrollment verification
- Professional qualification verification

**Process:** Upload verification documents → Admin reviews → Verified/Rejected

Verified researchers get enhanced access. Verifications can have expiry dates (e.g., student enrollment verification expires annually).

### Credential Renewal

**URL:** `/research/renewal`

When a researcher's access expires (based on their type's `expiry_months`), they can request renewal through this page.

---

## 5. Reading Room & Bookings

### Creating a Booking

**URL:** `/research/book`

1. Select a **reading room** from the available rooms
2. Choose a **date** (limited by your researcher type's `max_booking_days_advance`)
3. Select **start time** and **end time** (limited by `max_booking_hours_per_day`)
4. Specify **purpose** of visit (optional)
5. Add **notes** (optional)
6. Submit — booking status becomes "Pending"

### Booking Lifecycle

```
Pending → Confirmed (by admin) → Checked In → Completed (Checked Out)
   ↓                                              ↓
Cancelled                                    Materials Returned
```

### Material Requests

When viewing a booking, you can add material requests:

1. Search for archival items by title, identifier, or reference number
2. Add items to the booking's material request list
3. Each item gets a status: Requested → Delivered → Returned
4. Material request notes can be added per item

**Material request limits** are enforced by your researcher type (e.g., 10 items for Registered Researchers, 25 for Heritage Professionals).

### Check-In / Check-Out

- **Check In** (`/research/booking/:id/check-in`) — Marks arrival time
- **Check Out** (`/research/booking/:id/check-out`) — Marks departure, completes booking

### Walk-In Visitors

**URL:** `/research/walk-in`

For unregistered visitors who walk into the reading room without a booking:
1. Staff records visitor details (name, ID, purpose)
2. Visitor is assigned a temporary seat
3. On departure, staff checks them out
4. Walk-in visitors can later be converted to registered researchers

### Seat Assignment

**URL:** `/research/seats`

Reading rooms can have mapped seats:
- **Seat Map** (`/research/seats/map`) — Visual layout of the room
- **Auto-Assign** — System selects the best available seat based on preferences
- **Manual Assign** — Staff selects a specific seat for a researcher

Seat properties: number, label, section, row, position, has_power, has_network, has_lamp, is_accessible, equipment type.

### Equipment Booking

**URL:** `/research/equipment`

Reading rooms may have bookable equipment:
- Microfilm readers
- Digital scanners
- Laptops
- Magnifying glasses

Book equipment alongside your reading room booking.

### Retrieval Queue

**URL:** `/research/retrieval-queue`

Staff use this to manage the physical retrieval of materials. The queue is divided into named sub-queues (New, Rush, Retrieval, Transit, Delivery, Curatorial, Return), each with a summary card showing the count of pending requests.

**Queue Summary Cards** — click any card to filter the table below to that queue.

**Per-Request Actions:**
- **Print Call Slip** — open a printable call slip for an individual request
- **Checkout** — available when status is `retrieved` or `delivered`; opens the custody checkout form
- **Return** — available when status is `in_use`; opens the custody return/check-in form
- **Custody Chain** — view the full chain-of-custody history for the linked archival object

**Batch Actions (bottom toolbar):**
1. Select one or more requests using the checkboxes
2. **Update Status** — move selected requests to a new status (Requested, Retrieved, Delivered, In Use, Returned, Unavailable) with optional notes
3. **Print Selected** — print call slips for all selected requests
4. **Batch Checkout** — open the batch checkout form for selected requests
5. **Batch Return** — open the batch return form for selected requests

**Status Lifecycle:**

```
Requested → Retrieved → Delivered → In Use → Returned
                                       ↓
                                   Unavailable
```

**Location Tracking** — the `Current Location` column updates automatically as requests move through statuses:
- Requested: original shelf location
- Retrieved: "In transit"
- Delivered / In Use: "Reading room"
- Returned: "Return shelf (pending re-shelving)"

### Call Slips

**URL:** `/research/call-slips/print`

Generate printable call slips for material retrieval. Call slips include:
- Researcher name and booking details
- Item reference number, title, and location
- Date and retrieval schedule

---

## 6. Collections

### Creating Collections

**URL:** `/research/collections`

Collections are personal groupings of archival items for research purposes.

1. Click "Create Collection"
2. Enter name and description
3. Set visibility: Private or Public
4. A unique share token is automatically generated

### Adding Items to Collections

There are several ways to add items:
- **From browse/search results** — Click "Add to Collection" button on any item
- **From the collection view** — Search and add items
- **Via AJAX** (`/research/ajax/add-to-collection`) — Used by the browse interface
- **From clipboard** — Move clipboard items to a collection

### Collection Operations

| Operation | Description |
|-----------|-------------|
| View items | See all items with titles, slugs, notes |
| Reorder items | Change sort order via drag-and-drop |
| Add notes | Per-item researcher notes |
| Remove items | Remove individual items |
| Export finding aid | Generate PDF/DOCX finding aid |
| Create snapshot | Freeze the collection state for reproducibility |
| Share | Share via public link or project |

### Finding Aid Export

**URL:** `/research/collection/:id/export/:format`

Formats: PDF, DOCX

The finding aid includes:
- Collection metadata (name, description, researcher)
- All items with their archival description fields
- Hierarchical descendants of each item
- Researcher notes per item

---

## 7. Saved Searches & Discovery

### Saving Searches

When you perform a search in the browse interface, you can save it:

1. Run a search query
2. Click "Save Search"
3. Enter a name and optional description
4. Choose whether to enable alerts

Each saved search automatically receives a **Citation ID** in the format:
`QRY-{researcherId}-{searchId}-{hash8}`

Example: `QRY-12-45-a3b7c9d1`

### Managing Saved Searches

**URL:** `/research/saved-searches`

The saved searches page shows:

| Column | Description |
|--------|-------------|
| Name | Search name and description |
| Citation ID | Stable identifier for referencing in publications |
| Query | The search query text |
| Results | Last snapshot result count (or "No snapshot") |
| Alerts | On/Off badge |
| Created | Date created |
| Actions | Run, Diff, Snapshot, Delete |

### Result Diffing

Click the **Diff** button (exchange icon) to compare current search results against the last snapshot:

- **Added** — New items matching the query since the last snapshot
- **Removed** — Items that no longer match
- **Unchanged** — Items present in both

The diff modal shows:
- Previous count, Current count, Unchanged count
- List of added items with titles
- List of removed items with titles

### Result Snapshots

Click the **Snapshot** button (camera icon) to save the current result set as a baseline:
- Stores all current result IDs in `result_snapshot_json`
- Updates `last_result_count`
- Future diffs will compare against this snapshot

### Search Alerts

When alerts are enabled on a saved search:
- The system periodically checks for new results
- New result counts appear on the dashboard
- Notification is sent when new items match your query

---

## 8. Annotations & Notes

### Legacy Annotations

**URL:** `/research/annotations`

Basic annotations can be attached to any archival item:

| Field | Description |
|-------|-------------|
| Object | The archival item being annotated |
| Type | Note, Highlight, Bookmark, Tag |
| Title | Optional title |
| Content | Annotation text (supports rich text via Quill editor) |
| Format | text or html |
| Visibility | Private, Shared, or Public |
| Tags | Comma-separated tags |

### Rich Text Editing

The annotation editor supports:
- Bold, italic, underline, strikethrough
- Headings (H1-H6)
- Ordered and unordered lists
- Blockquotes and code blocks
- Links and images
- Tables

### Searching Annotations

Full-text search across all your annotations using MySQL's MATCH...AGAINST:
- Searches title and content fields
- Boolean mode for advanced queries

### Exporting Annotations

**URL:** `/research/notes/export/:format`

Formats: PDF, DOCX, CSV

---

## 9. Annotation Studio (W3C Web Annotations)

### Overview

**URL:** `/research/annotation-studio/:object_id`

The Annotation Studio provides a standards-compliant annotation interface implementing the [W3C Web Annotation Data Model](https://www.w3.org/TR/annotation-model/).

### Creating Annotations

The right sidebar contains the "Create Annotation" panel:

1. **Motivation** — Select the purpose:
   - Commenting — General comments
   - Describing — Descriptive annotations
   - Classifying — Classification/categorization
   - Linking — Linking to related resources
   - Questioning — Questions about the content
   - Tagging — Tags/labels
   - Highlighting — Highlights of interest

2. **Body Text** — Enter the annotation content

3. **Visibility** — Private, Shared, or Public

4. Click "Create Annotation"

### Multi-Target Annotations

After creating an annotation, you can add multiple targets using different selector types:

| Selector Type | Fields | Use Case |
|---------------|--------|----------|
| **TextQuoteSelector** | Exact text, Prefix, Suffix | Select specific text passages |
| **FragmentSelector** | xywh coordinates (pixel) | Select image regions |
| **TimeSelector** | Start time, End time (seconds) | Select audio/video segments |
| **PointSelector** | X, Y coordinates | Mark specific points |
| **SvgSelector** | SVG markup | Draw complex regions |

To add a target:
1. Click "Add Target" on an existing annotation
2. Select the selector type
3. Fill in the selector-specific fields
4. Click "Save Target"

### IIIF Import/Export

**Import:** Click "Import IIIF" to upload a W3C Web Annotation RiC-O JSON-LD file
**Export:** Click "Export IIIF" to download annotations as a RiC-O JSON-LD annotation list

The export follows the IIIF Presentation API 3.0 annotation format, compatible with:
- Mirador
- Universal Viewer
- Annona
- Other IIIF-compliant viewers

### Promote to Assertion

Each annotation has a "Promote to Assertion" button that converts the annotation into a formal research assertion in the knowledge graph. This creates:
- Subject: the annotated object
- Predicate: `annotated_as`
- Type: `attributive`

---

## 10. Bibliographies & Citations

### Creating Bibliographies

**URL:** `/research/bibliographies`

1. Click "Create Bibliography"
2. Enter name and description
3. Optionally link to a project

### Adding Entries

There are multiple ways to add bibliography entries:

**Manual Entry** — Full citation metadata:
- Entry type (book, article, chapter, thesis, website, archival, report, other)
- Title, Authors, Year, Publisher
- Journal, Volume, Issue, Pages
- DOI, ISBN, URL
- Abstract, Notes

**From Archive Items** — Click "Add to Bibliography" while browsing:
- Automatically extracts title, dates, creators, repository
- Links back to the archival record

**Import:**
- BibTeX format
- RIS format

### Citation Styles

Generate citations in 6 styles:

| Style | Example |
|-------|---------|
| **Chicago** | Author. "Title." Date. Repository. URL (accessed Date). |
| **MLA** | Author. "Title." Repository, Date. URL. Accessed Date. |
| **APA** | Author. (Date). Title. Retrieved from URL |
| **Harvard** | Author (Year) 'Title', Repository. Available at: URL (Accessed: Date). |
| **UNISA Harvard** | Surname, I. Year. *Title*. Repository. [Online]. Available from: URL [Accessed Date]. |
| **Turabian** | Same as Chicago |

### Exporting Bibliographies

**URL:** `/research/bibliography/:id/export/:format`

| Format | Description | Compatible With |
|--------|-------------|----------------|
| RIS | Research Information Systems | EndNote, Zotero, Mendeley |
| BibTeX | LaTeX bibliography format | LaTeX, Overleaf, JabRef |
| Zotero RiC-O (Records in Contexts Ontology)/RDF | Zotero native format | Zotero |
| Mendeley JSON | Mendeley export format | Mendeley |
| CSL-JSON | Citation Style Language JSON | Pandoc, Citeproc |

---

## 11. Research Projects

### Creating Projects

**URL:** `/research/projects`

**Fields:**
- Title
- Description
- Project type (Individual, Group, Institutional, Grant-funded)
- Institution
- Supervisor (for academic projects)
- Funding source and Grant number
- Ethics approval reference
- Start date and Expected end date
- Status (Active, On Hold, Completed, Archived)
- Visibility (Private, Collaborators, Public)

### Project Resources

Attach resources to projects:
- Link archival collections
- Link saved searches
- Add external document references
- Add URL resources
- Move clipboard items to project

### Project Milestones

Track project progress with milestones:
- Title and description
- Type: deliverable, ethics, data_collection, analysis, writing, review
- Status: pending, in_progress, completed, overdue
- Due date
- Completion date

### Project Activity Log

Every action within a project is logged:
- Collection additions
- Annotation creation
- Assertion changes
- Collaborator joins
- Resource additions

**URL:** `/research/project/:id/activity`

### Clipboard Integration

The clipboard feature lets you:
1. Add items from browse to your clipboard
2. Move clipboard items to a specific project
3. Pin important clipboard items
4. Add notes to clipboard items

---

## 12. Collaboration & Workspaces

### Project Collaboration

**Inviting Collaborators:**
1. Navigate to project → Collaborators tab
2. Click "Invite Collaborator"
3. Search for registered researchers
4. Assign a role: Viewer, Contributor, Editor, Admin

**Roles:**

| Role | View | Add Items | Edit | Manage Members |
|------|:----:|:---------:|:----:|:--------------:|
| Viewer | Yes | No | No | No |
| Contributor | Yes | Yes | No | No |
| Editor | Yes | Yes | Yes | No |
| Admin | Yes | Yes | Yes | Yes |

**Invitation Flow:**
```
Invite Sent → Pending → Accepted/Declined
```

### Workspaces

**URL:** `/research/workspaces`

Workspaces are independent collaboration spaces (not tied to a specific project):

1. Create a workspace with name, description, visibility
2. Invite members with roles
3. Attach resources (items, documents, URLs)
4. Start discussions

### Discussions

Within workspaces, create threaded discussions:
- Create a new discussion topic
- Reply to existing discussions (nested threading)
- Pin important discussions
- Resolve discussions when concluded

### Comments

Comments can be added to:
- Projects
- Reports
- Assertions
- Any entity with a comment thread

Supports markdown formatting and nested replies.

### Peer Review

**URL:** `/research/report/:id/review/request`

Request peer review on research reports:
1. Select a reviewer from registered researchers
2. Send review request
3. Reviewer receives notification
4. Reviewer submits review with:
   - Rating (1-5)
   - Recommendation (accept, revision, reject)
   - Detailed feedback
   - Private notes (visible only to admin)

---

## 13. Research Journal

### Overview

**URL:** `/research/journal`

The research journal is a chronological log of your research activities, thoughts, and findings.

### Creating Entries

**URL:** `/research/journal/new`

**Fields:**
- Entry date
- Title
- Content (rich text)
- Entry type: reflection, observation, methodology, finding, question, other
- Mood/energy (optional)
- Time spent (hours)
- Linked project (optional)
- Related entity (type + ID)
- Tags

### Auto-Entries

The system automatically creates journal entries for key actions:
- Saving a search query
- Creating a collection
- Adding annotations
- Completing bookings

Auto-entries are tagged with type `auto_*` (e.g., `auto_search`, `auto_collection`).

### Calendar View

The journal page shows a monthly calendar with entry dates highlighted. Click a date to see entries for that day.

### Time Tracking

The journal tracks time spent per project, available as a summary:
- Hours per project
- Hours by entry type
- Monthly totals

### Exporting

**URL:** `/research/journal/export/:format`

Formats: PDF, DOCX, CSV

---

## 14. Research Reports

### Creating Reports

**URL:** `/research/report/new`

Reports are multi-section documents for formal research output.

**Report Fields:**
- Title
- Type: research_report, literature_review, case_study, methodology, analysis, proposal, finding_aid
- Abstract
- Keywords
- Status: draft, review, final, published
- Linked project

### Report Sections

Each report consists of ordered sections:
- Add sections with title and content (rich text)
- Reorder sections via drag-and-drop
- Each section can have its own heading level

### Auto-Population

Click "Auto-populate from Project" to automatically generate sections from:
- Project description → Introduction
- Collection items → Sources
- Annotations → Findings
- Bibliographies → References

### Templates

Pre-defined templates:
- Research Report (standard academic format)
- Literature Review
- Case Study
- Methodology paper

### Exporting Reports

**URL:** `/research/report/:id/export/:format`

| Format | Description |
|--------|-------------|
| PDF | Formatted PDF document |
| DOCX | Microsoft Word document |
| XLSX | Spreadsheet format |
| Markdown | Plain text markdown |

---

## 15. Hypotheses

### Overview

**URL:** `/research/hypotheses/:project_id`

Hypotheses are formal research propositions that can be tested through evidence.

### Creating a Hypothesis

**Fields:**
- Title
- Description
- Assertion basis (what the hypothesis claims)
- Status: proposed, testing, supported, refuted, inconclusive

### Evidence Tracking

For each hypothesis, you can add supporting or opposing evidence:
- Evidence type: supports, contradicts, neutral
- Source reference (object ID, document, URL)
- Confidence score (0-1)
- Notes

### Hypothesis Lifecycle

```
Proposed → Testing → Supported / Refuted / Inconclusive
```

The evidence timeline shows all evidence added over time, allowing you to track how the hypothesis evolved.

---

## 16. Source Assessment & Trust Scoring

### Source Assessment

**URL:** `/research/source-assessment/:object_id`

Evaluate the trustworthiness and quality of archival sources:

**Assessment Criteria:**

| Criterion | Scale | Description |
|-----------|-------|-------------|
| Authority | 1-10 | Creator's expertise and credentials |
| Reliability | 1-10 | Consistency and accuracy of the source |
| Bias | 1-10 | Degree of objectivity (10 = unbiased) |
| Accuracy | 1-10 | Factual correctness |
| Overall Trust Score | Computed | Weighted average of criteria |

**Additional Fields:**
- Assessment methodology (textual description)
- Notes and observations
- Cross-reference sources

### Quality Metrics

Automated quality metrics from AI services:
- OCR confidence scores
- Digitization quality
- Metadata completeness
- Format identification confidence

### Trust Score Computation

The trust score is computed from:
1. Human assessments (authority, reliability, bias, accuracy)
2. Automated quality metrics (OCR confidence, format ID)
3. Weighted combination with configurable weights

---

## 17. Knowledge Graph & Assertions

### Overview

The knowledge graph represents structured research claims as subject-predicate-object triples with evidence tracking and conflict detection.

### Creating Assertions

**URL:** `/research/assertion/create`

An assertion consists of:

| Field | Description | Example |
|-------|-------------|---------|
| Subject Type | Entity type | `actor` |
| Subject ID | Entity identifier | `142` |
| Predicate | Relationship | `sameAs`, `createdBy`, `locatedIn` |
| Object Type | Target entity type | `actor` |
| Object ID | Target entity identifier | `287` |
| Assertion Type | Category | identity, attributive, temporal, spatial, causal |
| Confidence | Certainty (0-1) | `0.85` |

### Assertion Status

```
Proposed → Under Review → Verified / Disputed / Withdrawn
```

### Evidence

Each assertion can have multiple pieces of evidence:
- Source type (document, oral_testimony, physical_evidence, digital_record)
- Source ID (reference to archival object)
- Confidence score
- Status: proposed, verified, disputed

### Conflict Detection

The system automatically detects conflicting assertions:
- `sameAs` conflicts with `differentFrom`
- Contradictory temporal claims
- Inconsistent spatial assertions

**URL:** `/research/assertion/:id/conflicts`

### Knowledge Graph Visualization

**URL:** `/research/knowledge-graph/:project_id`

Interactive D3.js visualization showing:
- Nodes = Entities (actors, objects, places)
- Edges = Assertions (predicates)
- Colors = Assertion status (green=verified, yellow=proposed, red=disputed)

### Batch Review

**URL:** `/research/assertion-batch-review/:project_id`

Review multiple proposed assertions at once:
- Select assertions to review
- Set new status (verified, disputed, withdrawn)
- Apply to all selected

---

## 18. Snapshots & Reproducibility

### Creating Snapshots

**URL:** `/research/snapshot/create`

Snapshots are immutable captures of a collection's state at a point in time.

**From a collection:**
1. Navigate to a collection
2. Click "Create Snapshot"
3. The snapshot captures:
   - All item IDs and their metadata
   - Rights state per item
   - Query state and rights state JSON
   - A cryptographic hash of all state

**Snapshot Status:**
```
Active → Frozen → Archived
```

### Frozen Snapshots

When a snapshot is frozen:
- It cannot be modified, archived, or deleted
- A `frozen_at` timestamp is recorded
- A **Citation ID** is generated: `SNAP-{projectId}-{snapshotId}-{shortHash}`
- Rights snapshots are captured per item

### Hash Verification

Each snapshot has a SHA-256 HMAC hash computed from:
- Snapshot metadata (query_state_json, rights_state_json, metadata_json)
- Each item's metadata_version_json and rights_snapshot_json
- Stable sorting by object_id ASC, object_type ASC

**Verify integrity:** The system recomputes the hash and compares it to the stored hash. If they match, the snapshot is verified as unmodified.

### Comparing Snapshots

**URL:** `/research/snapshot/compare`

Compare two snapshots side-by-side:
- Items added since snapshot A
- Items removed since snapshot A
- Items present in both

### Snapshot Citation

Use the citation ID (e.g., `SNAP-5-23-a1b2c3d4`) to reference a specific state of a collection in publications, ensuring reproducibility.

---

## 19. AI Extraction & Validation Queue

### Extraction Jobs

**URL:** `/research/extraction-jobs/:project_id`

Create AI extraction jobs to process archival items:

**Job Types:**
| Type | Description |
|------|-------------|
| OCR | Optical Character Recognition on images/PDFs |
| NER | Named Entity Recognition (people, places, organizations, dates) |
| Summarize | AI-powered text summarization |
| Translate | Machine translation |
| Spellcheck | Spelling and grammar checking |
| Face Detection | Detect faces in images |
| Form Extraction | Extract structured data from forms |

### Creating a Job

1. Select a project and optionally a collection
2. Choose the extraction type
3. Configure parameters (e.g., language, model)
4. Submit — job runs in the background

### Job Status

```
Pending → Running → Completed / Failed
```

Progress tracking shows: items processed / total items.

### Extraction Results

Each extraction produces results with:
- Result type (entity, summary, translation, transcription, form_field, face)
- Extracted data (JSON)
- Confidence score (0-1)
- Model version
- Input hash (for reproducibility)

### Validation Queue

**URL:** `/research/validation-queue`

The validation queue is where human reviewers approve, modify, or reject AI extraction results.

**Stats Bar:** Shows counts of Pending, Accepted, Rejected results and the average confidence score.

**Filters:**
- Status: Pending, Accepted, Rejected, Modified, All
- Result Type: entity, summary, translation, transcription, form_field, face
- Extraction Type: OCR, NER, summarize, translate, spellcheck, face_detection, form_extraction
- Minimum Confidence: 0.00 - 1.00

**Table Columns:**
- Object (title or ID)
- Extraction type
- Result type
- Model version
- Confidence (with color-coded progress bar)
- Reviewer name
- Status badge

**Actions:**
- **Preview** — View the full extracted data as formatted JSON
- **Accept** — Approve the result
- **Edit & Accept** — Modify the JSON data, then accept
- **Reject** — Reject with a reason

**Bulk Operations:**
- Select multiple items with checkboxes
- Bulk Accept — Accept all selected
- Bulk Reject — Reject all selected (with reason)

---

## 20. Entity Resolution

### Overview

**URL:** `/research/entity-resolution`

Entity resolution identifies and links duplicate or related entities across archival collections. For example, recognizing that "J. Smith" in Collection A and "John Smith" in Collection B are the same person.

### Proposing Matches

Click "Propose Match" to open the proposal form:

**Fields:**
- Entity A: Type (actor/information_object/repository) and ID
- Entity B: Type and ID
- Relationship type:
  - **sameAs** — Identical entities (default)
  - **relatedTo** — Associated entities
  - **partOf** — Hierarchical relationship
  - **memberOf** — Group membership
- Match method: Manual, Name Similarity, Identifier Match, Authority Record
- Confidence score (0-1)
- Notes
- Evidence (one per line): `source_type | source_id | note`

### Automatic Candidate Finding

**URL:** `/research/entity-resolution/candidates?entity_type=actor&entity_id=123`

The system can find potential matches automatically:
1. Enter an entity type and ID
2. The system searches for similar entities using name similarity
3. Results are ranked by similarity score (0-1)
4. Only candidates above 30% similarity are shown

For actors, similarity is computed using PHP's `similar_text()` function on the authorized form of name.

### Reviewing Proposals

The entity resolution table shows:

| Column | Description |
|--------|-------------|
| Entity A | Name, type, and ID |
| Entity B | Name, type, and ID |
| Relationship | sameAs, relatedTo, partOf, memberOf |
| Confidence | Color-coded progress bar |
| Method | How the match was identified |
| Evidence | Count with expandable evidence table |
| Resolver | Who resolved the proposal |
| Status | Proposed, Accepted, Rejected |

### Conflict Detection

Before accepting a match, click the **Conflict Check** button (warning triangle):
- Checks for existing assertions that contradict the proposed relationship
- For example, a `differentFrom` assertion would conflict with a `sameAs` proposal
- Shows "Safe to accept" if no conflicts found

### sameAs Assertion Creation

When a `sameAs` match is accepted:
1. An assertion is automatically created: Entity A `sameAs` Entity B
2. The assertion includes the resolution evidence
3. The assertion appears in the knowledge graph

### Entity Link Network

Use `getEntityLinks()` to see all accepted resolutions for an entity — its full sameAs/relatedTo network.

---

## 21. Visualization Tools

### Timeline Builder

**URL:** `/research/timeline/:project_id`

Create chronological timelines of research events:

**Event Fields:**
- Title, Description
- Start date, End date
- Event type (creation, modification, transfer, destruction, discovery, other)
- Related entity (type + ID)
- Media URL (optional image/video)

**Features:**
- Auto-populate from a collection's date metadata
- Interactive timeline visualization
- Add/edit/delete events
- Link events to archival items

### Geographic Map

**URL:** `/research/map/:project_id`

Plot research locations on an interactive map:

**Point Fields:**
- Title, Description
- Latitude, Longitude
- Point type (origin, destination, event, discovery, repository, other)
- Related entity (type + ID)
- Date (optional)

**Features:**
- Add points manually or from archival metadata
- Bounding box search for nearby items
- Interactive map with markers and popups
- Link points to archival items

### Network Graph

**URL:** `/research/network-graph/:project_id`

Visualize entity relationships as a network:

**Features:**
- D3.js force-directed graph
- Nodes represent entities (actors, objects, repositories)
- Edges represent relationships (creator, subject, location, etc.)
- Filter by entity type and relationship type
- Export to GEXF format (compatible with Gephi)

### Knowledge Graph

**URL:** `/research/knowledge-graph/:project_id`

Dedicated visualization of research assertions:
- Subject → Predicate → Object relationships
- Color-coded by assertion status
- Interactive node exploration
- Filter by assertion type and status

---

## 22. Reproduction Requests

### Creating Requests

**URL:** `/research/reproduction/new`

Request digital reproductions of archival materials:

**Request Fields:**
- Purpose of reproduction
- Intended use (personal, publication, exhibition, commercial)
- Required format (digital scan, photocopy, photograph)
- Quality requirements (72dpi, 150dpi, 300dpi, 600dpi)
- Delivery method (download, email, post)
- Special instructions

### Adding Items

Add items to the reproduction request:
- Search for archival items
- Specify pages/sections needed
- Add per-item notes

### Request Lifecycle

```
Draft → Submitted → Pending Triage → Triage Approved → In Fulfilment → Delivered → Closed
                                          ↓
                                    Triage Denied
                                          ↓
                                    Needs Information (researcher contacted)
```

**Triage** — every incoming request goes through a triage step where staff can approve, deny, or request more information from the researcher before work begins. See [Section 23: Request Lifecycle & SLA](#23-request-lifecycle--sla) for full details.

### Pricing

Costs are calculated based on:
- Item count and type
- Quality/resolution
- Format requested
- Rush processing (if available)

### File Delivery

Once completed:
- Files are uploaded by staff
- Each file gets a unique download token
- Researchers access downloads at `/research/reproduction/download/:token`
- Download is logged for audit purposes

---

## 23. Request Lifecycle & SLA

### Requests Dashboard

**URL:** `/research/requests-dashboard`

The combined requests dashboard shows all material and reproduction requests in a single view with SLA status indicators.

**Summary Cards:**
- Total material requests
- Total reproduction requests
- Overdue requests (past SLA due date)

**Table Columns:**
- ID, Type (Material/Reproduction), Title, Researcher, Status, Priority, SLA Due Date, Assigned To

**SLA Badges:**
- Green: on track (more than 3 days remaining)
- Yellow/Warning: approaching deadline (3 days or fewer)
- Red/Danger: overdue (past SLA due date)

### Triage

**URL:** `/research/request/:id/triage/:type`

Every new request must go through triage before processing begins. Triage captures:

**Triage Decisions:**
- **Approve** — request is valid and can proceed to fulfilment; SLA timer starts
- **Deny** — request is rejected with a reason (e.g., restricted material, insufficient justification)
- **Needs Information** — researcher is contacted for clarification before a decision

**Triage Form Fields:**
- Decision (approve / deny / needs information)
- Notes (required for deny and needs-information decisions)

When a request is triaged as "approved", the system automatically computes the SLA due date based on the configured policy (default: 10 working days). A workflow event is emitted for the audit trail.

### Assignment

**URL:** `/research/request/:id/assign/:type`

Staff can assign requests to specific team members for fulfilment. Assignment:
- Records who is responsible for the request
- Emits a workflow event for the audit trail
- Visible on the requests dashboard

### Correspondence

**URL:** `/research/request/:id/correspond/:type`

A built-in correspondence thread between staff and researchers, attached to each request.

**Features:**
- Threaded view with staff messages (blue) and researcher messages (green)
- Internal notes visible only to staff (yellow highlight, lock icon)
- Quick action buttons for triage and closure
- Timeline sidebar showing combined status history, workflow events, and correspondence

**Fields:**
- Message body (required)
- Internal note checkbox (staff-only visibility)

### Closing Requests

**URL:** `/research/request/:id/close/:type`

Close a request when fulfilment is complete or the request is no longer needed.

**Closure Reasons:**
- Fulfilled
- Cancelled by researcher
- Duplicate
- Unable to fulfil
- Other

### SLA Configuration

SLA policies are managed centrally. The default research request SLA is:
- **Warning:** 7 days (approaching deadline)
- **Due:** 10 working days
- **Escalation:** 14 days (breached — triggers escalation)

SLA computation starts when a request is triaged as "approved". The SLA status is visible on the requests dashboard and in individual request views.

### "Request This Item" Button

On archival description (information object) pages, a "Request this item" button allows researchers to create a material request directly from the catalogue. The button:
- Submits via AJAX to `/research/ajax/request-item`
- Creates a new material request linked to the archival object
- Shows success/error feedback inline
- Only visible to logged-in, approved researchers

---

## 24. Material Retrieval & Custody

### Overview

The custody system tracks the physical chain of custody for archival materials as they move between storage, staff, and researchers. Every handoff is recorded with timestamps, handlers, condition assessments, and optional barcode scans.

### Custody Checkout

**URL:** `/research/custody/:id/checkout`

When materials are handed to a researcher:

**Checkout Form Fields:**
- Condition at handoff (Excellent, Good, Fair, Poor, Critical)
- Barcode scan (optional — for barcode-enabled collections)
- Destination (defaults to "Reading Room")
- Notes

**What Happens:**
1. A custody handoff record is created
2. A Spectrum movement record is auto-generated (movement reason: `research_checkout`)
3. The material request status changes to `in_use`
4. The physical object's access status updates to `in_use`
5. A workflow event is emitted for the audit trail
6. The item's current location updates to the reading room

### Custody Check-In / Return

**URL:** `/research/custody/:id/checkin`

When materials are returned by a researcher:

**Return Form Fields:**
- Condition before (state when material was checked out)
- Condition after (state when returned)
- Notes (e.g., damage observations)

**What Happens:**
1. A custody handoff record is created (type: `checkin`)
2. A Spectrum movement record is auto-generated (movement reason: `research_return`)
3. The material request status changes to `returned`
4. The physical object's access status updates to `available`
5. Current location updates to "Return shelf (pending re-shelving)"

### Return Verification

**URL:** `/research/custody/:id/return-verify`

An optional verification step after return, where a second staff member confirms:
- The material's condition matches what was reported
- The material is ready to be re-shelved
- Current location is updated to the original shelf location

### Staff-to-Staff Transfer

Custody can be transferred between staff members (e.g., shift handover). A transfer records:
- From handler and to handler
- Condition at transfer
- Notes

### Batch Checkout

**URL:** `/research/custody/batch-checkout`

Process multiple checkouts at once:
1. Select requests from the retrieval queue
2. Set a default condition and destination for all items
3. Review the list and uncheck any items to exclude
4. Submit — each item gets an individual custody record

### Batch Return

**URL:** `/research/custody/batch-return`

Process multiple returns at once:
1. Select requests from the retrieval queue
2. For each item, set condition-before and condition-after individually
3. Add per-item notes if needed
4. Submit — each item gets an individual custody and movement record

### Custody Chain

**URL:** `/research/custody/chain/:object_id`

View the full chain of custody for any archival object. The chain combines data from three sources:

| Source | Icon | Description |
|--------|------|-------------|
| Custody Handoff | Hand icon (blue) | Research checkout/checkin/transfer events |
| Spectrum Movement | Truck icon (cyan) | Physical movement records |
| Provenance | Scroll icon (yellow) | Historical provenance events |

**Table Columns:**
- Date, Source, Event Type, From (handler + location), To (handler + location), Condition, Confirmed (signature), Notes

### Spectrum Movement Integration

Every custody handoff automatically creates a corresponding `spectrum_movement` record:
- Movement reference: `RR-{request ID}`
- Includes condition before/after
- Links back to the custody handoff via foreign key
- Compliant with Spectrum 5.1 Object Location and Movement Control procedures

---

## 25. Accessibility

### WCAG 2.1 AA Compliance

All research and workflow screens comply with WCAG 2.1 Level AA accessibility standards.

### Skip Navigation

Every page includes a "Skip to main content" link, visible when focused via keyboard (Tab key). This allows keyboard users and screen reader users to bypass the navigation and jump directly to the page content.

### Screen Reader Support

**ARIA Live Region** — an invisible region (`aria-live="polite"`) announces dynamic changes (AJAX updates, status changes) to screen readers without requiring a page reload.

**Helper Functions (JavaScript):**
- `ahgAnnounce(message, priority)` — announce a message to screen readers
- `ahgFocusTo(selector)` — programmatically move focus to an element

### Data Tables

All data tables include:
- `aria-label` on the `<table>` element describing the table's purpose
- `<caption class="visually-hidden">` with a detailed description
- `scope="col"` on all `<th>` header cells
- Row-level `aria-label` on checkboxes identifying the associated item

### Status Badges

Status indicators never rely on colour alone:
- Every badge includes an icon and text alongside the colour
- Badges use `role="status"` and `aria-label` for screen reader context
- Priority badges: bolt icon (rush/red), arrow-up icon (high/yellow), dash icon (normal/grey)

### Form Accessibility

- Required fields use `aria-required="true"`
- Validation errors use `role="alert"` and `aria-invalid="true"`
- Labels are programmatically associated with inputs via `for`/`id` or wrapping `<label>` elements

### Keyboard Navigation

- All interactive elements (buttons, links, checkboxes, dropdowns) are reachable via Tab
- Escape key dismisses open modals
- Focus indicators use a visible 3px blue outline (`:focus-visible`)
- Batch select-all checkboxes toggle all items in the list

### Decorative Icons

All decorative Font Awesome icons include `aria-hidden="true"` to prevent screen readers from announcing them. Icons that convey meaning include appropriate `aria-label` or accompanying visible text.

---

## 26. Rights & Access Policies (ODRL)

### Overview

**URL:** `/research/odrl/policies`

ODRL (Open Digital Rights Language) policies define structured access rules for research materials.

### Creating Policies

**Policy Fields:**
- Target type (project, collection, object)
- Target ID
- Policy type: permission, prohibition, obligation
- Action: use, reproduce, distribute, modify, archive, display
- Assignee (researcher or group)
- Constraint (time period, purpose, jurisdiction)
- Duty (attribution, payment, notification)

### Access Evaluation

**URL:** `/research/odrl/evaluate`

Evaluate whether a specific action is permitted:
- Input: target type, target ID, researcher ID, requested action
- Output: permitted/prohibited with explanation and applicable policy

---

## 27. RO-Crate & DOI Minting

### RO-Crate Packaging

**URL:** `/research/ro-crate/:project_id`

Package a research project as an RO-Crate (Research Object Crate):
- Follows the RO-Crate specification (FAIR data principles)
- Includes metadata, data files, and provenance information
- BagIt format for integrity verification
- Schema.org dataset description

### Collection Packaging

**URL:** `/research/ro-crate/collection/:id`

Package a single collection as an RO-Crate.

### RiC-O JSON-LD Export

**URL:** `/research/json-ld/:project_id`

Export project metadata as RiC-O JSON-LD for RiC (Records in Contexts) linked data applications.

### DOI Minting

**URL:** `/research/doi/:project_id`

Mint a Digital Object Identifier for your research project:
- Integrates with DataCite API
- Generates DOI metadata from project information
- Registers the DOI with DataCite
- Tracks DOI status and updates

### Reproducibility Pack

**URL:** `/research/reproducibility/:project_id`

Generate a complete reproducibility pack including:
- Project metadata
- Collection snapshots with hash verification
- Extraction job configuration and results
- Assertion graph
- Bibliography references
- DOI reference

---

## 28. Notifications

### Viewing Notifications

**URL:** `/research/notifications`

Notifications are generated for:
- Project invitations
- Workspace invitations
- Peer review requests
- Booking confirmations
- Material request updates
- Search alert triggers
- Collaboration activity

### Notification Preferences

Configure which notifications you receive:
- In-app notifications (always on)
- Email notifications (configurable per type)
- Digest frequency (immediate, daily, weekly)

### API Access

**URL:** `/research/notifications/api`

JSON API for real-time notification polling:
- Get unread count
- Mark as read
- Mark all as read
- Delete old notifications

---

## 29. REST API & API Keys

### API Key Management

**URL:** `/research/profile/api-keys`

Generate API keys for programmatic access:
1. Click "Generate New Key"
2. Enter a name/description
3. Set permissions (optional)
4. Set expiry (default: 365 days)
5. Copy the generated key (shown only once)

### API Endpoints

Base URL: `/api/research/`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/research/profile` | GET | Get researcher profile |
| `/api/research/projects` | GET/POST | List or create projects |
| `/api/research/collections` | GET/POST | List or create collections |
| `/api/research/collections/:id` | GET | Get collection details |
| `/api/research/searches` | GET | List saved searches |
| `/api/research/bookings` | GET/POST | List or create bookings |
| `/api/research/citations/:id/:format` | GET | Generate citation |
| `/api/research/bibliographies` | GET | List bibliographies |
| `/api/research/bibliographies/:id/export/:format` | GET | Export bibliography |
| `/api/research/annotations` | GET | List annotations |
| `/api/research/stats` | GET | Usage statistics |

### Authentication

Include the API key in the `Authorization` header:
```
Authorization: Bearer YOUR_API_KEY
```

### Rate Limiting

Default rate limit: 1000 requests per day per key. Configurable per key.

---

## 30. ORCID Integration

### Connecting ORCID

**URL:** `/research/orcid/connect`

1. Click "Connect ORCID"
2. You are redirected to ORCID.org
3. Authorize the application
4. You are redirected back with your ORCID iD linked

### Benefits

- Verified researcher identity
- ORCID iD displayed on your profile
- Potential for automatic publication tracking
- Enhanced trust for institutional sharing

### Disconnecting

**URL:** `/research/orcid/disconnect`

You can disconnect your ORCID iD at any time.

---

## 31. Institutional Sharing

### Managing Institutions

**URL:** `/research/admin/institutions` (Admin only)

Register external institutions for collaboration:
- Name, Code, Description
- Contact name, email, phone
- Website, Country
- Agreement type and status

### Sharing Projects

**URL:** `/research/project/:id/share`

Share a research project with an external institution:
1. Select an institution
2. Set access level (view, contribute, full)
3. Set expiry date
4. Generate access token

### External Access

**URL:** `/research/share/:token`

External collaborators access shared projects via a unique token URL. They can view project resources according to their access level.

### External Collaborators

Add external collaborators (non-researchers) to shares:
- Name, Email, Institution
- Access level and expiry
- Individual access tokens

---

## 32. Audit Trail

### Audit Module

**URL:** `/audit`

The audit trail records all significant actions in the research portal:

**Tracked Actions:**
- Researcher registration, approval, rejection
- Booking creation, check-in, check-out
- Collection modifications
- Annotation creation and deletion
- Project changes
- Report generation
- API key creation and revocation

### Viewing Audit Records

| View | URL | Description |
|------|-----|-------------|
| Index | `/audit` | All audit entries with filters |
| By User | `/audit/user/:id` | All actions by a specific user |
| By Record | `/audit/record/:table/:id` | All changes to a specific record |
| Detail | `/audit/view/:id` | Full audit entry with before/after values |

### Exporting

**URL:** `/audit/export`

Export audit logs as CSV for compliance reporting.

### What's Logged

Each audit entry contains:
- Action type (create, update, delete, approve, reject, etc.)
- Entity type and ID
- Old values (before change)
- New values (after change)
- Changed fields list
- User who performed the action
- Timestamp
- Module/plugin source

---

## 33. Administration

### Researcher Approval

**URL:** `/research/researchers`

Admin workflow for managing researchers:
1. View all pending researchers
2. Review registration details
3. Approve — sets status to "approved", creates access request
4. Reject — archives data, deactivates user, records reason

### Reading Room Management

**URL:** `/research/rooms`

Configure reading rooms:
- Name, Location, Description
- Capacity
- Opening time, Closing time
- Days open (bitmask)
- Active/Inactive status

### Researcher Type Management

**URL:** `/research/admin/types`

Configure researcher types with booking limits and permissions (see Section 4).

### Statistics Dashboard

**URL:** `/research/admin/statistics`

Administrative analytics:
- Active researchers count
- Bookings per day/week/month
- Most viewed items
- Most cited items
- Most collected items
- Active researchers ranking
- Usage trends over time

### Ethics Milestones

**URL:** `/research/ethics-milestones/:project_id`

Track ethics approval milestones for projects:
- IRB/Ethics committee submission
- Approval status
- Conditions and modifications
- Renewal dates

---

## 34. Database Reference

### Core Tables (64 tables)

**Researcher Management:**
- `research_researcher` — Researcher profiles
- `research_researcher_audit` — Archived/rejected researchers
- `research_researcher_type` — Configurable researcher types
- `research_researcher_type_i18n` — Type translations
- `research_verification` — ID/institutional verification
- `research_password_reset` — Password reset tokens

**Reading Room:**
- `research_reading_room` — Room definitions
- `research_booking` — Reading room bookings
- `research_material_request` — Material retrieval requests
- `research_request_status_history` — Request status tracking

**Collections:**
- `research_collection` — Researcher collections
- `research_collection_item` — Items in collections

**Projects:**
- `research_project` — Research projects
- `research_project_collaborator` — Project collaborators
- `research_project_resource` — Project resources
- `research_project_milestone` — Project milestones
- `research_clipboard_project` — Clipboard items

**Workspaces:**
- `research_workspace` — Collaborative workspaces
- `research_workspace_member` — Workspace members
- `research_workspace_resource` — Workspace resources
- `research_discussion` — Discussion threads

**Annotations:**
- `research_annotation` — Legacy annotations (v1)
- `research_annotation_v2` — W3C Web Annotations (v2)
- `research_annotation_target` — Annotation targets/selectors

**Knowledge Graph:**
- `research_assertion` — Subject-predicate-object triples
- `research_assertion_evidence` — Evidence for assertions
- `research_hypothesis` — Research hypotheses
- `research_hypothesis_evidence` — Evidence for hypotheses
- `research_source_assessment` — Source quality assessment
- `research_quality_metric` — Automated quality metrics

**Extraction:**
- `research_extraction_job` — AI extraction jobs
- `research_extraction_result` — Extraction results
- `research_validation_queue` — Validation queue

**Entity Resolution:**
- `research_entity_resolution` — Entity matching proposals

**Visualization:**
- `research_timeline_event` — Timeline events
- `research_map_point` — Geographic map points

**Bibliography:**
- `research_bibliography` — Bibliography collections
- `research_bibliography_entry` — Bibliography entries

**Search:**
- `research_saved_search` — Saved searches with citation IDs
- `research_search_alert_log` — Alert event log

**Reproductions:**
- `research_reproduction_request` — Reproduction requests
- `research_reproduction_item` — Items in requests
- `research_reproduction_file` — Generated files

**Reports:**
- `research_report` — Research reports
- `research_report_section` — Report sections
- `research_report_template` — Report templates
- `research_journal_entry` — Journal entries

**Collaboration:**
- `research_comment` — Comments
- `research_peer_review` — Peer review records

**Notifications:**
- `research_notification` — User notifications
- `research_notification_preference` — Notification settings

**Institutional:**
- `research_institution` — External institutions
- `research_institutional_share` — Project shares
- `research_external_collaborator` — External collaborators

**Rights:**
- `research_rights_policy` — ODRL policies
- `research_access_decision` — Access decisions

**API & Analytics:**
- `research_api_key` — API keys
- `research_api_log` — API request log
- `research_activity_log` — Activity tracking
- `research_citation_log` — Citation usage log
- `research_statistics_daily` — Daily statistics
- `research_document_template` — Document templates
- `research_snapshot` — Immutable snapshots
- `research_snapshot_item` — Snapshot items
- `research_request_correspondence` — Staff/researcher correspondence threads on requests
- `research_custody_handoff` — Chain-of-custody handoff records (checkout, checkin, transfer, return, condition check)

---

## 35. Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Cannot access research portal | Ensure you are logged in and registered as a researcher |
| Registration pending | Wait for admin approval; contact your archivist |
| Booking rejected | Check your researcher type limits (days advance, hours per day) |
| Materials not available | Check retrieval queue status; materials may be in use |
| Annotations not saving | Clear browser cache; check for JavaScript errors |
| Export fails | Check file permissions on the uploads directory |
| API key not working | Verify key is active and not expired |
| ORCID connection fails | Check ORCID configuration in app.yml |
| Snapshot hash mismatch | Data may have been modified; contact admin |
| Search diff shows no changes | Ensure you have taken at least one snapshot first |

### Browser Compatibility

The Research Portal requires a modern browser:
- Chrome 90+
- Firefox 90+
- Safari 14+
- Edge 90+

JavaScript must be enabled. The portal uses Bootstrap 5, D3.js for visualizations, and Quill.js for rich text editing.

### Getting Help

Contact your institution's archivist or system administrator for:
- Account activation issues
- Access permission changes
- Technical problems
- Feature requests

---

*This manual covers ahgResearchPlugin v3.1.0. For the latest updates, check the plugin documentation in the Heratio repository.*
