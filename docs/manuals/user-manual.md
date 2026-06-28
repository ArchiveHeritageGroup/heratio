# Heratio — User Manual

> The task-oriented manual for everyone who **uses** Heratio: archivists,
> cataloguers, curators, librarians, records managers, researchers, reading-room
> staff, and administrators.
>
> This is the navigational spine. Each module's step-by-step guide lives in the
> in-app **Help Center** (`docs/help/*.md` → searchable `help_article` records);
> this manual orients you, walks the common end-to-end workflows, and links the
> right guide for each task by domain. Developers/operators: see the
> **[Technical Manual](technical-manual.md)**.
>
> _Last reviewed: 2026-06-28. Coverage: 238 module guides._

## Table of contents
- [How to use this manual](#how-to-use-this-manual)
- [Roles](#roles)
- [Common end-to-end workflows](#common-end-to-end-workflows)
- Domain reference:
  1. [Getting started & platform](#1-getting-started--platform)
  2. [Describing & cataloguing](#2-describing--cataloguing)
  3. [Sector cataloguing](#3-sector-cataloguing)
  4. [Digital objects & preservation](#4-digital-objects--preservation)
  5. [Search & discovery](#5-search--discovery)
  6. [Research & RDM](#6-research--rdm)
  7. [AI tools](#7-ai-tools)
  8. [Rights, privacy & compliance](#8-rights-privacy--compliance)
  9. [Interoperability & open data](#9-interoperability--open-data)
  10. [Commerce & engagement](#10-commerce--engagement)
  11. [Reporting & operations](#11-reporting--operations)

---

## How to use this manual

- **In-app contextual help.** On most admin screens the header **Help** button
  opens that screen's own guide (wired via the contextual-help map). If you're not
  sure where to start, that button is the fastest route.
- **Global Help Center.** Search every guide from the Help Center; the tables
  below link the same articles by domain.
- **This manual** gives you the *workflows that span modules* and a map of where
  each task lives. Follow a workflow top-to-bottom, or jump to a domain.
- Links below point at `docs/help/<slug>.md` — the same content the Help Center
  serves.

## Roles

Heratio uses role-based access (see your administrator):

- **Administrator** — full access incl. destructive/operational actions (backup &
  restore, records destruction, user & ACL management, system settings).
- **Editor** — create/update/delete catalogue content; most "admin/*" management
  screens; cannot perform administrator-only operational actions.
- **Contributor / reading-room / researcher** — scoped create or request actions
  (e.g. submit descriptions, request access).
- **Guest (public)** — sees only **published** records; drafts and restricted
  material are never shown.

---

## Common end-to-end workflows

These cross several modules — the spine of day-to-day work.

### A. Describe → attach → publish an archival record
1. **Create the description** — Information Objects (archival description).
   → [information-object-manage](../help/information-object-manage-user-guide.md)
2. **Link authorities** — creator/people, holding repository, subjects/places.
   → [actor-manage](../help/actor-manage-user-guide.md) ·
   [repository-manage](../help/repository-manage-user-guide.md) ·
   [term-taxonomy](../help/term-taxonomy-user-guide.md)
3. **Attach digital objects** — upload masters; derivatives generate automatically.
   → [dam](../help/dam-user-guide.md) ·
   [media-processing](../help/media-processing-user-guide.md)
4. **Review & publish** — move from Draft to Published via the workflow / publish
   gates. Only published records are visible to the public.
   → [workflow](../help/workflow-user-guide.md) ·
   [publish-gates](../help/publish-gates-user-guide.md) ·
   [request-publish](../help/request-publish-user-guide.md)

### B. Bulk bring-in (accession → ingest)
Accession the acquisition, then bulk-import descriptions/objects with field
mapping, preview, and job tracking.
→ [accession-manage](../help/accession-manage-user-guide.md) ·
[ingest](../help/ingest-user-guide.md) ·
[data-migration](../help/data-migration-user-guide.md) ·
[ftp-upload](../help/ftp-upload-user-guide.md)

### C. Researcher access request → reading room
A researcher registers, requests access to restricted material, an approver
reviews, and a reading-room booking follows.
→ [researcher](../help/researcher-user-guide.md) ·
[access-request](../help/access-request-user-guide.md) ·
[research](../help/research-user-guide.md)

### D. Public discovery
The public browses/searches only published records, can view IIIF images, cite,
and (where enabled) buy reproductions.
→ [advanced-search](../help/advanced-search-user-guide.md) ·
[discovery](../help/discovery-user-guide.md) ·
[glam-browse](../help/glam-browse-user-guide.md) ·
[cite-this-record](../help/cite-this-record-user-guide.md)

---

## 1. Getting started & platform

| Task | Guide |
|---|---|
| System settings | [ahg-settings](../help/ahg-settings-user-guide.md) |
| Users & profiles | [user-manage](../help/user-manage-user-guide.md) |
| Roles & permissions (ACL) | [acl](../help/acl-user-guide.md) |
| Navigation menus | [menu-manage](../help/menu-manage-user-guide.md) |
| Controlled dropdowns | [dropdown-manage](../help/dropdown-manage-user-guide.md) |
| Static & landing pages | [static-page](../help/static-page-user-guide.md) · [landing-page](../help/landing-page-user-guide.md) |
| Articles / news | [articles-authoring](../help/articles-authoring-user-guide.md) |
| Form builder | [forms-builder](../help/forms-builder-user-guide.md) |
| Custom fields | [custom-fields](../help/custom-fields-user-guide.md) |
| Multi-tenant setup | [multi-tenant](../help/multi-tenant-user-guide.md) |
| UI translation / locales | [translation](../help/translation-user-guide.md) |
| Theme | [theme-b5](../help/theme-b5-user-guide.md) |
| Using the Help Center | [help](../help/help-user-guide.md) · [contextual-help](../help/contextual-help-user-guide.md) |

## 2. Describing & cataloguing

| Task | Guide |
|---|---|
| Archival descriptions | [information-object-manage](../help/information-object-manage-user-guide.md) |
| People & organisations (actors) | [actor-manage](../help/actor-manage-user-guide.md) · [people-and-organisations](../help/people-and-organisations-user-guide.md) |
| Repositories (ISDIAH) | [repository-manage](../help/repository-manage-user-guide.md) |
| Functions (ISDF) | [function-manage](../help/function-manage-user-guide.md) |
| Terms & taxonomies (SKOS) | [term-taxonomy](../help/term-taxonomy-user-guide.md) |
| Accessions | [accession-manage](../help/accession-manage-user-guide.md) |
| Authority resolution | [authority-resolution](../help/authority-resolution-user-guide.md) |
| Version history & restore | [version-control](../help/version-control-user-guide.md) |
| Descriptive standards | [dacs-manage](../help/dacs-manage-user-guide.md) · [rad-manage](../help/rad-manage-user-guide.md) · [dc-manage](../help/dc-manage-user-guide.md) · [mods-manage](../help/mods-manage-user-guide.md) |
| Public display / GLAM browse | [display](../help/display-user-guide.md) · [glam-browse](../help/glam-browse-user-guide.md) |

## 3. Sector cataloguing

| Sector | Guide |
|---|---|
| Library (cataloguing, serials, acquisitions, KBART) | [library](../help/library-user-guide.md) · [library-serials](../help/library-serials-user-guide.md) · [library-acquisitions](../help/library-acquisitions-user-guide.md) · [kbart-remote](../help/kbart-remote-user-guide.md) |
| Museum (Spectrum / CCO) | [museum](../help/museum-user-guide.md) · [spectrum](../help/spectrum-user-guide.md) |
| Gallery (artworks, loans, valuations) | [gallery](../help/gallery-user-guide.md) |
| Heritage sites & accounting | [heritage-manage](../help/heritage-manage-user-guide.md) · [heritage-sites](../help/heritage-sites-user-guide.md) · [heritage-accounting](../help/heritage-accounting-user-guide.md) |
| Records management (retention, disposal) | [records-manage](../help/records-manage-user-guide.md) |
| Physical storage | [storage-manage](../help/storage-manage-user-guide.md) |
| Loans | [loan](../help/loan-user-guide.md) |
| Exhibitions & wayfinding | [exhibition](../help/exhibition-user-guide.md) · [exhibition-wayfinding](../help/exhibition-wayfinding-user-guide.md) |
| Condition reporting | [condition](../help/condition-user-guide.md) |
| Vendors | [vendor](../help/vendor-user-guide.md) |
| IPSAS heritage assets | [ipsas](../help/ipsas-user-guide.md) |
| Jurisdiction compliance | [nmmz](../help/nmmz-user-guide.md) · [naz](../help/naz-user-guide.md) · [narssa](../help/narssa-user-guide.md) · [cdpa](../help/cdpa-user-guide.md) |
| Security classification | [security-classification](../help/security-classification-user-guide.md) |

## 4. Digital objects & preservation

| Task | Guide |
|---|---|
| Digital asset management | [dam](../help/dam-user-guide.md) |
| Image derivatives & watermarking | [media-processing](../help/media-processing-user-guide.md) |
| Audio/video streaming | [media-streaming](../help/media-streaming-user-guide.md) · [audio-player](../help/audio-player-user-guide.md) |
| Preservation (PREMIS, maturity) | [preservation](../help/preservation-user-guide.md) · [preservation-maturity](../help/preservation-maturity-user-guide.md) |
| Integrity / fixity | [integrity](../help/integrity-user-guide.md) · [integrity-assurance](../help/integrity-assurance-user-guide.md) |
| 3D models & viewer | [3d-model](../help/3d-model-user-guide.md) · [3d-model-viewer](../help/3d-model-viewer-user-guide.md) |
| Image AR animation | [image-ar](../help/image-ar-user-guide.md) |
| PDF tools / merge | [pdf-tools](../help/pdf-tools-user-guide.md) · [pdf-merge](../help/pdf-merge-user-guide.md) |
| Scanning & capture | [scan](../help/scan-user-guide.md) · [scanner-capture](../help/scanner-capture-user-guide.md) · [capture-queue](../help/capture-queue-user-guide.md) |
| Bulk / resumable upload | [ftp-upload](../help/ftp-upload-user-guide.md) · [resumable-upload](../help/resumable-upload-user-guide.md) |
| Metadata extraction | [metadata-extraction](../help/metadata-extraction-user-guide.md) · [embedded-image-metadata-full](../help/embedded-image-metadata-full-user-guide.md) |
| IIIF (Mirador / OpenSeadragon) | [iiif-collection](../help/iiif-collection-user-guide.md) · [iiif-integration](../help/iiif-integration-user-guide.md) · [mirador](../help/mirador-user-guide.md) · [openseadragon](../help/openseadragon-user-guide.md) |
| Content authenticity (C2PA) | [content-credentials-authenticity](../help/content-credentials-authenticity-user-guide.md) · [authenticity-report](../help/authenticity-report-user-guide.md) |

## 5. Search & discovery

| Task | Guide |
|---|---|
| Search (advanced / fuzzy) | [advanced-search](../help/advanced-search-user-guide.md) · [search](../help/search-user-guide.md) · [fuzzy-search](../help/fuzzy-search-user-guide.md) |
| Semantic search | [semantic-search](../help/semantic-search-user-guide.md) |
| Discovery & explore hubs | [discovery](../help/discovery-user-guide.md) · [explore-the-collection](../help/explore-the-collection-user-guide.md) · [explore-hub](../help/explore-hub-user-guide.md) |
| Browse by genre/place/theme | [browse-by-genre](../help/browse-by-genre-user-guide.md) · [browse-by-place](../help/browse-by-place-user-guide.md) · [explore-by-theme](../help/explore-by-theme-user-guide.md) |
| Spatial / GIS search | [gis](../help/gis-user-guide.md) |
| Knowledge / graph explorer | [knowledge-graph](../help/knowledge-graph-user-guide.md) · [graph-explorer](../help/graph-explorer-user-guide.md) |

## 6. Research & RDM

| Task | Guide |
|---|---|
| Research portal | [research](../help/research-user-guide.md) |
| Researcher registration & quotas | [researcher](../help/researcher-user-guide.md) · [researcher-manage](../help/researcher-manage-user-guide.md) · [researcher-quotas](../help/researcher-quotas-user-guide.md) |
| Research workspaces & journals | [research-workspace-files](../help/research-workspace-files-user-guide.md) · [research-journal-builder](../help/research-journal-builder-user-guide.md) |
| Research-data management (RDM) | [research-knowledge-platform](../help/research-knowledge-platform-user-guide.md) |
| Annotations (W3C) | [annotations](../help/annotations-user-guide.md) |
| Favourites & collections | [favorites](../help/favorites-user-guide.md) |
| Access requests | [access-request](../help/access-request-user-guide.md) · [access-requests](../help/access-requests-user-guide.md) |
| Request to publish | [request-publish](../help/request-publish-user-guide.md) |

## 7. AI tools

| Task | Guide |
|---|---|
| Collection chatbot | [ai-chatbot](../help/ai-chatbot-user-guide.md) · [ask-the-collection](../help/ask-the-collection-user-guide.md) |
| AI services (NER, summarise, translate) | [ai-services](../help/ai-services-user-guide.md) · [ner](../help/ner-user-guide.md) · [ai-tools](../help/ai-tools-user-guide.md) |
| AI condition assessment | [ai-condition](../help/ai-condition-user-guide.md) |
| AI governance & transparency | [ai-governance](../help/ai-governance-user-guide.md) · [ai-usage-transparency](../help/ai-usage-transparency.md) |
| Inference provenance | [ai-inference-provenance](../help/ai-inference-provenance-user-guide.md) · [provenance-ai](../help/provenance-ai-user-guide.md) · [inference-provenance-explorer](../help/inference-provenance-explorer-user-guide.md) |

## 8. Rights, privacy & compliance

| Task | Guide |
|---|---|
| Rights statements & extended rights | [rights-management](../help/rights-management-user-guide.md) · [extended-rights](../help/extended-rights-user-guide.md) |
| Embargoes | [embargo](../help/embargo-user-guide.md) |
| Rights holders | [rights-holder-manage](../help/rights-holder-manage-user-guide.md) |
| Privacy / POPIA | [privacy](../help/privacy-user-guide.md) · [privacy-compliance](../help/privacy-compliance-user-guide.md) |
| Indigenous cultural IP (ICIP) | [icip](../help/icip-user-guide.md) |
| Security clearance | [security-clearance](../help/security-clearance-user-guide.md) · [security-compliance](../help/security-compliance-user-guide.md) |
| Donors & agreements | [donor-manage](../help/donor-manage-user-guide.md) · [donor-agreement](../help/donor-agreement-user-guide.md) |
| Provenance / chain of custody | [provenance](../help/provenance-user-guide.md) |
| Time-limited share links | [share-link](../help/share-link-user-guide.md) |
| Audit trail | [audit-trail](../help/audit-trail-user-guide.md) |
| Approvals & workflow | [workflow](../help/workflow-user-guide.md) · [publish-gates](../help/publish-gates-user-guide.md) |
| Encryption & password security | [encryption](../help/encryption-user-guide.md) · [password-security](../help/password-security-user-guide.md) |

## 9. Interoperability & open data

| Task | Guide |
|---|---|
| OAI-PMH | [oai](../help/oai-user-guide.md) |
| Z39.50 / SRU | [z3950](../help/z3950-user-guide.md) |
| ResourceSync | [open-data-resourcesync](../help/open-data-resourcesync-user-guide.md) |
| Federation / peer harvest | [federation](../help/federation-user-guide.md) |
| SharePoint (M365) | [sharepoint](../help/sharepoint-user-guide.md) |
| REST API / GraphQL | [api](../help/api-user-guide.md) · [graphql](../help/graphql-user-guide.md) |
| Metadata export (RDF) | [metadata-export](../help/metadata-export-user-guide.md) |
| BIBFRAME / FRBR | [bibframe](../help/bibframe-user-guide.md) · [frbr](../help/frbr-user-guide.md) |
| Records in Contexts (RiC) | [ric](../help/ric-user-guide.md) |
| DOI (DataCite) | [doi-manage](../help/doi-manage-user-guide.md) |
| ORCID | [orcid-integration](../help/orcid-integration-user-guide.md) |
| Open data (DCAT / schema.org / linked data) | [open-data-dcat-catalog](../help/open-data-dcat-catalog-user-guide.md) · [open-data-schema-org-dataset](../help/open-data-schema-org-dataset-user-guide.md) · [open-data-linked-data-crawl-sitemap](../help/open-data-linked-data-crawl-sitemap-user-guide.md) |

## 10. Commerce & engagement

| Task | Guide |
|---|---|
| Shopping cart & e-commerce | [cart](../help/cart-user-guide.md) · [cart-ecommerce](../help/cart-ecommerce-user-guide.md) |
| Marketplace | [marketplace](../help/marketplace-user-guide.md) |
| Feedback | [feedback](../help/feedback-user-guide.md) |
| Forms | [forms](../help/forms-user-guide.md) |

## 11. Reporting & operations

| Task | Guide |
|---|---|
| Reports & dashboards | [reports](../help/reports-user-guide.md) · [reports-dashboard](../help/reports-dashboard-user-guide.md) · [report-builder](../help/report-builder-user-guide.md) |
| Usage statistics | [statistics](../help/statistics-user-guide.md) · [counter-sushi](../help/counter-sushi-user-guide.md) |
| Backup & restore (admin only) | [backup](../help/backup-user-guide.md) · [backup-restore](../help/backup-restore-user-guide.md) |
| Background jobs | [jobs-manage](../help/jobs-manage-user-guide.md) |
| Data migration & export | [data-migration](../help/data-migration-user-guide.md) · [export-data](../help/export-data-user-guide.md) · [portable-export](../help/portable-export-user-guide.md) |
| Deduplication | [dedupe](../help/dedupe-user-guide.md) · [duplicate-detection](../help/duplicate-detection-user-guide.md) |
| Trust & transparency | [trust-dashboard](../help/trust-dashboard-user-guide.md) · [transparency-report](../help/transparency-report-user-guide.md) |

---

> **Not finding a module here?** The Help Center holds the complete set of 238
> guides — this manual links the most common entry points per domain. Every admin
> screen also surfaces its own guide via the header **Help** button.
>
> Maintenance: keep this spine in sync with `docs/help/` as modules are added;
> the coverage inventory is `docs/reference/heratio-docs-coverage-matrix-2026-06-28.md`.
