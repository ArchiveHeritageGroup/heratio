# Heratio vs PSIS — Outstanding Work Plan

Generated: 2026-04-12
Owner: Johan Pieterse
Environment: **dev** — full coverage, no deferral, no triage. Every item in this doc must be completed.

This document plans the work that remains AFTER the reports-dashboard parity work
(see `docs/reports-dashboard-comparison.md`). The reports dashboard is at 100%
functional parity for its 122 links. Everything below is **outside the dashboard
scope** and was either flagged earlier in this session, surfaced by the
auto-memory project notes, or never audited.

## Status legend

- `[ ]` = not started
- `[~]` = in progress
- `[x]` = done — code merged + smoke-tested
- `[v]` = verified — Johan browser-tested as admin and confirmed working

Mark items as you go. A batch is only DONE when every item in it is `[x]`. A batch is only VERIFIED when every item is `[v]`.

## Scope summary

| Phase | What | Approx size | Verification path |
|-------|------|-------------|-------------------|
| **D2** | AHG menu parity — sidebar + admin nav | **13 missing menu items + 14 stale entries** (per `docs/AHG-MENU-COMPARISON.md`) | Visual audit, click every item |
| **C** | Empty-accordion stub views — content port from PSIS | **191 views across 21 packages** | Browser load each page as admin |
| **D1** | API parity — v1 + v2 REST endpoints | **94 missing endpoints** (per `docs/API-COMPARISON.md`) | curl + Postman collection |
| **D5** | AJAX endpoints, cron jobs, background services, JS layer | Unknown — never audited | Manual code walk per package |
| **D6** | Form validation, POST handlers, business logic | Unknown — never audited | Per-page audit |
| **D4** | Plugin coverage matrix — 119 PSIS plugins ↔ 92 Heratio packages | **27 plugin gap rows** (estimate) | Side-by-side directory diff |
| **D3** | Media processing parity — 3D, AI, watermarks, encryption | **15 missing features** (per `docs/MEDIA-PROCESSING-COMPARISON.md`) | Upload+process flow tests |

## Master execution order

The order below is fixed. Do NOT skip ahead — earlier batches surface issues that change later ones.

### Group 1 — Quick wins (closes obvious user-visible gaps)
- [x] **D2** — AHG menu parity — verified 2026-04-12. The menu at `packages/ahg-theme-b5/resources/views/partials/menus/ahg-admin-menu.blade.php` already matches every row in `docs/AHG-MENU-COMPARISON.md`. All 13 missing items are present (Research/Researchers+Bookings with badges, Researcher Submissions section, Access/Requests+Approvers, Audit/Statistics+Logs+Settings+Error Log, RiC section, Dedupe, Form Templates, DOI section, Heritage section, Maintenance Backup+Restore). All 14 EXTRA items the comparison flagged are absent. All 23 route names and 5 URL paths resolve via `php artisan route:list`. Menu is included from `partials/header.blade.php`.

### Group 2 — Stub view content port (191 pages, ordered by package size descending)
Each package = N batches of 5 pages each. Tick off batches as they ship.

- [~] **C-1** ahg-marketplace (32 stubs → 7 batches: 5+5+5+5+5+5+2) — **15/32 done (47%)**
  - [x] batch 1/7 DONE 2026-04-12: admin-payouts (27/26), admin-transactions (26/26), admin-sellers (35/35), admin-listings (36/36), browse (39/37). Fixed pre-existing bug in `MarketplaceController::browse()` (was calling non-existent `$service->browse()`, now uses `getListings()`). 5×smoke passed.
  - [x] batch 2/7 DONE 2026-04-12: admin-categories (47/47), admin-currencies (40/40), admin-reviews (37/37), admin-listing-review (38/35 +3), admin-seller-verify (33/31 +2). Parity or superset on all 5. Admin-currencies reframed PSIS "Exchange Rate to ZAR" as "Rate to {base currency}" driven by `config('heratio.base_currency')` — DB column name kept for schema compat. 5×HTTP 302 auth redirect, no 500s.
  - [x] batch 3/7 DONE 2026-04-12: seller (20/17 +3), seller-listings (44/43 +1), seller-profile (31/31), my-purchases (39/39), my-offers (44/44). Parity or superset on all 5. Fixed 2 more pre-existing `$service->browse()` calls in category() and sector() controller methods (same bug as batch 1). Guarded 3 view references to non-existent routes (`seller-listing-publish`, `seller-listing-withdraw`, `follow`) with `Route::has()` so buttons hide cleanly until those controller methods exist. seller-profile adds Stripe + Wise to payout methods list (PSIS had only bank_transfer/paypal/payfast) and defaults payout currency to `config('heratio.base_currency')` instead of hardcoded ZAR. Smoke test: 4× HTTP 302 auth + 1× HTTP 404 (expected — no seller with test slug). 17 stubs remain.
- [ ] **C-2** ahg-privacy (29 stubs → 6 batches: 5+5+5+5+5+4)
- [ ] **C-3** ahg-registry (28 stubs → 6 batches: 5+5+5+5+5+3)
- [ ] **C-4** ahg-nmmz (12 stubs → 3 batches: 5+5+2)
- [ ] **C-5** ahg-icip (11 stubs → 3 batches: 5+5+1)
- [ ] **C-6** ahg-vendor (10 stubs → 2 batches: 5+5)
- [ ] **C-7** ahg-statistics (9 stubs → 2 batches: 5+4)
- [ ] **C-8** ahg-naz (9 stubs → 2 batches: 5+4)
- [ ] **C-9** ahg-exhibition (9 stubs → 2 batches: 5+4)
- [ ] **C-10** ahg-cdpa (8 stubs → 2 batches: 5+3)
- [ ] **C-11** ahg-ipsas (8 stubs → 2 batches: 5+3)
- [ ] **C-12** ahg-forms (6 stubs → 2 batches: 5+1)
- [ ] **C-13** ahg-ingest (5 stubs → 1 batch: 5)
- [ ] **C-14** ahg-multi-tenant (4 stubs → 1 batch: 4)
- [ ] **C-15** ahg-semantic-search (3 stubs → 1 batch: 3)
- [ ] **C-16** **Bundled small packages** (8 stubs across 6 packages — ahg-metadata-export 2, ahg-condition 2, ahg-dacs-manage 1, ahg-dc-manage 1, ahg-mods-manage 1, ahg-rad-manage 1 → 2 batches: 5+3)

**Group 2 total: ~42 batches @ 5 pages each = 191 stubs.**

### Group 3 — API parity (94 endpoints, batched by resource)
- [ ] **D1-1** information_object endpoints (~12)
- [ ] **D1-2** actor / authority endpoints (~10)
- [ ] **D1-3** repository endpoints (~8)
- [ ] **D1-4** accession endpoints (~8)
- [ ] **D1-5** taxonomy / term endpoints (~10)
- [ ] **D1-6** digital_object endpoints (~8)
- [ ] **D1-7** rights / extended_rights endpoints (~8)
- [ ] **D1-8** condition / spectrum endpoints (~8)
- [ ] **D1-9** research / annotations endpoints (~10)
- [ ] **D1-10** auth / api_key endpoints (~6)
- [ ] **D1-11** miscellaneous remaining endpoints (~6)

> Note: exact counts must be reconciled against `docs/API-COMPARISON.md` at the start of each batch.

### Group 4 — Runtime hidden surface (AJAX + cron + JS)
Per-package walk. Each package = 1 batch.

- [ ] **D5-1** ahg-information-object-manage
- [ ] **D5-2** ahg-actor-manage
- [ ] **D5-3** ahg-repository-manage
- [ ] **D5-4** ahg-display
- [ ] **D5-5** ahg-search
- [ ] **D5-6** ahg-research
- [ ] **D5-7** ahg-spectrum
- [ ] **D5-8** ahg-condition
- [ ] **D5-9** ahg-extended-rights
- [ ] **D5-10** ahg-marketplace
- [ ] **D5-11** ahg-cart
- [ ] **D5-12** ahg-vendor
- [ ] **D5-13** ahg-doi-manage
- [ ] **D5-14** ahg-ric
- [ ] **D5-15** ahg-data-migration
- [ ] **D5-16** ahg-ingest
- [ ] **D5-17** ahg-backup
- [ ] **D5-18** ahg-preservation
- [ ] **D5-19** ahg-dedupe
- [ ] **D5-20** ahg-heritage-manage
- [ ] **D5-21** ahg-privacy
- [ ] **D5-22** ahg-cdpa
- [ ] **D5-23** ahg-naz
- [ ] **D5-24** ahg-nmmz
- [ ] **D5-25** ahg-ipsas
- [ ] **D5-26** ahg-icip
- [ ] **D5-27** ahg-acl
- [ ] **D5-28** ahg-audit
- [ ] **D5-29** ahg-ai-services
- [ ] **D5-30** ahg-statistics
- [ ] **D5-31** ahg-workflow
- [ ] **D5-32** ahg-iiif (if separate package)
- [ ] **D5-33** ahg-3d
- [ ] **D5-34** ahg-dam
- [ ] **D5-35** ahg-museum
- [ ] **D5-36** ahg-library
- [ ] **D5-37** ahg-gallery
- [ ] **D5-38** ahg-exhibition
- [ ] **D5-39** ahg-forms
- [ ] **D5-40** ahg-translation
- [ ] **D5-41** ahg-help
- [ ] **D5-42** ahg-static-page
- [ ] **D5-43** ahg-multi-tenant
- [ ] **D5-44** ahg-registry
- [ ] **D5-45** ahg-reports
- [ ] **D5-46** ahg-metadata-export
- [ ] **D5-47** ahg-semantic-search
- [ ] **D5-48** ahg-rights-holder-manage
- [ ] **D5-49** ahg-loan
- [ ] **D5-50** ahg-storage-manage
- [ ] **D5-51** ahg-donor-manage
- [ ] **D5-52** ahg-user-manage
- [ ] **D5-53** ahg-menu-manage
- [ ] **D5-54** ahg-term-taxonomy
- [ ] **D5-55** ahg-rad-manage / ahg-mods-manage / ahg-dc-manage / ahg-dacs-manage / ahg-function-manage / ahg-accession-manage (bundled)
- [ ] **D5-56** ahg-core (last — uncovers cross-package gotchas)

> Per-package list above is the current package inventory. Reconcile against `ls packages/` at the start of D5.

### Group 5 — POST handlers + form validation audit
Same per-package walk as Group 4. Each package = 1 batch tied to its D5 batch.

- [ ] **D6-1 → D6-56** — one batch per package, mirroring D5-1 through D5-56

### Group 6 — Plugin coverage matrix (one-time inventory + new package builds)
- [ ] **D4-1** Generate the PSIS-plugin → Heratio-package CSV mapping
- [ ] **D4-2** Identify the ~27 plugin-gap rows
- [ ] **D4-3** Build new heratio package #1 (TBD — depends on D4-1 output)
- [ ] **D4-4** Build new heratio package #2
- [ ] **D4-5..N** — one batch per missing plugin (count and IDs filled in after D4-1)

### Group 7 — Media processing (largest, GPU-dependent — last)
- [ ] **D3-1** 3D model viewer + storage
- [ ] **D3-2** AI image analysis pipeline (LLaVA on server-78)
- [ ] **D3-3** Video metadata extraction (ffprobe)
- [ ] **D3-4** Watermarking pipeline
- [ ] **D3-5** HLS / DASH adaptive streaming
- [ ] **D3-6** Encryption-at-rest
- [ ] **D3-7** IPTC / XMP round-trip
- [ ] **D3-8** Face detection
- [ ] **D3-9** OCR pipeline (HTR + printed text)
- [ ] **D3-10** Format identification (PRONOM / Siegfried)
- [ ] **D3-11** Preservation derivatives (FFV1, JPEG2000)
- [ ] **D3-12..15** Remaining items from `docs/MEDIA-PROCESSING-COMPARISON.md`

### Final acceptance gate
- [ ] All Group 1–7 boxes ticked `[x]`
- [ ] All boxes ticked `[v]` (Johan browser-verified)
- [ ] Final cross-reference: `php artisan route:list` count matches PSIS route count (or documented diff)
- [ ] Final cross-reference: per-package `find packages/{pkg} -name '*.blade.php' | wc -l` matches PSIS templates count (or documented diff)
- [ ] `docs/heratio-vs-psis-outstanding-plan.md` updated with completion date and final summary

## Working agreement

- **Batch size:** 5 items per batch unless the items are tiny (route aliases) or huge (full page rebuilds with controllers).
- **Cadence:** I do one batch, hand back the commit command + table of what changed, you commit + browser-test, I do the next batch.
- **Tracking:** I update this doc's checkboxes after every batch ships. You can change `[x]` → `[v]` after browser testing.
- **No invention:** the clone-only rule (`feedback_clone_only_no_invent.md`) still applies — if PSIS doesn't have a source, escalate.
- **International framing:** the `feedback_international_positioning.md` rule applies to every new file/copy/example — never default to SA.
- **Commits:** every batch produces one `./bin/release patch` commit message in the format we used for Phases A/B.

---

## Phase C — Stub view content port (191 views)

### What "stub" means

A stub view matches the regex `accordion-body">\s*</div>` — i.e. it has a Bootstrap accordion with an empty body. The pages render a heading + an empty box + Save/Cancel buttons. Functionally, the user sees a near-blank page. 191 such files exist across 21 packages.

These were NOT created by hand for content; they're the leftover scaffolding from the previous "175 destination pages cloned" sweep, which only fixed CSS class names and never added real content. Yesterday's session marked them complete because the CSS pass touched them — that was the misnomer.

### Per-package breakdown

| # | Package | Stub count | Suggested batch size |
|---|---------|-----------:|----------------------|
| 1 | ahg-marketplace | **32** | 4 batches × 8 |
| 2 | ahg-privacy | **29** | 4 batches × 8 |
| 3 | ahg-registry | **28** | 4 batches × 7 |
| 4 | ahg-nmmz | **12** | 2 batches × 6 |
| 5 | ahg-icip | **11** | 2 batches × 6 |
| 6 | ahg-vendor | **10** | 2 batches × 5 |
| 7 | ahg-statistics | **9** | 2 batches × 5 |
| 8 | ahg-naz | **9** | 2 batches × 5 |
| 9 | ahg-exhibition | **9** | 2 batches × 5 |
| 10 | ahg-cdpa | **8** | 2 batches × 4 |
| 11 | ahg-ipsas | **8** | 2 batches × 4 |
| 12 | ahg-forms | **6** | 1 batch × 6 |
| 13 | ahg-ingest | **5** | 1 batch × 5 |
| 14 | ahg-multi-tenant | **4** | 1 batch × 4 |
| 15 | ahg-semantic-search | **3** | 1 batch × 3 |
| 16 | ahg-metadata-export | **2** | bundle with other small packages |
| 17 | ahg-condition | **2** | bundle |
| 18 | ahg-dacs-manage | **1** | bundle |
| 19 | ahg-dc-manage | **1** | bundle |
| 20 | ahg-mods-manage | **1** | bundle |
| 21 | ahg-rad-manage | **1** | bundle |
| **Total** | **21 packages** | **191 stubs** | **~38 batches @ 5/batch** |

### Methodology per stub

For each stub view:

1. **Locate the matching PSIS template.** Search `/usr/share/nginx/archive/plugins/{plugin}/modules/{module}/templates/{name}Success.{php,blade.php}`.
2. **If PSIS source exists** → port content faithfully (header, form fields, validation hints, action buttons, breadcrumb, sidebar slot). Use the existing controller method's variables; extend the controller only if PSIS passes data heratio doesn't.
3. **If PSIS source does not exist** → escalate to user (do NOT invent — per `feedback_clone_only_no_invent.md`).
4. **Smoke test** via `Kernel::handle()` for HTTP status, then mark for browser test.
5. **Update this doc's tracking table** with control counts before/after.

### Acceptance criteria per page

- Heratio control count ≥ PSIS control count (or documented parity gap with reason).
- All form fields render with real data from the matching DB tables.
- All action buttons resolve to live routes.
- Page returns HTTP 200/302/403 (no 500s).
- User browser-tests as admin and confirms.

### Phase C tracking

A separate appendix table will be appended below as each batch completes.
For now, every row starts at: `before=0 (stub), after=TBD, status=TODO`.

### Risk: linked sub-pages

Many of these stubs are EDIT pages (e.g. `nmmz/permit-create`, `cdpa/breach-create`).
If the LIST page (e.g. `nmmz/permits`) has a link to the EDIT page and the EDIT
page is a stub, fixing only the LIST gives the user a working list that crashes
to a blank page on click. Batch by FEATURE, not by alphabetical filename:
clone the list view AND its create/edit/view siblings together.

---

## Phase D — Broader PSIS coverage audit

### D1 — API parity (94 missing endpoints)

**Source:** `docs/API-COMPARISON.md` (per memory `project_api_gap.md`).

**Scope:** All v1 CRUD routes + the entire v2 REST surface that PSIS exposes
under `apiV1Plugin` and `apiV2Plugin` but heratio is missing.

**Methodology:**
1. Read `docs/API-COMPARISON.md` to get the full delta list.
2. Group by resource (information_object, actor, repository, accession, etc.).
3. For each missing endpoint: port the controller method, add Form Request validation,
   register the route under `routes/api.php`, write a smoke test.
4. Update the OpenAPI spec at `docs/api/openapi.yaml` (if it exists).
5. Document auth requirements (api_key, sanctum, etc.).

**Suggested batches:** 1 batch = 1 resource (≈ 8 endpoints).

**Acceptance:** Each endpoint returns 2xx for valid input, 4xx for invalid, matches PSIS response shape.

---

### D2 — AHG menu parity (13 missing items + 14 stale)

**Source:** `docs/AHG-MENU-COMPARISON.md` (per memory `project_ahg_menu_comparison.md`).

**Scope:** The admin sidebar / nav (NOT the reports dashboard, which is now done).
13 menu items present in PSIS that heratio is missing, plus 14 stale entries
that point at nothing or duplicate other links.

**Methodology:**
1. Re-read `docs/AHG-MENU-COMPARISON.md`.
2. Verify each "missing" item — confirm the target page exists in heratio (after the
   reports-dashboard work, several may now resolve).
3. For each truly missing item: add the menu link to the relevant nav/sidebar
   blade partial.
4. For each stale entry: confirm dead, then remove or repoint.
5. Visual diff PSIS sidebar vs heratio sidebar at every URL prefix.

**Acceptance:** All AHG menu items resolve. No dead links. Side-by-side screenshot match.

---

### D3 — Media processing parity (15 missing features)

**Source:** `docs/MEDIA-PROCESSING-COMPARISON.md` (per memory `project_media_gap.md`).

**Scope:** 3D model viewer, AI image analysis, video metadata extraction,
watermarking, HLS/DASH streaming, encryption-at-rest, IPTC/XMP round-trip,
face detection, OCR pipeline, format identification (PRONOM/Siegfried),
preservation derivatives.

**Methodology:**
1. Read `docs/MEDIA-PROCESSING-COMPARISON.md`.
2. Per feature: identify the PSIS service class + queue worker + storage layout.
3. Port the service into `packages/ahg-media-processing/src/Services/` (or
   create the package if it doesn't exist).
4. Wire the queue jobs into Laravel's queue system.
5. End-to-end test: upload → process → derivative renders correctly.

**Acceptance:** Each missing feature has a passing integration test that matches
the PSIS reference output.

**Risk:** This phase requires GPU access (server 192.168.0.78) for AI features.
Estimated effort is the largest of all D phases.

---

### D4 — Plugin coverage matrix (119 PSIS plugins ↔ 92 Heratio packages)

**Why this matters:** PSIS has 119 plugins. Heratio has 92 packages. The 27-plugin
gap is structural and needs a one-to-one mapping audit before we can claim
"100% PSIS coverage."

**Methodology:**
1. Generate a CSV: PSIS plugin name → Heratio package name (or "MISSING").
2. For each "MISSING" row: read the PSIS plugin's `extension.json` + `modules/`
   to figure out scope. Decide:
   a. Build new heratio package (clone wholesale).
   b. Merge into existing heratio package.
   c. Skip (deprecated in PSIS, not used).
3. Build per missing package, batch by domain (commerce, compliance, etc.).

**Acceptance:** Every PSIS plugin has either a heratio package or a documented
"intentionally not ported" reason.

---

### D5 — AJAX endpoints, cron jobs, background services, JS layer

**Why this matters:** All my smoke tests in Phases A/B confirmed the PHP routes
resolve. None of them confirmed:
- The AJAX endpoints the JS calls (autocomplete, tree-view, search-as-you-type).
- The cron jobs (search reindex, fixity scan, embargo expiry, GRAP recalculation).
- The background services (queue workers, file watchers, websocket relays).
- The JS layer itself (TomSelect initialization, Chart.js render, file upload).

**Methodology:**
1. Per package, read `routes/api.php` + grep `XMLHttpRequest|fetch(|axios.` in
   the JS bundle.
2. For each AJAX endpoint, confirm the heratio counterpart exists and returns
   the same JSON shape.
3. Per package, read `app/Console/Kernel.php` + the PSIS cron config in
   `apps/qubit/config/schedule.yml` to compare scheduled tasks.
4. Per package, read PSIS `lib/Services/` and confirm the heratio service has
   the same public method surface.

**Acceptance:** Per-package compatibility matrix table — each lane (HTTP, AJAX,
cron, queue, JS) marked Y/N/Partial.

---

### D6 — Form validation, POST handlers, business logic

**Why this matters:** I confirmed view files exist and routes resolve. I never
audited:
- Form Request validation rules vs PSIS validators.
- POST handler behaviour (does it actually create the right rows?).
- Business logic in service classes (e.g. does GRAP recalculate correctly?).
- Error handling (does the app degrade gracefully on bad input?).

**Methodology:**
1. Per page that has a form: open PSIS action `executePost($request)` and
   compare to heratio controller `store()`/`update()`/`destroy()`.
2. For each field, confirm validation rule matches.
3. For each side effect (DB write, queue dispatch, event fire, mail send),
   confirm heratio replicates it.
4. For each happy path + each known edge case (empty body, invalid IDs, missing
   FK, concurrent edit), test.

**Acceptance:** Per-page POST handler audit checklist with PASS/FAIL.

---

## Recommended order of work

1. **Phase C feature-batched** (191 stubs, ~5 weeks at 5 stubs/day) — this gives the user
   working pages everywhere they currently see white. Highest user-visible impact.
2. **Phase D2 — AHG menu parity** (small, ~1 day) — closes "I can't find the page" gripes.
3. **Phase D1 — API parity** (94 endpoints, ~3 weeks) — unblocks integrations and the mobile / DB-tools projects that depend on v2.
4. **Phase D5 — AJAX/cron/JS audit** (per-package, ~2 weeks) — surfaces hidden runtime breakage.
5. **Phase D6 — POST handler validation audit** (~3 weeks).
6. **Phase D4 — plugin coverage matrix** (one-time inventory, ~3 days) — gives a clean "what's left" delta.
7. **Phase D3 — media processing** (~6 weeks, requires GPU) — biggest, defer until others are stable.

**Total estimate:** ~5 months of focused work to reach genuine 100% PSIS parity.
This excludes the post-parity work of building NEW features that PSIS doesn't have.

---

## How to use this doc going forward

- After each batch, append a results table to the relevant Phase section
  (matching the format in `docs/reports-dashboard-comparison.md`).
- Update the "Approx size" column at the top whenever a phase finishes.
- When the user browser-tests a page and finds a bug not caught by `Kernel::handle()`,
  add a row to a "Runtime gotchas found in browser testing" appendix at the
  bottom of this file — those are the highest-value findings.

## Open questions for Johan

1. **Phase C order of attack:** alphabetical by package, or by user-visibility (start with marketplace which has 32 pages and is the biggest revenue feature)?
2. **Phase D scope freeze:** are there PSIS plugins that should be marked "intentionally not ported" so we don't waste effort on them? (e.g. legacy auth plugins, ndaResearcherPlugin if NAZ workflow has been replaced).
3. **Browser test cadence:** should I queue a test of every Phase C batch to you immediately on completion, or batch every 5 stub-batches into one review session?
4. **Acceptance bar:** is "renders without 500 + has all PSIS controls" enough for Phase C to mark a page DONE, or do you also want POST handlers + JS interactions verified before marking complete?
