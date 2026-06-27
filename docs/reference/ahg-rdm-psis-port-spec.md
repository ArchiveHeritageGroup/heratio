# PSIS / AtoM-AHG port spec — sovereign RDM + POPIA scan (ahgRdmPlugin)

Status: **PLAN / spec only.** The PSIS/archive project executes the build in
`ArchiveHeritageGroup/atom-ahg-plugins`. This document is the source-of-truth port
spec for taking the net-new Laravel-Heratio `ahg-rdm` module (epic heratio#1337,
Features 1-3 + dashboard filters heratio#1345) across to the AtoM-AHG (PSIS) platform
at `/usr/share/nginx/archive`. Authored from heratio-dev 2026-06-27.

Heratio-side as-built references (KM): `ahg-rdm-feature2-build.md`,
`ahg-rdm-feature1-dmp-link.md`, `ahg-rdm-feature3-dashboard.md`,
`ahg-rdm-feature2-spec.md`.

## Why a reverse port
`ahg-rdm` is net-new in Laravel Heratio with no AtoM source. PSIS/archive is still
the authoritative client-facing platform until Heratio is 100%, so the sovereign
RDM + POPIA-scan capability must exist there too. This is the one case where the
port runs Heratio -> AtoM rather than AtoM -> Heratio.

## Design principle (carried over verbatim)
`ahgRdmPlugin` = **thin orchestration over existing AtoM-AHG plugins**, NOT a new
sub-suite. It owns only the Dataset wrapper + the POPIA scan/gate/dashboard; every
other capability is wired from a plugin that already exists on the AtoM side.

## Dependency map (Heratio service -> AtoM-AHG plugin) - all VERIFIED present
| Heratio (Laravel) | AtoM-AHG (Symfony 1.4) plugin | Notes |
|---|---|---|
| `IngestService::ingestFile()` (ahg-ingest) | `ahgIngestPlugin/lib/Services/IngestService.php` | per-file deposit -> child IO + master digital_object |
| `InformationObjectService::create` (ahg-information-object-manage) | AtoM `QubitInformationObject` + ahgCorePlugin | container IO creation |
| `NerService` via ai.theahg.co.za gateway (ahg-ai-services) | `ahgAIPlugin` (ai module) | gateway-routed NER; NEVER a direct node port |
| `OcrService` / `PdfTextExtractService` | `ahgAIPlugin` / pdftotext on the AtoM host | text extraction for scanning |
| `OdrlService` + `OdrlPolicyMiddleware` (ahg-research) | `ahgResearchPlugin` (ODRL present) / `ahgRightsPlugin` / `ahgExtendedRightsPlugin` | access/embargo policies |
| `DmpService` (ahg-research) | `ahgResearchPlugin/lib/Services/DmpService.php` | **DMP builder already exists on AtoM** - Feature 1 wires it, never rebuild |
| `DoiService::mint` (ahg-doi-manage) | `ahgDoiPlugin` (doi module) | DataCite DOI, dry-run on non-prod |
| `AiDisclosureService` (ahg-research) | `ahgAIPlugin` / `ahgAiCompliancePlugin` | AI-provenance log |
| `ComplianceReportService` / dashboard (ahg-reports + ahg-rdm) | `ahgReportsPlugin` / new plugin views | scoreboard + dashboard |

PSIS project must CONFIRM each dependency's method signatures before wiring (these are
the AtoM equivalents, not guaranteed identical APIs).

## Data model (additive sidecar tables on the AtoM `atom` DB)
The rdm_* tables are AHG sidecar tables (NOT Qubit base tables), so they port
directly - no ALTER of any Qubit/AtoM base table (per the AtoM base-tables-read-only
rule). Create via the plugin's install SQL, idempotent (`CREATE TABLE IF NOT EXISTS`
+ guarded `ALTER` for added columns), mirroring the Heratio `install.sql`:
- `rdm_dataset` (project_id, **dmp_id**, io_parent_id, title, description, status,
  verdict, scanned_at, disposition + _by/_at, doi, created_by, timestamps)
- `rdm_dataset_file` (dataset_id, io_id, do_id, original_name)
- `rdm_scan_finding` (dataset_id, dataset_file_id, file_name, type, category, sample
  MASKED, confidence, method, review_status, reviewed_by/_at, decision_note)
- Dropdowns (AtoM term/taxonomy or the AHG dropdown equivalent - NEVER MySQL ENUM):
  `dataset_status`, `rdm_disposition`.

## Pipeline (identical semantics)
deposit -> POPIA scan -> human gate -> access/embargo + DOI -> public landing ->
compliance scoreboard -> DMP link -> roll-up dashboard.

## Services to port (the only real logic)
- **DatasetService** - create (container IO) + deposit (per-file via AtoM IngestService).
  Gotcha: AtoM CLI/cron workers must FORCE-COMMIT the Propel PDO transaction or
  digital_object inserts roll back at process exit (see the AtoM commit gotcha).
- **PopiaScanService** - deterministic-first (SA ID Luhn+date, email, SA phone,
  passport - masked) + special-category lexicon + gateway NER (AI-suggested,
  quota-guarded). Async via an AtoM job/task (NER exceeds request limits).
- **PopiaGateService** - human gate; open `release` BLOCKED while any PERSONAL/
  SPECIAL finding is pending or confirmed. Provenance via AiDisclosure.
- **DatasetReleaseService** - disposition as ODRL prohibition (restrict/de-identify)
  / embargo (date_to) on container + child IOs; DOI minted for ANY disposition
  (dry-run off-prod).
- **DmpLinkService** (Feature 1) - context/link/createAndLink/unlink over the AtoM
  `DmpService`. Writes only `rdm_dataset.dmp_id`; never touches research_dmp* except
  through DmpService. DMP is project-scoped + advisory (not a hard release gate).
- **ComplianceReportService** (Feature 2) - per-dataset scoreboard, filterable.
- **DashboardService** (Feature 3 + #1345 filters) - KPI roll-up + verdict/
  disposition/method/type breakdowns + 12-month deposit trend + per-faculty posture
  + human-gate backlog + recent; `from`/`to`/`institution` filters resolved to one
  dataset-id set scoping every aggregate; trend honours institution only.

## Surfaces (Symfony 1.4 modules/templates under the plugin)
- Auth module: dataset index, create/store, show (deposit + scan + findings + gate
  card + DMP card), deposit, scan, finding resolve, disposition, **dashboard**
  (Chart.js 4.4 via jsDelivr - same CDN the AtoM AI dashboards already use),
  **compliance** scoreboard.
- Public (no auth): `/research/datasets/{id}/landing` - DataCite-style citation, DOI,
  access badge, "Governed by a DMP [status]"; binaries stay gated.
- Add an "RDM" entry to the AtoM reports/research menu.
- Charts mirror `ahgAIPlugin` donut dashboard pattern for CSP/asset consistency.

## Demo
Port `ahg:rdm-demo` to an AtoM symfony task (`php symfony rdm:demo --fresh`) on the
same 100%-synthetic assets (Luhn-valid fake SA IDs, health transcript, consent PDF,
clean climate set). Acceptance: ~17 findings, clean files CLEAR, open release blocked
-> restrict -> DOI minted -> landing + scoreboard + dashboard + DMP linked.

## Phasing (mirror the Heratio epic order - lowest risk first)
1. Scaffold `ahgRdmPlugin` + Dataset model + deposit (AtoM IngestService).
2. PopiaScanService (deterministic + lexicon + gateway NER, async task).
3. Human gate + provenance (release blocked until resolved).
4. Access/embargo (ODRL) + DOI + public landing.
5. Compliance scoreboard.
6. Synthetic demo task.
7. Feature 1 - DMP link (wire AtoM DmpService).
8. Feature 3 - dashboard + filters.

## AtoM-specific gotchas to honour
- AtoM/Qubit base tables are READ-ONLY (no ALTER) - rdm_* are sidecar tables only.
- Force-commit the Propel transaction in CLI/cron (digital_object rollback bug).
- AtomFramework\... namespace (NOT AtomExtensions\...).
- All AI calls route through ai.theahg.co.za - never a direct GPU node port.
- Symfony 1.4 + PHP: no Laravel idioms; use the plugin's modules/actions/templates +
  lib/Services pattern (see ahgIngestPlugin/ahgResearchPlugin as the reference shape).
- opcache: restart, don't reload, on the AtoM host after deploys.

## Tracking
Epic + per-phase issues filed in `ArchiveHeritageGroup/atom-ahg-plugins`, cross-linked
to heratio#1337. This spec is the body-of-record; issues reference it.
