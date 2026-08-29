# IPSAS Heritage Assets — Research module

Purpose

This note audits the Research module for IPSAS (International Public Sector Accounting Standards) heritage-asset readiness. It lists concrete gaps, traces incomplete code locations, suggests practical enhancements, and proposes a staged implementation plan. Place this file at: /usr/share/nginx/heratio/docs/research/ipsas-heritage-assets.md

1. Gaps (what is missing now)

- No dedicated heritage-asset valuation model: the Research module does not expose a linear model or lifecycle fields required by IPSAS for reporting heritage assets (acquisition cost, valuation method, revaluation schedule, impairment indicators).
- Lack of financial lifecycle events: acquisition, revaluation, impairment, disposal events that produce accounting entries are not modelled as Events with financial attributes or RiC links.
- No depreciation / revaluation schedule engine: IPSAS heritage assets often need revaluation (not depreciation) and an engine to schedule revaluations and compute fair value adjustments is missing.
- Missing audit trail for valuation decisions: appraisal, expert valuations, provenance of valuation evidence, and who authorised a revaluation are not captured with the required financial metadata.
- Limited reporting connectors: no standard export to accounting journals / General Ledger (GL) feeds, no ICS/IPSAS-report-ready CSV/JSON exporters.
- Compliance-driven metadata gaps: fields for asset class, custodial responsibility, legal ownership, donor restrictions, restrictions on disposal, insurance valuation, and restoration liability are incomplete or scattered across modules.

2. Incomplete code (where partial/stubbed implementations exist)

- packages/ahg-research/src/Services — no ResearchHeritageAssetService exists; some services (ResearchService, DonorService, RiC export) contain partial mapping logic but not IPSAS-focused attributes.
- packages/ahg-research/database/install.sql — research-related tables exist but do not include finance-specific columns (fair_value, valuation_date, valuation_method, revaluation_reserve_account, impairment_flag).
- packages/ahg-ric/exports — RiC exporter includes research Activity/Agent/Event mapping but lacks a mapping for financial Events (revaluation/impairment) and accounting metadata.
- tmp/ and patches — there are several design notes and tmp patches referencing "valuation" and "asset" but no committed migration or service scaffolds for heritage accounting.

3. Enhancements and suggested features (concrete, pragmatic)

- HeritageAsset model + migration
  - Add a focused research_heritage_asset table: id, research_id, asset_class, legal_owner_id (agent FK), custodial_agent_id, acquisition_date, acquisition_cost, valuation_method, fair_value, valuation_date, revaluation_frequency (years), impairment_flag, insurance_value, disposal_restrictions (json), accounting_reference (journal_id), created_by, created_at, updated_at.

- Financial Event modelling
  - Model events: acquisition, revaluation, impairment, disposal. Each Event captures: event_type, event_date, amount (positive/negative), rationale, evidence (attachment ids), authorised_by, accounting_journal_reference. Emit these as RiC Events and link to the research_heritage_asset node.

- Valuation engine and scheduler
  - A small ValuationService that computes fair_value updates either via manual input (expert valuation) or via configured oracle (e.g. market index, third-party appraisal integration). Schedule revaluation jobs and emit Events and accounting entries.

- Accounting integration
  - Export hooks: generate an accounting journal entry CSV (date, account_debit, account_credit, amount, description, reference) consumable by GL systems. Implement a ResearchAccountingAdapter interface with implementations for CSV and a stub for direct GL push.

- Provenance & audit for valuations
  - Every valuation decision must write a provenance row (who requested, which evidence attached, modelUsed/null if manual, confidence score if automated). Link provenance to RiC Events and to the research_heritage_asset.

- Compliance & policy enforcement
  - Per-asset mandatory checks before disposal (donor restrictions, legal holds). Implement pre-disposal policy check that queries donor_agreement_restriction and other modules.

- Reports & disclosures
  - IPSAS reports: heritage asset register (list with valuation history), revaluation surplus/deficit report, impairment summary, disposals log. Export-ready CSV/XLS and a printer-friendly disclosure page.

4. Staged implementation plan (PRs)

PR A — HeritageAsset model + migration (small)
- Add migration: packages/ahg-research/database/migrations/xxxx_xx_xx_create_research_heritage_asset_table.php
- Add model: packages/ahg-research/src/Models/ResearchHeritageAsset.php
- Add basic CRUD endpoints: ResearchHeritageAssetController (index/show/create/update/delete) and Blade CRUD views (minimal).
- Acceptance: model exists, migrations run, basic CRUD works via web UI.

PR B — Financial Events & Provenance wiring (medium)
- Add migration for research_heritage_events table (event_type, amount, evidence_json, authorised_by, accounting_ref, created_at).
- Implement ResearchHeritageAssetService::recordEvent($assetId,$type,$data,$actor) which writes event and provenance (ai_provenance or dedicated provenance table).
- Acceptance: ability to create a revaluation Event via UI; event appears in asset history and provenance recorded.

PR C — Valuation engine + scheduler (medium)
- Implement ValuationService + CLI job (php artisan research:revalue --asset=ID or bulk). Support manual appraisal input and stubbed oracle adapter.
- Schedule via cron; create a revaluation queue job to run off-peak and emit Events + accounting entries.
- Acceptance: scheduled revaluation job runs and records Events.

PR D — Accounting Adapter + export (medium)
- Implement ResearchAccountingAdapterInterface and CSV adapter; add export route /research/heritage/{id}/export-journal.
- Acceptance: downloadable CSV with balanced debit/credit lines for revaluation/impairment events.

PR E — Policy checks & reports (larger)
- Pre-disposal policy checks, IPSAS disclosure generator, register exporter, and tests.
- Acceptance: a report page lists assets and their valuation history and a disposal attempt blocked if restrictions present.

5. Acceptance criteria & safety

- No automatic disposal or accounting effect occurs without an Event recorded and an authorised_by non-null value. Manual steps require a logged operator with appropriate policy group.
- Migrations must run safely; add a backfill script if adoption will live-migrate large installations (default values for existing rows: acquisition_cost and fair_value set to 0, impairment_flag false).
- Every financial Event links to an accounting export entry and RiC Event for archival provenance.

6. Quick wins

- Add the migration and simple model first (PR A). This unlocks forms and reports quickly.
- Add Event recording (PR B) with a simple UI to create a revaluation Event and require an authorised signer.
- Add an accounting CSV exporter for manual import to GL systems (PR D) so finance teams can consume the data immediately.

7. Files to read / touch (where to implement)

- packages/ahg-research/database/migrations/
- packages/ahg-research/src/Models/ResearchHeritageAsset.php
- packages/ahg-research/src/Services/ResearchHeritageAssetService.php
- packages/ahg-research/src/Controllers/ResearchHeritageAssetController.php
- packages/ahg-research/resources/views/research/heritage/* (index/show/edit/history)
- packages/ahg-ric (to add mapping for events and asset nodes)

Status: very good

Next action — outstanding issue to work on
1. Implement PR A: add ResearchHeritageAsset model + migration + CRUD.  
2. Implement PR B: add Event model + provenance wiring (revaluation/impairment).  
3. Implement PR D: add accounting CSV adapter & export endpoint.  
4. Nothing — hold and schedule a release window.

Reply with the single digit (1–4) to pick which PR to start.