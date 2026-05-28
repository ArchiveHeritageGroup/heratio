# ILS Build Plan — Phased Implementation Roadmap

**Reference:** `library-system-tender-response.md`
**Last updated:** 2026-01-XX
**Total estimated effort:** ~260 developer-days across 12 phases
**Assumptions:** Single developer; no client-provided specifications to reverse-engineer.

---

## Dependency Key

```
[A] = prerequisite for  [B]  means  A must complete before B starts
~ = loosely coupled, can run in parallel
```

---

## Phase 1 — Cataloguing Foundation
**Duration:** 14 days
**Priority:** P1 (blocking all downstream modules — acquisitions handoff, OPAC, ERM all need clean bib records)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| C-1 | MARC 008/006/007 fixed-field guided editors | 5d | `ahg-library` | `MarcEditorController`, `resources/views/marc-editor/` |
| C-2 | MARC field/subfield validation engine | 3d | `ahg-library` | New `MarcValidationService.php` |
| C-3 | RDA fields 336/337/338 guided input | 3d | `ahg-library` | `MarcEditorController` |
| C-7 | De-duplication merge with audit trail | 3d | `ahg-library` | `MarcEditService` |

**Phase 1 deliverables:**
- Staff can create full MARC records with guided fixed fields per material type.
- Validation errors surface inline before save.
- RDA content/media/carrier types selectable from controlled vocabularies.
- Duplicate detection flags records before commit; merge writes audit log.

**Acceptance criteria:** Create a monograph record with all 008 fields populated; validation error appears if ISBN format is wrong; merge of two ISBN-identical records produces one record with audit entry.

---

## Phase 2 — Authority Control
**Duration:** 10 days
**Priority:** P1 (Authority control is prerequisite for clean MARC data feeding OPAC and ERM)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| C-4 | Authority control — heading validation + propagation | 8d | `ahg-library` | New `AuthorityControlService.php` |
| C-4b | Global heading change propagation | 2d | `ahg-library` | `AuthorityControlService` |

**Phase 2 deliverables:**
- Every MARC heading is checked against the authority file on save; unauthorized headings are flagged (not blocked — staff can override with reason).
- Changing an authority heading updates all linked bib records (with count preview before applying).
- Authority index view with type filter (personal / corporate / subject / genre / title).

**Acceptance criteria:** Save a bib record with an unrecognised 100 $a; warning badge appears; change a subject heading; all linked bib records update in the preview and on confirm.

---

## Phase 3 — Copy Cataloguing (Z39.50/SRU Client)
**Duration:** 12 days
**Priority:** P1 (major time-saver for cataloguers; blocks efficiency of Phase 4+)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| C-5 | Z39.50/SRU client for copy cataloguing | 10d | New `ahg-z3950-client` | `Z3950ClientService`, `SruClientService` |
| C-5b | Import and overlay workflow | 2d | `ahg-library` | `MarcEditorController` |

**Phase 3 deliverables:**
- Staff search LC, OCLC, BL, NLC, National Library of South Africa via Z39.50/SRU.
- Preview matched record before import.
- Overlay or create new; source attribution recorded in MARC 040 $a.
- Configurable target list (host, port, database, auth) managed in settings.

**Acceptance criteria:** Search OCLC by ISBN 978-0-13-468599-1; a live record returns; import it as a new record; the 040 $a shows the source library code.

---

## Phase 4 — BIBFRAME RDF Output Completion
**Duration:** 5 days
**Priority:** P2 (linked data readiness; can follow Phase 2)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| C-6 | BIBFRAME RDF output (Turtle / N-Triples / JSON-LD) | 5d | `ahg-biblio-bf` | `BibframeService` + new serializers |

**Phase 4 deliverables:**
- Every bib record exports Turtle, N-Triples, and JSON-LD via the BIBFRAME 2.0 vocabulary.
- JSON-LD includes Schema.org `@context` for Google Dataset Discovery.
- The `/bibframe/export/{id}` endpoint streams all three formats.

**Acceptance criteria:** Export the record from Phase 1 as Turtle; validate the Turtle against the BIBFRAME 2.0 SHACL shape (if available); the JSON-LD passes Google's structured data validator.

---

## Phase 5 — Patron Self-Registration + SSO
**Duration:** 18 days
**Priority:** P1 (circulation needs patron records; self-reg is the volume path)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| P-1 | Self-registration with staff approval workflow | 5d | `ahg-library` | New `PatronSelfRegController`, `resources/views/patron/self-reg/` |
| P-2 | LDAP / Active Directory integration | 8d | `ahg-library` | New `LdapAuthService`, `LdapPatronSync` |
| P-3 | SAML 2.0 / Shibboleth / OAuth2/OIDC patron SSO | 10d | `ahg-library` | New SAML middleware + config |
| P-4 | Patron photo capture + ID document upload | 3d | `ahg-library` | `LibraryPatronService` |
| P-6 | Bulk patron import (CSV) | 3d | `ahg-library` | `LibraryPatronService` |

*Note: P-2 and P-3 can run in parallel if separate developers.*

**Phase 5 deliverables:**
- Public self-registration form at `/library/patron/register`; staff approval queue at `/library-manage/patron/approvals`.
- Patron can log in via LDAP (enterprise) or SAML 2.0 (academic). Local password fallback always available.
- Photo upload on patron edit form; stored in `storage/app/patrons/{id}/`.
- CSV upload at `/library-manage/patron/import`; validates, previews, commits with rollback on failure.

**Acceptance criteria:** Register a new patron via the public form; receive email; staff approves in the queue; patron can log in with the temporary password and change it.

---

## Phase 6 — Circulation Policy Matrix + Fines Engine
**Duration:** 16 days
**Priority:** P1 (the core circulation loop depends on this)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| L-1 | Circulation policy matrix (category x type x branch) | 8d | `ahg-library` | New `CirculationPolicyService`, `library_circulation_policy` table |
| L-2 | Fine accrual engine (calendar-aware) | 5d | `ahg-library` | `LibraryCirculationService`, `library_fine` table |
| L-3 | Fine waiving, adjustment, write-off | 3d | `ahg-library` | `LibraryCirculationService` |

**Phase 6 deliverables:**
- Admin CRUD for policy rules: a grid UI showing patron_category × item_type with override at branch level.
- On check-out: due date calculated from the applicable policy.
- On overdue return: fine calculated as (days_overdue - grace_period) × rate, capped at maximum.
- Supervisor can waive fines above a configurable threshold; all waivers logged.

**Acceptance criteria:** Set a policy (patron: student, item: book, loan: 14 days, fine: R1/day, cap: R20). Check out a book to a student patron. Advance date by 20 days. Check in the book. A fine of R20 is assessed and displayed.

---

## Phase 7 — Holds Queue + Recall + Transfer
**Duration:** 12 days
**Priority:** P1 (holds is the next logical circulation feature after loans)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| L-5 | Holds queue (trap, pickup routing, expiry, suspend) | 5d | `ahg-library` | `LibraryCirculationService`, `library_hold` table |
| L-6 | Recall (shorten active loan) | 3d | `ahg-library` | `LibraryCirculationService` |
| L-10 | Offline circulation client | 5d | `ahg-library` | New `OfflineCirculationService` |

**Phase 7 deliverables:**
- Place a hold on a title (or specific item); patron is queued in order of request time.
- On check-in, if a hold exists, item status becomes "on hold" and patron is notified.
- Patron can suspend a hold (pause queue position) for a configurable period.
- Recall: patron or staff initiates; the current borrower gets an email; due date is shortened; fine applies if returned late.
- Transfer: branch A checks in an item with a hold at branch B; item enters "in transit" status.

**Acceptance criteria:** Place a hold on an item already on loan; check in the item; the patron receives a hold notification; their account shows the item ready for pickup.

---

## Phase 8 — OPAC Faceted Search + My Account
**Duration:** 21 days
**Priority:** P1 (patron-facing; needed for launch)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| D-1 | Faceted search UI (ES aggregations) | 8d | `ahg-discovery` | `DiscoveryController`, `resources/views/discovery/` |
| D-2 | My Account self-service portal | 10d | `ahg-library` | New `PatronAccountController`, `resources/views/patron/account/` |
| D-7 | Autocomplete + did-you-mean | 3d | `ahg-discovery` | `DiscoveryService` |

**Phase 8 deliverables:**
- OPAC search results show faceted sidebar: author, subject, format, language, year, branch, availability.
- Selecting a facet refines results in real-time (AJAX; URL remains shareable).
- My Account at `/library/patron/account`: current loans with renew button; holds management (suspend, cancel, change pickup); fine balance; loan history; profile edit.
- Autocomplete on the search bar from ES suggester; spelling correction on zero-result pages.

**Acceptance criteria:** Search "south africa"; results show with facets for year, author, format. Click "History" facet; only history books show. Log in as patron; My Account shows current loans and allows renewal.

---

## Phase 9 — OPAC Enrichment + Link Resolver + Citation Export
**Duration:** 16 days
**Priority:** P2 (nice-to-have for launch; can follow Phase 8)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| D-3 | Cover image integration | 5d | `ahg-library` | `LibraryOpacService` |
| D-4 | Citation export (RIS, BibTeX, EndNote) | 3d | `ahg-library` | New `CitationExportService` |
| D-5 | Enrichment sources (ToC, reviews, excerpts) | 5d | `ahg-library` | `LibraryOpacService` |
| D-6 | OpenURL link resolver (target endpoint) | 8d | `ahg-library` | New `OpenUrlResolverController` |
| D-8 | FRBR-grouped results display | 3d | `ahg-discovery` | `DiscoveryController` |

**Phase 9 deliverables:**
- Cover images fetched from Open Library API by ISBN; cached locally after first fetch.
- Citation export on record view: one-click RIS, BibTeX, EndNote download.
- Enrichment panel (ToC, excerpt) loaded from Google Books API.
- OpenURL target: `/resolve?` endpoint parses OpenURL, resolves to the best available copy (local electronic > local physical > ILL > link resolver redirect).
- FRBR work-level grouping: results show one row per work with a format switcher.

**Acceptance criteria:** Open a record for ISBN 978-0143039433; a cover image appears. Click "Cite" > "BibTeX"; valid BibTeX downloads. Access `/resolve?` with an OpenURL for a known e-journal; the resolver redirects to the access URL.

---

## Phase 10 — Acquisitions: Invoice Matching + Vendor Management
**Duration:** 16 days
**Priority:** P1 (acquisitions Phase 2 after order/budget CRUD)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| A-1 | Selection lists + patron-driven acquisition | 5d | `ahg-library` | New `SelectionListService`, `selection_list` tables |
| A-2 | Standing orders + approval plans | 5d | `ahg-library` | `LibraryAcquisitionService` |
| A-5 | Vendor management (terms, discounts, metrics) | 8d | `ahg-library` | `VendorService`, `vendor` tables |
| A-6 | 3-way invoice matching | 10d | `ahg-library` | `InvoiceMatchingService`, `library_invoice` tables |
| A-7 | Currency conversion + tax handling | 3d | `ahg-library` | `LibraryAcquisitionService` |

**Phase 10 deliverables:**
- Selection list: staff add titles; patrons suggest items; librarian approves or rejects.
- Standing orders: set recurrence (e.g., annual); system auto-creates orders when triggered.
- Vendor registry: contact details, discount schedules, performance metrics (fill rate, on-time rate), claim history.
- Invoice matching: on receiving, create a receipt. When the vendor invoice arrives, match it against the receipt and the original order. Variances flagged for manual resolution.
- Tax: per-vendor country tax rules applied to invoice totals.

**Acceptance criteria:** Create a standing order for an annual subscription. After 12 months, an order is auto-generated. Receive 9 of 10 items. Submit the vendor invoice for 10 items. The invoice shows 9 received, 1 missing, and requests variance approval.

---

## Phase 11 — Acquisitions: EDI/ONIX + GOBI/OASIS
**Duration:** 25 days
**Priority:** P2 ( EDI is vendor-specific; defer if only one or two vendors)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| A-3 | EDI/EDIFACT ordering and invoicing | 15d | `ahg-library` | New `EdiMessageService`, `EdiInboundProcessor`, `EdiOutboundBuilder` |
| A-4 | GOBI/OASIS integration | 10d | `ahg-library` | New `GobiService`, `OasisService` |
| A-8 | Cataloguing handoff on receipt | 5d | `ahg-library` | `LibraryAcquisitionService` |

**Phase 11 deliverables:**
- EDIFACT ORDERS message sent to vendor on PO confirmation (EDI-enabled vendors only).
- EDIFACT INVOIC message received from vendor; auto-parsed and queued for matching.
- GOBI/OASIS API: title search, availability check, order submission.
- On receiving confirmation, a brief bib record is created in Heratio; staff complete the full record at leisure.

**Acceptance criteria:** Configure an EDI-enabled vendor. Submit a PO. An EDIFACT ORDERS message is sent. Vendor sends an INVOIC. The message is parsed and queued for matching.

---

## Phase 12 — Serials: Routing + Binding
**Duration:** 13 days
**Priority:** P2 (low urgency; needed for academic libraries)

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| S-1 | Routing lists | 5d | `ahg-library` | New `RoutingListService`, `library_serial_routing` tables |
| S-2 | Binding management | 8d | `ahg-library` | New `BindingService`, `library_serial_binding` tables |

**Phase 12 deliverables:**
- Routing list: define a list of recipients/departments; received issues are automatically added to the routing queue; each recipient marks received before the next receives it.
- Binding: group received issues into volumes; create a bindery work order; track bindery cost; receive bound volumes back into the collection.

**Acceptance criteria:** Create a routing list (3 staff members). A new issue is checked in. Staff member 1 marks received; staff member 2 is notified. After all mark received, the issue is marked "routed". Alternatively, group 6 issues and send to bindery; track the work order.

---

## Phase 13 — ERM: Licence Management + Access + A-to-Z
**Duration:** 20 days
**Priority:** P2

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| E-1 | E-resource knowledge base (title registry) | 8d | `ahg-library` | New `ErmService`, `library_eresource` tables |
| E-2 | Licence terms tracking | 8d | `ahg-library` | `ErmService` |
| E-3 | Access management (proxy, IP ranges, SSO) | 8d | `ahg-library` | `ErmService` |
| E-4 | A-to-Z database list | 5d | `ahg-library` | New `AzListController` |
| E-7 | Trial management + renewal alerts | 3d | `ahg-library` | `ErmService` |
| E-8 | Perpetual-access tracking | 3d | `ahg-library` | `ErmService` |

**Phase 13 deliverables:**
- E-resource title registry with coverage dates (from KBART import).
- Licence record per title: allowed uses, ILL clause, simultaneous user limit, perpetual access clause.
- Access configuration: proxy URL prefix, IP allowlist, SSO entitlement string mapping.
- A-to-Z list: alphabetical listing at `/library/eresources` with search and filter.
- Renewal alert sent 60 days before resource expiry.

**Acceptance criteria:** Import a KBART file. 500 e-journal records appear in the knowledge base. Create a licence record for one of them (ILL: allowed, simultaneous users: 5). Add an IP range. The A-to-Z list shows the resource with an active link.

---

## Phase 14 — ERM: Cost-Per-Use Analytics
**Duration:** 5 days
**Priority:** P2

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| E-6 | Cost-per-use analytics dashboard | 5d | `ahg-library` | `LibraryUsageService`, new KPI charts |

**Phase 14 deliverables:**
- SUSHI data ingested per database. Cost-per-use = annual subscription cost / total article requests.
- Trend chart (monthly requests per database over 12 months).
- Flag resources where cost-per-use exceeds a configurable threshold.

**Acceptance criteria:** After ingesting SUSHI data, the cost-per-use table shows 5 databases ranked by cost-per-use. The cheapest database is used most; the most expensive is used least. Flag the outlier.

---

## Phase 15 — ILL: ISO Workflows + NCIP + Document Delivery
**Duration:** 18 days
**Priority:** P2

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| I-1 | ISO 10160/10161 / ISO 18626 workflow UI completion | 8d | `ahg-library` | `LibraryIllService`, ILL views (create, view exist; lending workflow needs completion) |
| I-2 | NCIP patron/ circulation fulfilment | 5d | `ahg-library` | New `NcipService` |
| I-3 | Electronic document delivery + copyright compliance | 5d | `ahg-library` | New `DocumentDeliveryService` |

**Phase 15 deliverables:**
- Lending workflow: incoming request → check availability → approve/lend → ship → track → receive back.
- Borrowing workflow: search external catalogue → place request → track → receive → notify patron → return.
- NCIP: ILL request drives a check-out to the borrowing library's NCIP system; return drives a check-in.
- Electronic delivery: staff uploads a PDF; system checks copyright exception; if within fair dealing limits, sends a secure link to the patron.

**Acceptance criteria:** Receive an incoming ILL request via ISO 18626 message. Check availability. Lend the item. On return, mark received. The lending history is complete.

---

## Phase 16 — Reporting: Dashboards + KPI Cards + Statutory Returns
**Duration:** 19 days
**Priority:** P2

| # | Item | Effort | Package | Key Files |
|---|---|---|---|
| R-1 | KPI dashboard cards | 8d | `ahg-reports` | `ReportController`, `ReportService`, new dashboard views |
| R-2 | Circulation canned reports | 5d | `ahg-reports` | New `CirculationReportService` |
| R-3 | Acquisitions spend reports | 3d | `ahg-reports` | `LibraryAcquisitionService` |
| R-4 | Serials check-in reports | 3d | `ahg-reports` | `LibrarySerialService` |
| R-5 | Statutory returns | 8d | `ahg-reports` | New `StatutoryReturnService`, sector-specific formatters |

**Phase 16 deliverables:**
- Dashboard: KPI cards for active patrons, loans today, overdues, holds awaiting pickup, fund burn-down, serials issues received this month.
- Canned circulation report: loan turnover by collection section; overdues by patron category; hold fill rate.
- Statutory returns: configurable output format (national library statistics; IPEDS academic libraries); pre-filled from live data.
- Ad-hoc report builder: SQL-safe query interface; results downloadable as CSV; schedule for email delivery.

**Acceptance criteria:** Dashboard shows all 6 KPI cards with live numbers. Run the statutory returns report; the output matches the national library statistics submission format for the current year.

---

## Phase 17 — Integration: SIP2 + NCIP Circulation + RFID
**Duration:** 28 days
**Priority:** P2

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| L-7 | SIP2 self-check protocol | 10d | `ahg-library` | New `Sip2ServerService`, SIP2 protocol handler |
| L-8 | NCIP circulation integration | 8d | `ahg-library` | `NcipService` (extends Phase 15) |
| L-9 | RFID ISO 28560 tag encoding + security gate | 10d | `ahg-library` | New `RfidService`, `library_rfid_tag` table |

**Phase 17 deliverables:**
- SIP2: self-check machine connects to Heratio on port 6001; patron can check out and return items; receipts printed.
- RFID: encode item barcodes to ISO 28560 tags (or use existing barcode in ISO 28560 encoding). Handheld RFID reader triggers inventory scan (read all tags in range, compare against catalogue).
- Security gate: item barcode encoded in ISO 28560 on RFID tag; security bit set on checkout, cleared on return; gate alarms if active item passes.

**Acceptance criteria:** Connect a SIP2 self-check machine. Patron scans card + item barcode. Item is checked out. Patron receives a receipt. Item's security bit is set. The item is checked in via the self-check on return.

---

## Phase 18 — Patron Messaging + PCI-DSS Payments
**Duration:** 16 days
**Priority:** P2

| # | Item | Effort | Package | Key Files |
|---|---|---|---|---|
| P-5 | Patron messaging: email, SMS, push | 5d | `ahg-library` | New `PatronNotificationService`, `library_notification_log` table |
| L-4 | Online fine payment (PCI-DSS gateway) | 8d | `ahg-library` | New `PaymentService`, PCI-DSS compliant gateway integration |
| R-6 | Inventory / stocktake (RFID-assisted) | 10d | `ahg-library` | New `StocktakeService` |
| Ad-1 | RTL support audit for library UI | 3d | `ahg-library` | All library views |
| Ad-2 | Patron RBAC — staff vs patron permission tiers | 5d | `ahg-acl` | ACL permission definitions |

**Phase 18 deliverables:**
- Notice types: overdue 1st / overdue 2nd / hold available / membership expiry / fine notice / recall notice.
- Email via Laravel Mail; SMS via configured provider; push via AHG notification infrastructure.
- Fine payment: patron sees balance in My Account; clicks "Pay now"; redirected to PCI-DSS-compliant payment page; on return, balance is cleared.
- Stocktake: start a stocktake session; scan shelf with RFID reader; system compares scanned barcodes against catalogue; missing items flagged; exception report generated.
- Arabic / Hebrew locale: all library views render correctly RTL.

**Acceptance criteria:** Check out an item to a patron. Set due date to yesterday (simulate overdue). Run the fine accrual job. Patron receives an overdue notice email. In My Account, the fine appears. Patron pays R20 online; balance clears; payment receipt is emailed.

---

## Effort Summary

| Phase | Name | Days |
|---|---|---|
| 1 | Cataloguing Foundation (MARC editor, validation, RDA) | 14 |
| 2 | Authority Control | 10 |
| 3 | Copy Cataloguing (Z39.50/SRU client) | 12 |
| 4 | BIBFRAME RDF Output | 5 |
| 5 | Patron Self-Reg + SSO | 18 |
| 6 | Circulation Policy Matrix + Fines | 16 |
| 7 | Holds Queue + Recall + Transfer | 12 |
| 8 | OPAC Faceted Search + My Account | 21 |
| 9 | OPAC Enrichment + Link Resolver + Citations | 16 |
| 10 | Acquisitions: Invoice Matching + Vendors | 16 |
| 11 | Acquisitions: EDI/ONIX + GOBI/OASIS | 25 |
| 12 | Serials: Routing + Binding | 13 |
| 13 | ERM: Licence Management + Access + A-to-Z | 20 |
| 14 | ERM: Cost-Per-Use Analytics | 5 |
| 15 | ILL: ISO Workflows + NCIP + Document Delivery | 18 |
| 16 | Reporting: Dashboards + Statutory Returns | 19 |
| 17 | Integration: SIP2 + RFID | 28 |
| 18 | Patron Messaging + Payments + Inventory | 23 |
| **Total** | | **281 days** |

---

## Delivery Timeline (Single Developer, 5d/week)

```
Month  1-2: Phase 1 (Cataloguing Foundation)    ← START
Month  2-3: Phase 2 (Authority Control)
Month  3-4: Phase 3 (Copy Cataloguing) + Phase 4 (BIBFRAME)  [parallel: Phase 5 start]
Month  4-6: Phase 5 (Patron Self-Reg + SSO)       ← P1 gateway
Month  6-7: Phase 6 (Circulation Policy + Fines)
Month  7-9: Phase 7 (Holds + Recall) + Phase 8 (OPAC)  [parallel]
Month  9-11: Phase 9 (OPAC Enrichment) + Phase 10 (Acquisitions Invoice/Vendor)
Month 11-13: Phase 11 (EDI/ONIX) + Phase 12 (Serials)
Month 13-15: Phase 13 (ERM) + Phase 14 (Cost-Per-Use)
Month 15-16: Phase 15 (ILL)
Month 16-18: Phase 16 (Reporting Dashboards)
Month 18-20: Phase 17 (SIP2 + RFID)
Month 20-22: Phase 18 (Messaging + Payments + Inventory)
Month 22-23: Acceptance testing + hardening
Month 23+: Reserve / contingency
```

**Full delivery:** ~23 months single-developer.
**With 2 developers:** ~14 months.
**With 3 developers:** ~10 months.

---

## Acceptance Testing Strategy

Each phase ends with:
1. Unit tests for the new service classes (PHPUnit).
2. Feature/integration tests for the controller routes (Laravel Dusk or Laravel test routes).
3. A signed-off demo: 10-step walkthrough showing the feature from the user's perspective.
4. Documentation: every new endpoint and service method documented in the package README.

---

*Reference: `docs/tenders/library-system-tender-response.md`*
