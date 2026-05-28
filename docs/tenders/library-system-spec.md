# Integrated Library System (ILS) — Full Specification

**Scope:** End-to-end specification for a complete, production-grade library management system covering bibliographic control, circulation, acquisitions, serials, patron management, discovery, fulfilment, and administration. Standards-led, suitable for integration into a GLAM stack.

**Standards baseline:** MARC 21 / RDA, BIBFRAME 2.0, Dublin Core, MODS, METS, FRBR/LRM, ISBD, Z39.50, SRU/SRW, NCIP, SIP2, OAI-PMH, ISO 2709, ISO 10160/10161 (ILL), EDIFACT/ONIX (acquisitions), KBART (e-resources), OpenURL (link resolution), RFID ISO 28560, WCAG 2.1 AA.

---

## 1. System Overview

### 1.1 Functional Domains

| Domain | Responsibility |
|---|---|
| Cataloguing / Bibliographic | Create, edit, import, and maintain bibliographic, authority, and holdings records |
| Patron Management | Registration, profiles, categories, fines, communication |
| Circulation | Loans, returns, renewals, holds/reservations, fines, self-service |
| Acquisitions | Selection, ordering, receiving, invoicing, fund accounting, vendor management |
| Serials | Subscription control, prediction, check-in, claiming, binding, routing |
| Discovery (OPAC) | Public search, faceting, availability, account self-service |
| Electronic Resources (ERM) | E-journals, databases, licences, access, usage statistics |
| Interlibrary Loan (ILL) | Borrowing/lending requests, resource sharing, fulfilment |
| Reporting & Analytics | Operational reports, dashboards, KPIs, statutory returns |
| Administration | Parameters, users/roles, branches, policies, integrations |

### 1.2 Architecture Principles

- **Layered, modular monolith or service-oriented** — each domain is an independently deployable module sharing a common identity/authorization layer.
- **API-first** — every operation is available via REST/JSON; UIs are clients of the API.
- **Standards in, standards out** — ingest and emit MARC, MODS, BIBFRAME, Dublin Core; never lock data in a proprietary format.
- **Separation of bibliographic, holdings, and item data** — one bib record → many holdings → many items.
- **Multi-tenant / multi-branch** — consortium-capable with shared catalogue and distributed circulation policies.
- **Auditability** — every create/update/delete on a record is logged with actor, timestamp, and before/after state.

---

## 2. Data Model

### 2.1 Core Bibliographic Entities

```
BibliographicRecord (1) ──< Holding (n) ──< Item (n)
       │
       ├──< Authority links (Name, Subject, Title, Genre)
       ├──── BibframeWork / BibframeInstance (RDF projection)
       └──── ExternalIdentifiers (ISBN, ISSN, OCLC, LCCN, DOI)
```

| Entity | Key Fields |
|---|---|
| `BibliographicRecord` | bib_id, leader, control_fields (00X), data_fields (MARC), record_status, encoding_level, source, created_at, updated_at |
| `Holding` | holding_id, bib_id, location_code, call_number, call_number_scheme, shelving_location, summary_holdings (MARC 866/867/868) |
| `Item` | item_id, holding_id, barcode, item_type, status, home_branch, current_branch, price, acquisition_date, circulation_count |
| `Authority` | authority_id, type (personal/corporate/subject/title/genre), heading, see_also, source_vocab (LCSH, MeSH, AAT, FAST) |

### 2.2 Patron Entities

| Entity | Key Fields |
|---|---|
| `Patron` | patron_id, barcode/card_number, names, contact, category, branch, status, expiry, privacy_consent |
| `PatronCategory` | code, name, loan_limits, fine_rules, reservation_limits, default_loan_period |
| `PatronAccount` | balance, fines[], payments[], blocks[] |

### 2.3 Transactional Entities

`Loan`, `Reservation` (hold), `Fine`, `Payment`, `Order`, `Invoice`, `Subscription`, `SerialIssue`, `ILLRequest`, `Fund`, `Budget`.

### 2.4 Identifiers & Vocabularies

- **Bibliographic identifiers:** ISBN-13/10, ISSN, OCLC number, LCCN, DOI, Handle, ISMN.
- **Authority vocabularies:** LCSH, FAST, MeSH, AAT, TGN, ULAN, LCNAF, VIAF, ORCID, ISNI.
- **Classification schemes:** Dewey Decimal (DDC), Library of Congress (LCC), UDC, NLM.

---

## 3. Cataloguing Module

### 3.1 Capabilities

- **MARC editor** — full MARC 21 bibliographic, authority, and holdings formats; field/subfield validation against the MARC standard; fixed-field (008) guided editors per material type.
- **RDA-compliant input** — content/media/carrier types (336/337/338), relationship designators, FRBR-aware linking.
- **Templates & macros** — reusable record templates per material type (book, serial, e-resource, AV, map, score, manuscript, kit, 3D object).
- **Copy cataloguing** — Z39.50 / SRU search against external targets (LC, OCLC, national libraries); import and overlay.
- **Authority control** — automatic heading validation; link bib headings to authority records; flag unauthorized headings; global heading change propagation.
- **De-duplication** — match-and-merge on ISBN/ISSN/OCLC/title-key; configurable match rules; merge with audit trail.
- **Batch import/export** — ISO 2709 (.mrc), MARCXML, MODS, Dublin Core, BIBFRAME RDF; staged review queue before commit.
- **BIBFRAME projection** — emit Work/Instance/Item RDF graph alongside MARC for linked-data publishing.

### 3.2 Validation Rules

- Leader and 008 consistency with material type.
- Mandatory fields per encoding level (full, minimal, core).
- Indicator and subfield code validity.
- Authority-controlled fields must resolve to an authority record or be flagged.
- Duplicate detection on save.

---

## 4. Patron Management Module

### 4.1 Capabilities

- Self-registration (with staff approval workflow) and staff registration.
- Patron categories with inherited circulation and fine policies.
- Configurable required/optional fields; multiple addresses, phones, emails.
- Photo capture, ID document upload, guarantor/child linkage.
- Bulk import (CSV, LDAP/AD sync, SIS/SAML federation for academic libraries).
- Membership expiry, renewal, and automated reminders.
- Privacy and consent management (GDPR/POPIA): consent flags, data export, right-to-erasure with transaction-history anonymisation.
- Patron messaging: email, SMS, push (overdue, hold-available, expiry, fine notices).

### 4.2 Authentication & SSO

- Local password (hashed, policy-enforced), LDAP/Active Directory, SAML 2.0, OAuth2/OIDC, Shibboleth (academic).
- Card + PIN for self-service kiosks.

---

## 5. Circulation Module

### 5.1 Core Transactions

| Transaction | Behaviour |
|---|---|
| **Check-out (loan)** | Validate patron status, loan limits, item availability, blocks; assign due date per policy matrix; trigger receipt |
| **Check-in (return)** | Update item status, clear loan, calculate fines, trigger holds/transit, sort to shelving location |
| **Renewal** | Online/staff; check renewal limits, outstanding holds, blocks; recalculate due date |
| **Hold / Reservation** | Title-level or item-level; queue management; trap on check-in; pickup-branch routing; expiry |
| **Recall** | Shorten active loan when held by another patron (academic) |
| **Transfer / Transit** | Move items between branches; in-transit status |

### 5.2 Circulation Policy Matrix

Policies resolved by the intersection of **patron category × item type × branch**:

- Loan period, renewal count, max items on loan.
- Fine rate, grace period, maximum fine, fine cap.
- Hold limits, hold pickup window.
- Overdue notice schedule (1st / 2nd / final / lost).
- Lost-item replacement cost + processing fee.

### 5.3 Fines & Payments

- Accrual engine (per-day, per-hour, calendar-aware excluding closed days).
- Fine waiving, adjustment, and write-off with reason codes and authorisation levels.
- Payment integration: cash, card (PCI-DSS-compliant gateway), account credit, fee-forgiveness programmes.
- Receipts and statements.

### 5.4 Self-Service & Interoperability

- **SIP2** and **NCIP** for self-check machines, RFID stations, and automated material handling (AMH/sorters).
- **RFID** ISO 28560 tag encoding; security gate alarm integration.
- Offline circulation client (queues transactions, syncs on reconnect).

---

## 6. Acquisitions Module

### 6.1 Workflow

```
Selection → Order → Encumbrance → Receiving → Invoicing → Payment → Cataloguing handoff
```

### 6.2 Capabilities

- Selection lists, suggestions (patron-driven acquisition), approval plans.
- Purchase orders: firm order, standing order, approval, gift, exchange.
- **EDI** ordering and invoicing (EDIFACT, ONIX) with vendors; **GOBI/OASIS** integration.
- Fund and budget accounting: fund hierarchy, encumbrances, expenditures, fiscal-year rollover.
- Vendor management: terms, discounts, performance metrics, claim cycles.
- Receiving (full/partial), claiming (late/incomplete), cancellation.
- Invoice matching (3-way: order ↔ receipt ↔ invoice), tax handling, currency conversion.
- Brief/order records auto-promoted to full bib records on receipt.

---

## 7. Serials Module

- Subscription records linked to bib + vendor + fund.
- **Prediction patterns** (Holdings/Patterns 853/854/855, 863/864/865) — regular, irregular, combined issues.
- Issue check-in against predicted schedule; expected/late/received status.
- **Claiming** of missing/late issues (auto-generated, EDI or letter).
- Routing lists (circulate issues to staff/departments).
- Binding management (group issues into volumes, send to bindery, return).
- Union list / holdings statements (ISSN-linked, MARC 866).

---

## 8. Discovery / OPAC

### 8.1 Public Catalogue

- Faceted search (author, subject, format, language, year, branch, availability).
- Relevance-ranked full-text + fielded search; did-you-mean; autocomplete.
- FRBR-grouped results (works with multiple editions/formats).
- Real-time availability and shelf location; map/wayfinding integration.
- Cover images, tables of contents, reviews, enrichment (Syndetics/LibraryThing/Google Books).
- Save searches, lists, RSS alerts, citation export (RIS, BibTeX, EndNote).
- Persistent URIs for every record (linked-data ready).

### 8.2 Patron Self-Service (My Account)

- Current loans, due dates, renew, loan history (opt-in).
- Holds: place, view queue position, suspend, cancel, change pickup.
- Fines: view, pay online.
- Profile: update contact, set preferences, manage consent.
- Reading recommendations and saved lists.

### 8.3 Federated & Discovery-Layer Integration

- **OAI-PMH** provider for metadata harvesting.
- **SRU/SRW** and **Z39.50** server endpoints.
- OpenURL source/target for link resolution.
- Exposes RDF/JSON-LD (Schema.org + BIBFRAME) for SEO and linked data.

---

## 9. Electronic Resource Management (ERM)

- Knowledge base of e-journals, e-books, databases (KBART-compliant import).
- Licence terms tracking (allowed uses, ILL, course reserves, simultaneous users).
- Access management: proxy (EZproxy/OpenAthens), IP ranges, SSO entitlements.
- Link resolver (OpenURL) and A-to-Z list.
- Usage statistics ingestion: **COUNTER 5 / SUSHI** harvesting; cost-per-use analysis.
- Trial management, renewal alerts, perpetual-access tracking.

---

## 10. Interlibrary Loan (ILL) & Resource Sharing

- Borrowing and lending request workflows (ISO 10160/10161 / ISO 18626).
- Integration with resource-sharing networks (e.g., national union catalogues).
- Request routing, status tracking, due-date management, charges.
- NCIP messaging for circulation-side fulfilment.
- Document delivery (electronic) with copyright-compliance prompts.

---

## 11. Reporting & Analytics

- Canned operational reports: circulation stats, collection turnover, overdues, fines, acquisitions spend, serials check-in, patron activity.
- Ad-hoc report builder (SQL-safe, role-restricted) with scheduled delivery.
- Dashboards: KPIs (active patrons, loans/day, hold fulfilment time, collection age, fund burn-down).
- Statutory / sectoral returns (e.g., national library statistics, IPEDS academic libraries).
- Data warehouse / export to BI tools; anonymised analytics respecting privacy policy.
- Inventory and stocktake (RFID-assisted shelf reading, missing-item detection).

---

## 12. Administration & System Management

- **Parameters:** branches, locations, item types, patron categories, calendars (open hours, holidays), notice templates.
- **Users & roles:** RBAC with granular permissions per module/action; staff accounts distinct from patron accounts; branch-scoped permissions.
- **Notice & receipt templates:** multilingual, channel-specific (print/email/SMS), token substitution.
- **Internationalisation:** UTF-8 throughout; UI localisation; RTL support; multilingual bib data (MARC 880 linked fields).
- **Audit log:** all record and policy changes; security/access log.
- **Background jobs:** notice generation, fine accrual, hold expiry, membership expiry, index rebuild, report scheduling.

---

## 13. Integrations & Interfaces

| Interface | Standard | Purpose |
|---|---|---|
| Self-check / AMH | SIP2, NCIP | Self-service circulation, sorters |
| Discovery layer | OAI-PMH, SRU, Z39.50 | Metadata harvesting & federated search |
| Acquisitions/serials EDI | EDIFACT, ONIX, KBART | Vendor ordering, invoicing, e-resource KB |
| Authentication | LDAP, SAML 2.0, OIDC, Shibboleth | SSO / identity federation |
| Usage statistics | COUNTER 5 / SUSHI | E-resource analytics |
| Link resolution | OpenURL | Appropriate-copy resolution |
| RFID | ISO 28560 | Tagging, security, inventory |
| Linked data | BIBFRAME, Schema.org, JSON-LD | Web exposure, SEO, semantic web |
| Payments | PCI-DSS gateway | Online fine payment |
| Student info systems | SAML / REST / batch | Academic patron sync |

---

## 14. Non-Functional Requirements

| Category | Requirement |
|---|---|
| **Performance** | OPAC search < 1s for typical queries; circulation transaction < 500ms; support 10k+ concurrent OPAC sessions |
| **Scalability** | Horizontal scaling of API and search tiers; catalogue of 10M+ bib records, 50M+ items |
| **Availability** | 99.9% uptime; offline circulation fallback; zero-downtime deploys |
| **Security** | RBAC, encrypted transport (TLS 1.3), encrypted PII at rest, PCI-DSS for payments, OWASP Top 10 hardening |
| **Privacy** | GDPR / POPIA compliant; configurable data retention; loan-history anonymisation; consent management |
| **Accessibility** | WCAG 2.1 Level AA across OPAC and patron account |
| **Internationalisation** | Full Unicode, UI localisation, multilingual metadata, RTL |
| **Interoperability** | Standards-compliant import/export; documented public API |
| **Auditability** | Complete change history; tamper-evident logs |
| **Backup/DR** | Automated backups; point-in-time recovery; documented RPO/RTO |

---

## 15. Technology Reference Architecture

| Layer | Suggested Technology |
|---|---|
| API / application | PHP 8.3 + Laravel (or equivalent), REST/JSON, OpenAPI spec |
| Bibliographic store | Relational (MySQL 8 / PostgreSQL) for transactional; triplestore (Apache Jena Fuseki) for BIBFRAME/RDF |
| Search | OpenSearch / Elasticsearch with MARC-aware analyzers and facets |
| Cache / queue | Redis (cache, sessions), message queue for background jobs |
| Frontend | Bootstrap 5, WCAG 2.1 AA, responsive; staff client + OPAC |
| Auth | OAuth2/OIDC, SAML, LDAP connectors |
| Deployment | Containerised, reverse proxy (Nginx), TLS termination |
| Storage | NFS/object storage for covers, attachments, digitised content |

---

## 16. Module Build Sequence (Implementation Phasing)

1. **Foundation** — data model, auth/RBAC, branch/location/calendar parameters, audit log.
2. **Cataloguing** — MARC editor, validation, Z39.50/SRU import, authority control.
3. **Patron management** — registration, categories, SSO.
4. **Circulation** — loans/returns/renewals, policy matrix, fines, holds; SIP2/NCIP.
5. **OPAC / discovery** — search, facets, availability, My Account.
6. **Acquisitions** — orders, funds, receiving, EDI.
7. **Serials** — subscriptions, prediction, check-in, claiming.
8. **ERM** — knowledge base, licences, COUNTER/SUSHI.
9. **ILL** — borrowing/lending, ISO 18626.
10. **Reporting & analytics** — dashboards, statutory returns, BI export.
11. **Linked data & advanced integrations** — BIBFRAME publishing, OAI-PMH, RFID/AMH.

---

## 17. Compliance & Standards Summary

**Metadata:** MARC 21 (bib/authority/holdings), RDA, BIBFRAME 2.0, Dublin Core, MODS, METS, ISBD, FRBR/LRM, ONIX.
**Interchange:** ISO 2709, MARCXML, Z39.50, SRU/SRW, OAI-PMH, OpenURL.
**Circulation/fulfilment:** SIP2, NCIP, ISO 28560 (RFID).
**ILL:** ISO 10160/10161, ISO 18626.
**E-resources:** KBART, COUNTER 5, SUSHI.
**Identity:** LDAP, SAML 2.0, OIDC, Shibboleth.
**Accessibility/privacy/security:** WCAG 2.1 AA, GDPR/POPIA, PCI-DSS, TLS 1.3, OWASP Top 10.
