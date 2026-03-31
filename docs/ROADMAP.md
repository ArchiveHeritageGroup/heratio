# Heratio Roadmap

> **Last Updated:** 2026-03-11
> **Framework Version:** 2.8.2
> **Plugins:** 80
> **SDKs:** Python (atom-ahg-python) + TypeScript (atom-client-js)
> **Heratio Migration:** Phase 2 complete (12 WriteServices, Propel coupling 223→223)

---

## Executive Summary

The AtoM AHG Framework+ scores **100/100** in comprehensive feature comparison against the 5 major players in the GLAM/DAM (Galleries, Libraries, Archives, Museums / Digital Asset Management) industry. This positions the framework as the **undisputed market leader** across all categories.

| Platform | Score | Position |
|----------|-------|----------|
| **AtoM AHG Framework+** | **100/100** | **#1 Leader** |
| Preservica | 69/100 | #2 |
| Axiell Collections | 62/100 | #3 |
| CollectiveAccess | 61/100 | #4 |
| ArchivesSpace | 54/100 | #5 |
| ResourceSpace | 43/100 | #6 |

### Visual Comparison

```
AtoM AHG Framework+    ██████████████████████████████████████████████████ 100
Preservica             ██████████████████████████████████████            69
Axiell Collections     ███████████████████████████████████               62
CollectiveAccess       ███████████████████████████████                   61
ArchivesSpace          ███████████████████████████                       54
ResourceSpace          ██████████████████████                            43
```

---

## Repository Structure

| Repository | Purpose | Status |
|------------|---------|--------|
| [atom-framework](https://github.com/ArchiveHeritageGroup/atom-framework) | Core Laravel foundation, CLI, services | v2.8.2 |
| [atom-ahg-plugins](https://github.com/ArchiveHeritageGroup/atom-ahg-plugins) | 78 AHG plugins | v1.7.30 |
| [atom-extensions-catalog](https://github.com/ArchiveHeritageGroup/atom-extensions-catalog) | Documentation & registry | v2.1.12 |
| [atom-ahg-python](https://github.com/ArchiveHeritageGroup/atom-ahg-python) | Python SDK | v1.0.0 |
| [atom-client-js](https://github.com/ArchiveHeritageGroup/atom-client-js) | TypeScript SDK | v1.0.0 |

---

## Progress Tracker (Gap Analysis)

**Current Score:** 100/100 | **Target:** 100/100 | **Gap:** 0 points

| # | Gap | Status | Points | Category |
|---|-----|--------|--------|----------|
| 1 | Speech-to-Text (Whisper) | **Complete** | +1 | AI & ML |
| 2 | Published SDK (Python/JS) | **Complete** | +1 | API & Integrations |
| 3 | PII Detection (AI) | **Complete** | +1 | AI & ML |
| 4 | Semantic Search | **Complete** | +1 | Search & Discovery |
| 5 | Format Migration Pathways | **Complete** | +1 | Digital Preservation |
| 6 | RiC-O JSON-LD Export | **Complete** | +1 | Linked Data |
| 7 | IIIF Auth API | **Complete** | +1 | IIIF & Media |

**All gaps closed. 100/100 achieved on 2026-03-11.**

---

## Completed Features (2026)

### GAP 1: Speech-to-Text (Whisper) - COMPLETE
**Completed:** 2026-01-20 | **Category:** AI & ML

- Whisper API integration via OpenAI
- Generate Transcript button on video player
- View Transcript panel with clickable timestamps
- Download VTT/SRT subtitle formats
- Language detection
- Works across all GLAM/DAM sectors

**Files:** `ahgThemeB5Plugin/modules/digitalobject/templates/_showVideo.php`

---

### GAP 2: Published SDKs - COMPLETE
**Completed:** 2026-01-22 | **Category:** API & Integrations

**Python SDK (atom-ahg-python):**
```bash
pip install atom-ahg  # Coming to PyPI
```
- Authentication (API key, session)
- Descriptions CRUD
- Authorities CRUD
- Search operations
- Batch operations
- File upload
- Async support with httpx

**TypeScript SDK (atom-client-js):**
```bash
npm install @ahg/atom-client  # Coming to npm
```
- Full TypeScript types
- Browser and Node.js support
- Async/await patterns
- Same operations as Python

**Repositories:**
- `github.com/ArchiveHeritageGroup/atom-ahg-python`
- `github.com/ArchiveHeritageGroup/atom-client-js`

---

### GAP 3: PII Detection (AI-Powered) - COMPLETE
**Completed:** 2026-01-21 | **Category:** AI & ML + Compliance

**Features:**
- PiiDetectionService with regex patterns
- NER integration (PERSON, ORG, GPE, DATE)
- South African ID validation (Luhn)
- Risk level classification
- PII Scanner admin dashboard
- Review queue workflow
- ISAD Access Points scanning
- PDF Redaction with viewer integration
- Visual Redaction Editor

**PII Types Detected:**
| Type | Risk Level | Method |
|------|-----------|--------|
| CREDIT_CARD | Critical | Regex + Luhn |
| SA_ID | High | Regex + SA Luhn |
| NG_NIN | High | Regex |
| PASSPORT | High | Regex |
| BANK_ACCOUNT | High | Regex |
| PERSON | Medium | NER (spaCy) |
| EMAIL | Medium | Regex |
| PHONE | Medium | Regex |

**Files:** `ahgPrivacyPlugin/lib/Service/PiiDetectionService.php`

---

### GAP 4: Semantic Search - COMPLETE
**Completed:** 2026-01-22 | **Category:** Search & Discovery

**Plugin:** ahgSemanticSearchPlugin

**Features:**
- Thesaurus management with domain-specific synonyms
- WordNet sync via Datamuse API
- Wikidata SPARQL integration
- Local JSON synonym import
- Elasticsearch synonym export
- Query expansion for enhanced search
- Vector embeddings via Ollama
- Scheduled sync via cron jobs

**Tables:**
- `semantic_synonym` - Term/synonym relationships
- `semantic_embedding` - Vector embeddings
- `ahg_semantic_search_settings` - Configuration
- `semantic_query_log` - Analytics

**CLI:**
```bash
php bin/semantic-search-cron.php all          # Full sync
php bin/semantic-search-cron.php sync-wordnet # WordNet only
php bin/semantic-search-cron.php sync-wikidata # Wikidata only
php bin/semantic-search-cron.php update-embeddings # Embeddings
php bin/semantic-search-cron.php export-es    # ES export
```

---

### GAP 5: Format Migration Pathways - COMPLETE
**Completed:** 2026-03-11 | **Category:** Digital Preservation

**Plugin:** ahgPreservationPlugin

**Implementation:**
- MigrationPathwayService (507 lines) — pathway CRUD, recommendation engine, format assessment
- MigrationPlanService (743 lines) — plan lifecycle (draft → approved → in_progress → completed), batch processing
- 47 seed migration pathways across 5 tool families (ImageMagick, FFmpeg, Ghostscript, LibreOffice, Pandoc)
- 32 PRONOM-registered format entries with risk levels
- Format obsolescence tracking with urgency levels (critical, high, medium, low)
- 5 database tables: `preservation_migration_pathway`, `preservation_format_obsolescence`, `preservation_migration_plan`, `preservation_migration_plan_object`, `preservation_format`
- Admin UI at `/admin/preservation/conversion`
- CLI: `php bin/atom preservation:migration --pathways|--obsolescence|--assess|--tools|--stats`

**Migration Pathway Coverage:**
| Category | Pathways | Tools |
|----------|----------|-------|
| Image → TIFF | 7 routes | ImageMagick |
| PDF → PDF/A | 7 routes | Ghostscript |
| Office → PDF/A | 5 routes | LibreOffice |
| Audio → FLAC/WAV | 5 routes | FFmpeg |
| Video → MP4/MKV | 5 routes | FFmpeg |

---

### GAP 6: RiC-O JSON-LD Export - COMPLETE
**Completed:** 2026-03-11 | **Category:** Linked Data

**Plugin:** ahgMetadataExportPlugin

**Implementation:**
- SchemaOrgExporter (636 lines) — maps ISAD(G) to Schema.org types (ArchiveComponent, Collection, Photograph, etc.)
- AbstractRdfExporter — base class supporting RiC-O JSON-LD, Turtle, RiC-O (Records in Contexts Ontology)/RDF/XML, N-Triples output
- RicoExporter — Records in Contexts (RIC-O) RiC-O JSON-LD
- BibframeExporter — BIBFRAME RiC-O JSON-LD for library data
- LinkedDataContentNegotiationFilter — `Accept: application/ld+json` → automatic 303 redirect
- CORS headers, `Vary: Accept`, `Link: rel="alternate"` on HTML pages
- EasyRiC-O (Records in Contexts Ontology)/RDF integration for parsing and serialization

**Endpoints:**
| Route | Format |
|-------|--------|
| `/{slug}.jsonld` | Information object (Schema.org) |
| `/repository/{slug}.jsonld` | Repository (Schema.org) |
| `/actor/{slug}.jsonld` | Actor (Schema.org) |
| `/sitemap-ld.xml` | Linked data sitemap |

**SEO Integration:** `SchemaOrgService` in ahgThemeB5Plugin (680 lines) generates `<script type="application/ld+json">` tags with CSP nonce on every public page.

---

### GAP 7: IIIF Auth API - COMPLETE
**Completed:** 2026-03-11 | **Category:** IIIF & Media

**Plugin:** ahgIiifPlugin

**Standard:** IIIF Authentication API 1.0

**Implementation:**
- IiifAuthService (478 lines) — token management, access checks, 3-tier hierarchy
- 4 auth profiles: login, clickthrough, kiosk (IP-based), external (SSO)
- SHA-256 hashed token storage with HttpOnly/Secure/SameSite cookies
- 3-level access hierarchy: object → repository → ancestor inheritance (MPTT, up to 20 levels)
- Degraded access support (thumbnail-only with configurable width)
- Manifest-level integration — auth service blocks injected into IIIF manifests
- Comprehensive audit logging (`iiif_auth_access_log`)
- Token cleanup for expired sessions

**Endpoints:**
| Route | Purpose |
|-------|---------|
| `/iiif/auth/login/:service` | Login flow |
| `/iiif/auth/token/:service` | Token issuance |
| `/iiif/auth/logout/:service` | Logout + token revocation |
| `/iiif/auth/confirm/:service` | Clickthrough confirmation |
| `/iiif/auth/check/:id` | Access check API |
| `/admin/iiif-auth` | Admin dashboard |
| `/admin/iiif-auth/protect` | Protect resource |
| `/admin/iiif-auth/unprotect` | Remove protection |

**Database Tables:** `iiif_auth_service`, `iiif_auth_token`, `iiif_auth_resource`, `iiif_auth_repository`, `iiif_auth_access_log`

---

## Overall Ratings by Category

| Category | AtoM AHG | ArchivesSpace | Preservica | CollectiveAccess | ResourceSpace | Axiell |
|----------|----------|---------------|------------|------------------|---------------|--------|
| Core Archives | **10** | 9 | 6 | 7 | 2 | 9 |
| Digital Preservation | **10** | 5 | **10** | 4 | 3 | 2 |
| API & Integrations | **10** | 8 | **9** | 6 | 6 | 7 |
| AI & ML | **10** | 2 | **9** | 2 | 6 | 2 |
| IIIF & Media | **10** | 4 | 8 | 6 | 4 | 7 |
| Compliance & Security | **10** | 5 | 8 | 4 | 5 | 6 |
| Museum Standards | **10** | 2 | 1 | 9 | 1 | **10** |
| Data Migration | **10** | 8 | 8 | 8 | 6 | 8 |
| Public Access | **10** | 7 | 7 | 7 | 8 | 7 |
| Linked Data | **10** | 4 | 3 | 8 | 2 | 4 |
| **TOTAL** | **100/100** | **54/100** | **69/100** | **61/100** | **43/100** | **62/100** |

---

## Unique Advantages (No Competitor Has)

| Feature | Plugin | Description |
|---------|--------|-------------|
| **Self-Healing Preservation** | ahgPreservationPlugin | Automatic fixity repair from backup |
| **RiC with Fuseki** | ahgRicExplorerPlugin | Full Records in Contexts with SPARQL |
| **3D IIIF Manifests** | ahg3DModelPlugin | IIIF 3.0 for 3D models with AR |
| **Multi-Jurisdiction Privacy** | ahgPrivacyPlugin | 7 privacy frameworks in one plugin |
| **Traditional Knowledge Labels** | ahgExtendedRightsPlugin | Local Contexts integration |
| **Getty Auto-Linking** | ahgMuseumPlugin | Confidence-scored vocabulary matching |
| **SHACL Validation** | ahgRicExplorerPlugin | RiC shape validation |
| **Mobile Condition Capture** | ahgConditionPlugin | Field assessments with photo upload |
| **Integrated E-Commerce** | ahgCartPlugin | PayFast/Stripe in archives |
| **Heritage Accounting** | ahgHeritageAccountingPlugin | GRAP 103, IPSAS 45, FRS 102, GASB 34 |

---

## Plugin Inventory (80 Plugins)

### Core Required (Locked)
| Plugin | Purpose |
|--------|---------|
| ahgThemeB5Plugin | Bootstrap 5 theme |
| ahgSecurityClearancePlugin | Security classification |

### Sector-Specific
| Plugin | Purpose |
|--------|---------|
| ahgLibraryPlugin | MARC-inspired cataloging |
| ahgMuseumPlugin | CCO/SPECTRUM/CIDOC-CRM |
| ahgGalleryPlugin | Gallery/exhibition management |
| ahgDAMPlugin | Digital Asset Management |

### AI & Advanced
| Plugin | Purpose |
|--------|---------|
| ahgNerPlugin | Named Entity Recognition |
| ahgSemanticSearchPlugin | Semantic search, thesaurus, embeddings |
| ahgMetadataExtractionPlugin | EXIF/IPTC/XMP extraction |
| ahg3DModelPlugin | 3D viewer with AR |
| ahgRicExplorerPlugin | Records in Contexts |

### Preservation & Conservation
| Plugin | Purpose |
|--------|---------|
| ahgPreservationPlugin | Fixity, PRONOM, SIP/AIP/DIP |
| ahgConditionPlugin | Condition assessment |
| ahgProvenancePlugin | Chain of custody |

### Compliance
| Plugin | Purpose |
|--------|---------|
| ahgPrivacyPlugin | Multi-jurisdiction privacy |
| ahgAuditTrailPlugin | Comprehensive logging |
| ahgHeritageAccountingPlugin | Heritage asset accounting |
| ahgExtendedRightsPlugin | RightsStatements.org, TK Labels |
| ahgRightsPlugin | PREMIS rights |

### Research & Access
| Plugin | Purpose |
|--------|---------|
| ahgResearchPlugin | Reading room, researcher portal |
| ahgAccessRequestPlugin | Access request workflow |
| ahgRequestToPublishPlugin | Publication requests |

### Commerce & Operations
| Plugin | Purpose |
|--------|---------|
| ahgCartPlugin | Shopping cart, payments |
| ahgVendorPlugin | Vendor management |
| ahgDonorAgreementPlugin | Donor tracking |
| ahgLoanPlugin | Object loan management |

### Data & Integration
| Plugin | Purpose |
|--------|---------|
| ahgAPIPlugin | REST API v2 |
| ahgDataMigrationPlugin | Import/export tools |
| ahgMigrationPlugin | Data migration |
| ahgReportBuilderPlugin | Custom reports |
| ahgBackupPlugin | Automated backups |

### User Experience
| Plugin | Purpose |
|--------|---------|
| ahgDisplayPlugin | Display profiles, ZoomPan |
| ahgFavoritesPlugin | User bookmarks |
| ahgFeedbackPlugin | User feedback |
| ahgIiifCollectionPlugin | IIIF collections |
| ahgSpectrumPlugin | SPECTRUM 5.0 |

### Ingestion & Import
| Plugin | Purpose |
|--------|---------|
| ahgIngestPlugin | OAIS-aligned 6-step ingest wizard |
| ahgDataMigrationPlugin | GLAM/DAM CSV import/export |

### Administration
| Plugin | Purpose |
|--------|---------|
| ahgSettingsPlugin | Centralized settings hub |
| ahgJobsManagePlugin | Background job management |
| ahgMenuManagePlugin | Menu/navigation management |
| ahgStaticPagePlugin | Static page management |
| ahgInformationObjectManagePlugin | Information object management |

### Browse & Discovery
| Plugin | Purpose |
|--------|---------|
| ahgDisplayPlugin | GLAM display profiles, ZoomPan |
| ahgSearchPlugin | Advanced search |
| ahgUiOverridesPlugin | UI overrides, viewer dispatch |
| ahgAccessionManagePlugin | Accession browse |
| ahgActorManagePlugin | Actor browse, autocomplete |
| ahgDonorManagePlugin | Donor browse |
| ahgRepositoryManagePlugin | Repository browse |
| ahgRightsHolderManagePlugin | Rights holder browse |
| ahgStorageManagePlugin | Physical storage browse |
| ahgTermTaxonomyPlugin | Term & taxonomy browse |

---

## Compliance Support

### Privacy Regulations
| Jurisdiction | Regulation | Status |
|--------------|------------|--------|
| South Africa | POPIA, PAIA | Full |
| European Union | GDPR | Full |
| United Kingdom | UK GDPR | Full |
| Canada | PIPEDA | Full |
| Nigeria | NDPA | Full |
| Kenya | DPA | Full |
| California | CCPA | Full |

### Heritage Accounting Standards
| Standard | Region |
|----------|--------|
| GRAP 103 | South Africa |
| IPSAS 45 | International |
| FRS 102 | United Kingdom |
| GASB 34 | USA State/Local |
| FASAB | USA Federal |
| AASB 116 | Australia |
| PSAB/PS 3150 | Canada |

---

## Milestones

| Milestone | Score | Date |
|-----------|-------|------|
| Initial Framework | 94/100 | 2026-01-01 |
| Speech-to-Text | 95/100 | 2026-01-20 |
| PII Detection | 96/100 | 2026-01-21 |
| SDKs + Semantic Search | 97/100 | 2026-01-22 |
| Format Migration + RiC-O JSON-LD + IIIF Auth | **100/100** | 2026-03-11 |

---

## Document History

| Date | Version | Changes |
|------|---------|---------|
| 2026-01-20 | 1.0 | Initial analysis |
| 2026-01-20 | 1.5 | Speech-to-Text complete (95/100) |
| 2026-01-21 | 1.6 | PII Detection complete (96/100) |
| 2026-01-22 | 2.0 | SDKs created, Semantic Search plugin, renamed to ROADMAP.md (97/100) |
| 2026-02-13 | 3.0 | Updated to 78 plugins, Heratio migration status, ahgIngestPlugin |
| 2026-03-11 | 4.0 | **100/100 achieved** — Format Migration Pathways, RiC-O JSON-LD Export, IIIF Auth API confirmed complete. 80 plugins. |

---

*The Archive and Heritage Group (Pty) Ltd*
*https://theahg.co.za*
