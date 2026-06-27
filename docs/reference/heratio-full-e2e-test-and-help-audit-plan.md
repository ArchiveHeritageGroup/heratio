# Heratio full E2E test + help-file audit plan

Status: **PLAN.** Scope (confirmed): **all modules/plugins** - the full 115-package
estate on heratio-dev. Every module gets a functional end-to-end smoke test AND a
help-file presence/accuracy check. Authored 2026-06-27.

Baseline today: 115 packages; 560 `help_article` rows from 550 `docs/help/*.md`.

## Objective
For every module: (1) prove the user-facing flow works end to end on heratio-dev,
(2) confirm a help article exists, is reachable from the module, and is accurate to
the current behaviour, (3) record defects with a tier + an issue. Exit when every
module is GREEN or its gaps are filed.

## Environment + ground rules
- Test on **heratio-dev** (`http://192.168.0.112:8090`, isolated `heratio_dev` DB,
  shared NAS, mail=log). NEVER test-mutate the public demo (heratio.theahg.co.za).
- Admin login required for admin flows; keep a known admin + a research/anon user for
  ACL checks.
- AI calls route through `ai.theahg.co.za` only.
- No DB writes outside the tested flow; SELECT freely.

## Per-module checklist (the unit of work)
For each package, record PASS/FAIL/N-A + notes against:

**Functional**
1. Routes resolve - `php artisan route:list | grep <prefix>`; no orphan/duplicate.
2. Index/landing renders (HTTP 200 authed; 302->login unauth where gated).
3. CRUD where applicable: create -> validation errors fire -> store -> show -> edit
   -> update -> delete, all via the service layer (no static HTML, real data).
4. Central theme applied (BS5 admin via ahgThemeB5 bundle; no alien Tailwind).
5. Dropdowns come from `ahg_dropdown` (no hardcoded `<option>`, no MySQL ENUM).
6. ACL: gated routes deny the anon/wrong-role user.
7. Module-specific happy path (e.g. search returns hits; ingest commits a row;
   DOI mints dry-run; scan produces findings).
8. No 500s / no `Read-only file system` (php-fpm drop-in) / no stale-opcache blade.

**Help**
9. A help article exists for the module (`help_article` slug) and is linked from the
   module UI / `/help`.
10. The article matches CURRENT behaviour (no stale screenshots, removed buttons,
    renamed routes).
11. Cross-site parity: the PSIS/AtoM help has the twin article (per the
    update-help-and-docs rule) - flag gaps, don't fix AtoM help from here.
12. If missing/stale: write/refresh `docs/help/<slug>.md` then `php artisan
    ahg:help-ingest-all` (upsert by slug).

## Test domains (10 tranches - run in this order; foundations first)
Dependencies flow downward, so a break in an earlier tranche explains later failures.

**T1 - Core / platform / theme** (everything depends on these)
ahg-core, ahg-theme-b5, ahg-settings, ahg-dropdown-manage, ahg-menu-manage, ahg-acl,
ahg-user-manage, ahg-multi-tenant, ahg-term-taxonomy, ahg-help, ahg-static-page,
ahg-landing-page

**T2 - Description / cataloguing (GLAM core)**
ahg-information-object-manage, ahg-display, ahg-search, ahg-semantic-search,
ahg-actor-manage, ahg-repository-manage, ahg-authority-resolution, ahg-condition,
ahg-provenance, ahg-custom-fields, ahg-ric, ahg-function-manage, ahg-functions-docs
(note: IO show tree is LOCKED - test only, no edits)

**T3 - Metadata standards / formats**
ahg-dacs-manage, ahg-dc-manage, ahg-mods-manage, ahg-rad-manage, ahg-biblio-bf,
ahg-biblio-frbr, ahg-metadata-export, ahg-metadata-extraction, ahg-export,
ahg-portable-export

**T4 - Sector verticals**
ahg-gallery, ahg-museum, ahg-library, ahg-dam, ahg-spectrum, ahg-heritage-manage,
ahg-loan, ahg-exhibition, ahg-marketplace, ahg-vendor, ahg-cart, ahg-label,
ahg-3d-model, ahg-image-ar

**T5 - Digital objects / media / preservation**
ahg-media-processing, ahg-media-streaming, ahg-pdf-tools, ahg-iiif-collection,
ahg-preservation, ahg-ocfl, ahg-integrity, ahg-c2pa, ahg-storage-manage,
ahg-ftp-upload, ahg-scan, ahg-ingest, ahg-dedupe

**T6 - Research / RDM**
ahg-research, ahg-researcher-manage, ahg-rdm, ahg-annotations, ahg-favorites,
ahg-share-link, ahg-request-publish, ahg-access-request
(ahg-rdm: run `php artisan ahg:rdm-demo --fresh` as the end-to-end harness)

**T7 - AI services**
ahg-ai-services, ahg-ai-chatbot, ahg-ai-compliance, ahg-provenance-ai,
ahg-inference-receipts, ahg-discovery
(verify gateway routing; note ahg-ai-chatbot's known gateway-bypass follow-up)

**T8 - Rights / compliance / records / jurisdiction modules**
ahg-rights, ahg-extended-rights, ahg-rights-holder-manage, ahg-doi, ahg-doi-manage,
ahg-records-manage, ahg-privacy, ahg-security-clearance, ahg-icip, ahg-cdpa,
ahg-ipsas, ahg-narssa, ahg-naz, ahg-nmmz, ahg-donor-manage, ahg-workflow,
ahg-audit-trail, ahg-version-control
(jurisdiction modules are pluggable per-market - test as optional, never SA-default)

**T9 - Interop / APIs / federation**
ahg-api, ahg-api-plugin, ahg-graphql, ahg-oai, ahg-z3950, ahg-resourcesync,
ahg-federation, ahg-sharepoint, ahg-gis
(APIs get full CRUD coverage regardless of PSIS page parity)

**T10 - Reporting / ops / admin**
ahg-reports, ahg-statistics, ahg-observability, ahg-jobs, ahg-jobs-manage,
ahg-backup, ahg-data-migration, ahg-forms, ahg-feedback, ahg-articles,
ahg-translation, ahg-accession-manage

## Tooling
- **Route map:** `php artisan route:list` (one capture, diff per tranche).
- **HTTP smoke:** scripted `curl -s -o /dev/null -w '%{http_code}'` over each
  module's key routes (authed cookie jar + anon), asserting 200/302/403 not 500.
- **Browser E2E:** headless `google-chrome`/playwright for JS-heavy pages (viewers,
  charts, clipboard, IIIF/Mirador) - the technique already used for Mirador checks.
- **Module harnesses:** the artisan demo/seed commands (ahg:rdm-demo,
  ahg:seed-scale-corpus, sector CSV importers, ahg:es-reindex) as E2E fixtures.
- **DB truth:** `DESCRIBE`/SELECT to confirm writes landed; never assume from UI.
- **Help:** `ahg:help-ingest-all` after any doc edit; grep `help_article` for slug
  coverage per module.

## Results tracking
- One **results matrix** (module x checklist) kept in
  `docs/reference/heratio-e2e-results-<date>.md`, updated per tranche.
- **Defect tiers** (reuse the mail-triage tiers): A = deterministic auto-fixable
  (lint/seed/selector), B = app-code change (default), C = flag-only (data/PII,
  security, third-party contract, locked hot paths).
- **Issue protocol:** file each confirmed defect as a heratio issue with the module
  label + tier; for behaviour that PSIS/AtoM should mirror, file the PSIS-parity twin
  in `atom-ahg-plugins`. Prefix `[ON HOLD]` when blocked on an unlock/decision.
- Locked surfaces (IO show tree, all .blade pages, ahg-reports, etc.): test only;
  any fix needs a per-change unlock - do not edit during the sweep.

## Sequencing + effort
- T1 first (a foundation break cascades). Then T2-T10 in order; T6/T7 depend on
  T1+T5. Each tranche: smoke all routes -> deep E2E the CRUD/happy-path -> help audit
  -> log results -> file defects.
- Rough budget: ~0.5-1.5 hrs/module depending on surface area; ~10 tranches.
  Parallelisable across agents by tranche (independent), with T1 done first and
  shared.

## Exit criteria
Every module: routes green, happy-path E2E pass (or defect filed with tier), help
article present + accurate (or `docs/help` gap filed + ingested), theme/dropdown/ACL
conventions honoured. Results matrix complete; defect issues filed; PSIS-twin gaps
filed in atom-ahg-plugins.
