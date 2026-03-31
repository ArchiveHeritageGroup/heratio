# Research Knowledge Platform (Phase 2) - User Guide

## Overview

The Research Knowledge Platform extends the Researcher Portal with advanced tools for source analysis, knowledge building, AI-assisted extraction, visualization, and research output packaging. All Phase 2 features are accessed from the **Research Dashboard** or individual **Project** pages.

**Plugin:** ahgResearchPlugin
**Access:** Approved researchers (status = `approved`)

---

## Navigation

### Sidebar Sections

| Section | Features |
|---------|----------|
| **Research** | Workspace, Projects, Team Workspaces, Evidence Sets, Journal, Bibliographies, Reports |
| **Knowledge Platform** | Saved Searches, Annotation Studio, Validation Queue, Entity Resolution, ODRL Policies |
| **Services** | Reproduction Requests, Book Reading Room |

### Dashboard Quick Access

The Knowledge Platform card on the Research Dashboard provides one-click access to global features. Project-specific tools (Knowledge Graph, Timeline, Map, AI Extraction) are accessed from each project's page.

### Information Object Context Menu

When viewing any archival description (GLAM/DAM), approved researchers see a **"Research Tools"** section in the left sidebar with links to:
- **Source Assessment** - Evaluate source reliability
- **Annotation Studio** - Create W3C annotations
- **Trust Score** - View computed trust score

---

## Features by Section

### Section 2: Source Assessment & Trust Scoring

#### Source Assessment
**URL:** `/research/source-assessment/{object_id}`

Evaluate the reliability and quality of an archival source using structured criteria.

**Fields:**
| Field | Description |
|-------|-------------|
| Source Type | Primary, secondary, tertiary |
| Completeness | How complete is the source (percentage) |
| Authenticity | Assessment of authenticity |
| Bias Context | Known biases or perspectives |
| Quality Metrics | Structured quality scoring |
| Rationale | Free-text assessment rationale |

**How to use:**
1. Navigate to any archival description
2. Click **"Source Assessment"** in the Research Tools sidebar
3. Fill in the assessment form
4. Save your assessment

#### Trust Score
**URL:** `/research/trust-score/{object_id}`

View the computed trust score for a source, aggregated from all assessments.

#### Assessment History
**URL:** `/research/assessment-history/{object_id}`

View all past assessments for a source, including assessments by other researchers (if public).

---

### Section 3: Annotation & Knowledge Building

#### Annotation Studio
**URL:** `/research/annotation-studio/{object_id}`

Create W3C Web Annotations on archival objects. Annotations can be:
- **Text annotations** - Notes attached to specific content
- **Tags** - Keyword labels
- **Classifications** - Structured categorization
- **Links** - Connections to other resources

**Features:**
- Create/edit/delete annotations
- Import/export IIIF annotations
- Promote annotations to formal assertions
- W3C Web Annotation compliant

**How to use:**
1. Navigate to an archival description
2. Click **"Annotation Studio"** in Research Tools
3. Create annotations using the editor
4. Optionally promote important annotations to assertions

#### My Annotations
**URL:** `/research/annotations`

Browse and manage all your annotations across all objects.

#### Assertions (Project-scoped)
**URL:** `/research/assertions/{project_id}`

Formal scholarly claims attached to a research project. Assertions are more structured than annotations and can be:
- Linked to evidence (sources, annotations)
- Reviewed by collaborators
- Included in the knowledge graph
- Tagged with confidence levels

**How to use:**
1. Open a project
2. Click **"Assertions"** in the Analysis Tools sidebar
3. Create assertions with evidence links
4. Set confidence levels and tags

#### Knowledge Graph (Project-scoped)
**URL:** `/research/knowledge-graph/{project_id}`

Visual representation of entities, relationships, and assertions in your project.

**How to use:**
1. Open a project
2. Click **"Knowledge Graph"** in the Analysis Tools sidebar
3. Explore the interactive graph visualization
4. Click nodes to view details
5. Add new relationships directly from the graph

---

### Section 4: AI-Assisted Extraction

#### Extraction Jobs (Project-scoped)
**URL:** `/research/extraction-jobs/{project_id}`

Use AI to extract entities, relationships, and metadata from archival materials linked to your project.

**Extraction types:**
| Type | Description |
|------|-------------|
| Named Entity Recognition | Extract persons, organizations, places, dates |
| Relationship Extraction | Identify connections between entities |
| Metadata Extraction | Extract structured metadata from unstructured text |
| Summarization | Generate AI summaries of content |

**How to use:**
1. Open a project with linked archival items
2. Click **"AI Extraction"** in Analysis Tools
3. Select items to process
4. Choose extraction type
5. Submit the job
6. Review results in the Validation Queue

#### Validation Queue
**URL:** `/research/validation-queue`

Review and approve AI-extracted entities and relationships before they enter your knowledge graph.

**Statuses:**
| Status | Meaning |
|--------|---------|
| Pending | Awaiting review |
| Approved | Accepted into knowledge graph |
| Rejected | Discarded |
| Modified | Corrected and accepted |

**How to use:**
1. Navigate to **Validation Queue** from the sidebar
2. Filter by project, type, or status
3. Review each extracted item
4. Approve, reject, or modify
5. Approved items are added to the project's knowledge graph

---

### Section 5: Visualization & Analysis

#### Timeline Builder (Project-scoped)
**URL:** `/research/timeline/{project_id}`

Create temporal visualizations of events and assertions in your project.

**How to use:**
1. Open a project
2. Click **"Timeline Builder"** in Visualization
3. Add events with dates, descriptions, and links
4. Events from assertions are auto-populated
5. Export or embed the timeline

#### Map Builder (Project-scoped)
**URL:** `/research/map/{project_id}`

Create geospatial visualizations of locations referenced in your project.

**How to use:**
1. Open a project
2. Click **"Map Builder"** in Visualization
3. Add map points with coordinates, descriptions, and links
4. Location data from assertions is auto-populated
5. Export or embed the map

#### Network Graph (Project-scoped)
**URL:** `/research/network-graph/{project_id}`

Visualize relationships between entities (people, organizations, places) in your project as an interactive network.

#### Entity Resolution
**URL:** `/research/entity-resolution`

Identify and merge duplicate entities across your research. When the same person, organization, or place appears under different names, entity resolution helps you link them.

**How to use:**
1. Navigate to **Entity Resolution** from the sidebar
2. View proposed matches (auto-detected or manual)
3. Confirm or reject matches
4. Merged entities update across all projects

#### Snapshots (Project-scoped)
**URL:** `/research/snapshots/{project_id}`

Save point-in-time snapshots of your project state for comparison and reproducibility.

**How to use:**
1. Open a project
2. Click **"Snapshots"** in Analysis Tools
3. Create a new snapshot (captures current state)
4. Compare snapshots to see what changed
5. Use snapshots for reproducibility packs

#### Hypotheses (Project-scoped)
**URL:** `/research/hypotheses/{project_id}`

Track research hypotheses with evidence for/against each.

---

### Section 6: Research Output & Reproducibility

#### RO-Crate Package (Project-scoped)
**URL:** `/research/ro-crate/{project_id}`

Export your project as a Research Object Crate (RO-Crate) package. RO-Crate is a standard for packaging research data, metadata, and provenance information.

**Package includes:**
- Project metadata (JSON-LD)
- Assertions and evidence
- Knowledge graph data
- Linked archival references
- Annotation data

#### Reproducibility Pack (Project-scoped)
**URL:** `/research/reproducibility/{project_id}`

Generate a complete reproducibility package documenting your research process, including:
- Methodology description
- Data sources and versions
- Search queries and parameters
- Analysis steps
- Snapshots of project state

#### DOI Minting (Project-scoped)
**URL:** `/research/doi/{project_id}`

Mint a Digital Object Identifier for your research output via DataCite integration.

#### Ethics Milestones (Project-scoped)
**URL:** `/research/ethics-milestones/{project_id}`

Track ethics review milestones for your research project (IRB approval, consent forms, etc.).

---

### Section 7: Rights & Licensing

#### ODRL Policies
**URL:** `/research/odrl/policies`

Manage Open Digital Rights Language policies for your research outputs. Define how your research data can be used, shared, and cited.

**How to use:**
1. Navigate to **ODRL Policies** from the sidebar
2. Browse existing policies
3. Create new policies using the ODRL editor
4. Assign policies to research outputs

---

## Project View — Analysis Tools

When viewing a project, the right sidebar contains three tool cards:

### Analysis Tools
| Tool | Description |
|------|-------------|
| Knowledge Graph | Visual entity-relationship graph |
| Assertions | Formal scholarly claims with evidence |
| Hypotheses | Track research hypotheses |
| AI Extraction | Run AI extraction jobs on project items |
| Snapshots | Save/compare project states |

### Visualization
| Tool | Description |
|------|-------------|
| Timeline Builder | Temporal event visualization |
| Map Builder | Geospatial visualization |
| Network Graph | Relationship network visualization |

### Research Output
| Tool | Description |
|------|-------------|
| RO-Crate Package | Research Object packaging |
| Reproducibility Pack | Full reproducibility documentation |
| DOI Minting | Digital Object Identifier assignment |
| Ethics Milestones | Ethics review tracking |

---

## Central Dashboard Integration

The Central Dashboard (`/reports/`) includes a **Research Services** row with three cards:
1. **Research Services** - Dashboard, Projects, Evidence Sets, Journal, Reports, Bibliographies
2. **Knowledge Platform** - Annotations, Saved Searches, Validation Queue, Entity Resolution, ODRL, Templates
3. **Research Admin** (admin-only) - Manage Researchers, Bookings, Rooms, Reproductions, Statistics

---

## Saved Search Bridge

When you save a search from the GLAM Browse page (via the "Save Search" modal), it is automatically bridged to your Research Saved Searches:
- Appears in both **Saved Searches** (general) and **Research Saved Searches** (with citation IDs)
- Citation IDs are auto-generated in format `QRY-{researcherId}-{id}-{hash}`
- Alert settings (daily/weekly/monthly) are preserved
- You can manage alerts, view diffs, and take snapshots from the Research Saved Searches page

---

## Security & Access

| Feature | Access Level |
|---------|-------------|
| Research Dashboard | All authenticated users |
| Knowledge Platform tools | Approved researchers only |
| Research Admin | Administrators only |
| Source Assessment / Annotation | Approved researchers only |
| Central Dashboard - Research block | All authenticated users |
| Central Dashboard - Reports/Export | Editors and administrators |
| Central Dashboard - Security/Compliance | Administrators only |

---

## Related Documentation

- [Researcher Portal (Phase 1)](researcher-user-guide.md) - Registration, Collections, Bookings, Annotations, Citations
- [GLAM Browse](glam-browse-user-guide.md) - Browse and search interface
- [AI Tools](ai-tools-user-guide.md) - NER, Translation, Summarization
- [Data Ingest](data-ingest-user-guide.md) - Batch data import
