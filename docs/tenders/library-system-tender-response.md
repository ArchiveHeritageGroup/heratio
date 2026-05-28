# Library ILS Tender Response
## Heratio Integrated Library System
### The Archive Heritage Group (Pty) Ltd | Version 1.0 | May 2026

---

## 1. Introduction

The Archive Heritage Group (AHG) submits this response for the Library Information System tender. Heratio is a Laravel 12 archival and library management platform, AGPL-3.0, designed for the international GLAM (Gallery, Library, Archive, Museum) market with jurisdiction-neutral core and pluggable per-market compliance modules (GRAP 103, POPIA, PAIA, NAZ, CDPA, NMMZ, IPSAS, etc.).

This response covers:
- Confirmation of existing capability
- Gap analysis per tender section
- Implementation plan for all outstanding items
- Standards compliance matrix
- Delivery timeline

---

## 2. Existing Capability (Already Built)

### 2.1 Section 3 — Cataloguing (Partial)

| Requirement | Status | Package / Files |
|---|---|---|
| MARC editor (edit existing records) | Built | ahg-library: MarcEditorController, MarcEditService, edit.blade.php |
| MARCXML batch import | Built | ahg-metadata-export: MarcXmlImporter, formImportPreview/Commit |
| MARCXML export | Built | ahg-metadata-export: MarcxmlSerializer |
| MARC21 binary export | Built | ahg-metadata-export: Marc21BinaryEncoder |
| MARCXML → MARC21 binary decode | Built | ahg-metadata-export: Marc21BinaryEncoder |
| Z39.50 client (search remote targets) | Built | ahg-z3950: Z3950Service |
| Z39.50 server (expose catalogue) | Built | ahg-z3950: Z3950ServerController |
| SRU (HTTP Z39.50) | Built | ahg-z3950: SruService |
| BIBFRAME 2.0 RDF/XML | Built | ahg-biblio-bf: BibframeService |
| BIBFRAME RDF (Turtle / JSON-LD) | Stub | requires EasyRdf |
| RDA fields 336/337/338 | Built (serializer only) | RdaCarrierMapper; edit form needs wiring |
| Copy cataloguing workflow | Not built | Z39.50 client present; configuration UI needed |
| Authority control | Partial schema | library_subject_authority table present; UI not built |
| Bulk import (batch MARCXML) | Built | ahg-library import flow |
| 008 / Leader editing | Built | edit.blade.php + MarcEditService |
| 1XX/7XX/8XX field editing | Built | edit.blade.php author entry table |
| 6XX subject access | Built | edit.blade.php subject access table |
| 5XX notes | Built | edit.blade.php notes table |
| 856 electronic access | Built | edit.blade.php electronic access table |
| Series (490/830) | Built | edit.blade.php series_info section |
| Content type / RDA carrier mapping | Built | RdaCarrierMapper |
| Local classification (Dewey/LCC/NLM) | Built | library_item.dewey_decimal, classification_scheme, classification_number, cutter_number |
| ISBN / ISSN / LCCN / OCLC fields | Built | library_item columns |
| 020/022/024 ISBN/ISMN/EAN | Editable via 856 URL; 020/022 not yet inline |
| 028 publisher number | Not yet inline |

### 2.2 Section 4 — Patron Management (Partial)

| Requirement | Status | Notes |
|---|---|---|
| Patron registration | Built | LibraryPatronService; form + routes present |
| Patron categories / types | Built | ahg_dropdown 'library_patron_type' taxonomy |
| Suspend / reactivate | Built | patronSuspend, patronReactivate |
| Card number generation | Built | generateCardNumber() in LibraryPatronService |
| Self-registration (OPAC) | Not built | UI needed |
| Patron fines | Built | library_fine table; CalculateFinesCommand; UI in patron view |
| Fine payment | Not built | payment UI needed |
| SSO (LDAP/SAML) | Not built | Heratio core has user management; LDAP/SAML plugin stub present |
| Patron import | Not built | CSV import UI needed |
| Patron history / statistics | Built | patron view page shows loans, holds |

### 2.3 Section 5 — Circulation (Partial)

| Requirement | Status | Notes |
|---|---|---|
| Checkout | Built | LibraryCirculationService::checkout |
| Return | Built | LibraryCirculationService::returnItem |
| Renew | Built | LibraryCirculationService::renew |
| Place hold | Built | LibraryCirculationService::placeHold |
| Cancel hold | Built | LibraryCirculationService::cancelHold |
| Holds queue | Built | library_hold table; queue_position auto-set |
| Promote next hold on return | Built | LibraryCirculationService::promoteNextHold |
| Loan rules matrix | Built | library_loan_rule table; checkout applies rules |
| Max checkouts / holds | Built | LibrarySettings defaults |
| Max renewals | Built | LibraryCirculationService |
| Auto-expire holds | Built | AutoExpireHoldsCommand cron |
| SIP2 / NCIP | Not built | daemon stub present; not wired |
| RFID ISO 28560 | Not built | future |
| Branch / location transfer | Not built | UI needed |
| Short loan / reserve | Built | library_loan_rule.inter_library flag |

### 2.4 Section 6 — Acquisitions (Phase 0 Complete, Phase 1 In Progress)

| Requirement | Status | Notes |
|---|---|---|
| Purchase orders | Phase 1 | ahg-library: LibraryAcquisitionService, routes, views (budget/create/show/edit) |
| Budget / fund management | Phase 1 | library_acquisition_budget; budgets view built |
| Receive items | Phase 2 | receiveAll method exists; receiving UI not built |
| Vendor management | Not built | library_vendor table stub; vendor UI not built |
| EDI / ONIX ordering | Not built | vendor_code column exists; EDI parser not built |
| Invoice matching | Phase 2 | invoice handling not built |
| Claim missing items | Built | SerialOverdueClaimsController + view |
| Batch ISBN capture | Built | batch-capture.blade.php + lookup flow |

### 2.5 Section 7 — Serials (Complete)

All serial management features built (subscription, prediction, check-in, claiming, binding, routing).

### 2.6 Section 8 — OPAC / Discovery (Basic)

| Requirement | Status | Notes |
|---|---|---|
| Browse / search | Built | LibraryOpacService, OPAC index/view/hold/account |
| Faceted search | Not built | Elasticsearch present; facet UI not built |
| My Account | Built | OPAC account page |
| Z39.50 server | Built | ahg-z3950: Z3950ServerController |
| OAI-PMH | Built | ahg-oai: OaiProviderController |
| BIBFRAME JSON-LD | Partial | stub for JSON-LD output |
| Cover images | Built | OpenLibrary cover proxy |
| New arrivals / popular | Built | LibraryOpacService::newArrivals/popularItems |
| Holds via OPAC | Built | opacHoldStore |

### 2.7 Section 9 — ERM (Partial)

| Requirement | Status | Notes |
|---|---|---|
| SUSHI / COUNTER R5 | Built | SushiService + LibraryUsageService |
| KBART import | Built | KbartService |
| KBART export | Built | KbartService::generateTsv |
| KBART remote feed admin | Built | KbartRemoteService + admin UI |
| COUNTER report export (PR/TR/DR) | Built | LibraryUsageService::buildCounterReport |
| Licence management | Not built | UI needed |
| Cost-per-use analytics | Partial | usage stats table exists; dashboards not built |
| KBART AutoImport | Built | KbartRefreshFeedsCommand |

### 2.8 Section 10 — ILL (Partial)

| Requirement | Status | Notes |
|---|---|---|
| ILL request form | Built | ill/create + patron-create |
| ILL status transitions | Built | illTransition in LibraryIllService |
| ILL settings | Built | ill/settings |
| ISO 10160/10161 | Stub | Z3950Service handles protocol; ISO ILL UI needs work |
| ISO 18626 | Not built | REST API for ILL not built |
| Resource sharing | Partial | Z3950Service proxyToOpenric handles proxy |
| Overdue ILL cron | Built | LibraryIllOverdueCommand |

### 2.9 Section 11 — Reporting

| Requirement | Status | Notes |
|---|---|---|
| Catalogue report | Built | ahg-library reports index |
| Subject / creator / publisher reports | Built | ahg-library report pages |
| Call number report | Built | reportCallNumbers |
| Circulation report | Built | circulation/index |
| Overdue report | Built | circulation/overdue |
| Custom / canned reports | Built | ahg-reports framework |
| Statutory returns | Not built | DHET/NCL templates not built |
| BI / dashboard | Not built | KPI cards not built |

### 2.10 Section 12 — Administration

| Requirement | Status | Notes |
|---|---|---|
| RBAC | Built | Heratio core ACL |
| Parameters | Built | ahg-settings: library-group-settings |
| Notices | Built | Heratio notification system |
| i18n | Built | Laravel localisation |
| Audit trail | Built | ahg-audit-trail |
| Notifications | Built | LibraryOverdueCheckCommand, EmailUsageReportsCommand |

### 2.11 Section 13 — Integration

| Requirement | Status | Notes |
|---|---|---|
| OAI-PMH provider | Built | ahg-oai |
| SRU (Z39.50 over HTTP) | Built | ahg-z3950: SruService |
| Z39.50 server | Built | ahg-z3950: Z3950ServerController |
| Z39.50 client | Built | ahg-z3950: Z3950Service |
| MARC import/export | Built | ahg-metadata-export |
| SIP2 | Not built | stub for daemon |
| NCIP | Not built | future |
| EDIFACT | Not built | vendor adapter stubs present |
| RFID | Not built | future |
| Linked data (BIBFRAME JSON-LD) | Partial | RDF/XML built; serialisation to Turtle/JSON-LD needs EasyRdf |

---

## 3. Gap Analysis Summary

| Section | Gap Items | Est. Effort |
|---|---|---|
| 3 Cataloguing | 4: RDA inline edit, copy cataloguing workflow, authority UI, 020/022 fields, BIBFRAME serialisation | 18 days |
| 4 Patron | 5: self-registration, LDAP/SAML, fine payment, CSV import, patron history | 12 days |
| 5 Circulation | 2: SIP2/NCIP daemon, branch transfer UI | 10 days |
| 6 Acquisitions | 5: receiving UI, vendor mgmt, EDI/ONIX parser, invoice matching, payment | 28 days |
| 8 OPAC | 3: faceted search, OPAC dashboard, Z39.50 OPAC server | 21 days |
| 9 ERM | 3: licence UI, cost-per-use dashboard, statutory returns | 15 days |
| 10 ILL | 2: ISO 18626 REST API, resource-sharing UI | 8 days |
| 11 Reporting | 3: statutory returns, BI dashboard, custom report builder | 14 days |
| 13 Integration | 3: SIP2 daemon, EDIFACT adapter, BIBFRAME JSON-LD/Turtle | 18 days |
| **Total outstanding** | **~29 named items** | **~144 days** |

---

## 4. Standards Compliance Matrix

| Standard | Version | Implementation | Status |
|---|---|---|---|
| MARC21 | ANSI/NISO Z39.71 | MarcxmlSerializer, Marc21BinaryEncoder | Built |
| MARCXML | Z39.64 | MarcXmlImporter, MarcEditService | Built |
| RDA | 2014 | RdaCarrierMapper (336/337/338) | Built |
| BIBFRAME 2.0 | LC 2016 | BibframeService (RDF/XML) | Partial |
| Z39.50 | ANSI/NISO Z39.50 | Z3950Service (yaz PECL) | Built |
| Z39.83-1 | NISO KBART | KbartService + KbartRemoteService | Built |
| SRU | 1.2 / 2.0 | SruService | Built |
| COUNTER 5 | R5 | LibraryUsageService + SushiService | Built |
| SUSHI | SOAP/REST | SushiService + SushiServerController | Built |
| OAI-PMH | 2.0 | OaiProviderController | Built |
| ISO 23950 | Z39.50:2003 | Z3950Service | Built |
| ISO 10160/10161 | ILL | Z3950Service protocol; ILL UI partial | Partial |
| ISO 18626 | ILL messaging | Not built | Future |
| ISO 28560 | RFID tags | Not built | Future |
| FRBR | 1998 | ahg-biblio-frbr: WorkKeyService | Built |
| RiC-O | 0.2 | ahg-ric + OpenRiC proxy | Built |
| Dublin Core | 1.1 | ahg-oai + MarcxmlSerializer | Built |
| MODS | 3.7 | ahg-mods-manage | Built |
| PREMIS | 3.0 | ahg-preservation | Built |
| OAIS | ISO 14721 | Full archival package | Built |
| Unicode | UTF-8 | Full system | Built |

---

## 5. Delivery Plan

### Phase 1: Cataloguing Foundation (14 days)
- RDA fields 336/337/338 inline in MARC editor
- Authority control UI (library_subject_authority → library_item_subject linking)
- MARC21 binary import (yaz record decode → MarcEditService)
- Copy cataloguing workflow (Z39.50 target search → preview → import)
- 020/022 ISBN/ISMN inline fields in editor
- BIBFRAME Turtle/JSON-LD serialisation (add EasyRdf dependency)

### Phase 2: Patron + Circulation (22 days)
- OPAC self-registration
- LDAP/SAML SSO integration
- Fine payment UI
- Patron CSV import
- SIP2 / NCIP circulation daemon
- Branch transfer workflow

### Phase 3: Acquisitions (28 days)
- Receiving UI (receive-all with line-by-line confirmation)
- Vendor management (library_vendor CRUD + EDI ONIX adapter)
- Invoice matching (receive → invoice → payment workflow)
- Claim tracking integration with ILL
- Budget utilisation reporting

### Phase 4: OPAC + Discovery (21 days)
- Faceted search (Elasticsearch aggregation facets)
- OPAC My Account dashboard
- Z39.50 OPAC server (expose catalogue to world)
- Cover image enrichment pipeline
- New arrivals / popular items personalised

### Phase 5: ERM + Usage (15 days)
- Licence management (library_licence CRUD + access monitoring)
- Cost-per-use dashboard
- DHET statutory return templates (South African national library returns)
- SUSHI partner preset management (NAZ, SABINET, DALS)

### Phase 6: ILL + Reporting (22 days)
- ISO 18626 REST API for ILL
- ILL resource-sharing portal
- Statutory returns (DHET, national library council)
- BI dashboard (circulation KPIs, collection growth, usage trends)
- Custom report builder (parameterised queries)

### Phase 7: Integration (18 days)
- SIP2 circulation daemon (Circulation System Interface Protocol)
- EDIFACT order/invoice adapter (vendor-specific)
- RFID ISO 28560 tag management
- BIBFRAME JSON-LD linked data endpoint

---

## 6. Technical Architecture

**Stack:** Laravel 12, PHP 8.3, MySQL 8.0, Elasticsearch (search), Apache Jena Fuseki (RDF/RiC-O), nginx

**Existing packages:**
- ahg-library (cataloguing, circulation, serials, patrons, ILL, usage, KBART, SUSHI)
- ahg-metadata-export (MARCXML/MARC21/ONIX/Dublin Core/MODS/ISAD(G) crosswalks)
- ahg-z3950 (Z39.50 client + server, SRU)
- ahg-biblio-bf (BIBFRAME 2.0)
- ahg-biblio-frbr (FRBR work-key clustering)
- ahg-oai (OAI-PMH provider)
- ahg-ric (Records-in-Contexts conformance)
- ahg-search (Elasticsearch indexing)
- ahg-preservation (PREMIS, OAIS)
- ahg-settings (library group settings)

**OpenRiC integration:** All RiC-O canonical record management proxied through OpenRiC (Fuseki).

**Heratio KM:** Semantic search via Ollama + Qdrant RAG.

---

## 7. Pricing Note

Pricing available on request. Delivery timelines and costs scale with:
- Number of concurrent developers
- Integration complexity (vendor EDI, SIP2 hardware, RFID infrastructure)
- Multi-tenant / multi-branch requirements
- Custom statutory return templates per jurisdiction

---

## 8. Contact

Johan Pieterse
The Archive Heritage Group (Pty) Ltd
johan@theahg.co.za