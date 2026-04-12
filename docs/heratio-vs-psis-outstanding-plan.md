# Heratio vs PSIS — Outstanding Work Plan

Generated: 2026-04-12
Owner: Johan Pieterse

This document plans the work that remains AFTER the reports-dashboard parity work
(see `docs/reports-dashboard-comparison.md`). The reports dashboard is at 100%
functional parity for its 122 links. Everything below is **outside the dashboard
scope** and was either flagged earlier in this session, surfaced by the
auto-memory project notes, or never audited.

## Scope summary

| Phase | What | Approx size | Verification path |
|-------|------|-------------|-------------------|
| **C** | Empty-accordion stub views — content port from PSIS | **191 views across 21 packages** | Browser load each page as admin |
| **D1** | API parity — v1 + v2 REST endpoints | **94 missing endpoints** (per `docs/API-COMPARISON.md`) | curl + Postman collection |
| **D2** | AHG menu parity — sidebar + admin nav | **13 missing menu items + 14 stale entries** (per `docs/AHG-MENU-COMPARISON.md`) | Visual audit, click every item |
| **D3** | Media processing parity — 3D, AI, watermarks, encryption | **15 missing features** (per `docs/MEDIA-PROCESSING-COMPARISON.md`) | Upload+process flow tests |
| **D4** | Plugin coverage matrix — 119 PSIS plugins ↔ 92 Heratio packages | **27 plugin gap rows** (estimate) | Side-by-side directory diff |
| **D5** | AJAX endpoints, cron jobs, background services, JS layer | Unknown — never audited | Manual code walk per package |
| **D6** | Form validation, POST handlers, business logic | Unknown — never audited | Per-page audit |

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
