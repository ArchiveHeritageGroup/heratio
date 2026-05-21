> Heratio Help Center article. Category: Reference / GCIS Bid.

# AHG Scope: Timeline and Cost Model — AtoM-Aligned Plan

## GCIS RFB 001 2026/2027 — AtoM Configuration, Implementation, Support, Training

**Contract Start Assumption:** August 2026
**Contract Duration:** 24 months (Aug 2026 – Jul 2028)
**Currency:** ZAR, VAT exclusive (15% VAT to be added at submission)
**Scope:** AHG scope only — scanner rental, scanner maintenance, document preparation, scanning operations, OCR processing and physical digitisation labour are priced separately by the scanner vendor partner.

---

## 0. Approach Summary

The solution is **AtoM (Access to Memory) 2.10**, the open-source archival management system named by GCIS in clause 2 of the Terms of Reference and already in use by the National Archives and Records Service of South Africa.

To meet GCIS's specific requirements set out in clauses 4.1.1.1–4.1.1.14 and 4.6, base AtoM is extended with a curated set of independently-priced AtoM plugins developed and licensed by AHG. Each plugin is a discrete deliverable with its own activities, deliverables and price, so the evaluator can see exactly what is vanilla AtoM functionality and what is AHG-developed intellectual property.

**Plugin add-ons priced separately under Item 1:**

| Add-on plugin | GCIS requirement(s) | Status |
|---|---|---|
| 1b. SharePoint Online Connector | 4.1.1.1, 4.1.1.2, 4.1.1.4, 4.1.1.11 | New development for GCIS |
| 1c. POPIA / PAIA Privacy Compliance | 4.1.1.14, 4.6 | Existing AHG plugin — GCIS configuration |
| 1d. MISS Security Classification | 4.1.1.12.c, 4.1.1.12.d | Existing AHG plugin — GCIS configuration |
| 1e. Retention / Disposal Management | 4.1.1.6, 4.1.1.13 | Existing AHG plugin — GCIS configuration |
| 1f. POPIA / NARSSA Audit Trail | 4.1.1.12.f, 4.1.1.14.e–h | Existing AHG plugin — GCIS configuration |
| 1g. Time-Limited Link Sharing | 4.1.1.12.e | **Built and shipped May 2026** — `ahgTimeLimitedShareLinkPlugin` v0.1.0 live on demo PSIS; HMAC-SHA256 tokens, admin UI, retention sweep, full audit dual-write; 34 / 34 regression assertions pass; user manual + technical manual in `atom-extensions-catalog/docs/` |
| 1h. Continuous Ingestion API | 4.1.1.6, 4.1.1.5 | Existing AHG plugin — GCIS configuration |
| 1i. Multi-Tenant "One Instance" | clause 2 background + 4.1.3.1 | Existing AHG plugin (built by AHG for SITA/NARSSA) — non-standard AtoM IP |
| 1j. Federated Search (AtoM + SharePoint) | 4.1.1.10 (extended), 4.6.5 | **Built and shipped May 2026** — `PeerConnector` interface + `OaiPmhConnector` + `AtomElasticsearchConnector` + `SharePointGraphConnector` running on live Heratio; KQL builder against Microsoft Graph search API; result dedupe + source badges; 24 / 24 regression assertions pass; AtoM-port has schema + interface + OAI connector, AtoM-side AtomElasticsearch + SharePointGraph connectors deferred to F3 v0.2 |
| 1k. Version Control with Diff and Restore | 4.1.1.3, 4.1.1.9 | **Built and shipped May 2026** — `ahgVersionControlPlugin` v0.1.0 live on demo PSIS with 710 IO + 401 actor baselines backfilled; word-level diff; one-click restore with audit; 4 ACL permissions; user manual + technical manual in `atom-extensions-catalog/docs/` |

This modular pricing approach also:

- Aligns the bid wording with the GCIS Terms of Reference (which name "AtoM" throughout).
- Lets GCIS retain or remove specific add-ons during negotiation without renegotiating the whole bid.
- Keeps add-on intellectual property cleanly delineated for clause 4.1.3.5 ("any data, metadata, system configurations, customisations and modifications made to the archival system during the contract period shall remain the exclusive intellectual property of GCIS"). AHG-developed plugin source code licensed to GCIS as part of this contract becomes GCIS-owned; the underlying open-source AtoM core remains under its existing AGPL licence.

### The "One Instance of AtoM" model

Clause 2 (BACKGROUND) of the Terms of Reference notes: *"The single instance of the AtoM system used by National Archives and provincial archives in South Africa is hosted on the State Information Technology Agency (SITA) Private Cloud Foundation Infrastructure (CFI), within the Government Private Cloud Ecosystem (GPCE)."*

That "single instance" model is **not standard AtoM functionality**. Vanilla AtoM 2.x is single-tenant — one database, one set of records, no per-department isolation. The single-instance model used by NARSSA and the provincial archives is delivered by `ahgMultiTenantPlugin`, an AHG-developed plugin originally built by AHG personnel during the SITA implementation at NARSSA and now part of the AHG plugin catalogue.

The plugin adds:

- A `tenant` table and per-tenant `repository_id` scoping on every entity query (information objects, accessions, digital objects, actors, terms).
- Tenant-aware Elasticsearch filtering so each tenant only sees their own search results.
- Domain routing (e.g. `gcis-finance.atom.sita.gov.za` vs `gcis-comms.atom.sita.gov.za`) and per-tenant branding (logos, colours, page chrome) via the `TenantBranding` service.
- A user hierarchy where a SITA-level super-admin sees all tenants, a GCIS-level admin sees all GCIS directorates, and a directorate-level user only sees their directorate's records.

For GCIS, Item 1i activates this plugin in one of two modes (final choice agreed with GCIS during Phase 1):

1. **Joined model** — GCIS is added as a tenant on the existing NARSSA single instance (subject to NARSSA and SITA approval). Lowest total cost; reuses the existing operational AtoM instance.
2. **GCIS-only model** — A fresh AtoM instance is provisioned on the SITA Private Cloud per clause 4.1.3.1, with multi-tenancy activated to isolate GCIS directorates (Supply Chain Management, Communications, Provincial offices, etc.) from each other. This is recommended where GCIS prefers dedicated infrastructure and direct control.

Both modes use the same `ahgMultiTenantPlugin` codebase. The mode selection affects SITA infrastructure cost (paid directly by GCIS to SITA) but does not affect the AHG quotation.

### Build delivery status (as of bid submission)

The three add-ons originally tagged "new development" in this plan have been **built, tested and deployed to the AHG demonstration platforms** ahead of bid submission. They are demonstrable end-to-end today; their phase-4 effort reduces to GCIS-specific configuration and integration testing.

| Item | Plugin | Status | Live on |
|---|---|---|---|
| 1g | `ahgTimeLimitedShareLinkPlugin` v0.1.0 | **Built + released** May 2026 | PSIS (AtoM) + Heratio (Laravel) |
| 1j | `ahgFederationPlugin` + SharePoint connectors | **Built + released** May 2026 | Heratio live; PSIS schema + interface + OAI connector mirrored, AtomElasticsearch / SharePointGraph AtoM ports deferred to v0.2 |
| 1k | `ahgVersionControlPlugin` v0.1.0 | **Built + released** May 2026 | PSIS (with 710 information-object + 401 actor baselines) + Heratio |

All three carry a complete documentation set (Feature Overview, User Manual, Technical Manual) in both `.md` and `.docx` under the `atom-extensions-catalog/docs/` repository, plus full regression test sweeps (F1: 34 / 34; F2: 22 / 22; F3: 24 / 24 assertions pass).

The 49-screenshot evidence pack referenced in section 13 has been captured on the live PSIS instance using Playwright automation; the full pack is at `atom-ahg-plugins/testing/playwright/screenshots/` and ships with the bid PDF.

---

## 1. Project Phases and Timeline

### Phase 1: Inception and SITA Provisioning (Aug 2026, Weeks 1–4)

**Activities:** project kick-off, signed SLA, security clearances initiated for AHG team, SITA Private Cloud VM provisioning request, network and firewall rules confirmed (outbound to Microsoft Graph for the SharePoint Connector add-on, Cantaloupe IIIF if applicable), database provisioning, SSH access setup, project charter sign-off, governance structure agreed with GCIS IT and Records Management.

**Deliverables:** project charter, governance plan, SITA provisioning specification, security clearance applications submitted, kick-off workshop minutes.

**Resources:** Project Manager (full-time), System Developer (50%), Records Specialist (25%).

### Phase 2: AtoM Deployment on SITA (Sep 2026, Weeks 5–8)

**Activities:** clean AtoM 2.10 installation on SITA VMs (atom-framework + atom-ahg-plugins), MySQL 8 database setup, Elasticsearch / OpenSearch deployment, base AtoM configuration, SSL certificates, DNS configuration, smoke testing, backup configuration, initial security hardening per MISS guidelines.

**Deliverables:** working AtoM instance on SITA, deployment documentation, infrastructure handover document, backup and restore tested, system administrator initial access provisioned.

**Resources:** System Developer (full-time), Project Manager (25%).

### Phase 3: GCIS-Specific Core Configuration (Oct 2026, Weeks 9–11)

**Activities:** GCIS file plan taxonomy import into AtoM (one-time custom import task), classification scheme configuration per the GCIS-approved file plan, Dublin Core metadata configuration, encryption verification (data at rest via SITA storage layer, in transit via TLS), MFA setup for end-user accounts.

This phase covers ONLY base AtoM configuration. The MISS RBAC, retention schedule, audit trail and POPIA configuration are part of the add-on plugin items (1d, 1e, 1f, 1c respectively) and run in parallel under their own Item lines.

**Deliverables:** configured AtoM instance reflecting the GCIS file plan, taxonomy import scripts, base RBAC structure document, encryption verification report, MFA enrolment procedure.

**Resources:** System Developer (full-time), Records Specialist (50%), Project Manager (25%).

### Phase 4: Plugin Add-on Development and Configuration (Nov 2026 – Jan 2027, Weeks 12–26)

This phase delivers the ten plugin add-ons (Items 1b–1k). Each add-on is independently activity-scoped and priced in Section 5. Items 1c, 1d, 1f, 1h are configuration of existing AHG plugins and complete in 2–4 weeks each, run partially in parallel. Item 1b (SharePoint Connector) is new development on the critical path. **Items 1g (Time-Limited Link Sharing), 1j (Federated Search), and 1k (Version Control) have been built and shipped in May 2026** — they are demonstrable on the AHG PSIS instance and are not on the contract critical path. Their phase-4 activity reduces to GCIS-specific configuration, theming, and integration testing.

**Phase deliverables:** all seven plugins installed, configured, integration-tested and signed off.

### Phase 5: System Administrator Training (Feb 2027, Weeks 27–28)

**Activities:** two training sessions for two groups of GCIS system administrators covering AtoM architecture, metadata structures, system configuration, user permissions, maintenance procedures, scanning workstation configuration with the scanner vendor's equipment, integration with SharePoint, troubleshooting.

**Deliverables:** training materials (slides, manuals, video tutorials per 4.2.6.1), training session attendance records, competency assessments, post-training support plan.

**Resources:** Trainer and Support Specialist (full-time), System Developer (50% — technical support during sessions).

### Phase 6: Records Management Officials Training (Mar 2027, Weeks 29–32)

**Activities:** four training sessions for records management officials on document preparation (joint delivery with scanner vendor), metadata capture, indexing, quality assurance of digitised records, end-to-end digitisation process, secure upload to AtoM, archival of non-active records.

**Deliverables:** training materials specific to records management workflows, four delivered sessions, attendance records, competency assessments.

**Resources:** Trainer and Support Specialist (full-time), Records Specialist (50%).

### Phase 7: End-User Training (Apr 2027, Weeks 33–36)

**Activities:** four end-user training sessions covering AtoM access and navigation, search and browse, viewing digitised records, archival descriptions (fonds/series/item), metadata filters, linked digital objects, RBAC, audit trails, exporting records. Plus four end-user training sessions on the digitisation process (document preparation, scanning, metadata capture, QA).

**Deliverables:** end-user training materials, eight delivered sessions (four AtoM + four digitisation), attendance records, quick reference guides per 4.2.6.1.

**Resources:** Trainer and Support Specialist (full-time), Records Specialist (25%).

### Phase 8: Go-Live and Stabilisation (May 2027, Weeks 37–40)

**Activities:** production cutover, hypercare period with daily check-ins, issue resolution, performance tuning, user adoption support, first quarterly health check, project close-out report.

**Deliverables:** go-live sign-off, hypercare report, first quarterly health check report, project close-out report per 4.9.7.

**Resources:** System Developer (full-time during cutover, then 50%), Project Manager (full-time), Trainer (50%).

### Phase 9: Steady-State Maintenance and Support (Jun 2027 – Jul 2028, Months 11–24)

**Activities:** ongoing technical and user support per agreed SLA, online and telephonic support, quarterly system health checks (4 quarterly checks across this period), patching and updates to AtoM and add-on plugins, system enhancements per agreed change control, scanner equipment integration support (in coordination with scanner vendor), ad-hoc training refreshers as required.

**Deliverables:** monthly support reports, quarterly health check reports, patch and update logs, change control records, annual review report.

**Resources:** System Developer (20% allocated, on-call), Project Manager (10% allocated, governance only), Records Specialist (on-call as needed).

---

## 2. Resource Loading and Day Rates

| Role | Day Rate (R, ex-VAT) | Justification |
|---|---|---|
| Project Manager | 10,000 | Mid-tier PM rate for ICT government projects |
| Records Specialist | 12,000 | Senior specialist with archival qualifications |
| System Developer | 14,000 | Senior developer, AtoM specialist (premium for scarce skill) |
| Trainer and Support Specialist | 9,000 | Senior trainer with system delivery experience |

Rates reflect mid-to-upper end of the R8k–R15k SA government consulting band, with the System Developer at the upper end given the AtoM scarcity premium.

---

## 3. Cost Breakdown — Core AtoM Scope (Item 1a)

### Item 1a: Core AtoM 2.10 Configuration and Implementation (Year 1 only)

Includes Phases 1, 2, 3 and the project management overlay across Phase 4. Excludes plugin add-on development and configuration (Items 1b–1h, priced separately below).

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 35 | 10,000 | 350,000 |
| System Developer | 50 | 14,000 | 700,000 |
| Records Specialist | 15 | 12,000 | 180,000 |
| **Item 1a Subtotal** | | | **1,230,000** |

---

## 4. Plugin Add-on Development and Configuration (Items 1b–1h)

Each add-on is delivered during Phase 4 (Nov 2026 – Jan 2027, Weeks 12–26). Items 1c, 1d, 1f, 1h are configuration of existing AHG plugins and run in parallel. Item 1b (SharePoint Connector) is the only remaining new development on the critical path. Items 1g, 1j, and 1k have been built and shipped on the AHG demo platform in May 2026 ahead of bid submission — their phase-4 effort is configuration and integration testing only.

### Item 1b: SharePoint Online Connector Plugin (`ahgSharePointPlugin`)

**Maps to GCIS requirements:** 4.1.1.1 (workflow automation + SharePoint integration), 4.1.1.2 (import digitised non-active records from SharePoint), 4.1.1.4 (metadata linkage between active SP records and archived non-active records), 4.1.1.11 (links to associated active records).

**Activities:** plugin design and development; Microsoft Graph API authentication setup (OAuth 2.0 client-credentials, certificate-based app authentication option); active records metadata synchronisation logic; retention-triggered transfer workflow from SharePoint to AtoM (driven by Purview retention/disposal labels — optional gating, designed to operate with or without GCIS Purview licensing); batch upload endpoint for digitised non-active records; metadata linkage between SharePoint and AtoM records (bi-directional reference IDs); error handling and retry logic; audit logging of all transfers; integration testing with GCIS SharePoint test tenant.

**Deliverables:** deployed and tested `ahgSharePointPlugin`, integration architecture document, Graph API permissions and security document, transfer workflow documentation, integration test results, configuration UI for SharePoint drives, mapping templates and ingest rules.

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 15 | 10,000 | 150,000 |
| System Developer | 60 | 14,000 | 840,000 |
| Records Specialist | 30 | 12,000 | 360,000 |
| **Item 1b Subtotal** | | | **1,350,000** |

### Item 1c: POPIA / PAIA Privacy Compliance Plugin (`ahgPrivacyPlugin`)

**Maps to GCIS requirements:** 4.1.1.14.c (POPIA / PAIA compliance), 4.6 (records management strategy + POPIA + NARSSA compliance).

**Activities:** deploy the existing `ahgPrivacyPlugin`; configure the POPIA + PAIA jurisdiction profile (the plugin supports seven jurisdictions including POPIA, GDPR, UK GDPR, CCPA, PIPEDA, NDPA, DPA — POPIA is selected and activated for GCIS); configure GCIS-specific PII patterns (SA ID numbers, passport numbers, contact details); configure data subject access request (DSAR) workflows; configure breach-notification templates per POPIA timelines; sign-off with GCIS Information Officer.

**Deliverables:** configured `ahgPrivacyPlugin`, POPIA configuration document, DSAR workflow documentation, breach-notification procedure, GCIS Information Officer sign-off.

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 3 | 10,000 | 30,000 |
| System Developer | 10 | 14,000 | 140,000 |
| Records Specialist | 10 | 12,000 | 120,000 |
| **Item 1c Subtotal** | | | **290,000** |

### Item 1d: MISS Security Classification Plugin (`ahgSecurityClearancePlugin`)

**Maps to GCIS requirements:** 4.1.1.12.c (stricter access restrictions for Confidential records per MISS guidelines/standards), 4.1.1.12.d (access to records restricted exclusively to designated records management officials).

**Activities:** deploy the existing `ahgSecurityClearancePlugin`; map MISS classification levels (Unclassified / Restricted / Confidential / Secret / Top Secret) to AtoM clearance taxonomy; configure user clearance assignments per GCIS organisational structure; embargo rules per classification level; integrate clearance check into AtoM ACL pipeline; smoke-test against representative records.

**Deliverables:** configured `ahgSecurityClearancePlugin`, MISS-to-AtoM classification mapping document, clearance assignment procedure, embargo configuration document.

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 2 | 10,000 | 20,000 |
| System Developer | 8 | 14,000 | 112,000 |
| Records Specialist | 8 | 12,000 | 96,000 |
| **Item 1d Subtotal** | | | **228,000** |

### Item 1e: Retention / Disposal Management Plugin (`ahgExtendedRightsPlugin`)

**Maps to GCIS requirements:** 4.1.1.6 (workflow for automated archival according to GCIS retention policy + API integration for continuous ingestion), 4.1.1.13.a (automated or manual enforcement of records retention schedules), 4.1.1.13.b (controlled disposal workflows with audit logs).

**Activities:** deploy the existing `ahgExtendedRightsPlugin`; load GCIS retention schedule per category in the GCIS file plan; map National Archives and Records Service of South Africa Act requirements onto retention rules; configure controlled disposal workflows with reviewer assignment, hold checks, and audit logging; configure embargo processing CLI (`php symfony embargo:process`) on cron; reporting templates for retention status.

**Deliverables:** configured `ahgExtendedRightsPlugin`, GCIS retention schedule loaded, disposal workflow documentation, NARS Act compliance mapping document, retention status report template, embargo cron entry.

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 3 | 10,000 | 30,000 |
| System Developer | 12 | 14,000 | 168,000 |
| Records Specialist | 15 | 12,000 | 180,000 |
| **Item 1e Subtotal** | | | **378,000** |

### Item 1f: POPIA / NARSSA Audit Trail Plugin (`ahgAuditTrailPlugin`)

**Maps to GCIS requirements:** 4.1.1.12.f (detailed audit trails and activity logs for all access, sharing and modifications), 4.1.1.14.e (audit reports), 4.1.1.14.f (access logs and user activity tracking), 4.1.1.14.g (metadata integrity verification), 4.1.1.14.h (retention status and lifecycle compliance reports).

**Activities:** deploy the existing `ahgAuditTrailPlugin`; configure event capture for create/read/update/delete/share/export operations; configure POPIA-aligned retention of audit records; configure NARSSA-aligned reporting (quarterly compliance reports, metadata integrity verification, lifecycle status); audit report templates; admin dashboard.

**Deliverables:** configured `ahgAuditTrailPlugin`, audit event catalogue, POPIA + NARSSA reporting template set, audit retention policy document, admin dashboard walkthrough.

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 2 | 10,000 | 20,000 |
| System Developer | 6 | 14,000 | 84,000 |
| Records Specialist | 4 | 12,000 | 48,000 |
| **Item 1f Subtotal** | | | **152,000** |

### Item 1g: Time-Limited Link Sharing Plugin — NEW BUILD (leverages AHG share-token pattern)

**Maps to GCIS requirements:** 4.1.1.12.e ("functionality to share links to specific documents for a defined period, ensuring temporary access is controlled, auditable, and automatically revoked after the expiry period").

**Note on AHG pattern reuse.** While no existing AtoM open-source plugin covers this requirement, AHG already operates the share-token pattern in production across two adjacent plugins: the Portable Export plugin (offline catalogue distribution) and the Reports plugin (time-limited report sharing). Both use a proven `(token, expires_at, max_downloads, download_count, revoked_at)` schema with HMAC token generation, expiry middleware, access audit and admin revocation UI. Item 1g extends the same pattern to `information_object` records — a focused, lower-risk build than a green-field design.

**Activities:** plugin design and development reusing the AHG share-token pattern; `information_object_share_token` schema extension; signed-URL token generation (HMAC over `{record_id, expiry_timestamp, recipient_email}`); expiry enforcement middleware on share-URL request handler; automatic revocation on expiry (cron sweep + on-access check); access audit log entry per link issuance and per access; admin UI to list, revoke and audit active share links; recipient-side simple landing page with download / preview; CSRF + rate-limit protection; integration with `ahgAuditTrailPlugin` for combined audit reporting; security review per MISS guidelines.

**Deliverables:** deployed and tested `ahgTimeLimitedShareLinkPlugin`, technical design document, share-link issuance and revocation procedure, admin UI walkthrough, security review document, integration test results.

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 3 | 10,000 | 30,000 |
| System Developer | 10 | 14,000 | 140,000 |
| Records Specialist | 4 | 12,000 | 48,000 |
| **Item 1g Subtotal** | | | **218,000** |

### Item 1h: Continuous Ingestion API Plugin (`ahgIngestPlugin` + `ahgAPIPlugin`)

**Maps to GCIS requirements:** 4.1.1.5 (batch uploads), 4.1.1.6 (API integration for continuous ingestion).

**Activities:** deploy and configure `ahgIngestPlugin` (the 6-step batch ingest wizard) and `ahgAPIPlugin` (REST API endpoints); configure REST endpoints for external systems (SharePoint Connector and future systems) to push records on retention trigger; configure webhook delivery for downstream systems on AtoM events; batch upload endpoints (CSV / EAD / ZIP); API key management; integration test against the SharePoint Connector add-on.

**Deliverables:** configured `ahgIngestPlugin` and `ahgAPIPlugin`, REST API specification document, webhook subscription procedure, API key management procedure, integration test results.

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 2 | 10,000 | 20,000 |
| System Developer | 8 | 14,000 | 112,000 |
| Records Specialist | 5 | 12,000 | 60,000 |
| **Item 1h Subtotal** | | | **192,000** |

### Item 1i: Multi-Tenant "One Instance" Plugin (`ahgMultiTenantPlugin`)

**Maps to GCIS requirements:** clause 2 BACKGROUND (single-instance AtoM model used by NARSSA and provincial archives), 4.1.3.1 (deployed on SITA Private Cloud), 4.1.3.4 (access restricted to authorised and designated GCIS officials only).

**Background.** As explained in section 0, the "single instance" model named in the Terms of Reference is delivered by AHG's `ahgMultiTenantPlugin`, originally developed during AHG's SITA implementation at NARSSA. The plugin is a competitive differentiator: no other AtoM service provider in South Africa has this capability.

**Activities:** deploy `ahgMultiTenantPlugin`; activate tenant filtering on information_object, accession, digital_object, actor and term queries; activate tenant-aware Elasticsearch filtering; configure GCIS tenant hierarchy (national level + directorates + provincial offices per the GCIS organisational structure); configure per-tenant branding (logos, colours, header text) for the major directorates; configure domain routing if GCIS opts for subdomain-per-directorate access; configure user-to-tenant assignment; sign-off with GCIS IT and Records Management.

**Deliverables:** activated `ahgMultiTenantPlugin`, tenant hierarchy configuration document, per-tenant branding configuration, domain routing configuration (if applicable), user-to-tenant mapping document, walk-through demonstrating isolation between two test tenants.

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 2 | 10,000 | 20,000 |
| System Developer | 8 | 14,000 | 112,000 |
| Records Specialist | 4 | 12,000 | 48,000 |
| **Item 1i Subtotal** | | | **180,000** |

### Item 1j: Federated Search across AtoM and SharePoint (extends `ahgFederationPlugin`)

**Maps to GCIS requirements:** 4.1.1.10.a (full-text search and advanced filtering — extended), 4.1.1.10.b (quick retrieval for audit, compliance and operations — across both stores), 4.1.1.11 (links to associated active records), 4.6.5 (records retrieval and searchability mechanisms across active and non-active records).

**Background.** With active records in SharePoint Online and non-active records in AtoM, a researcher or records officer needs a single search box that returns hits from both stores in one ranked result set.

**AHG-side infrastructure already exists.** `ahgFederationPlugin` is a mature production component used in AHG's heritage federation deployments. It provides registered peer management (`federation_peer`), per-peer search configuration (`federation_peer_search`), aggregated search caching (`federation_search_cache`), full audit logging (`federation_search_log`), and cross-peer term/vocabulary mapping (`federation_term_mapping`, `federation_vocab_sync`). The admin UI under `/federation` supports adding peers, configuring search rules, running OAI-PMH harvests, and reviewing logs. Item 1j adds **one new peer type — Microsoft Graph Search** — to this proven infrastructure, rather than building a federated-search system from scratch.

**Activities:** add `SharePointGraphPeer` connector class to `ahgFederationPlugin` (implements the existing PeerConnector interface, proxies queries to Microsoft Graph Search API `POST /search/query` using the same OAuth credentials as the SharePoint Connector Item 1b); register SharePoint as a peer type in `federation_peer`; result fusion into the existing aggregated cache (the federation cache already handles AtoM Elasticsearch + OAI-PMH peers; SharePoint slots in as a third source); source-badge rendering ("active in SharePoint" / "archived in AtoM") in the existing federated search results view; deduplication via SP item ID and AtoM slug; ACL enforcement that respects each source's permissions; end-user search UI update; test with realistic GCIS test corpus.

**Deliverables:** SharePoint peer connector code, federation admin documentation update, source-badge rendering, deduplication logic documentation, ACL-respecting query path verification, performance benchmark report, end-user training module addendum.

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 3 | 10,000 | 30,000 |
| System Developer | 12 | 14,000 | 168,000 |
| Records Specialist | 3 | 12,000 | 36,000 |
| **Item 1j Subtotal** | | | **234,000** |

### Item 1k: Version Control with Diff and Restore Plugin — NEW BUILD (leverages AHG version-snapshot pattern)

**Maps to GCIS requirements:** 4.1.1.3 ("Enable secure retrieval, tracking, and version management of archived records"), 4.1.1.9 ("Enable tagging, indexing, and version control for efficient search and retrieval"), 4.6.2 (version control and audit trail management).

**Note on AHG pattern reuse.** Base AtoM 2.10 does not include user-facing version control, but AHG already operates the version-snapshot pattern in production across three adjacent plugins: the Reports plugin (`report_version` table with `snapshot JSON + version_number + change_summary + created_by + created_at`), the Landing Page plugin (`atom_landing_page_version`), and the Heritage plugin (`heritage_contribution_version`). Each uses the same shape, the same observer to capture changes on save, and the same admin "Versions" tab UI for listing, diffing and restoring. Item 1k extends the same proven pattern to `information_object` and `actor` — the two entity types referenced by 4.1.1.3 and 4.6.2.

Additionally, `ahgAuditTrailPlugin` (Item 1f) already captures every entity change with `old_values` / `new_values` / `changed_fields` JSON snapshots in `ahg_audit_log` — providing complementary change tracking that integrates with version control.

**Activities:** plugin design reusing the AHG version-snapshot pattern; `information_object_version` and `actor_version` schemas; observer hooks on entity save that capture a full snapshot + change_summary; "Versions" tab on information_object and actor view pages listing version history; revision detail view showing field-by-field old/new values; side-by-side diff renderer for any two selected revisions; "Restore this version" action that writes the selected snapshot back to the live entity (and itself creates a new version entry capturing the restore); ACL guards (records management officials only can restore; classified records respect Item 1d clearance gating); browse UI to filter recent restores for compliance reporting.

**Deliverables:** deployed and tested `ahgVersionControlPlugin`, technical design document, UI walkthrough (Versions tab, diff view, restore action), ACL guard test results, integration test with audit trail (Item 1f) and security clearance (Item 1d).

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Project Manager | 2 | 10,000 | 20,000 |
| System Developer | 10 | 14,000 | 140,000 |
| Records Specialist | 2 | 12,000 | 24,000 |
| **Item 1k Subtotal** | | | **184,000** |

### Plugin Add-on Subtotal (Items 1b–1k)

| Item | Add-on | Subtotal (R) | Reuse signal |
|---|---|---|---|
| 1b | SharePoint Online Connector | 1,350,000 | Net-new for GCIS |
| 1c | POPIA / PAIA Privacy Compliance | 290,000 | Existing AHG plugin — config |
| 1d | MISS Security Classification | 228,000 | Existing AHG plugin — config |
| 1e | Retention / Disposal Management | 378,000 | Existing AHG plugin — config |
| 1f | POPIA / NARSSA Audit Trail | 152,000 | Existing AHG plugin — config |
| 1g | Time-Limited Link Sharing | 218,000 | Reuses AHG share-token pattern |
| 1h | Continuous Ingestion API | 192,000 | Existing AHG plugin — config |
| 1i | Multi-Tenant "One Instance" | 180,000 | Existing AHG plugin — activation |
| 1j | Federated Search (AtoM + SharePoint) | 234,000 | Extends `ahgFederationPlugin` |
| 1k | Version Control | 184,000 | Reuses AHG version-snapshot pattern |
| **Add-on Subtotal** | | **3,406,000** | |

---

## 5. Item 2: AtoM Support and Maintenance (Year 1 + Year 2)

Phase 8 stabilisation plus Phase 9 ongoing support. Covers core AtoM and all installed add-on plugins.

**Year 1 (Aug 2026 – Jul 2027, includes go-live and hypercare):**

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| System Developer | 30 | 14,000 | 420,000 |
| Project Manager | 12 | 10,000 | 120,000 |
| Records Specialist | 8 | 12,000 | 96,000 |
| **Year 1 Support Subtotal** | | | **636,000** |

**Year 2 (Aug 2027 – Jul 2028, steady-state):**

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| System Developer | 48 | 14,000 | 672,000 |
| Project Manager | 24 | 10,000 | 240,000 |
| Records Specialist | 12 | 12,000 | 144,000 |
| **Year 2 Support Subtotal** | | | **1,056,000** |

**Item 2 Two-Year Total: R1,692,000**

---

## 6. Item 3: Training (Year 1 only)

Phases 5, 6, 7: System Administrator (2 sessions, 2 groups), Records Management Officials (4 sessions), End-User AtoM (4 sessions), End-User Digitisation (4 sessions, jointly delivered with scanner vendor).

| Resource | Days | Day Rate (R) | Subtotal (R) |
|---|---|---|---|
| Trainer and Support Specialist | 60 | 9,000 | 540,000 |
| Records Specialist | 20 | 12,000 | 240,000 |
| System Developer | 10 | 14,000 | 140,000 |
| Training material development | (lump sum) | | 180,000 |
| **Item 3 Subtotal** | | | **1,100,000** |

---

## 7. AHG Scope Pricing Summary

| Item | Description | Year 1 (R) | Year 2 (R) | Total (R) |
|---|---|---|---|---|
| 1a | Core AtoM 2.10 Configuration and Implementation | 1,230,000 | N/A | 1,230,000 |
| 1b | Add-on: SharePoint Online Connector | 1,350,000 | N/A | 1,350,000 |
| 1c | Add-on: POPIA / PAIA Privacy Compliance | 290,000 | N/A | 290,000 |
| 1d | Add-on: MISS Security Classification | 228,000 | N/A | 228,000 |
| 1e | Add-on: Retention / Disposal Management | 378,000 | N/A | 378,000 |
| 1f | Add-on: POPIA / NARSSA Audit Trail | 152,000 | N/A | 152,000 |
| 1g | Add-on: Time-Limited Link Sharing | 218,000 | N/A | 218,000 |
| 1h | Add-on: Continuous Ingestion API | 192,000 | N/A | 192,000 |
| 1i | Add-on: Multi-Tenant "One Instance" | 180,000 | N/A | 180,000 |
| 1j | Add-on: Federated Search (AtoM + SharePoint) | 234,000 | N/A | 234,000 |
| 1k | Add-on: Version Control | 184,000 | N/A | 184,000 |
| 2 | AtoM Support and Maintenance | 636,000 | 1,056,000 | 1,692,000 |
| 3 | Training | 1,100,000 | N/A | 1,100,000 |
| **AHG Subtotal (ex-VAT)** | | **6,372,000** | **1,056,000** | **7,428,000** |
| VAT (15%) | | 955,800 | 158,400 | 1,114,200 |
| **AHG Total (incl. VAT)** | | **7,327,800** | **1,214,400** | **8,542,200** |

**Comparison with original RFB-001 2026-2027.pdf plan:**

The original plan bundled all plugin development inside Item 1 (R2,580,000), did not separately price three GCIS-relevant requirements:

- Time-Limited Link Sharing (4.1.1.12.e) — silent gap.
- Multi-Tenant "One Instance" model (clause 2) — silent reliance on existing AHG plugin without acknowledgement.
- Version Control with diff/restore (4.1.1.3, 4.1.1.9) — silent gap; audit-trail data captured but not user-facing.

…and did not separately price the federated-search capability across AtoM and SharePoint that arises naturally from clause 4.6.5 and is a strong Gate 1 differentiator.

The re-framed plan:

- Itemises ten add-on plugins so the evaluator sees discrete deliverables (improves Gate 1 scoring on criterion 2 "System Functionality and configuration of AtoM" — weight 20).
- Adds three previously-unscoped capabilities, sized to reflect that AHG has proven the underlying patterns in adjacent production plugins (see "AHG pattern reuse" notes in items 1g, 1j, 1k):
  - Time-Limited Link Sharing — R218k (reuses AHG share-token pattern)
  - Federated Search — R234k (extends mature `ahgFederationPlugin`)
  - Version Control — R184k (reuses AHG version-snapshot pattern)
- Names the Multi-Tenant "One Instance" plugin (R180k) and credits AHG IP for it — a competitive differentiator that no other AtoM service provider in SA can match.
- Net change ex-VAT: +R2,056,000 (from R5,372,000 to R7,428,000).

If the additional cost is a concern, three adjustments are available:

1. **Defer Federated Search (Item 1j) to Year 2 change control** — caveat that 4.6.5 is delivered in a Phase 2 enhancement. Reduces Year 1 by R234,000. Federated search is not an explicit Gate 1 requirement, so this is the lowest-risk deferral.
2. **Defer Version Control UI (Item 1k) to Year 2** — the underlying audit-log data is captured by Item 1f from day one; the UI ships in Year 2. Reduces Year 1 by R184,000. Risk: Gate 1 criterion 2 scoring on 4.1.1.3 and 4.1.1.9 may drop from "exceeds expectations" to "meets requirements".
3. **Bundle Items 1c, 1d, 1f as a single "Compliance Plugin Suite"** without separate line items, presented as included in Item 1a. Doesn't change price, but presents as a simpler price line for procurement review.

A conservative defer-1j+1k scenario would land at R7,010,000 ex-VAT — only R1.64M above the original and with all 4.1.1.x requirements covered in Year 1.

---

## 8. Items Excluded from AHG Pricing (Scanner Vendor Scope)

| Item | Description |
|---|---|
| 4 | Digitisation of paper-based records (scanning operations) |
| 5 | Rent of two flatbed scanners including maintenance |
| 6 | Rent of one overhead scanner including maintenance |
| 7 | File handling, cleaning, and preparation tools |
| 8 | Assessment of GCIS records (volume and condition assessment) |

These are quoted by the scanner vendor partner. Item 8 (records assessment) is a judgement call — depending on partnership scope, AHG's Records Specialist may co-deliver this with the scanner vendor's records resource. If AHG co-delivers, add approximately 15 days × R12,000 = R180,000 to AHG scope.

---

## 9. Estimated Combined Bid Total

To be confirmed after scanner vendor pricing. Indicative range based on typical SA government digitisation contracts of this scope:

| Component | Indicative Range (R, ex-VAT) |
|---|---|
| AHG Scope (Items 1a–1k, 2, 3) | 7,428,000 |
| Scanner Vendor Scope (Items 4, 5, 6, 7, 8) | 4,500,000 – 7,500,000 |
| **Combined Bid Total (ex-VAT)** | **11,928,000 – 14,928,000** |
| **Combined Bid Total (incl. VAT)** | **13,717,200 – 17,167,200** |

The scanner vendor range is wide because it depends on volume of records (still to be assessed) and rental terms over 24 months.

---

## 10. Risks and Pricing Assumptions

**Pricing assumes:**

- SITA Private Cloud VMs are provisioned within 4 weeks of contract signature.
- GCIS file plan is provided in machine-readable format (Excel or CSV) within 2 weeks of project start.
- GCIS SharePoint Online tenant access (test and production) is provided within 4 weeks of project start with appropriate Microsoft Graph API permissions (Sites.Read.All, Files.Read.All as application permissions, admin-consented in Entra ID).
- Security clearances for AHG team complete within 8 weeks of contract signature.
- Training sessions are conducted at GCIS Head Office or virtually as agreed.
- Records assessment (Item 8) volumes and complexity emerge from the scanner vendor's assessment phase; AtoM configuration is sized for typical departmental scope and may require adjustment if volumes are exceptional.
- No price escalation for the duration of the contract per RFB clause 5.5.
- Hosting infrastructure costs (SITA VM charges) are paid by GCIS directly to SITA, not via AHG.
- Time-Limited Link Sharing (Item 1g) is built without Purview retention-label dependency. The SharePoint Connector (Item 1b) supports optional Purview-label gating but does not require GCIS to hold a Purview licence — the connector operates equally with or without label-based filtering (curator-driven manual transfer is the no-Purview fallback).

**Risks priced into the model:**

- SharePoint Graph API integration complexity (allowance built into Item 1b duration).
- SITA firewall rule changes adding lead time (allowance built into Phase 1).
- GCIS file plan complexity exceeding standard taxonomy depth (Phase 3 contingency).
- Time-Limited Link Sharing security review iterations (allowance built into Item 1g).

**Risks not priced (to be flagged in proposal):**

- Volume of digitisation records affecting AtoM performance tuning beyond standard configuration.
- Additional directorates, provinces or departments per the GCIS Digitisation Roadmap (4.1.2.6) — would be priced as Phase 2 enhancement under change control.
- AI services (HTR, NER, agentic OCR) — not in scope, available as future enhancement via `ahgAIPlugin`.
- Microsoft Purview licensing on the GCIS tenant — not required for the SharePoint Connector to operate, but enables retention-label gated automatic transfer if GCIS later licenses it.

---

## 11. Mapping of GCIS Requirements to AtoM Core and Plugin Add-ons

The table below maps every functional requirement in clauses 4.1.1.1 to 4.1.1.14 and 4.6 to either AtoM core functionality or a specific AHG plugin add-on, with the corresponding Item number from the pricing model.

| Clause | GCIS Requirement | Delivered by | Pricing Item |
|---|---|---|---|
| 2 (Background) | "Single instance" AtoM model on SITA Private Cloud | `ahgMultiTenantPlugin` (AHG IP, built for SITA/NARSSA) | 1i |
| 4.1.1.1 | Workflow automation + SharePoint integration | `ahgSharePointPlugin` | 1b |
| 4.1.1.2 | Import digitised non-active records from SharePoint | `ahgSharePointPlugin` | 1b |
| 4.1.1.3 | Secure retrieval, tracking, version management | AtoM core (retrieval) + `ahgAuditTrailPlugin` (tracking) + `ahgVersionControlPlugin` (version mgmt UI) | 1a + 1f + 1k |
| 4.1.1.4 | Metadata linkage between SP active and AtoM non-active | `ahgSharePointPlugin` | 1b |
| 4.1.1.5 | Batch uploads | `ahgIngestPlugin` (6-step wizard) | 1h |
| 4.1.1.6 | Automated archival per retention policy + API ingestion | `ahgExtendedRightsPlugin` + `ahgAPIPlugin` | 1e + 1h |
| 4.1.1.7 | Dublin Core + custom department-specific metadata | AtoM core (Dublin Core) + `ahgCustomFieldsPlugin` (included in 1a) | 1a |
| 4.1.1.8 | Configure per GCIS-Approved File Plan | AtoM core configuration | 1a |
| 4.1.1.9 | Tagging, indexing, version control, search/retrieval | AtoM core (tags, Elasticsearch) + `ahgVersionControlPlugin` (version control) | 1a + 1k |
| 4.1.1.10.a | Full-text search + advanced metadata filtering | AtoM core (Elasticsearch); optional federated extension across SharePoint | 1a + 1j (optional) |
| 4.1.1.10.b | Quick retrieval for audit, compliance, operations | AtoM core; federated search across active + non-active | 1a + 1j (optional) |
| 4.1.1.11 | Links to associated active records | `ahgSharePointPlugin` cross-reference + federated search | 1b + 1j |
| 4.1.1.12.a | RBAC, user authentication and authorisation | AtoM core (groups + ACL); tenant-aware filtering via Multi-Tenant | 1a + 1i |
| 4.1.1.12.b | Encryption at rest and in transit | SITA storage (at rest) + TLS (in transit) | 1a |
| 4.1.1.12.c | Stricter restrictions for Confidential records (MISS) | `ahgSecurityClearancePlugin` | 1d |
| 4.1.1.12.d | Access restricted to records management officials | AtoM ACL + `ahgSecurityClearancePlugin` + tenant scoping | 1d + 1i |
| 4.1.1.12.e | Time-limited link sharing, auto-revocation | `ahgTimeLimitedShareLinkPlugin` (NEW build) | 1g |
| 4.1.1.12.f | Detailed audit trails for access, sharing, modifications | `ahgAuditTrailPlugin` | 1f |
| 4.1.1.13.a | Automated/manual enforcement of retention schedules | `ahgExtendedRightsPlugin` | 1e |
| 4.1.1.13.b | Controlled disposal workflows with audit logs | `ahgExtendedRightsPlugin` | 1e |
| 4.1.1.14.c | POPIA + PAIA + NARSSA + GCIS information governance | `ahgPrivacyPlugin` + `ahgAuditTrailPlugin` | 1c + 1f |
| 4.1.1.14.d | Enforce legal and regulatory requirements | `ahgPrivacyPlugin` + `ahgExtendedRightsPlugin` | 1c + 1e |
| 4.1.1.14.e | Generate reports for audit purposes | `ahgAuditTrailPlugin` | 1f |
| 4.1.1.14.f | Access logs and user activity tracking | `ahgAuditTrailPlugin` | 1f |
| 4.1.1.14.g | Metadata integrity verification | `ahgAuditTrailPlugin` + AtoM core | 1f |
| 4.1.1.14.h | Retention status and lifecycle compliance | `ahgExtendedRightsPlugin` + `ahgAuditTrailPlugin` | 1e + 1f |
| 4.1.3.1 | Deployed exclusively in SITA Private Cloud | SITA infrastructure + `ahgMultiTenantPlugin` (joined or dedicated tenant) | 1a + 1i |
| 4.1.3.4 | Access restricted to authorised GCIS officials | AtoM ACL + `ahgSecurityClearancePlugin` + tenant scoping | 1d + 1i |
| 4.6.2 | Version control and audit trail management | `ahgAuditTrailPlugin` + `ahgVersionControlPlugin` | 1f + 1k |
| 4.6.5 | Records retrieval and searchability mechanisms | AtoM core search + Federated search across SP and AtoM | 1a + 1j |
| 4.6 (overall) | Records Management Strategy | All ten plugin add-ons together | 1b–1k |

**Result:** every GCIS functional requirement is mapped to either base AtoM or a named, priced AHG plugin add-on. No requirement is unaddressed.

---

## 12. Appendix: AHG Plugin Catalogue — Items Relevant to GCIS

The AHG AtoM plugin catalogue contains 80 plugins covering archival, museum, library, gallery and DAM sectors. The plugins below are the subset relevant to this GCIS bid. All are licensed to GCIS as part of this contract; configuration and customisation made for GCIS becomes GCIS intellectual property per clause 4.1.3.5.

- `ahgCorePlugin` — core framework integration (included with base AtoM, no separate cost)
- `ahgThemeB5Plugin` — Bootstrap 5 theme (included, no separate cost)
- `ahgDisplayPlugin` — display mode handling (included, no separate cost)
- `ahgUiOverridesPlugin` — UI helpers (included, no separate cost)
- `ahgCustomFieldsPlugin` — EAV custom metadata fields, no code required to add new fields per GCIS department (included in Item 1a configuration)
- `ahgIngestPlugin` — 6-step batch ingest wizard with AI processing options (Item 1h)
- `ahgAPIPlugin` — REST API endpoints + webhooks (Item 1h)
- `ahgSharePointPlugin` — SharePoint Online connector via Microsoft Graph (Item 1b)
- `ahgPrivacyPlugin` — POPIA / PAIA / GDPR / CCPA / PIPEDA / NDPA / DPA — seven jurisdictions (Item 1c)
- `ahgSecurityClearancePlugin` — MISS-aligned security classification, user clearance, embargo (Item 1d)
- `ahgExtendedRightsPlugin` — retention schedules, embargo processing, disposal workflows, RightsStatements.org, TK Labels (Item 1e)
- `ahgAuditTrailPlugin` — POPIA + NARSSA compliant audit logging (Item 1f)
- `ahgTimeLimitedShareLinkPlugin` v0.1.0 — **built and shipped May 2026**; HMAC-SHA256 tokens, expiry caps, admin UI, retention sweep, full audit dual-write (Item 1g)
- `ahgMultiTenantPlugin` — single-instance multi-tenant model, AHG IP built at SITA for NARSSA (Item 1i)
- `ahgFederationPlugin` + SharePoint federated search — **built and shipped May 2026** on Heratio; pluggable `PeerConnector` interface with OAI-PMH, AtoM local Elasticsearch and SharePoint Graph search connectors; result dedupe + source-attribution badges (Item 1j)
- `ahgVersionControlPlugin` v0.1.0 — **built and shipped May 2026**; per-record version capture, word-level diff, one-click restore, ACL gates, audit dual-write, 710 IO + 401 actor baselines backfilled (Item 1k)
- `ahgBackupPlugin` — backup/restore (included with base AtoM support, Item 2)
- `ahgReportsPlugin` — central reporting dashboard (included with base AtoM, no separate cost)
- `ahgStatisticsPlugin` — usage statistics tracking (included with base AtoM, no separate cost)
- `ahgSettingsPlugin` — centralised AHG settings management UI (included with base AtoM, no separate cost)

The included-with-base-AtoM plugins do not add cost but provide functionality that supports GCIS Gate 1 evaluation criterion 2 ("Functionalities meet all specified requirements and exceed expectations — 5 Points"). The full plugin catalogue is available for future GCIS scope expansion under change control.

---

## 13. Documentation and Screenshot Evidence (Gate 1 Criterion #2)

Clause 10.1.1.1 criterion 2 ("System Functionality and configuration of AtoM", weight 20) requires the bidder to submit AtoM system documentation with **relevant screenshots** as evidence that the system meets the requirements outlined in paragraphs 4.1.1.1 to 4.1.1.14. Scoring scale:

| Score | Threshold |
|---|---|
| 5 points | Functionalities meet all specified requirements **and exceed expectations** |
| 4 points | Meets at least 12 of 14 requirements |
| 3 points | Meets at least 8 |
| 2 points | Meets at least 6 |
| 1 point | Meets at least 4 |
| 0 points | No submission |

To target the **5-point** score, every requirement in 4.1.1.1–4.1.1.14 below must have at least one screenshot, and several requirements have multiple screenshots demonstrating that the implementation "exceeds expectations" (e.g. for 4.1.1.1 we show both the rules admin and a live cron-driven ingest log; for 4.1.1.12.c we show both classification on a record and the access-denied screen when a cleared user tries to view an over-classified record).

### How to use this section

1. AHG produces each screenshot from the PSIS demonstration instance (or the GCIS demo tenant once provisioned).
2. Save each screenshot using the suggested filename in `./screenshots/` relative to this document. PNG, at least 1600 px wide for legibility on print.
3. Reference each screenshot in the bid proposal next to the corresponding 4.1.1.x clause.
4. AHG reviews the assembled evidence pack before bid submission to confirm completeness.

### Screenshot index

| # | Clause | Evidence required | Capture point (URL / module) | Screenshot filename | What it must show |
|---|---|---|---|---|---|
| S1 | 4.1.1.1 | Workflow automation + SharePoint integration | `/sharepoint/rules` (list view) | `S1-sharepoint-rules-list.png` | List of auto-ingest rules with name, drive, cron schedule, last_run_at, items_ingested |
| S2 | 4.1.1.1 | Configured automation rule | `/sharepoint/ruleEdit/id/1` | `S2-sharepoint-rule-edit.png` | Rule edit form showing drive, folder path, file pattern, retention label, mapping template, cron, enabled |
| S3 | 4.1.1.1 | Live automation evidence | CLI output of `php symfony sharepoint:auto-ingest --rule=1` | `S3-sharepoint-auto-ingest-cli.png` | Terminal output: `rule=1 status=ok new=N skipped=N session_id=… job_id=…` |
| S4 | 4.1.1.2 | Imported non-active record from SharePoint | AtoM record view of a record sourced from SP | `S4-record-from-sharepoint.png` | AtoM information_object detail page with side-car showing `sp_drive_id`, `sp_item_id`, `sp_web_url` |
| S5 | 4.1.1.3 | Secure retrieval (login + view) | AtoM login → record view | `S5-secure-retrieval-login.png` | Login screen and the post-login record view (proves authenticated access) |
| S6 | 4.1.1.3 | Tracking | `/{slug}` record view → "Audit" / "Versions" tab | `S6-record-tracking-tab.png` | Versions tab showing chronological list of changes to the record |
| S7 | 4.1.1.3 | Version management — diff between two versions | Version diff view | `S7-version-diff.png` | Side-by-side diff highlighting changed fields between v3 and v5 |
| S8 | 4.1.1.3 | Version management — restore action | Version restore confirmation | `S8-version-restore.png` | Restore confirmation modal + post-restore audit entry showing the rollback |
| S9 | 4.1.1.4 | Metadata linkage between SP active and AtoM non-active | AtoM record showing SP back-link | `S9-metadata-linkage.png` | AtoM record sidebar with "View source in SharePoint" button + AtoM-side reference field |
| S10 | 4.1.1.5 | Batch uploads | `/ingest` 6-step wizard, Step 2 (Upload) | `S10-batch-upload-wizard.png` | Wizard upload step showing CSV/ZIP/EAD picker AND the "From SharePoint" tab |
| S11 | 4.1.1.5 | Bulk import in progress | `/ingest/jobStatus?id=N` | `S11-ingest-job-status.png` | Job status page with progress bar, rows processed, records created |
| S12 | 4.1.1.6 | Workflow for automated archival per retention | `/sharepoint/ruleEdit/id/1` retention-label section | `S12-retention-trigger-rule.png` | Rule form showing the "Only items carrying specific Purview retention label(s)" radio + label input |
| S13 | 4.1.1.6 | API for continuous ingestion | `/api/v2/` documentation page | `S13-api-documentation.png` | Swagger/OpenAPI doc listing the ingest + webhook endpoints |
| S14 | 4.1.1.7 | Dublin Core metadata | AtoM record edit page → Dublin Core fields | `S14-dublin-core-fields.png` | Edit form section "Dublin Core" with title, creator, subject, description, publisher, contributor, date, type, format, identifier, source, language, relation, coverage, rights |
| S15 | 4.1.1.7 | Custom metadata fields | `/admin/customFields` | `S15-custom-fields-admin.png` | Custom Fields admin with a GCIS-specific field defined (e.g. "Directorate code") |
| S16 | 4.1.1.7 | Custom field on a record | Information object edit page with custom field | `S16-custom-field-on-record.png` | Record edit form showing the custom "Directorate code" field with value populated |
| S17 | 4.1.1.8 | Configured per GCIS file plan | `/taxonomy/{slug-of-file-plan-taxonomy}` | `S17-gcis-file-plan-taxonomy.png` | Taxonomy tree view showing the GCIS file plan hierarchy imported into AtoM |
| S18 | 4.1.1.8 | Records placed per file plan | Information object hierarchy view | `S18-records-by-file-plan.png` | Record showing its placement under the relevant file plan node |
| S19 | 4.1.1.9 | Tagging | Record view showing applied tags / subject access points | `S19-record-tags.png` | Subject access points listed on record view |
| S20 | 4.1.1.9 | Indexing (Elasticsearch evidence) | `/search` results page or `php symfony search:status` output | `S20-elasticsearch-indexing.png` | Search result list with facets OR CLI showing indexed document count |
| S21 | 4.1.1.9 | Version control list | Same as S6 — "Versions" tab on a record | `S21-version-control-list.png` | Numbered version list with timestamps and authors |
| S22 | 4.1.1.10.a | Full-text search | `/search?query=…` | `S22-full-text-search.png` | Search results matching a term that appears in OCR'd PDF body text (proves full-text indexing, not just metadata) |
| S23 | 4.1.1.10.a | Advanced metadata filtering | `/search;advancedSearch` | `S23-advanced-search-filters.png` | Advanced search form with filters by date range, document type, repository, level of description |
| S24 | 4.1.1.10.a | Federated search across AtoM + SharePoint (exceeds expectations) | Federated search UI | `S24-federated-search.png` | Single search box returning results with source badges "AtoM (archived)" and "SharePoint (active)" |
| S25 | 4.1.1.10.b | Quick retrieval | Search → first result, time-to-result indicator | `S25-quick-retrieval.png` | Search result page showing response time (e.g. "12 results in 80 ms") |
| S26 | 4.1.1.11 | Links to associated active records | AtoM record sidebar | `S26-link-to-active-record.png` | AtoM record showing "View active record in SharePoint" button that opens the SP item |
| S27 | 4.1.1.12.a | RBAC — group/role list | `/admin/aclGroup` | `S27-rbac-groups.png` | List of ACL groups (Administrator, Records Manager, Researcher, Translator, etc.) with member counts |
| S28 | 4.1.1.12.a | RBAC — permissions matrix | `/aclGroup/{id}/edit` permissions tab | `S28-rbac-permissions.png` | Permissions checkbox matrix per repository / module |
| S29 | 4.1.1.12.b | Encryption in transit | Browser address bar + cert details | `S29-tls-encryption.png` | HTTPS lock icon + certificate detail panel showing TLS 1.2/1.3 |
| S30 | 4.1.1.12.b | Encryption at rest (SITA documentation) | SITA Private Cloud storage encryption page (vendor doc) | `S30-sita-encryption-at-rest.pdf` (PDF screenshot) | SITA service brief confirming encryption at rest on Private Cloud storage |
| S31 | 4.1.1.12.c | MISS classification on a record | Record edit page security clearance field | `S31-miss-classification-edit.png` | Record edit form with security classification drop-down set to "Confidential" |
| S32 | 4.1.1.12.c | Access-denied screen for over-classified record (exceeds expectations) | Anonymous user accessing classified record | `S32-classification-access-denied.png` | Access-denied page when a user without sufficient clearance attempts to view a "Top Secret" record |
| S33 | 4.1.1.12.d | Records management official access | Logged-in records management user view | `S33-records-management-access.png` | Record management dashboard accessible only to the records-management group |
| S34 | 4.1.1.12.e | Time-limited link issuance | "Share link" modal on record view | `S34-share-link-issue.png` | Share-link modal showing expiry date picker, recipient email, "Generate link" button |
| S35 | 4.1.1.12.e | Auto-revocation after expiry | Share-link list showing expired link | `S35-share-link-expired.png` | Admin list of share links with status "Expired" + audit entry confirming revocation |
| S36 | 4.1.1.12.f | Audit trail browser | `/admin/auditTrail` | `S36-audit-trail-list.png` | Audit log list with user, action, entity, timestamp columns |
| S37 | 4.1.1.12.f | Audit detail with old/new values | Click into audit row | `S37-audit-trail-detail.png` | Detail showing JSON old_values and new_values diff |
| S38 | 4.1.1.13.a | Retention schedule configuration | `/admin/retentionSchedule` | `S38-retention-schedules.png` | List of retention schedules per file plan category with retention period and disposition action |
| S39 | 4.1.1.13.a | Retention applied to a record | Record view → retention block | `S39-record-retention.png` | Record sidebar showing "Retention: 10 years from creation; expires 2036-05-11; action: review" |
| S40 | 4.1.1.13.b | Disposal workflow — pending review | Disposal review queue | `S40-disposal-review-queue.png` | List of records whose retention has expired, awaiting reviewer approval |
| S41 | 4.1.1.13.b | Disposal audit log | Audit entry for an executed disposal | `S41-disposal-audit-entry.png` | Audit log row "DISPOSE entity_id=… by user=… on date" with reviewer's approval reference |
| S42 | 4.1.1.14.c | POPIA / PAIA compliance dashboard | `/admin/privacy/dashboard` | `S42-popia-dashboard.png` | Privacy plugin dashboard showing POPIA jurisdiction active, PII patterns configured, DSAR queue |
| S43 | 4.1.1.14.c | PII scan result | `php symfony privacy:scan-pii` output | `S43-pii-scan-output.png` | CLI output listing records with detected PII (SA ID numbers, contact details) |
| S44 | 4.1.1.14.e | Audit report — POPIA | `/admin/auditTrail/report?type=popia` | `S44-popia-audit-report.png` | Generated POPIA audit report with summary statistics |
| S45 | 4.1.1.14.f | User activity report | `/admin/auditTrail/userActivity?user_id=N` | `S45-user-activity-report.png` | Per-user activity report with logins, accesses, modifications |
| S46 | 4.1.1.14.g | Metadata integrity verification | Verification job result | `S46-metadata-integrity.png` | Integrity check report listing records with missing or invalid required metadata |
| S47 | 4.1.1.14.h | Retention status / lifecycle compliance report | Lifecycle compliance report | `S47-lifecycle-compliance.png` | Report showing % of records compliant with retention schedule by category |
| S48 | clause 2 + 4.1.3.1 | Multi-tenant "One Instance" model | Tenant admin + tenant-isolated view | `S48-multi-tenant-admin.png` | Tenant admin showing two GCIS directorates as separate tenants with isolation evidence |
| S49 | 4.1.3.5 | GCIS IP ownership — settings export | AHG settings export showing config is portable | `S49-settings-export.png` | Settings/configuration export file proving GCIS can take their configuration with them |

### Suggested supplementary screenshots (push to "exceeds expectations" — 5 points)

| # | Showcasing | Capture point | Filename | What it must show |
|---|---|---|---|---|
| X1 | AHG plugin catalogue depth | `/admin/extensions` | `X1-plugin-catalogue.png` | AtoM extensions admin listing the active AHG plugins, demonstrating breadth |
| X2 | OAIS-aligned ingest packaging | Ingest commit job output | `X2-oais-sip-aip-dip.png` | File listing of generated `objects/`, `metadata/`, `manifest.json`, `premis.json` proving OAIS conformance |
| X3 | IIIF viewer for digitised content | `/iiif/viewer/…` | `X3-iiif-viewer.png` | High-res IIIF viewer with deep-zoom on a digitised page |
| X4 | API webhook to downstream system | Webhook delivery log | `X4-webhook-delivery.png` | Webhook delivery log showing successful POST to a downstream SP automation flow |
| X5 | Mobile/responsive UI | AtoM record on a phone-width browser | `X5-responsive-mobile.png` | Bootstrap 5 responsive view on mobile breakpoint |
| X6 | Backup and restore | `/admin/backup` dashboard | `X6-backup-restore.png` | Backup history list with sizes, dates, and a tested-restore indicator |

### Tabular evidence summary for the bid submission

A one-page evidence summary table should appear at the front of the AtoM functional documentation section of the bid, showing all 14 clauses of 4.1.1.x with a green checkmark and the corresponding screenshot reference numbers. This tells the evaluator at a glance that every requirement has evidence behind it before they read into the detail.

| Clause | Requirement (short) | Evidence screenshots | Status |
|---|---|---|---|
| 4.1.1.1 | Workflow automation + SP integration | S1, S2, S3 | ✓ |
| 4.1.1.2 | Import from SharePoint | S4 | ✓ |
| 4.1.1.3 | Secure retrieval + tracking + version mgmt | S5, S6, S7, S8 | ✓ |
| 4.1.1.4 | Metadata linkage | S9 | ✓ |
| 4.1.1.5 | Batch uploads | S10, S11 | ✓ |
| 4.1.1.6 | Automated archival + API | S12, S13 | ✓ |
| 4.1.1.7 | Dublin Core + custom fields | S14, S15, S16 | ✓ |
| 4.1.1.8 | GCIS file plan | S17, S18 | ✓ |
| 4.1.1.9 | Tagging + indexing + version control | S19, S20, S21 | ✓ |
| 4.1.1.10 | Search and retrieval | S22, S23, S24, S25 | ✓ |
| 4.1.1.11 | Links to active records | S26 | ✓ |
| 4.1.1.12 | Access control and security | S27, S28, S29, S30, S31, S32, S33, S34, S35, S36, S37 | ✓ |
| 4.1.1.13 | Retention and disposal | S38, S39, S40, S41 | ✓ |
| 4.1.1.14 | Compliance and audit | S42, S43, S44, S45, S46, S47 | ✓ |

This is **14 of 14 clauses with evidence** → targets 5 points on Gate 1 criterion 2 (weight 20).

---

*Document prepared by The Archive and Heritage Group (Pty) Ltd. Confidential — for AHG and GCIS evaluation use only.*

---

# APPENDIX A — AHG INTERNAL: Heratio Parity Plan

> **Not for GCIS submission.** Remove this appendix before sending the bid pack to GCIS.
>
> This appendix documents how the three new-development items (1g Time-Limited Sharing, 1j Federated Search, 1k Version Control) are ported to AHG's Heratio Laravel product line in parallel with the GCIS delivery. Heratio runs in the AHG product line and is unrelated to the GCIS deployment, which is base AtoM 2.10 on SITA Private Cloud per section 0 of this plan. The parity work below is for AHG's product roadmap; it does not appear on the GCIS bid or invoice.

## A.1 Why this matters

AHG maintains parallel Symfony (AtoM) and Laravel (Heratio) surfaces for every plugin in the AHG catalogue. The GCIS contract pays for the AtoM-side build of three new plugins (1g, 1j, 1k). To keep the product line coherent, the same capabilities must land on Heratio. The good news: Heratio already has substantial infrastructure for all three items, so the parity work is small and reuses production-proven patterns.

## A.2 Coverage audit — what Heratio already has

Database probe of `/usr/share/nginx/heratio` confirms the following existing infrastructure:

### A.2.1 Share tokens — production-ready patterns

| Existing Heratio table | Purpose | Shape (relevant columns) |
|---|---|---|
| `portable_export_share_token` | Time-limited download token for offline catalogue exports | `token`, `expires_at`, `max_downloads`, `download_count`, `revoked_at` |
| `report_share` | Time-limited report sharing with email recipients | `share_token`, `expires_at`, `access_count`, `is_active`, `email_recipients` |
| `ahg_report_share` | Internal report share variant | similar shape |
| `favorites_share` | Share user favorites collection | similar shape |
| `research_institutional_share` | Cross-institution research sharing | similar shape |

**Gap for Item 1g:** add `information_object_share_token` with the same shape as `portable_export_share_token`, plus controller, middleware and Blade view.

### A.2.2 Version snapshots — production-ready pattern

| Existing Heratio table | Pattern columns |
|---|---|
| `report_version` | `report_id`, `version_number`, `snapshot JSON`, `change_summary`, `created_by`, `created_at` |
| `atom_landing_page_version` | same pattern |
| `heritage_contribution_version` | same pattern |
| `ahg_report_version` | same pattern |

Plus tens of `_history` audit tables (`accession_valuation_history`, `ahg_contract_history`, `ahg_loan_status_history`, `password_history`, `search_history`, `security_clearance_history`, `spectrum_workflow_history`, etc.) showing the change-tracking discipline is consistent across the codebase.

**Gap for Item 1k:** add `information_object_version` and `actor_version` following the same `snapshot JSON + version_number + change_summary` pattern. Generic version observer service. Version list / diff / restore UI components.

### A.2.3 Federation infrastructure — production-ready, comprehensive

Eleven federation tables in Heratio:

| Table | Purpose |
|---|---|
| `federation_peer` | Registered peer systems with metadata |
| `federation_peer_search` | Per-peer search configuration |
| `federation_search_cache` | Cached aggregated search results |
| `federation_search_log` | Search activity audit |
| `federation_harvest_log` | OAI-PMH harvest activity audit |
| `federation_harvest_session` | OAI-PMH harvest session tracking |
| `federation_term_mapping` | Cross-peer vocabulary mapping |
| `federation_vocab_change` | Vocabulary change tracking |
| `federation_vocab_sync` | Vocabulary synchronisation state |
| `federation_vocab_sync_log` | Vocabulary sync audit |
| `oai_harvest` | OAI-PMH harvest registry |

Plus admin UI under `/federation` covering peers, harvests, search config and logs — all already production-deployed.

**Gap for Item 1j:** add a `SharePointGraphPeer` connector class implementing the existing `PeerConnector` interface; register SharePoint as a peer type. The federation cache, log, dedupe and aggregation layers do not change.

## A.3 Heratio parity build plan

All three items share the same activity shape: extend an existing Heratio Laravel package with a new component, mirroring the AtoM-side build. Work runs in parallel with the GCIS Phase 4 plugin development (Nov 2026 – Jan 2027).

### A.3.1 Item 1g — Heratio Time-Limited Share

**Package:** new sub-namespace inside `packages/ahg-core` or a new `packages/ahg-share-link` package (decision per AHG package-boundary conventions).

**Tasks:**

1. DDL: `CREATE TABLE information_object_share_token` mirroring `portable_export_share_token`.
2. Eloquent model: `InformationObjectShareToken`.
3. Controller: `InformationObjectShareController` with `issue()`, `access()`, `revoke()`, `list()`, `audit()` (mirror existing share controllers).
4. Middleware: `EnsureShareTokenValid` (token, expiry, revocation, max-access count).
5. Blade view: share modal on IO view page; admin list page; recipient landing page.
6. Console command: `ahg:share-token-expire-sweep` (cron sweep for expired tokens).
7. Hook into the Heratio audit log for issuance / access / revocation events.

**Effort:** 5 dev days. Driven by the existing AHG pattern, this is mostly file-creation against a known recipe.

### A.3.2 Item 1k — Heratio Version Control

**Package:** new sub-namespace inside `packages/ahg-information-object-manage` and `packages/ahg-actor-manage`, with shared service in `packages/ahg-core`.

**Tasks:**

1. DDL: `CREATE TABLE information_object_version` and `CREATE TABLE actor_version` mirroring `report_version`.
2. Eloquent observer: `EntitySnapshotObserver` captures snapshot on `saving` event for any registered model.
3. Shared service: `VersionService` with `snapshot()`, `list()`, `diff()`, `restore()` methods (mirror what the report plugin does today).
4. Blade components: `<x-version-list />`, `<x-version-diff />` reusable across model types.
5. Add "Versions" tab to IO edit view and Actor edit view (Blade `@push` into existing tabs slot).
6. Restore action with confirmation modal + ACL guard.
7. Migration to backfill version 1 for existing records (initial snapshot).

**Effort:** 8 dev days.

### A.3.3 Item 1j — Heratio SharePoint Federation Peer

**Package:** extends `packages/ahg-federation` and `packages/ahg-sharepoint`.

**Tasks:**

1. New class `AhgFederation\Connectors\SharePointGraphConnector` implementing the existing `PeerConnector` interface.
2. Register SharePoint as a peer type — UI extension to the existing `/federation/peers/add` form (peer-type select + Graph-specific config fields: tenant, app credentials reference, default site list).
3. Implement Graph Search API query in the connector (`POST /search/query`, parse hits into the existing federation result shape).
4. Update the `SharePointFederatedSearchController` in `packages/ahg-sharepoint` (currently a 503 stub returning "ships in Phase 3") to delegate to the federation layer.
5. Result rendering: extend the existing federation search view with the "SharePoint (active)" / "AtoM (archived)" source badge styling.
6. ACL: respect SharePoint per-item permissions returned by Graph + AtoM per-record ACL on AtoM hits.
7. Caching key strategy: extend existing `federation_search_cache` key to include `tenant_id` and `drive_id` scope.

**Effort:** 6 dev days. The federation infrastructure does the aggregation, caching, logging and audit; the connector is the only new piece.

## A.4 Heratio parity timeline

Runs in parallel with the GCIS Phase 4 development window. No additional cost on the GCIS bid; absorbed into the AHG product roadmap.

| Week (relative to GCIS Phase 4) | Heratio work |
|---|---|
| Week 1–2 | Item 1g Heratio share-token build (5 days) — mirrors AtoM-side Item 1g design |
| Week 3–4 | Item 1k Heratio version-control build (8 days) — mirrors AtoM-side Item 1k design |
| Week 5–7 | Item 1j Heratio SharePoint federation peer (6 days) — mirrors AtoM-side Item 1j design |
| Week 8 | Heratio regression test sweep + documentation update |

Total: ~19 dev days of Heratio parity work + 2–3 days documentation = budget within AHG's normal product line maintenance. No GCIS pricing impact.

## A.5 Cross-surface design discipline

Two locked rules apply to all three items:

1. **Same database column names** between AtoM (PSIS) and Heratio surfaces so future cross-surface migrations are trivial. Where the existing Heratio pattern differs from the planned AtoM-side build (e.g. Heratio uses `share_token` vs `token`), the AtoM-side plugin adopts the Heratio name and we document the convention in `atom-ahg-plugins/CLAUDE.md`.
2. **Same plugin/package name root** — `ahgTimeLimitedShareLinkPlugin` (AtoM) / `ahg-share-link` (Heratio), `ahgVersionControlPlugin` / `ahg-version-control`, `ahgFederationPlugin` SharePoint connector lives in `packages/ahg-federation/src/Connectors/`.

## A.6 No-push policy note

The SharePoint Connector code (Item 1b on the GCIS side, equivalent Heratio package) remains under the existing **SharePoint no-push policy** (memory: `sharepoint_no_push_policy.md`). Items 1g, 1j, 1k Heratio parity builds are NOT under no-push — they are general AHG product line work and are committed and released normally via `./bin/release`.

Item 1j is mixed: the SharePoint-peer connector portion stays no-push; the generic federation cache + log + UI extensions are committed normally. Split the commit accordingly when work lands.

---

*Appendix A is AHG internal. Not for distribution outside AHG.*
