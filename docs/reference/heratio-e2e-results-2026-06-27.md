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

---

## T7 — AI services (run by a dedicated agent; gateway-routing = headline)

### Functional smoke (anon HTTP) — GREEN
24 routes, 6 modules, 0 server errors. Public surfaces: `discovery*` (200) +
`.well-known/ai-inference-pubkey` (200, confirmed safe — public-key metadata only,
no private-key column).

### Static audit (6 modules)

**🔴 ahg-discovery — FAIL (anon draft-leak, NEW + verified live).** Public discovery
search (`Route::prefix('discovery')->middleware(['web'])`, no auth/acl) →
`DiscoveryController::searchInformationObjects()` (`:885-897`) joins information_object
with only `io.id != 1` + LIKE, **no status 158/160 filter, no auth gate**. Verified:
anon `discovery?q=opensearch` → 200 returns the DRAFT IO `test-opensearch`. Same pattern
in `suggest()` (`:573`) + the raw-ES `keywordSearch`/`entitySearch` bodies, which also
**omit the multi-tenant filter** ahg-search applies. Plus: `pageindex/api` is a fully
public unauthenticated LLM-query endpoint (`:17`); `discovery/click` is an unauth write
to `ahg_discovery_log` (`:12`); `discovery/build` (costly LLM index build) is auth-only
no acl. #1353/#1360/#1363 class.

**🔴 AI-gateway rule — two real app-path bypasses (one shared root cause):**
- **`ahg_llm_config`-driven LLM completion** — `LlmService::complete/completeFull/
  generateSuggestion` → `callOllama()` defaults `http://localhost:11434`, and the table is
  **seeded to that node** (`install.sql:489`). This is the **ahg-ai-chatbot FAIL**
  (chat completion, `ChatbotService.php:491`) AND the IO `ai.describe`/suggestion path in
  ai-services. Bypasses `ai.theahg.co.za` (no metering/quota/failover). Fix once at the
  LlmService layer.
- **DonutService** hard-defaults to direct node `192.168.0.115:5008` (`DonutService.php:39`).
- Config-default: `install.sql` seeds general+NER `api_url` to worker node
  `http://localhost:5004/ai/v1` (`:403,:469`) — overrides the gateway code-default on fresh install.

**🟢 Gateway — clean / exemplary:** **ahg-discovery is the model** — every inference client
(OllamaPageIndexClient, ImageSearchStrategy, PageIndexService OCR) defaults to the gateway
and **rejects raw-node overrides via `looksLikeNode()`**. The dedicated ai-services clients
(NerService/HtrService/TtsService + Qdrant command) are clean too. CLIP image-search (#1272)
is gateway-routed. ai-compliance/provenance-ai/inference-receipts make no outbound inference (N-A).

**🟡 Missing acl: (extends #1354):** ahg-ai-compliance EVERY mutation (incl. oversight
countersign/attest) auth-only; ahg-provenance-ai governance group auth-only; ahg-ai-services
legacy POST aliases (`routes/web.php:226-236`) skip the admin gate their canonical twins have.

**🟡 Other:** ahg-ai-chatbot WhatsApp webhook `validateSignature()` returns true when
`app_secret` unset → accepts UNSIGNED payloads (fail-open; channel default-off + throttled).

**🟢 Clean:** Theme PASS all 6 (central BS5). Dropdowns PASS/minor. inference-receipts is a
pure crypto library (N-A, live — consumed by ai-compliance/c2pa/audit-trail/federation).
**Help:** all 6 have accurate articles, none wired (extends #1350; inference-receipts exempt).

### T7 verdict
Functional GREEN. New anon draft-leak (discovery, verified live) + the confirmed gateway
bypasses (LlmService/chatbot completion + DonutService). discovery's OWN gateway routing is
the exemplar; its public SEARCH is the leak.

## Fix applied (2026-06-27, #1367 discovery draft-leak — partial)
- **ahg-discovery** — guest published-status gate added to all four public search paths:
  `searchInformationObjects()` + `suggest()` IO-titles (DB: status 158/160 whereExists when
  `!auth()`), `keywordSearch()` + `entitySearch()` (ES: `publicationStatusId=160` filter
  when `!auth()`, mirroring ahg-search). Verified: anon `discovery?q=opensearch` no longer
  returns the draft `test-opensearch`; published search still returns hits.
  DEFERRED (#1367 stays open): multi-tenant scope filter on the ES paths; gating the public
  unauth `pageindex/api` (LLM-query) + `discovery/click` (log write) + `discovery/build` (acl).

---

## T8 — Rights / compliance / records / jurisdiction (heaviest security tranche)

### Functional smoke (anon HTTP) — GREEN
42 routes, 18 modules, 0 server errors. Sensitive admin surfaces (rights/doi/records/
clearance/cdpa/audit) all deny anon (403); privacy/icip/ipsas/naz/nmmz/donor/workflow 302.

### Static audit (18 modules, 5 parallel agents)

**🔴 ahg-privacy — FAIL (the standout).** Highest-sensitivity package, weakest control:
**zero `acl:` anywhere** (group is `['dp.enabled','auth']` only). Every record — DSAR,
breach, complaint, consent, ROPA, DPIA, Article-30, PII corpus — is read+edit+delete by
ANY authenticated user via bare `where('id',$id)` (`PrivacyController.php` DSAR `:975`,
breach `:370`, ROPA `:1352`, etc.). Only the data-subject self-service `dsarStatus` is
ownership-gated. POPIA DSAR/breach IDOR.

**🔴 Anon contact-PII leaks (public {slug} show):**
- **ahg-donor-manage** — public `/donor/{slug}` + `/donor/browse` render donor
  **address/email/telephone** to anon (`show.blade.php:153-256`; only edit links are
  `@auth`). VERIFIED live: `donor/rock-art-research-institute` → 200 leaking PII. Plus
  agreement IDOR (any authed user reads/edits/deletes any agreement — donor contact +
  financial terms; lying "ACL checked in controller" comment, `DonorController.php:307,431,502`).
- **ahg-rights-holder-manage** — public `/rightsholder/browse` + `/rightsholder/{slug}`
  render rights-holder email/phone/address to anon (latent on dev — no data).

**🔴 Authenticated IDOR / access-control:**
- **ahg-icip** — 8 sacred-data mutators (communities/consent/TK-labels/**access-restrictions
  on sacred objects**) missing `acl:` (controller comment lies), any authed user alters them.
- **ahg-extended-rights** — `apiCheck`/`apiEmbargo` IDOR (any authed reads any object's
  embargo incl. `internal_note`); `delete()` missing slug↔object ownership.
- **ahg-rights-holder-manage** — `lift-embargo` is a state-changing **GET**, auth-only no
  `acl:` → any authed user lifts ANY embargo; `embargo.show` IDOR read.
- **ahg-version-control** — read (list/show/diff) lacks clearance/publication check → any
  authed user with `version.list` reads **classified/draft** record snapshots (restore IS
  clearance-gated; viewing the same history isn't).
- **ahg-naz** — reads under `auth` only (no admin), researcher PII to any authed user
  (inconsistent with sibling NMMZ's `auth,admin`).
- **ahg-workflow** — approve/reject task IDOR (no `assigned_to` check; within admin).

**🔴 Separation-of-duties:**
- **ahg-records-manage** — no maker-checker on irreversible destruction (one admin/editor
  drives initiate→clear→execute + self-issues the destruction certificate); destruction
  available to EDITOR group, not administrators-only.
- **ahg-security-clearance (tier C)** — reviewer≠requester not enforced; admin can raise own
  clearance (admin-gated, low impact).

**🟡 Broken/stub (extends #1357):** ahg-doi-manage — config UI wired to wrong store (saved
creds never reach minting), Test Connection dead (GET vs POST), single mint/deactivate/sync
are no-op success stubs, report export inert, plaintext DataCite password. ahg-privacy 2
dead-route 500s. ahg-audit-trail `audit.compare` → 500 (missing view). ahg-donor broken
`agreement.show` ref. ahg-workflow many orphan routes.

**🟢 Exemplary / clean:**
- **ahg-audit-trail — tamper-evident** (SHA-256 hash-chain + Ed25519 + DB triggers blocking
  UPDATE/DELETE on signed rows). Only cleanup-tier defects.
- **ahg-security-clearance** — structurally sound (MFA/OTP/WebAuthn ownership-scoped,
  escalation admin+acl-gated). Only help + self-approval (tier C).
- Stubs: ahg-rights (#620), ahg-doi (#562 schema-only), ahg-narssa (CLI-only).

**🟢 Jurisdiction pluggability — PASS all 6** (composer auto-discovery, none hard-wired
SA-default; a SA deployment omits the Zimbabwe NAZ/NMMZ packages). Caveat: no runtime
per-tenant toggle once installed.

**🟡 Dropdowns:** systemic — jurisdiction modules use **0 `ahg_dropdown`** (~350 hardcoded
options; NMMZ worst at 121); records-manage destruction action_type hardcoded; doi-manage
levels. **Help:** all unwired (extends #1350). **Theme:** PASS everywhere.

### T8 verdict
Functional GREEN; the richest access-control tranche — privacy zero-acl IDOR (lead), two
anon contact-PII leaks (donor verified live), icip sacred-object mutation gaps, embargo/
version-control/naz IDOR, records-manage destruction separation-of-duties. audit-trail is
the exemplar.

## Fix applied (2026-06-27, #1370 anon contact-PII — partial)
- **ahg-donor-manage** — `donor/browse` + `donor/{slug}` show now require `auth` (anon can't
  harvest donor address/email/phone). Verified: anon `donor/rock-art-research-institute` →
  302, PII gone. Agreement add/edit/delete gained `acl:create/update/delete` (closing the
  write IDOR; the "ACL checked in controller" comment was false).
- **ahg-rights-holder-manage** — `rightsholder/browse` + `rightsholder/{slug}` now require auth.
  DEFERRED (#1370 stays open): agreement READ (`agreementView`) still readable by any authed
  staff (financial/PII; lower severity — needs an acl-read/admin decision).

---

## T9 — Interop / APIs / federation (heaviest security tranche; multiple LIVE anon exposures)

### Functional smoke (anon HTTP) — GREEN
19 routes, 0 5xx. Public-by-design surfaces: `.well-known/void`, `api/actor*`, `oai`, `sru`,
`z3950` (200). The publication-filter on these public reads is the headline concern.

### Static audit (9 modules, 4 parallel agents)

**🔴 ahg-sharepoint — FAIL (CRITICAL, live).** The entire `/sharepoint/*` admin web surface
is `Route::middleware('web')` only — no auth/admin/acl; the controller guard is a TODO stub
(`SharePointController.php:15-18`). **Live-confirmed anon 200** on `/sharepoint/tenants`
(leaks Azure AD tenant_id/client_id), `/drives`, `/rules`. Anon can fire mutation POSTs
(rule save/delete/**run** → `exec(php artisan sharepoint:auto-ingest)`), mapping save/delete,
event retry, AND drive authenticated MS-Graph calls using the stored org M365 credentials.
Same class as ric #1352 but worse (credential-backed external calls + exec). Secrets storage
itself is OK (client_secret encrypted via Crypt in ahg_settings); webhook + push-API are
correctly bearer/clientState-gated. The route group is the hole.

**🔴 ahg-api — FAIL (multiple LIVE anon leaks + a 3rd ODRL bypass).** The open-data layer
(graph/`/id`/oai/cite/mets/iiif/feeds/sitemaps/void) is rigorously published-only — the
EXEMPLAR. But the `api/v1` resource reads leak:
- `informationobjects/{slug}` show/tree/children — no status filter → anon gets DRAFT records
  (verified: `api/v1/informationobjects/test-opensearch` → 200). Numeric-id enumerable.
- `informationobjects/{slug}/digitalobject` — streams the MASTER binary, no auth/ODRL/
  publication → a THIRD download path defeating the #1347/#1362 gate.
- `digitalobjects` index/show — leaks server filesystem `path` + checksum of drafts.
- `accessions` — fully anon-public (donor/acquisition data; verified 200).
- `physicalobjects` — location data anon-public (API version of #1364; verified 200).
- `actor show` — contact PII (email/phone/address) + unpublished related-IO leak.
- No per-object ACL on mutations (a write/delete-scoped key — or any session — mutates ANY record).

**🔴 ahg-graphql — FAIL (authed bulk leak).** `/admin/graphql/execute` is auth-gated (not
anon) but NO publication/visibility/owner filter and no `acl:` → any authed user bulk-reads
drafts, **private/project research annotations** (the #1365 class via GraphQL), researcher
PII (email/ORCID), non-public collections. Uncapped limit/offset.

**🔴 ahg-z3950 — WARN.** `/z3950` index is anon-public (leaks configured remote-target
host/port/db + counts), inconsistent with the auth-gated rest of `/z3950/*`. Target CRUD +
remote import are `auth`-only, no admin/`acl:` (help claims admin-only) → any authed user adds
targets + imports MARC. SRU `searchRetrieve` has no publication filter (latent — mirrors the
unfiltered library OPAC). No SQLi (bound params + whitelisted columns).

**🟡 ahg-federation — solid core, two gaps.** Harvest imports forced to Draft (good);
Europeana export published-only (good); the main `HarvestClient` has a STRONG SSRF guard
(metadata-host block, private-IP reject, DNS-rebind pin). Gaps: `testPeer()` uses a raw
`Http::get()` on an admin-supplied URL with NO SSRF guard and reflects the response (admin
SSRF defense-in-depth); peer `api_key`/`search_api_key` stored PLAINTEXT at rest.

**🟡 ahg-gis — WARN (authed).** bbox/geojson/radius are `auth`-gated (anon 302 — no anon
leak), but no publication filter + no `acl:` → any authed user enumerates coordinates of
draft IOs. Lower severity (authed).

**🟢 Exemplary / clean:**
- **ahg-oai — the exemplar** (strict status-160 gate across every verb; from/until/
  resumptionToken can't bypass; correct deleted-record tombstones).
- **ahg-resourcesync — clean** (published-only capability/changelist, no outbound surface).
- ahg-api open-data layer; ahg-api-plugin (auth-gated admin search).

**🟡 Help:** all unwired (extends #1350). **Theme:** mostly N-A (protocols); z3950 mixes
Tailwind utilities inside the BS5 shell (tier C).

### T9 verdict
The richest security tranche: a CRITICAL anon admin surface (sharepoint, live), the
harvestable-API draft-leak family + a 3rd ODRL binary bypass (api, live), a GraphQL authed
bulk leak, z3950 anon index/import gaps, federation testPeer SSRF + plaintext creds. OAI +
ResourceSync are the exemplars to copy.

## Fix applied (2026-06-27, #1376 sharepoint CRITICAL)
- **ahg-sharepoint** — the `/sharepoint/*` admin group now carries `['auth','admin']`
  (was bare `web` — anon). Verified: anon `sharepoint/tenants`/`drives`/`rules`/`columns`
  → 302 (was 200 leaking Azure AD config + credential-backed Graph ops); the Graph webhook
  stays public (clientState-gated). Closes the sweep's most severe finding.

## Fix applied (2026-06-27, #1377 api anon leaks)
- **ahg-api** — matched the open-data layer's unconditional published gate (status 158/160,
  root excluded) on the v1 catalogue reads: InformationObjectApiController show/tree/children
  (+ helper isPublished); DigitalObjectApiController index/show (+ dropped do.path/checksum
  from the public select). The `…/{slug}/digitalobject` master-binary endpoint now applies
  BOTH publication + ODRL (`isDigitalObjectPermitted`, class_exists-guarded) — closes the 3rd
  download bypass (#1347/#1362). Accessions + physicalobjects routes now `api.auth:read`.
  ActorApiController::show strips contact PII + filters related_resources to published for anon.
  Verified: anon draft 404, accessions/physobj 401, published record + index still 200.

---

## T10 — Reporting / ops / admin (final tranche)

### Functional smoke (anon HTTP) — GREEN
31 routes, 12 modules, 0 server errors. Ops admin (reports/jobs/backup/data-migration) all
403 anon; `feedback/general` 200 (public form, by design); `/metrics` 401 (token-gated).

### Static audit (12 modules, 4 parallel agents)

**🔴 ahg-accession-manage — FAIL (anon donor-PII leak, verified live).** Public
`/accession/{slug}` show (`routes/web.php:74`, outside the auth groups) renders donor contact
PII (street_address/city/telephone/fax/email + acquisition source) to anon
(`show.blade.php:64,220-285`; only edit links are `@auth`). Verified: anon `accession/2026-02-15-3`
→ 200 leaking donor email. The web twin of donor #1370 + api #1377. Also: public donor
typeahead `donorSearch`/`relatedDonor` enumerate donor names to anon; `accession/{slug}/edit`
GET is auth-only no `acl:` (authed PII read-IDOR). Mutations/finalise/rights-inheritance are
correctly acl-gated.

**🔴 ahg-jobs — FAIL (IDOR).** A LIVE `/jobs/*` web surface (`routes/web.php:35-40`),
`['web','auth']` only — no admin/scope. Any authed user lists ALL jobs (`/jobs/`), reads any
job by id incl. `output`/`download_path` (`/jobs/show/{id}` — IDOR), and `/jobs/clear-inactive`
global-deletes job history. Duplicates ahg-jobs-manage's admin-gated `job` table at a weaker
gate. (No arbitrary-class execution — start/cancel aren't web-exposed.)

**🟡 ahg-backup — WARN (editor-reachable DB dump/restore + no maker-checker).** All ops are
`admin` = `canAdmin` = **EDITOR-inclusive**, so an EDITOR can `download` a full DB dump (all
PII) and `restore` (overwrite the DB + untar over base_path). `doRestore` has NO server-side
confirm/maker-checker (only a client-side JS `confirm()`). `backup_path` is operator-settable
with only `string|max:500` — repointing into the web-served `/uploads` would expose dumps to
anon. Path traversal on download is SAFE (md5-of-enumerated-filename, never a user path).
Command-injection safe (escapeshellarg + config-sourced).

**🟡 ahg-reports (LOCKED) — WARN.** `/reports` dashboard + checksums-integrity are `auth`-only
while drill-downs are `admin`-only → any authed user sees draft/unpublished counts + total
user/donor counts (`ReportService.php:44,52`). The report-builder generic query/export
(`ReportBuilderController::export/apiData`) runs `DB::table($userSuppliedTable)` with **no
allow-list** (the advisory list is never enforced) → an admin can export the `user` table
(password hashes). Dead routes (`destroy`→nonexistent method, `apiData` id-less route) + ~30
orphan methods + a dead token share-link feature.

**🟡 Smaller (auth/abuse):** ahg-feedback public submit has no throttle/honeypot/captcha (anon
flood); ahg-articles anon comments insert `status='approved'` (no pre-moderation; honeypot +
throttle present, XSS-safe); ahg-translation `stringsMtSuggest` (`:1539`) lacks the
admin/editor check its siblings have → free AI-MT-proxy abuse for any authed user.

**🟢 Exemplary / clean:**
- **ahg-observability `/metrics`** — token-gated (`hash_equals`, fail-closed, loopback
  default), route-name labels (no PII/slug leak). The exemplar token-gated endpoint.
- **ahg-data-migration** — admin-only, path-traversal + zip-slip safe, draft-default import. Clean.
- **ahg-articles** — public index/show filter `status='published'` (NO draft-leak — the clean
  counter-example); comments escaped (no stored XSS).
- **ahg-forms** — no submission IDOR, injection-safe. **ahg-jobs-manage** — no arbitrary job
  execution, admin-gated. **ahg-statistics** — fully admin-gated (only chart.js CDN, tier C).

**🟡 Help:** all unwired (extends #1350). **Theme:** PASS (chart.js/TomSelect CDNs tier C).

### T10 verdict
Functional GREEN. Lead: accession anon donor-PII leak (verified live — the third donor-PII
surface after #1370/#1377). Plus ahg-jobs IDOR, backup editor-reachable DB dump/restore +
no maker-checker, reports auth-vs-admin disclosure. observability/data-migration/articles are
the exemplars.

## Fix applied (2026-06-27, #1381 accession anon donor-PII)
- **ahg-accession-manage** — `accession.show` (`routes/web.php:74`) now requires `auth`, and the
  donor-typeahead group (`['web']`→`['web','auth']`) gates donorSearch/relatedDonor/donor.add.
  Verified: anon `accession/{slug}` + donor typeahead → 302 (was 200 leaking donor email/PII).
  The third + final donor-PII surface closed (after #1370 donor-manage, #1377 api). (The authed
  `accession/{slug}/edit` GET read-IDOR remains — part of the #1354 missing-acl family.)

## Fix applied (2026-06-27, #1369 privacy zero-acl)
- **ahg-privacy** — split the single `['dp.enabled','auth']` group: the DPO/admin management
  surface (DSAR admin/scope, breach, complaint admin, consent, ROPA, DPIA, Article-30,
  autopilot, embedded-findings, PII review, dashboard, …) now carries `admin` (RequireAdmin)
  → any-authed-user IDOR closed; the 6 data-subject self-service routes (dsar-request/status/
  confirmation, complaint) stay `auth`-only with the existing in-controller ownership
  (`dsarStatus` scopes by created_by/requestor_email; dsarRequestStore binds created_by).
  Verified: management routes carry RequireAdmin, self-service routes don't; anon still 302.

## Fixes applied (2026-06-27, #1382 + #1383 ops gating)
- **#1382 ahg-jobs — NOT a live vuln (corrected).** route:list has ZERO Ahg\Jobs\JobsController
  routes; `ahg/jobs` declares no provider + isn't in the root composer require (only
  ahg/jobs-manage is); `/jobs` is 404. The T10 "live IDOR" was a static false positive — the
  package isn't loaded. The route group was nonetheless admin-gated defensively.
- **#1383 ahg-backup** — `download`/`restore`/`doRestore`/`destroy`/`saveSettings` now call
  `requireAdministrator()` (`AclService::isAdministrator`, ADMINISTRATOR group only) → the
  full-DB-dump + restore are administrators-only, no longer reachable by the EDITOR-inclusive
  `canAdmin` gate. `saveSettings` rejects a `backup_path` inside a web-served dir (/uploads or
  public/) — closes the anon-dump-via-repoint risk. Restore server-side maker-checker (typed
  confirm) deferred — needs a coordinated restore.blade change; #1383 stays open for it.

## Fixes applied (2026-06-27, #1371 icip + #1372 embargo)
- **#1371 ahg-icip** — added a `requireIcipWrite()` controller gate
  (`AclService::hasPermission(auth()->id(),'update')`, the same predicate the `acl:`
  middleware/`CheckAcl` uses) on the POST branch of all 9 sacred-data mutators
  (communityEdit/consentEdit/consultationEdit/noticeTypes/objectConsent/objectNotices/
  objectLabels/objectRestrictions/ocapSettings) — covers the primary + AtoM-alias +
  object-slug route paths that funnel to each method. Any-authed-user mutation of
  communities/consents/TK-labels/sacred-object access-restrictions is closed. FIXED.
- **#1372 ahg-rights-holder-manage** — `extended-rights.lift-embargo/{id}` (a state-changing
  GET that let any authed user lift ANY embargo) now carries `acl:update`. The
  GET→POST/CSRF conversion + the embargo.show read-IDOR + the ahg-extended-rights (LOCKED)
  apiCheck/apiEmbargo/delete/tk-labels parts remain — #1372 stays open for those.

## Fixes applied (2026-06-27, #1366 + #1373)
- **#1366 ahg-access-request** — `view()` now requires owner-or-admin
  (`user_id === auth()->id() || AclService::canAdmin`) → closes the IDOR where any authed
  user read any request's justification/requester/target-classification by id. (The
  wrong-table workflow bug — writes security_access_request, reads access_request — is
  functional and remains; #1366 stays open for it.)
- **#1373 ahg-version-control** — list/show/diff now call `assertClearedForClassified()`:
  if the record is classified (object_security_classification active), require
  `ClearanceCheck::canUserRestore()` (the same clearance-level check restore uses) → a
  version.list holder can no longer read classified record snapshots they couldn't view live.

## Fixes applied (2026-06-27, #1378 graphql + #1379 z3950)
- **#1378 ahg-graphql** — `/admin/graphql/*` group gains `acl:read`; resolvers now apply the
  open-data published gate (status 158/160, root excluded, `canAdmin` bypass) to IO; the
  #1365 visibility model to annotations (public + own; admin all); strip researcher
  email/ORCID for non-admin; filter project/collection to public for non-admin; clamp
  limit (max 100) + offset. Verified: execute carries CheckAcl:read.
- **#1379 ahg-z3950** — `/z3950` index now `['web','auth']` (was anon — leaked remote-target
  host/port/db); searchRun/import/importBatch/admin/target create/store/delete gain `admin`
  (RequireAdmin) on top of the group's web+auth (was any authed user). Verified: /z3950 → 302,
  z3950.target.store carries RequireAdmin. SRU publication filter DEFERRED (latent — mirrors
  the unfiltered library OPAC; adding status-160 could break SRU if library items aren't
  status-160; #1379 stays open for it).

## Fixes applied (2026-06-27, #1361 + #1380)
- **#1361 ahg-image-ar / ahg-3d-model** — `image-ar.delete` now requires `admin` (was any
  authed user deleting any object's animation+MP4 by id); the 3d-model legacy hotspot aliases
  (legacy.3d/ar3d addHotspot/deleteHotspot) now require `['auth','admin']` (matching the
  canonical routes — was an auth-only privilege downgrade); `apiHotspots` returns empty for an
  anon caller on a non-public model (was leaking hotspot titles/links/positions). Verified via
  route:list (RequireAdmin) + the is_public gate. (The image-ar direct-node AI URL — tier C —
  remains; overlaps the gateway work #1368.)
- **#1380 ahg-federation** — `testPeer()` now runs the admin-supplied URL through
  `FederationClient::hostAllowed()` (blocks private/reserved/cloud-metadata hosts — verified
  169.254.169.254 → false) before fetching, and both probe requests use `withoutRedirecting()`
  so a public host can't 30x-redirect into an internal one. The plaintext peer-credential
  encryption (multi-file across locked services) is DEFERRED — #1380 stays open for it.

## Fixes applied (2026-06-28, #1374 + #1354 batch)
- **#1374 ahg-records-manage** — separation-of-duties on the disposal chain: approve() requires
  approver≠initiated_by; clearLegal() requires clearer≠approved_by; executeDestroy() requires
  executor∉{approved_by,legal_cleared_by} AND `AclService::isAdministrator()` (irreversible
  destruction is administrators-only, not the EDITOR-inclusive canAdmin). FIXED.
- **#1354 (batch, unlocked packages)** — added the missing `acl:`/`admin` route gates:
  ai-compliance (all risk/systems/models/**oversight countersign/attest/halt** mutations →
  acl:create/update/delete), vendor (5 match-routes → acl:create/update), exhibition (add/edit
  → acl:create/update), media-streaming (caption CRUD → admin), provenance (write+legacy →
  acl:update/delete), mods-manage + rad-manage (edit-that-publishes → acl:update). Verified via
  route:list. #1354 STAYS OPEN — the LOCKED items (actor-manage config, spectrum POPIA writes,
  IO-manage 13 routes, condition) need unlocks, plus ftp-upload/researcher-manage/naz/gis remain.

## Fixes applied (2026-06-28, #1354 locked-package batch)
- **#1354 (locked subset)** — route-level gates added (each route verified to exist + lack a
  gate first): **ahg-actor-manage** config→admin (rewrites authority config), dedup.scan +
  reconcile.link + api.identifier.verify + completeness.recalc/batch-assign→acl:update;
  **ahg-spectrum** POPIA writes (privacy-breaches/dsar/ropa/templates)→admin, workflow/barcode/
  notification/annotations/procedure mutations→acl; **ahg-information-object-manage** the 13
  sibling write routes (condition photo/annotation/ai-assess, ai.describe, preservation
  create/update/export, privacy scan/redaction, research assessment/annotations)→acl,
  admin.fix-missing-slug→admin; **ahg-condition** annotation.save/photo.upload/delete/base→acl.
  Verified via route:list (RequireAdmin/CheckAcl), anon still 302. #1354 STILL OPEN for
  ftp-upload / researcher-manage / naz / gis (the remaining lower-impact items).

## Fixes applied (2026-06-28, #1354 tail batch)
- **ahg-ftp-upload** — upload/uploadChunk/combineFolder/attachExisting→acl:create,
  deleteFile/clearAll→acl:delete (were auth-only; attach writes a digital_object).
- **ahg-researcher-manage** — researcher.add→acl:create, researcher.edit→acl:update,
  import.store→acl:create (was auth-only; delete already admin).
- **ahg-naz** — route group now `['web','auth','admin']` (was web+auth; reads incl. researcher
  PII now require admin, aligning with sibling NMMZ).
- **ahg-gis** — admin/gis group now `['web','auth','admin']` (was web+auth; closed the
  any-authed-user read of draft-IO coordinates).
- Verified: route:list CheckAcl/RequireAdmin, anon still 302. #1354 STILL OPEN — remaining
  lower-impact items: gallery mutations, library /library-manage, dacs/dc-manage publish,
  biblio-bf/frbr, MARC/EAD import, favorites revokeSharing, workflow approve/reject IDOR,
  security-clearance self-approval, provenance-ai governance, ai-services legacy aliases.

## Fixes applied (2026-06-28, #1354 FINAL batch — closes #1354)
Route-level acl:/admin gates:
- **ahg-gallery** — store/update/destroy + artists/loans/valuations/venues store → acl:create/update/delete.
- **ahg-library** — serials/ILL(legacy)/kbart-remote/trading-partners mutations → acl:*; PLUS the
  phase-2.5 library.ill-requests.* group (store→create, update/patch/transition/send-edi→update,
  destroy→delete). Diagnostics (test-url/preview/test) + OPAC patron submit left.
- **ahg-dacs-manage / ahg-dc-manage** — edit match-route POST (publishes IO) → acl:update.
- **ahg-biblio-bf** — import-run→create, editor work/contributor/subject add+delete→update/delete.
  (export-run verified read-only, left.)
- **ahg-biblio-frbr** — admin.frbr.overrides store/destroy/cluster → admin.
- **ahg-metadata-export** — MARC + EAD import groups → +admin (parity with rad/dacs importStandard).
- **ahg-provenance-ai** — admin/governance group → +admin (LLM configs + inference activity).
- **ahg-ai-services** — legacy NER/decision/batch POST aliases → +admin (parity with /admin/ai twins).
In-controller checks:
- **ahg-favorites** — FolderService::revokeSharing now owner-scoped (was folder_id-only; cross-user
  share-row deletion). Signature + caller (Auth::id()) updated.
- **ahg-workflow** — approveTask/rejectTask now require assigned_to===userId (mirrors releaseTask; within-admin IDOR).
- **ahg-security-clearance** — reviewAccessRequest denies reviewer===requester; grantClearance denies
  userId===grantedBy (separation of duties / no self-grant).
- Verified: route:list CheckAcl/RequireAdmin, controller guards present, anon no 5xx. **#1354 COMPLETE.**

## Fixes applied (2026-06-28, #1368 — AI-gateway bypass, partial)
- **ahg-ai-services LlmService** — Ollama completion now routes through the AHG AI
  gateway: dispatch + checkProviderHealth call resolveOllamaBase() (rejects raw-node
  endpoint_url — :11434/localhost/LAN IP — and falls back to ai.theahg.co.za/ai/v1),
  callOllama POSTs to {base}/ollama/api/generate with the gateway Bearer key
  (resolveGatewayKey: ahg_ner_settings.api_key → ahg_ai_settings general api_key).
  Mirrors ahg-discovery OllamaPageIndexClient. Fixes the chatbot completion +
  ai.describe bypass at the shared layer. VERIFIED end-to-end: completeFull → gateway
  → "Pong!" (mistral:7b), stale :11434 dev row overridden to gateway.
- **install.sql** — reseeded ahg_ai_settings.api_url + ahg_ner_settings.api_url +
  ahg_llm_config.endpoint_url to the gateway (were :5004/:11434 nodes).
- **DEFERRED — DonutService** (#1368 part 2): the gateway exposes NO /donut route
  (probed: /ai/v1/donut/health → 404), so it cannot be pointed at the gateway without
  breaking extraction. Needs a gateway-side donut passthrough route added first
  (/opt/ahg-ai/gateway) — #1368 stays OPEN for this tail.

## Fixes applied (2026-06-28, #1368 Donut tail — closes #1368)
- **Gateway** (/opt/ahg-ai/gateway/app/routes/ai_proxy.py) — added a transparent
  /ai/v1/donut/{subpath} passthrough (DONUT_UPSTREAM_URL=.115:5008, DONUT_TIMEOUT=180),
  mirroring the htr/legacy + nuextract proxies: gateway auth + metering, verbatim
  method/body/query/content-type so JSON + multipart round-trip. Activated via
  `systemctl restart ahg-ai-gateway.service`. (Gateway-repo git commit left to its
  own deploy convention — not committed here.)
- **ahg-ai-services DonutService** — baseUrl now defaults to the gateway /donut base
  (raw-node DONUT_SERVICE_URL override ignored via looksLikeNode), every call carries
  the gateway Bearer key (resolveGatewayKey, same order as NER/HTR), 9 Http:: sites
  routed through http(). VERIFIED end-to-end: DonutService->health() → gateway → live
  node (status:ok, model_ready:true). #1368 fully resolved.

## Partials sweep (2026-06-28) — verify + finish #1361/65/66/67/70/72/79/80/83
Closeable (acceptance fully met): #1370 (already), #1372, #1365, #1380, #1383.
- **#1372** — extended-rights.lift-embargo GET→POST (+ 2 blade links→csrf POST forms); ext-rights remove-tk-label +acl:delete.
- **#1365** — annotations resolveIoIdFromTarget() implemented (slug from /iiif-manifest/{slug}), show() 404s anon on unpublished IO, search() empty for unpublished target.
- **#1380** — federation peer api_key/search_api_key encrypted at rest (new PeerSecret helper, Crypt + legacy-plaintext decrypt fallback); write sites encrypt, read sites decrypt. Verified round-trip + legacy passthru.
- **#1383** — doRestore() requires server-side confirm_phrase='RESTORE' (422 otherwise); restore.blade sends it. Admin-only + path-confinement already shipped.
- **#1367** — guest-gate actor/repo (suggest/searchActors/searchRepositories require auth) + throttle discovery/click (30,1) & pageindex/api (10,1). Anon IO draft-leak already gated. REMAINING: multi-tenant scoping (cross-tenant on multi-tenant installs) — #1367 stays open for that tail.
- **#1379** — SRU searchRetrieve() now status-158/160 gated. Z3950ServerService.executeSearch queries a separate library_marc_records store with no IO/status linkage (and no 'library' DB connection defined) — a status join would break it; needs a design decision. #1379 stays open for the Z3950-server tail.
- **#1366** — view() IDOR already fixed; wrong-table split NOT changed: security_access_request (write) vs access_request (reads) have materially different columns (classification_id/object_id/priority absent on access_request; NOT-NULL requested_classification_id/reason unsupplied). Needs a human reconciliation decision — stays open.
- **#1361** — image-ar delete/3d-model hotspots already gated; REMAINING: AR AI endpoint (.78:5052) needs a gateway-side route (like Donut) + MP4 publication gate — stays open.

## Fix applied (2026-06-28, #1356 — closes #1356)
- **ahg-biblio-frbr** WorkClusterController::show() — added the guest published-only
  gate (when !auth: information_object.id!=1 + whereExists status type_id=158/status_id=160),
  mirroring the #1353/#1367 canonical pattern. Latent on dev (clustering unpopulated → 404);
  anon smoke still 404 (no 5xx). Authenticated editors still see drafts.

## Docs (2026-06-28) — #1350 closed, #1375 phases 1-2 delivered
- **#1375 phase 1 (inventory):** docs/reference/heratio-docs-coverage-matrix-2026-06-28.md —
  115 ahg-* packages: 113 have a user article, only 11 were wired, 52 carry legacy AtoM
  plugin docs, 2 have no dedicated article (ahg-inference-receipts, ahg-rdm).
- **#1350 / #1375 phase 2 (wire):** help-context.php contextual-help map went 21→64 path
  entries — wired all 8 T1 modules + 37 more verified admin modules (every slug confirmed
  in help_article, every prefix a real route). Re-pointed admin/dropdowns to its dedicated
  guide. Verified via HelpArticleService::contextualFor(): admin/acl, admin/backup,
  taxonomy, tenant, admin/users/{id}/edit, admin/naz, … all resolve. Closes #1350.
- **#1375 remaining (stays open):** de-stale the 52 legacy AtoM plugin docs + fix the known
  factual errors; author the two coherent manuals (User + Technical) with a domain ToC;
  fill the ~30 ahg-research submodule articles. These are a multi-phase authoring program.

## Fix applied (2026-06-28, #1366 functional half — closes #1366)
- **ahg-access-request** createRequest() now writes the canonical `access_request`
  table (was security_access_request, ahg-security-clearance's own table) — so
  submitted requests appear in My-Requests and approve/deny/view act on the same row.
  Native reason/justification/urgency columns (flatten hack removed); requested_
  classification_id defaults to baseline Public (level 0) when the form omits it;
  object requests write an access_request_scope satellite row. Both controller callers
  (store/storeObjectRequest) updated to unambiguous keys. View() owner-or-admin IDOR
  guard was already shipped. VERIFIED e2e: submit→My-Requests→getRequest→approve all on
  access_request; object scope row written.
- CAVEAT: any requests created via the OLD path are stranded in security_access_request
  (different schema) and invisible to the review surface — a one-time manual reconciliation
  would be needed on installs that used the broken path (dev had 1 test row).
