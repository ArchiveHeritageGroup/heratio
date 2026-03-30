# Heratio + RiC Roadmap

## Purpose

This roadmap sets out the recommended direction for **Heratio** and **RiC** so that RiC becomes a first-class capability without weakening the current strength of Heratio as the operational platform.

The aim is **not** to replace Heratio with a separate RiC product. The aim is to make **Heratio and RiC operate as equal presentation modes over the same archival reality**, while preserving AtoM compatibility where it still provides value.

---

## 1. Strategic Position

### Recommended model

* **Heratio** remains the primary operational GLAM, archival, DAM, and records platform.
* **RiC** becomes a first-class semantic, contextual, and interoperability mode inside Heratio.
* **OpenRiC** should support this as the public RiC initiative, framework, documentation, and ecosystem layer.

### Principle

Users should not experience Heratio and RiC as competing systems.

They should experience:

* **Heratio View** for traditional archival description, hierarchy, workflow, editing, and operational work.
* **RiC View** for contextual traversal, entity relationships, linked-data understanding, and graph-based discovery.

Both views should operate over the same permissions, identifiers, services, and content.

---

## 2. Architectural Target State

### Target stack

#### A. Heratio application layer

Responsible for:

* primary UI
* workflows
* editing
* operational administration
* roles and permissions
* import/export flows
* digital object management
* institutional deployments

#### B. RiC semantic layer

Responsible for:

* contextual relationships
* linked data representation
* semantic traversal
* graph querying
* interoperability
* JSON-LD/RDF outputs
* SHACL and semantic validation
* relationship authority over time

#### C. Relational persistence strategy

Use a dual persistence strategy:

1. **AtoM-compatible layer**

   * retained for continuity, migration, compatibility, and existing deployments
   * not the long-term ceiling of the platform

2. **PostgreSQL Heratio-native layer**

   * introduced for new operational and semantic support domains
   * gradually becomes the stronger application domain store

3. **Triplestore / graph layer**

   * RiC relationships and linked-data authority

### Principle of authority

Over time:

* AtoM-compatible structures become a **compatibility and transition layer**
* PostgreSQL becomes the **primary operational domain layer**
* RiC graph services become the **primary contextual and semantic relationship layer**

---

## 3. Core Product Decision

### What we are not doing

* Not building OpenRiC as a competing full production product
* Not abandoning AtoM compatibility immediately
* Not forcing end users to browse ontology classes instead of archival concepts
* Not making RiC a hidden side panel or export-only feature

### What we are doing

* Making RiC visible and usable in normal Heratio workflows
* Creating dual-view entities throughout the platform
* Preserving Heratio as the main operational shell
* Raising RiC to equal footing through rendering, search, traversal, and services

---

## 4. UX and Navigation Roadmap

### 4.1 Primary navigation model

The primary navigation should remain familiar and AtoM/Heratio-like, but streamlined.

#### Recommended primary navigation

* Home
* Search
* Browse
* Collections
* Agents
* Functions & Activities
* Places
* Digital Objects
* Admin

### 4.2 Heratio vs RiC dual-view pattern

Every major entity page should support two modes:

* **Heratio View**
* **RiC View**

This must be a seamless switch, not a jump to another application.

#### Examples

* Description page -> switch between archival description and RiC contextual rendering
* Agent page -> authority view vs RiC network/context view
* Place page -> authority metadata vs contextual entity graph
* Function page -> administrative view vs relationship-centered RiC view

### 4.3 Record/description page pattern

#### Heratio View

Display familiar archival description structure:

* title
* identifier/reference code
* level of description
* dates
* extent and medium
* scope and content
* creator
* archival history
* conditions of access
* related units of description
* digital objects

#### RiC View

Display relationship-centered structure:

* RiC entity type
* parent/child relations
* creator and accumulator links
* related agents
* related functions
* related activities
* related places
* related instantiations
* provenance assertions
* graph/network panel

### 4.4 Sideways traversal

RiC should become visible through navigation, not only data export.

Each major page should expose:

* related agents
* related places
* related functions
* related activities
* related records
* instantiations
* provenance/context summary
* a clear action such as **Open Context** or **View Network**

### 4.5 Search and browse evolution

Search results should show both:

* traditional archival identity
* key RiC context

Browse should support:

* hierarchy browse
* creator browse
* function browse
* place browse
* date/event browse
* contextual network browse

---

## 5. Data Strategy Roadmap

### 5.1 Immediate database recommendation

Do **not** hard-cut away from the AtoM-compatible schema.

Instead:

* preserve compatibility
* stop treating it as the final architecture
* begin introducing a PostgreSQL Heratio-native domain layer

### 5.2 What stays in the AtoM-compatible layer initially

* existing archival description records
* current hierarchy structures
* legacy-compatible authority data where required
* features already stable and tied to current deployments
* compatibility for import/export and upgrade pathways

### 5.3 What should move first into PostgreSQL

The first migrations into PostgreSQL should be clearly Heratio-native domains:

* workflow and state machines
* audit logs and event history
* background jobs and orchestration
* semantic enrichment results
* AI extraction metadata
* validation outputs
* report materializations
* integration state tables
* user preferences and personalization
* advanced permissions extensions
* RiC sync and semantic bookkeeping

### 5.4 What should increasingly become graph-authoritative

* relationships between records and record sets
* relationships to agents
* functions and activities
* place-based relationships
* instantiation relationships
* related material connections
* context traversal logic
* semantic discovery features

### 5.5 Long-term goal

The long-term goal is not a blunt database rewrite.

It is a staged shift where:

* relational compatibility is preserved
* Heratio-native operational logic moves into PostgreSQL
* contextual and semantic authority shifts toward RiC
* the UI works against services, not directly against legacy tables

---

## 6. Service Layer Changes

A major requirement is to stop allowing the UI to depend too directly on legacy AtoM-shaped table assumptions.

### 6.1 Introduce service/repository contracts

Create explicit service contracts such as:

* DescriptionRepository
* AgentRepository
* ContextRelationRepository
* FunctionRepository
* PlaceRepository
* InstantiationRepository
* ProvenanceRepository

The UI should request entities and relationships through these services instead of hard-coded table logic.

### 6.2 Introduce dual renderers

Each major entity type should support:

* Heratio renderer
* RiC renderer
* shared identity and permissions

### 6.3 RiC relationship service

Build a strong central relationship service able to:

* fetch related entities
* fetch by relation type
* explain why items are related
* build graph summaries
* return timeline/context snippets
* support both page rendering and API responses

---

## 7. Editing Roadmap

RiC will not become equal if editing remains permanently flat and field-only.

### 7.1 Near-term editing improvements

Add relation-aware editing components for:

* linking agents to records with relationship types
* linking functions to records/series
* linking places to events or entities
* linking related records explicitly
* managing instantiation relationships
* qualifying relationships where needed

### 7.2 Preserve archival form comfort

Do not replace standard description forms immediately.

Instead:

* keep traditional forms
* enrich them with relation-aware controls
* validate relationships using semantic services where possible

### 7.3 Longer-term editing target

Move toward edit experiences where:

* traditional descriptive fields remain available
* contextual linking is first-class
* RiC-aware validation is built in
* the user can remain in archival language while the platform captures richer semantics underneath

---

## 8. API Roadmap

The platform should support dual-mode API outputs.

### 8.1 Recommended API model

For core entities support:

* traditional archival JSON
* RiC / JSON-LD / RDF-oriented outputs

### 8.2 Benefits

This allows:

* Heratio UI and RiC UI to share services
* external interoperability
* gradual architectural decoupling from the legacy schema
* future modules to rely on a stable entity/service contract

---

## 9. Governance and Validation Roadmap

RiC should not only appear in discovery. It should also improve governance.

### Recommended uses

* SHACL validation of entity relationships
* semantic integrity checking
* provenance capture
* contextual QA dashboards
* administrative quality assurance workflows
* authority linking review workflows

This makes RiC an operational governance benefit, not just a visualization layer.

---

## 10. Phased Delivery Plan

## Phase 1 - Make RiC visible everywhere

### Objectives

* expose RiC in normal Heratio use
* stop hiding it in advanced-only screens
* establish the dual-view model

### Deliverables

* global Heratio / RiC switch on key entity pages
* RiC side panel on description, agent, place, function, and instantiation pages
* common actions such as **View Context**, **View Network**, **View Hierarchy**
* richer search result cards with contextual links

### Outcome

RiC becomes visible and useful without disrupting current operations.

---

## Phase 2 - Introduce strong service boundaries

### Objectives

* reduce direct UI dependence on legacy tables
* prepare for mixed persistence underneath

### Deliverables

* repository/service contracts for major entity types
* centralized relationship service
* dual renderers for key entities
* standardized entity identity handling across views

### Outcome

Heratio can evolve underneath without breaking the user experience.

---

## Phase 3 - Introduce PostgreSQL Heratio-native domains

### Objectives

* move new operational domains into a stronger application data layer
* avoid forcing modern features into legacy schema patterns

### Deliverables

* PostgreSQL-backed workflow domain
* PostgreSQL-backed audit/event domain
* enrichment/validation/job metadata domains
* reporting and integration state domains

### Outcome

The platform gains a modern operational backbone while preserving AtoM compatibility.

---

## Phase 4 - Make RiC a first-class relationship engine

### Objectives

* move contextual authority toward the graph layer
* make RiC central to discovery and relationships

### Deliverables

* graph-backed related materials logic
* relationship-aware browse pages
* contextual graph summaries on all key entities
* semantic explanation services
* expanded JSON-LD/RDF outputs

### Outcome

RiC reaches equal footing in daily use.

---

## Phase 5 - Relation-aware editing and validation

### Objectives

* make RiC operational rather than decorative

### Deliverables

* relation-aware editing widgets
* relationship type and qualifier handling
* SHACL/integrity validation in admin and QA flows
* provenance capture improvements

### Outcome

RiC becomes part of how the platform is managed, not only viewed.

---

## Phase 6 - Decide on deeper model migration

### Objectives

* evaluate whether core description storage should remain primarily legacy-compatible or migrate further toward Heratio-native and RiC-aligned patterns

### Deliverables

* architecture review based on service abstraction success
* cost/benefit analysis of deeper AtoM schema de-emphasis
* optional compatibility view strategy for legacy data structures

### Outcome

The platform can decide on deeper evolution based on evidence, not theory.

---

## 11. Product and Brand Interpretation

### Heratio

Heratio remains:

* the operational platform
* the institutional product
* the main UI
* the deployment vehicle

### RiC in Heratio

RiC becomes:

* a first-class contextual mode
* a semantic engine
* a relationship layer
* a linked-data and interoperability capability

### OpenRiC

OpenRiC should support this as:

* the public RiC initiative
* documentation and framework layer
* architecture, mappings, reference tooling, and ecosystem brand

---

## 12. Summary Recommendation

The correct direction is:

* keep Heratio as the main platform
* raise RiC to equal footing inside Heratio
* preserve AtoM compatibility as a bridge, not a cage
* introduce PostgreSQL where Heratio-native capabilities need a stronger operational store
* let RiC become the increasingly authoritative contextual and relationship layer
* use OpenRiC to centralize the public RiC brand, ecosystem, and technical programme

### Final position

**Heratio in front. RiC beside it. OpenRiC above and beneath it.**

That gives continuity, architectural progress, and a credible path toward a richer RiC-native future without breaking what is already strong.

---

# OpenRiC Brand Centralisation Plan

## Purpose

This document sets out how **OpenRiC** should be positioned so that it strengthens the broader programme rather than competing with **Heratio**.

The goal is to centralise the RiC brand, technical narrative, and public ecosystem under **OpenRiC**, while keeping **Heratio** as the main operational platform and delivery vehicle.

---

## 1. Core Recommendation

Do not position OpenRiC as a second full production product competing with Heratio.

Instead position:

* **Heratio** as the operational platform
* **OpenRiC** as the public RiC-native initiative, framework, architecture, documentation, and ecosystem brand

### Recommended relationship

* **Heratio** = platform institutions use
* **OpenRiC** = RiC-native semantic initiative that powers, informs, documents, and extends Heratio’s RiC capabilities

---

## 2. Why this is the right approach

### 2.1 Heratio already has product strength

Heratio already carries:

* the operational UI
* archival workflows
* traditional archival views
* established user understanding
* deployment logic
* compatibility with current AtoM-shaped environments
* broader GLAM and records capability

### 2.2 OpenRiC has a different strategic strength

OpenRiC is strongest as:

* the public RiC-native narrative
* the semantic architecture programme
* the interoperability layer
* the reference implementation story
* the place for RiC docs, mappings, and demos
* the broader community-facing identity for RiC work

### 2.3 Avoid market confusion

If OpenRiC and Heratio are both presented as overlapping products, the result will be:

* unclear product story
* duplicated effort
* inconsistent roadmap signals
* confusion for buyers, developers, and standards communities

---

## 3. Recommended Brand Architecture

### 3.1 Simple public model

#### Heratio

**Operational GLAM / archival / DAM platform**

#### OpenRiC

**Open RiC-native semantic framework, documentation, and interoperability initiative**

### 3.2 Positioning statement

Recommended statement:

> OpenRiC is the open RiC-native semantic framework and interoperability initiative.
> Heratio is the operational platform that applies and extends those capabilities for institutional use.

### 3.3 Internal interpretation

OpenRiC should become:

* the semantic and standards-facing layer
* the public knowledge centre for RiC work
* the home of architecture, mapping, and graph-centred demos
* the home of shared RiC-related components that may power Heratio

Heratio should remain:

* the institutional deployment product
* the main application shell
* the operational records/archives interface
* the delivery mechanism for clients

---

## 4. What openric.org should become

OpenRiC should not read as a rival application homepage first.

It should become a centralised RiC programme site with four clear functions.

## A. Vision and explanation

Explain:

* why RiC matters
* why hierarchy alone is no longer enough
* how archival view and RiC view can coexist
* why linked archival context matters
* why OpenRiC exists in relation to Heratio

## B. Documentation centre

Host:

* implementation guides
* data model guidance
* RiC mappings
* JSON-LD and RDF examples
* SHACL examples
* Fuseki/triplestore guidance
* API conventions
* traditional-to-RiC view mappings

## C. Demo and sandbox layer

Show:

* sample records in Heratio-style view
* same records in RiC view
* graph traversal
* linked context examples
* semantic search concepts
* interoperability scenarios

## D. Ecosystem and code hub

Publish or link:

* open packages
* shared RiC components
* mapping libraries
* validation tooling
* transform examples
* integration connectors
* future reference implementation artifacts

---

## 5. How to reposition the current OpenRiC site

### Current issue

The current site reads primarily like a product landing page for a RiC explorer application.

That is useful, but too narrow if OpenRiC is going to centralise the RiC brand.

### Current strengths to preserve

Keep:

* Graph Explorer
* Documentation
* What's New
* SPARQL and linked-data orientation
* entity support visibility
* semantic search emphasis
* mention of standalone use where appropriate

### What to change

Shift the homepage from **tool-first** to **programme-first**.

The homepage should explain that OpenRiC is:

* a RiC-native initiative
* an ecosystem and framework
* a documentation and reference hub
* a semantic layer used by Heratio and usable independently where needed

---

## 6. Proposed openric.org information architecture

## Primary navigation

* About OpenRiC
* RiC in Practice
* Documentation
* Demo / Explorer
* Ecosystem
* News
* GitHub

### About OpenRiC

Cover:

* mission
* relationship to Heratio
* why RiC-native architecture matters
* key principles

### RiC in Practice

Show:

* traditional archival view vs RiC view
* use cases
* relationship-rich discovery
* contextual navigation
* semantic interoperability

### Documentation

Include:

* architecture
* installation
* RiC mappings
* SPARQL examples
* JSON-LD examples
* API guidance
* validation guidance

### Demo / Explorer

Keep the existing explorer concept here.
This should be a feature area, not the whole identity.

### Ecosystem

Include:

* packages
* libraries
* standards work
* deployment patterns
* integration patterns
* Heratio connection

### News

Track:

* releases
* features
* RiC support expansions
* implementation notes

---

## 7. Recommended homepage rewrite direction

### Recommended homepage framing

#### Hero

**OpenRiC**
Open RiC-native architecture, tools, and interoperability for next-generation archival systems.

Supporting line:
OpenRiC helps institutions and platforms move from isolated description toward relationship-rich archival context using RiC-O, linked data, graph services, and practical implementation patterns.

#### Secondary line

Used within Heratio and available as a standalone semantic and linked-data framework.

### Key homepage sections

#### 1. Why OpenRiC

* explain the problem
* explain the need for contextual archival discovery
* explain the role of RiC

#### 2. Two views, one archival reality

* traditional archival view
* RiC contextual view
* explain coexistence rather than replacement

#### 3. What OpenRiC provides

* graph explorer
* semantic APIs
* SPARQL access
* mappings and transforms
* validation and linked data support
* documentation

#### 4. OpenRiC and Heratio

A dedicated section explaining the relationship.

Suggested wording:

* Heratio is the operational archival platform
* OpenRiC provides RiC-native semantic architecture, graph capabilities, and interoperability approaches that can be integrated into Heratio or used independently

#### 5. Get started

* documentation
* GitHub
* demo/explorer
* releases/news

---

## 8. GitHub centralisation plan

OpenRiC GitHub should become the central public technical brand for the RiC programme.

### 8.1 Recommended GitHub role

Use GitHub under the OpenRiC brand for:

* core architecture docs
* public packages and libraries
* mapping artifacts
* validation tooling
* samples and examples
* demo/reference projects
* issue tracking around RiC-native evolution

### 8.2 Repository structure recommendation

#### A. `openric`

Main central repository for:

* overview docs
* architecture
* roadmap
* key concepts
* integration patterns
* examples

#### B. `openric-docs`

Optional if documentation grows large.
Use for:

* docs site source
* implementation guides
* standards mappings
* tutorials

#### C. `openric-explorer`

Explorer-specific code if it should stand alone from the documentation hub.

#### D. `openric-mappings`

Mappings between:

* ISAD(G)
* ISAAR-CPF
* EAD/EAC
* AtoM-shaped structures
* RiC-CM / RiC-O

#### E. `openric-validation`

SHACL, semantic validation rules, test cases, and examples.

#### F. `openric-examples`

Sample datasets, JSON-LD examples, SPARQL examples, and rendering examples.

### 8.3 Relationship to Heratio repos

Heratio repositories should continue to exist under their own identity.

But where Heratio includes RiC-specific packages or shared semantic components, you should decide whether they belong:

* in Heratio because they are product-specific
* or in OpenRiC because they are generic RiC ecosystem components

### Rule of thumb

Move to OpenRiC brand if the component is:

* reusable outside Heratio
* conceptually RiC-centric
* standards-oriented
* generic enough to serve multiple implementations

Keep in Heratio if the component is:

* tightly coupled to Heratio workflows or UI
* deployment-specific
* product-specific rather than framework-like

---

## 9. Suggested messaging rules

### When talking to institutions

Lead with **Heratio**.
Then explain that it includes or is powered by **OpenRiC capabilities**.

### When talking to standards communities, researchers, and developers

Lead with **OpenRiC**.
Then show **Heratio** as the operational implementation path.

### When talking publicly on the web

Avoid implying that Heratio and OpenRiC are rival systems.
Always present them as complementary.

---

## 10. Recommended next steps

### Immediate

* reposition the OpenRiC homepage copy
* add a clear Heratio relationship section
* shift the explorer from identity to feature area
* align GitHub repo descriptions under the new brand model

### Next

* publish architecture and roadmap docs
* separate reusable RiC-centric components from Heratio-specific ones
* use OpenRiC as the home for mappings, validation, examples, and linked-data guidance

### Longer-term

* make OpenRiC the trusted public source for the RiC-native direction
* make Heratio the trusted operational implementation of that direction

---

## 11. Summary Recommendation

Do not abandon OpenRiC.

Do not let it compete with Heratio either.

The correct move is to make OpenRiC the **central RiC-native brand, framework, and ecosystem layer**, while Heratio remains the **main platform and product**.

### Final position

**OpenRiC centralises the RiC story. Heratio delivers the operational platform.**

That gives:

* a clearer public narrative
* less product confusion
* stronger standards credibility
* less duplicated effort
* a better long-term architectural story
