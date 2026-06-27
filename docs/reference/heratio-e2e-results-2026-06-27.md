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

---

## T3 — Metadata standards / formats

### Functional smoke (anon HTTP) — GREEN
28 paramless GET routes across 10 modules: **0 server errors**. DACS/DC/MODS/RAD
managers have no paramless GET routes (IO-attached crosswalk editors). bibframe/frbr
landing 200; exports/imports 302/403. Consistent gating.

### Static audit (10 modules, 5 parallel agents)

**🟡 Draft-leak (anon) — LATENT:**
- **ahg-biblio-frbr** — public `/library/work-cluster/{workKey}` (`routes/web.php:11`,
  `WorkClusterController::show:22-51`) joins `information_object` with NO `status_id=160`
  guest filter → would list unpublished editions to anon. Same class as #1353, but
  currently unexploitable on dev (FRBR clustering unpopulated; live hit 404/0 IOs).
- **ahg-biblio-bf** (tier C) — public LOD `.ttl/.jsonld` + `/bibframe/{workId}` read the
  separate optional `library_biblio_work` scaffold (not the live catalogue), no status
  gate — low real-world leak.

**🟡 Auth-gating gaps (authenticated, missing `acl:` — extends #1354):**
- ahg-mods-manage / ahg-rad-manage / ahg-dacs-manage / ahg-dc-manage — the edit POST
  routes mutate AND publish/unpublish IO but are `['web','auth']` only, no `acl:`. Any
  authenticated user can edit/publish any record. (Agents note this is the "standard-edit
  family norm" — but it can change publication status, so it's a real gap.)
- ahg-biblio-bf — updateWork/addContributor/import mutations auth-only no acl.
- ahg-biblio-frbr — admin override (force-group/split clustering) routes auth-only no acl.
- ahg-metadata-export — MARC + EAD import routes `['web','auth']` (no admin), while
  rad/dacs importStandard is auth+admin. Any authed non-admin can commit catalogue writes.

**🟢 Export draft-leak check — CLEAN:** the one anon metadata-export surface
(`/data/cidoc-crm.ttl`) is rigorously published-only in both serving modes (verified);
per-record exports are admin-gated; `/admin/sparql` enforces auth/bearer in-controller.
ahg-export + ahg-portable-export are fully gated (302/403) — no anon bulk-export leak.

**🟡 Broken/inert features (functional):**
- ahg-export — **entirely non-functional**: forms POST to GET-only route names → 405;
  controllers only `return view()`, no export output generated anywhere.
- ahg-portable-export — `apiToken()` returns a `/portable-export/share/{token}` download
  URL but no such route is registered → dead share-link (would also need a publication
  decision if wired, since bundles include unpublished records).
- ahg-dacs-manage — duplicate `name="languageNotes"` on two distinct fields
  (`edit.blade.php:212` + `:360`) → one overwrites the other on save.

**🟡 Help:** systemic — articles exist for all T3 modules but none wired into
`help-context.php` / no in-UI link (extends #1350).

### T3 verdict
Functional GREEN; export-leak check mostly CLEAN (metadata-export is exemplary). New:
1 latent anon draft-leak (frbr work-cluster), the publish/import missing-`acl:` pattern
(extends #1354), and 2 broken features (ahg-export inert, portable-export dead share route).

---

## T4 — Sector verticals (richest tranche — most security findings)

### Functional smoke (anon HTTP) — GREEN
51 routes across 14 modules: **0 server errors**. Public landings (dam, cart,
exhibition-space/browse) 200; admin 302/403; library APIs 401 (token auth).

### Static audit (14 modules, 7 parallel agents)

**🔴 Anon draft-leaks (read paths missing status_id=160, #1353 class):**
- **ahg-dam — FAIL, ACTIVE.** Public `/dam/browse` + `/dam/{slug}` show unpublished
  assets (DamService::browse/getById no status filter; store() sets 160). Verified: 58
  dam assets incl. **1 draft leaking to anon now**.
- **ahg-gallery — FAIL.** Public browse/artists/show leak draft artworks (GalleryService
  no filter) + ALL mutations bare auth no acl.
- **ahg-museum — WARN.** Public browse/show leak draft objects (mutations correctly acl-gated).
- **ahg-marketplace.** Grids filter to active, but single-listing `getListingBySlug`
  (detail page) has no status filter → draft/pending/withdrawn listings viewable by slug.
- **ahg-exhibition.** Public space pages no visibility filter (schema has no draft flag —
  forward-compat; tier C today).

**🔴 IDOR / broken access control (NEW class):**
- **ahg-cart.** `/cart/order/{id}` PUBLIC, `getOrder()` = `where('id',$id)` only → anon
  enumerates orders, reads customer name/email/billing/line-items (PII/POPIA). Latent on
  dev (no orders) but clear. Also: guest `removeItem()` deletes arbitrary cart rows by id.
- **ahg-image-ar.** `delete` (auth-only) removes any object's animation+MP4 by id, no
  ownership/admin check — IDOR.
- **ahg-3d-model.** Public `apiHotspots` doesn't filter is_public → leaks hotspot metadata
  for non-public models; legacy hotspot mutation aliases auth-only while canonical require admin.

**🔴 Missing acl: on mutations (extends #1354, big in T4):**
- **ahg-vendor — FAIL.** 5 match-routes (add/edit/serviceTypes/add+editTransaction)
  comment "ACL checked in controller" but no check exists → any authed user mutates vendors/txns.
- **ahg-exhibition.** add/edit "ACL must be checked in controller" — no check.
- **ahg-spectrum.** Every mutation (incl. POPIA DSAR/breach/ROPA writes) feature-flag + bare auth.
- **ahg-gallery.** store/update/destroy (artworks/loans/valuations/venues) bare auth.
- **ahg-library.** Several /library-manage routes (serials/ill/kbart/trading-partners) bare auth.

**🟡 Gateway-rule violation:** ahg-image-ar AI default is a direct node URL
`http://192.168.0.78:5052` (settings.blade:56) — bypasses ai.theahg.co.za gateway.

**🟡 Broken/stub features:** ahg-heritage-manage accounting GRAP CRUD is a no-op stub
(store/update flash success, no DB write; forms action="#"); ahg-marketplace orphan route
ref `ahgmarketplace.listings` (RouteNotFoundException on a checkout error path); ahg-loan
dead filter option "pending".

**🟢 Clean:** ahg-heritage-manage public portal IS published-only (model pattern);
ahg-library API auth solid; ahg-museum/loan/label mutations gated; ahg-cart webhook
signature-verified; marketplace checkout ownership-checked (no IDOR there); 3d preview
session-whitelisted.

**🟡 Dropdowns:** widespread hardcoded vocab (loan/exhibition/cart FAIL; gallery/museum/
dam/marketplace/3d/image-ar WARN). **Help:** all 14 unwired (extends #1350).

### T4 verdict
Functional GREEN; the heaviest security tranche — 1 active draft-leak (dam), a cart PII
IDOR, more public read-leaks (gallery/museum/marketplace), 3 IDOR/broken-access, a large
missing-acl set (incl. POPIA writes in spectrum), a gateway-bypass, and broken stubs.

## Fixes applied (2026-06-27, draft-leak batch #1358 + #1360)
- **#1358 ahg-dam** — `DamService::browse()` + `getById()` now apply the guest published
  filter (`whereExists` status 158/160 when `!auth()->check()`). Verified: guest browse
  total 57 (was 58 incl. 1 draft).
- **#1360 ahg-gallery / ahg-museum** — `GalleryService::browse()`+`getBySlug()`,
  `MuseumService::browse()`+`getBySlug()` same guest filter. Public pages render 200;
  guest totals gallery 53 / museum 62, no 500s.
- **#1360 ahg-marketplace** — `getListingBySlug()` now only serves `status='active'`
  listings publicly (matching the storefront grids); the owning seller may view their own
  non-active listing, admins moderate via the admin screens.
- PSIS parity-audit twin filed: atom-ahg-plugins#178 (both security classes).

## Fix applied (2026-06-27, #1359 cart IDOR)
- **ahg-cart order-confirmation IDOR** — `CartController::orderConfirmation()` now gates
  via `canViewOrder()`: owner match (user_id for authed, session_id for guest checkout)
  or `AclService::canAdmin()`; else 403. Verified live: anon `cart/order/1` → **403**
  (was 200, leaking customer PII), `cart/order/999999` → 404, `cart` → 200.
- **ahg-cart guest cart deletion** — `CartService::removeItem()` gains the guest
  session-scope (`->when(!$userId, where session_id=$sessionId)`); controller passes
  `session()->getId()`. A guest can no longer delete arbitrary cart rows by id.

---

## T5 — Digital objects / media / preservation

### Functional smoke (anon HTTP) — GREEN
36 routes, 13 modules. Initial smoke flagged 11×502 (preservation/integrity/c2pa) but
re-testing individually returned proper codes (403/200) — **transient php-fpm saturation
from rapid sequential hits, 0 real 5xx**. media-streaming/ocfl have no paramless GET routes.

### Static audit (13 modules, 7 parallel agents)

**🔴 Anon-exploitable FAILs:**
- **ahg-iiif-collection — FAIL (draft-leak).** Public `/iiif-manifest/{slug}` (manifest),
  `/search`+`/autocomplete` (OCR text), `/iiif-viewer/{slug}` serve UNPUBLISHED objects to
  anon — no status 158/160 filter (`IiifCollectionService::loadObjectAndDigitalObjects`
  :1081; `IiifContentSearchService::resolveObject` :295; controller viewer :316). Leaks
  title/IPTC/EXIF/GPS + the Cantaloupe image IDs (so unpublished images are fetchable).
  Also: collection `is_public` not enforced on index/view; addItems/removeItem mutations
  have NO ACL (route comment lies). #1353/#1360 class.
- **ahg-c2pa — FAIL (binary bypass of #1347).** `/verify/{doId}/download` +
  `/credentials.c2pa` (public, `routes/web.php:249-255`) stream the master binary by
  digital_object id with NO publication/ODRL check (`VerifyObjectDownloadController` :58-131;
  `DigitalObjectProvenanceService::resolveMasterForDownload` :109-150). The route comment
  admits it "never touches the locked IO/media download route" — so it **defeats the #1347
  ODRL gate**. Also `/verify/{id|slug}` leaks unpublished record metadata/provenance.
- **ahg-storage-manage — FAIL (physical-security leak).** `/physicalobject/browse` (:7),
  `/physicalobject/{slug}` show (:33), `/physicalobject/autocomplete` (:30) are PUBLIC
  (outside the auth groups). Verified: anon `/physicalobject/main-vault` → 200 exposing
  building/floor/room/vault/shelf, barcode, **security_level, access_restrictions**, notes.
  Physical location/security data to anon.

**🟡 Missing acl: / IDOR (authenticated — extends #1354):**
- ahg-media-streaming — caption-track CRUD (`routes/web.php:29-40`) auth-only, no
  admin/ownership → any authed user edits/deletes any object's captions. (Public captions
  VTT also serves transcript text outside the #1347 gate — tier C.)
- ahg-iiif-collection addItems/removeItem (no acl). ahg-ftp-upload upload/delete/attach
  (auth-only). ahg-integrity / ahg-c2pa / ahg-preservation mutations rely on `admin` group
  only (no granular `acl:`) — admin-gated, lower risk.

**🟡 Path-confinement (admin-only, tier C):** ahg-scan `scan_folder.path` + ahg-ingest
`digitalObjectPath` accept arbitrary host paths (admin-only).

**🟡 Broken/stub features (extends #1357):** ahg-dedupe `mergeExecute()` doesn't merge —
sets status='merged' + claims a non-existent background task (destructive op is a
misleading no-op); 9 stub dedupe routes return empty views. ahg-media-processing 4 orphan
methods (one targets a non-existent route). ahg-ingest orphan post()/dead Cancel link.

**🟢 Clean:** ahg-ocfl (backend, N-A), ahg-pdf-tools, ahg-preservation gating, ahg-dedupe
gating (admin), ahg-scan/ingest gating (admin), media-streaming #1347 gate intact (confirmed).

### T5 verdict
Functional GREEN. Three anon-exploitable FAILs — iiif draft-leak, **c2pa binary bypass of
#1347**, storage physical-security leak — plus the familiar missing-acl + broken-stub sets.

## Fixes applied (2026-06-27, #1362 + #1364)
- **#1362 ahg-c2pa** — `VerifyObjectDownloadController::download()` + `credentials()` now
  call `denyIfOdrlRestricted()` → `OdrlService::isDigitalObjectPermitted($doId,'use')`
  (class_exists-guarded), the same gate as #1347. Closes the parallel-download bypass.
  Verified: restricted DO 1254814 → 403 (was streaming); open DO 702 → 404 (gate passes).
- **#1364 ahg-storage-manage** — `physicalobject/browse`+`{slug}`+`autocomplete` and
  `strongroom/browse`+`{slug}` gained `->middleware('auth')`. Verified anon: all → 302
  (was 200, leaking building/vault/security_level).

## Fix applied (2026-06-27, #1363 iiif draft-leak)
- **ahg-iiif-collection** — guest published-status gate (status 158/160 when
  `!auth()->check()`) added to the three public read paths: object manifest
  (`IiifCollectionService::loadObjectAndDigitalObjects`), content search
  (`IiifContentSearchService::resolveObject`), viewer (`IiifCollectionController::viewer`).
  Collection index now passes `publicOnly = !auth()` and `view()` 404s a non-public
  collection for anon. addItems/removeItem routes gained `acl:update`.
  Verified anon: published object manifest/viewer 200; DRAFT object manifest/viewer
  404 (was leaking metadata + Cantaloupe image IDs); public collection index 200.

---

## T6 — Research / RDM

### Functional smoke (anon HTTP) — GREEN
26 routes, 8 modules: 0 server errors. Gating consistent (research/researcher/rdm/
favorites/access-request 302; admin 403). One public surface: `api/annotations/search` 200.

### Static audit (8 modules, 4 parallel agents)

**🔴 ahg-annotations — FAIL (anon leak + IDOR):**
- `search()` applies its visibility filter ONLY when `?visibility=` is passed; the default
  anon query filters on `target_iri` alone → returns **private + project** annotations to
  anon for any targetId (`AnnotationsController.php:105-121`). `show()` returns ANY
  annotation by uuid with no visibility check (`:156-182`). No object-state gate
  (`resolveIoIdFromTarget()` is a `return null` stub) → annotations on draft/restricted
  IOs readable by anon. The route comment claims enforcement that doesn't exist. Confirmed
  in code; latent on dev (no `annotation` table installed → search returns total:0).
- IDOR: `update()` (`:381-435`) + `destroy()` (`:437-466`) only check `Auth::check()`, no
  `created_by === Auth::id()` → any authenticated user can overwrite/delete ANY annotation.

**🔴 ahg-access-request — IDOR:** `view()` (`/access-request/{id}`, auth-only) calls
`getRequest($id)` with no ownership/admin check → any authed user reads any request's
justification/requester/target classification (`Controller.php:95-101`). (`cancel` IS
owner-scoped.) Approve/deny correctly `['auth','admin']`-gated — no self-approval.
Also a **functional bug**: writes to `security_access_request` but reads/mutates
`access_request` (`Service.php:207` vs `:142,257,284`) — new requests never appear in
My-Requests; approve/deny act on the wrong table.

**🟡 ahg-researcher-manage — FAIL:** 3 `api-*` routes call `view('researchermanage::…')` —
wrong namespace (provider registers `ahg-researcher-manage`) + no such blade → 500. Latent
IDOR on submission/researcher view/edit stubs (no owner scope; currently inert). Hardcoded
item_type/level dropdowns.

**🟡 ahg-research (tier C):** public `cite`/`citeExport` (`ResearchCitationsController.php:56-110`)
emit citation metadata (title/author/date) for any object by slug with no publication/access
filter → unpublished/restricted citation data to anon. Hardcoded status dropdowns.

**🟢 Exemplary / clean:**
- **ahg-share-link — PASS (model).** HMAC-SHA256 tokens (`TokenService.php:23-36`), expiry/
  revocation/max-access quota, ACL+classification-gated issuance, metadata-only (no binary →
  no ODRL bypass). Only tier-C: point-in-time gating (a token issued while public keeps
  working after the record is later reclassified — recommend access-time re-check).
- **ahg-favorites — PASS.** Every mutation user-scoped (no IDOR); Str::random(64) share
  tokens with expiry/revoke. One tier-B: `revokeSharing()` deletes the share-audit row by
  folder_id only, not user-scoped (low impact — live token survives).
- **ahg-request-publish — PASS.** Approval admin-gated; browse filters to own rows; receipt
  token-gated (40-hex sha1). Some dead legacy stubs.
- **ahg-rdm — PASS** (epic-covered; only minor compliance-filter dropdown hardcoding + help wiring).

### T6 verdict
Functional GREEN. New: ahg-annotations anon private-read leak + IDOR (latent on dev),
access-request view() IDOR + a wrong-table functional bug, researcher-manage dead routes.
share-link is the exemplar to copy for token security.

## Fix applied (2026-06-27, #1365 annotations — partial)
- **ahg-annotations** — read-visibility scope added to `search()` + `show()`
  (`applyReadVisibilityScope`/`canReadRow`): anon → public only; authed → public + own;
  admin → all. IDOR closed on `update()`/`destroy()` (`canWriteRow` → 403 for non-owner).
  DEFERRED (documented TODOs, no leak): `project`-visibility rows (excluded for
  non-owner — no trustworthy membership lookup) and the object-state gate (public
  annotation on a draft IO; `resolveIoIdFromTarget` still a stub). #1365 stays OPEN for
  those two follow-ups.
