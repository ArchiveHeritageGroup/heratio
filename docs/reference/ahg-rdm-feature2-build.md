# ahg-rdm Feature 2 — as-built (sovereign RDM + POPIA scan)

Built 2026-06-25/26 on heratio-dev, released to main. Closes epic #1337 (children
#1338–#1343). Spec/source-of-truth: `ahg-rdm-feature2-spec.md`. This is the
as-built record.

## What it is
A new `packages/ahg-rdm` package — a **thin orchestration layer** (no new storage,
NER, DOI, or ODRL machinery; it wires existing services) for sovereign,
POPIA-resident research-data deposit with an AI-assisted, **human-gated** POPIA
sensitivity scan. Net-new code, so it touches no locked paths.

## Pipeline
deposit → POPIA scan → human gate → access/embargo + DOI → public landing → compliance scoreboard.

## Data model (`database/install.sql`, boot-installed + idempotent guarded ALTERs)
- `rdm_dataset` — `project_id` (→ research_project), `io_parent_id` (container IO),
  `status`, `verdict` (CLEAR|PERSONAL|SPECIAL_CATEGORY), `disposition`
  (restrict|embargo|de-identify|release) + `_by`/`_at`, `doi`, `scanned_at`.
- `rdm_dataset_file` — links each deposited file to its child IO + master DO.
- `rdm_scan_finding` — `{type, category, sample(masked), confidence, method}` +
  `review_status` (pending|confirmed|dismissed) + `reviewed_by`/`_at`/`decision_note`.
- Dropdowns (ahg_dropdown, no ENUM): `dataset_status`, `rdm_disposition`.

## Services (the only real logic)
- **DatasetService** — `create()` builds a container information_object via
  `InformationObjectService::create`; `deposit()` streams each upload through
  `IngestService::ingestFile()` (→ child IO + master `digital_object`). No bespoke
  storage. (Gotcha: stage path via `Storage::path()` — Laravel 11/12 local disk
  root is `storage/app/private`.)
- **PopiaScanService** — deterministic-first (SA ID Luhn+date, email, SA phone,
  passport — masked), special-category lexicon (health/religion/biometric), NER
  augmentation via `NerService` (gateway-routed, `QuotaExceededException`-guarded,
  AI-suggested). Text via direct read (decrypt-at-rest gate) / `PdfTextExtractService`
  (pdftotext) / `OcrService` (tesseract). Scanned/image-only PDFs (empty text layer,
  <24 chars) fall back to rasterise (pdftoppm, 200 dpi, 10-page cap) → OCR; all
  OCR-derived findings (incl. image files) are demoted one confidence notch as a
  lower-trust marking (#1346). Async via `ScanDatasetJob` (NER exceeds
  request limits). Persists findings + sets the dataset verdict.
- **PopiaGateService** — the authority. `resolveFinding()` confirm/dismiss;
  `setDisposition()` enforces the gate: **open `release` is blocked while any
  PERSONAL/SPECIAL finding is pending or confirmed**. Provenance: finding/dataset
  audit columns (always-on) + `AiDisclosureService::addLogEntry` when project-linked.
- **DatasetReleaseService** — applies the disposition as ODRL: restrict/de-identify
  → `prohibition` on use+reproduce for container + child IOs; embargo → same with
  `constraints_json.date_to` (auto-reopens); release → policies cleared. Mints a
  DataCite DOI for **any** disposition (a restricted dataset is still citable) via
  `DoiService::mint(dryRun)` when an `ahg_doi_config` exists (no external call on
  dev), else a `10.5072/...` draft fallback.
- **ComplianceReportService** — read-only scoreboard (verdict, findings, access,
  DOI, faculty=`research_project.institution`), filterable.

## Surfaces (all under /research/datasets, 'research' excluded from the /{slug} catch-all)
- Auth: index, create/store, show (deposit + scan button + findings + human-gate
  card), deposit, scan, finding resolve, disposition, **compliance** scoreboard.
- Public (no auth): **`/research/datasets/{id}/landing`** — DataCite-style citation,
  DOI, access-status badge; binaries stay gated.
- ahg-reports dashboard gets an "RDM Compliance" link (gated by research plugin).

## Enforcement reality
ODRL gates the IO `odrl:use` public show (`isPermitted(use, anon)=DENY` when
restricted). The raw binary download route is NOT odrl-wrapped, so the public
landing exposes no downloads for restricted datasets (defence in the rdm layer).

## Demo — one command
`php artisan ahg:rdm-demo --fresh` on 100%-synthetic assets (`resources/demo/`:
survey CSV with Luhn-valid fake SA IDs, transcript with health+names, text-layer
consent PDF, clean climate set). Verified: 17 findings (PDF SA ID + 3 CSV SA IDs +
emails/phones deterministic, health lexicon, names/places NER), climate+readme
CLEAR, open release blocked, restrict applied, DOI minted, landing + scoreboard.

## Known follow-ups (not in Feature 2)
- ~~Scanned-PDF OCR (rasterize → tesseract)~~ — DONE (#1346, 2026-06-27): empty
  text layer falls back to pdftoppm raster → OCR; OCR findings demoted one notch;
  demo set gains an image-only `consent_form_scanned.pdf` exercising the path.
- ~~Binary-download gating beyond the landing~~ — DONE (#1347, 2026-06-27): the raw
  byte routes (`media-streaming.stream` — public; `io.digitalobject.stream` — authed)
  now call `OdrlService::isDigitalObjectPermitted($doId,'use')`, which maps the
  digital_object → parent IO and enforces the same prohibition the IO show page
  uses (admin/group-100 bypass; open objects carry no policy, so it's a no-op).
  Verified: a restricted dataset's file returns 403 to anon on the public route
  while open objects pass through.
- Feature 1 (DMP tool) and Feature 3 (full dashboard) — later. DMP linkage is shown
  via the linked research project for now.
- ~~Production DOI: swap `DoiService::mint` dry-run → live~~ — DONE (#1348,
  2026-06-27): live vs dry-run is now decided by `ahg_doi_config.environment`
  (the env, not the code) — a real DataCite mint fires only when the active
  config is `production`/`prod`/`live`; `test`/dev/demo stay dry-run. Default
  OFF; flipping to live once real creds land is an ops action, no code change.
  Verified: demo (env=`test`) still yields the dry-run real-prefix DOI and
  registers nothing (`ahg_doi` stays empty).
