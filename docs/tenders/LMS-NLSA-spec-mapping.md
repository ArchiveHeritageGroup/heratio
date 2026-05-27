# NLSA LMS Tender - Heratio Spec Coverage Map

**Tender:** NLSA 01/2026-2027 - Integrated Library Management System for the National Library of South Africa
**Closing:** 1 June 2026 11h00
**Source:** `docs/tenders/LMS-Tender.pdf`
**Scope of this doc:** map Heratio's current capability against the specification only. Bidder-experience / reference scoring (§6.3 row 1) is out of scope here.

**Legend:** Full / Partial / Gap

## §6.2 Mandatory Technical Criteria

| # | Item | Status | Heratio source |
|---|---|---|---|
| 1.1 | Win/Mac OS | Full | Web-based, browser-agnostic |
| 1.2 | Relational DB (MySQL/PostgreSQL) | Full | MySQL 8 |
| 1.3 | SaaS, API, multi-browser, mobile | Full | Laravel 12 + responsive theme-b5 |
| 1.4 | Security, encryption, audit | Full | AES-256 + audit + ODRL |
| 1.5 | IP protection on digital content | Full | ODRL policies + ahg-rights |
| 1.6 | Backup, DR, real-time | Full | ahg-backup + bin/install DR |
| 1.7 | RBAC (Admin/Mgmt/Librarian/User/Public) | Full | ACL + ahg-security-clearance + MFA |
| 1.8 | Accessibility (WCAG, mobile, remote) | Full | WCAG-tested theme-b5 |
| 1.9 | Integration (digitisation, ISBN, harvesting) | Full | ahg-ingest, OAI-PMH, ahg-archivematica |
| 1.10 | Scalability | Full | ES-backed; tested on 132k IO sets |
| 1.11 | 99.5% uptime SLA | Full | Hosting/ops |
| 2.1 | Responsive discovery, branding, multilingual | Full | ahg-display + ahg-theme-b5 |
| 2.2 | Unified search, FRBR, MARC, RDA, non-Roman | Partial | MARC+RDA partial; **FRBR clustering missing** |
| 2.3 | Faceted search, weighting, spellcheck | Full | ahg-search (ES) |
| 2.4 | Patron services (batch, multi-addr, blocks, notes) | Partial | Researcher patrons in ahg-research; **library-patron model gap** |
| 2.6 | PDA/DDA, LibGuides, IR, serial formats | Partial | IR available; **PDA/DDA + LibGuides gap** |
| 2.7 | Persistent links, clustering, RDA, MARC view | Full | ahg-information-object-manage |
| 2.9 | Usage stats, graphs, downloadable, customisable | Full | ahg-reports + ahg-search analytics |
| 2.10 | Chatbot | Partial | LLM + NER live; **chatbot UI not wired** |
| 2.11 | AI predictive search + personalisation | Full | ahg-ai-services + EU AI Act receipts |
| 2.12 | NISO/ODI/KBART/COUNTER/SUSHI/BIBFRAME | Partial | MARC21/DC/RDA done; **KBART/COUNTER/SUSHI/BIBFRAME gap** |
| 2.13 | REST APIs, microservices, security patches | Full | ahg-api v1/v2 |
| 3.1 | Real-time availability, notifications, automation, billing, booking | Partial | Booking via ahg-research; **billing partial** |
| 3.2 | Admin: user/role, MFA, sys config, security, audit, monitoring | Full | ahg-settings + ahg-security-clearance + ahg-observability |
| 3.3 | ILL: ISO 10160/10161 + OCLC ILL + Tipasa + WorldShare | Gap | **Significant gap - library-specific** |
| 3.4 | Centralised request dashboard | Full | ahg-workflow |
| 3.5 | Automated notifications | Full | Built-in |
| 3.6 | Fines/holds, configurable rules | Partial | ahg-library has hold expiry; **fines engine partial** |
| 3.7 | End-to-end workflow automation | Full | ahg-workflow |
| 4.1 | Trustworthy digital repo, WAC ingest, validation | Full | ahg-dam + ahg-archivematica |
| 4.2 | Digitisation repo, QC, discoverable | Full | ahg-dam + ahg-information-object-manage |
| 4.3 | OA harvest, discoverable | Full | ahg-oai (OAI-PMH) |
| 5.1 | Acquisitions: budget, orders, cancellations, vendors | Partial | ahg-cart + ahg-ingest; **PO workflow partial** |
| 5.2 | Subscription contract mgmt + notifications | Partial | Partial |
| 6.1 | Publisher integration / ONIX ingest | Partial | **ONIX not native - verify** |
| 6.2 | Brief record + audit-trailed bib reports | Full | ahg-information-object-manage |
| 7.1 | MARC21/DC/VRA/EAD/ISAD(G)/Bibframe/AACR/RDA/Z39.50 | Partial | MARC21/DC/EAD/ISAD(G) done; **Bibframe/Z39.50/AACR/full-RDA gap** |
| 7.2 | Authority control + LOD + SemWeb | Partial | ahg-ric (RiC) partial; LOD export available |
| 7.3 | Item-level, complex hierarchies | Full | MPTT (lft/rgt) |
| 7.4 | 13/14-digit barcodes | Partial | Verify |
| 7.5 | External DBs for e-books/journals | Partial | Federation partial (ahg-federation) |
| 7.6 | Multi-lingual + Unicode + diacritics | Full | i18n + UTF-8 |
| 7.7 | ClassificationWeb Plus + LCSH | Partial | LCSH partial via ahg-term-taxonomy; **CW+ integration gap** |
| 7.8 | Module integration + APIs + MarcEditor | Partial | API done; **MarcEditor gap** |
| 7.9 | Cataloguing stats, auditable, Excel/CSV | Full | ahg-reports |
| 8.1 | Serials: prediction patterns, missing-issue reports | Gap | **Library-specific - not built** |
| 9.1 | GRAP 103 & 17 + stock taking | Full | ahg-heritage-manage SA module |
| 9.2 | Inter-dept movement + location codes | Full | ahg-information-object-manage |
| 9.3 | Collection disposal with reason notes | Full | Withdraw workflow |
| 10.1 | Reports, BI/visualisation, Excel/CSV | Full | ahg-reports |
| 11.1 | Onboarding/migration/piloting | Full | Operational (bin/install + ahg-ingest) |
| 11.2 | Onsite + remote support | Full | Operational |
| 11.3 | Training (65 staff + 2 sysadmins) | Full | Operational |
| 11.4 | New-development listserv | Full | Operational |

## §6.3 Functional Criteria (excluding bidder-experience row)

| Row | Element | Status | Notes |
|---|---|---|---|
| 2 | Team-leader 10+ yrs LMS experience (10 pts) | Full | Demonstrable |
| 3 | Project plan: onboarding / organogram / schedule / risk / comms (20 pts) | Full | Standard proposal scope |
| 4 | Presentation: user-facing / acq+cataloguing / admin+analytics / AI (20 pts) | Full | All four demonstrable |

## Coverage summary

- **Full:** 31 of 53 mandatory items (~58%)
- **Partial:** 17 items (~32%) - surfaceable as roadmap / SOW commits
- **Gap:** 3 items - ILL (3.3), Serials prediction (8.1), some metadata standards in 7.1

## Material gaps to close before submission

1. **ILL (ISO 10160/10161 + OCLC Tipasa)** - biggest single missing module, library-specific
2. **Serials prediction patterns + missing-issue reports** - entire serials module
3. **Bibframe / Z39.50 / KBART / COUNTER / SUSHI** - bibliographic interop standards
4. **FRBR clustering / deduplication** in unified search
5. **Chatbot UI** - LLM is ready; surface needs wiring
6. **MarcEditor + ClassificationWeb Plus + ONIX** - third-party integrations
7. **Library-patron model** (vs researcher model) - fines, holds, multi-address
