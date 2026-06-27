# Heratio full E2E + help-audit — results matrix

Run started 2026-06-27 against heratio-dev (`http://192.168.0.112:8090`, DB `heratio_dev`).
Plan: `heratio-full-e2e-test-and-help-audit-plan.md`. Defect tiers: A = deterministic
auto-fixable, B = app-code change, C = flag-only (data/PII/security/locked).

Auth note: no admin password available + password mutation is out-of-scope, so authed
CRUD (checklist item 3) and authed-200 (item 2) are **deferred** for routes that gate
anon. Anon HTTP smoke (200 public / 302|403 gated / **no 5xx**) + static audit
(routes, theme, dropdowns, ACL, help) carry the sweep; authed CRUD revisited if an
admin session is provisioned.

Baseline: 4131 routes, 560 `help_article` rows.

---

## T1 — Core / platform / theme

### Functional smoke (anon HTTP) — GREEN
54 paramless GET routes across the 12 modules: **0 server errors (5xx)**. Gating
consistent: public pages 200, admin pages deny anon (302→login or 403). Notes:
- ahg-settings / acl / dropdown / menu / user admin → **403** (ACL-deny) rather than
  302; ahg-multi-tenant / landing-page admin → **302**. Both are valid anon-denials;
  the 302-vs-403 split is a cosmetic inconsistency, not a defect.
- `term/export/skos` → 400 (needs params), `taxonomy/index` + `term/browse` → 200 to
  anon (public term browse — confirm intended). No 5xx anywhere.

### Static audit (help / theme / dropdowns / ACL) — DONE (6 parallel agents)

| Module | Routes | Theme | Dropdowns | Help | Notes |
|---|---|---|---|---|---|
| ahg-core | PASS | PASS | PASS | PASS | only hardcoded `<option>` are PDF/A engineering params (tier C) |
| ahg-theme-b5 | WARN | PASS (is the theme) | N-A | PASS¹ | orphan unloaded `routes/web.php` (logout/register stubs) — tier A dead code |
| ahg-settings | PASS | PASS | PASS | PASS² | stray `.bak` view shipped; legacy AtoM-era plugin doc |
| ahg-dropdown-manage | PASS | PASS | PASS | WARN | no in-UI help link; SortableJS via CDN |
| ahg-menu-manage | PASS | PASS | PASS | WARN | no in-UI help link |
| ahg-acl | PASS | PASS | **WARN** | WARN | controlled-vocab `<option>` lists should use ahg_dropdown (tier B) |
| ahg-user-manage | PASS | PASS | WARN | WARN | ACL-primitive options hardcoded (low); layout-alias inconsistency |
| ahg-multi-tenant | PASS | PASS | WARN | WARN | status enum hardcoded; help/UI drift ("Trial" not settable) |
| ahg-term-taxonomy | **WARN** | PASS | PASS | WARN | **importSkos write route lacks `acl:` (tier B/security)**; stale help URLs |
| ahg-help | PASS | PASS | N-A | PASS | help system itself; ingests `docs/help/*.md` → `help_article` |
| ahg-static-page | PASS | PASS | N-A | PASS² | orphan `home.blade.php` (legacy layout); redundant route aliases |
| ahg-landing-page | PASS | WARN³ | N-A | PASS² | dead controller methods admin()/post(); Leaflet via CDN |

¹ one stale claim (says it registers /logout+/register; it doesn't). ² content accurate but no
in-UI help link. ³ public `index.blade.php` has no theme layout/HTML scaffold (builder-rendered).

**Systemic finding (T1):** nearly every admin module has a correct, published help article in
`docs/help/` that is NOT reachable from its own UI — no in-view help link and no entry in
`packages/ahg-help/config/help-context.php` (the contextual-help map, #1332). Help is findable
only via the global Help Center search. This is the dominant T1 gap (8+ modules).

### Defects (T1)
**Tier B (app-code):**
- **[ahg-term-taxonomy][security]** `term.import.skos` (POST, bulk vocab mutation) is `auth`-only,
  no `acl:` — any authenticated user can rewrite the taxonomy. Siblings store/update/destroy are
  acl-gated. `routes/web.php:30-31`. GET form routes create/edit/confirmDelete also auth-only
  (`:18,20,22`) — minor. → **filed as an issue.**
- **[ahg-acl]** controlled-vocab `<option>` lists (watermark position, audit action, access-request
  action/urgency) hardcoded instead of `ahg_dropdown` (`security/watermark-settings.blade.php:144`,
  `security/audit.blade.php:27`, `security/access-request.blade.php:70`).
- **[ahg-landing-page]** dead controller methods `admin()`/`post()` (`LandingPageController.php:206,220`).

**Tier A (auto-fixable):**
- **[ahg-theme-b5]** orphan `routes/web.php` never loaded; refs unregistered `themeb5::` namespace +
  non-existent logout/register views; name-collides with app routes. Safe-delete.

**Tier C (flag-only):** systemic help-discoverability gap (above, ~8 modules); stale help claims
(term-taxonomy URLs, theme-b5 logout/register, multi-tenant "Trial", settings `/admin/ahgSettings`);
legacy AtoM-era plugin docs (settings/term-taxonomy/landing); stray `ai-services.blade.php.bak`
(ahg-settings); external CDNs (SortableJS, Leaflet, chart.js); orphan `home.blade.php`
(ahg-static-page); redundant route aliases (static-page, acl).

### T1 verdict
Functional: **GREEN** (0 5xx, gating consistent). Static: structurally clean; 1 security gap +
1 systemic help-discoverability gap + assorted tier-C cleanup. Authed CRUD deferred (no admin session).

---

## T2 — Description / cataloguing (GLAM core)

### Functional smoke (anon HTTP) — GREEN
50 paramless GET routes across 13 modules: **0 server errors**. Anomalies chased:
`semantic-search/discoveries` 000 was a transient slow first-load (retry 200, 1.6s,
167 KB heavy page); `admin/ric` 200-anon is a real gating gap (below).

### Static audit (13 modules, 7 parallel agents)
Theme: PASS across the board (central BS5; external JS-viz CDNs noted tier-C, not
framework violations). The real findings cluster in **route gating** and **hardcoded
controlled vocab** — far more than T1.

**🔴 Auth-gating gaps (security):**
- **ahg-ric — FAIL (CRITICAL).** Entire `/admin/ric*` web surface in a bare
  `Route::middleware('web')` group, RicController has no internal guard → anon gets
  admin reads (dashboard/stats/logs/queue/orphans, curl-confirmed 200) AND
  state-changing POSTs (config write to ahg_settings, create-entity, ajax-sync,
  cleanup-orphans, resync, clear-queue, delete). `routes/web.php:26-79`. The
  `/api/ric/v1` surface IS gated — the web admin routes never got the same treatment.
- **ahg-display — anon data leak.** Public `glam/exportCsv` (`routes/web.php:10`,
  200 anon) + `glam/print` (`:9`) build their own queries WITHOUT the guest
  published-only filter (`status_id=160`) that `browse()` applies (only at
  `DisplayController.php:1197`); `exportCsv` (`:766-855`) streams up to 5000 rows incl.
  DRAFT/unpublished to anon.
- **ahg-actor-manage — unguarded config write.** `config()` POST writes
  `ahg_authority_config` with NO admin/acl guard despite a route comment claiming one
  (`routes/web.php:113`; `ActorController.php:1478`); `dedupScan()` same (`:87`/`:1336`);
  + auth-only mutators reconcileLink/apiIdentifierVerify/completeness recalc+batch
  (`:24,38,51,52`).
- **Missing `acl:` on write routes (authenticated, but any role):**
  ahg-information-object-manage (13 sibling write routes — condition photo upload/delete,
  ai.describe, preservation create/update/export, privacy scan/redaction save, research
  assessment/annotations, admin.fix-missing-slug; `routes/web.php:197-222,277-278,102`);
  ahg-condition (annotation.save, photo.upload/delete/base; `:17-19,37`);
  ahg-provenance (update/addEvent/deleteEvent/deleteDocument + legacy; `:14-17,24-26`).

**🟡 Dropdowns (controlled vocab hardcoded, not ahg_dropdown):**
- ahg-provenance — **FAIL** (10+ selects in `edit.blade.php` + service arrays
  getEventTypes/getAcquisitionTypes/getCertaintyLevels).
- ahg-information-object-manage (provenance entity-type/certainty, heritage
  acquisition/recognition), ahg-condition (photo_type), ahg-semantic-search
  (term-category), ahg-actor-manage (relation-category JS), ahg-authority-resolution
  (entity_type/state, duplicated across views).

**🟡 Help:** systemic again — articles exist but no `help-context.php` mapping / no
in-UI link for nearly every module (extends #1350). Stale claims: semantic-search
"requires Ollama" (contradicts gateway rule, `semantic-search-user-guide.md:245`);
authority-resolution "uses Tailwind" (false — BS5, `review-screen.md:135`); repository
holdings/bulk-reassign routes don't exist; function-manage "Move" route doesn't exist.
ahg-search is the model: it HAS a help-context entry + in-UI link.

**Clean:** ahg-custom-fields, ahg-functions-docs, ahg-repository-manage (gating),
ahg-search, ahg-function-manage (gating) — only tier-C help-wiring gaps.

### T2 verdict
Functional GREEN; static surfaced **1 critical (ric anon admin/mutation)** + 2 anon
data-exposure gaps (display) + a systemic missing-`acl:`-on-writes pattern + provenance
dropdown FAIL. Issues filed below.

---

## Fixes applied (2026-06-27, during the sweep)
- **#1352 ahg-ric** — split the bare `Route::middleware('web')` admin group into a
  public viewer group (explorer/connections/data/timeline/autocomplete/semantic-search
  + RiC-O read-only export/validate, all reachable from public record pages) and an
  `['web','admin']` operator group (dashboard/sync-status/orphans/queue/logs/config/
  create-entity/import + all ajax mutations). create-entity gains `acl:create`.
  Verified anon: `/admin/ric`,`/config`,`/ajax-stats`,`/orphans` → 403; `/explorer`,
  `/data` → 200 (public viewer preserved). `routes/web.php`.
- **#1353 ahg-display** — `exportCsv()` + `printView()` now apply the same guest
  published-only gate (`status` 158/160 whereExists when `!auth()->check()`) as
  `browse()`/`applyFilters()`. Verified anon CSV = 359 published records (was 431 incl.
  72 drafts). `DisplayController.php`.
