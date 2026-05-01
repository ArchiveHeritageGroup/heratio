# Heratio - Migration Technical Reference

**Version:** 2.8.2
**Last Updated:** February 2026

---

## Overview

The Heratio migration transforms AtoM from a Symfony 1.x/Propel monolith into a standalone Laravel-based platform. The migration is incremental - a kill-switch toggles between modes, and both Symfony and Heratio can serve pages simultaneously. Zero base AtoM modifications required.

---

## Architecture Diagram

```
WITH Heratio installed (nginx includes heratio.conf):

  AHG plugin routes ──→ heratio.php ──→ Kernel ──→ Middleware ──→ ActionBridge
                                                                       │
                                                        ┌──────────────┼──────────────┐
                                                        ▼              ▼              ▼
                                                   AhgController   AhgActions    sfActions
                                                   (standalone)    (Blade)       (Propel Bridge)
                                                        │              │              │
                                                        ▼              ▼              ▼
                                                   WriteService   BladeRenderer  Symfony sfView
                                                   + Laravel DB   + Layout wrap  + theme partials
                                                        │              │              │
                                                        └──────────────┴──────────────┘
                                                                       │
                                                                       ▼
                                                                  Full HTML Page

  Base AtoM routes  ──→ index.php  ──→ Symfony ──→ Full HTML page (unchanged)

WITHOUT Heratio (standard AtoM):
  ALL routes        ──→ index.php  ──→ Symfony ──→ Full HTML page (unchanged)
![wireframe](./images/wireframes/wireframe_56587971.png)
```

---

## Kill-Switch Mechanism

| Component | Flag | Purpose |
|-----------|------|---------|
| App-level | `.heratio_enabled` file in root | PHP checks `file_exists()` |
| Nginx-level | `heratio.conf` include | Routes AHG plugin URLs to `heratio.php` |

Both can be toggled instantly without deployment. Removing the flag falls back to standard AtoM.

---

## Completed Foundation (10 Commits)

| Commit | Description | Status |
|--------|-------------|--------|
| C1 | App kill-switch (`.heratio_enabled` flag) | Done |
| C2 | Nginx kill-switch (heratio.conf dual entry) | Done |
| C3 | DB config + boot assertions | Done |
| C4 | WriteService interfaces + adapter skeleton (6 interfaces) | Done |
| C5 | Refactor Settings handlers (14 files) | Done |
| C6 | Refactor ACL permissions handler | Done |
| C7 | Refactor DO edit actions (2 files) | Done |
| C8 | Refactor Term/Accession/Import services | Done |
| C9 | Route modernization (Settings + Display -> routes.php) | Done |
| C10 | Audit scripts + CI guardrails | Done |

---

## Infrastructure Layer (All Complete)

| Component | Status | Details |
|-----------|--------|---------|
| HTTP Kernel | Done | Boot sequence, middleware pipeline, route dispatch |
| Authentication | Done | Login/logout/me, session sharing, SfUserAdapter |
| Menu Service | Done | MPTT tree from DB, culture-aware, static cache |
| Blade Rendering | Done | BladeRenderer, custom directives, 326 templates |
| Symfony Helper Shims | Done | blade_shims.php -- url_for, link_to, slots, partials (403 lines) |
| Master Layout | Done | heratio.blade.php + 8 partials (header, footer, nav, alerts) |
| Middleware Stack | Done | Session, Auth, Settings, CSP, Meta, Limits (7 middleware) |
| Nginx Config | Done | heratio.conf with kill-switch, ~40 plugin route patterns |
| WriteServiceFactory | Done | 12 interfaces, 12 PropelAdapters with Laravel DB fallback |
| Routes.php | Done | 77 native routes (Settings 55 + Display 22) |
| Audit Scripts | Done | bin/audit-propel, audit-propel-baseline, audit-propel-check |

---

## Phase 1: Read Services (Complete)

| Component | Details |
|-----------|---------|
| PaginationService (WP11) | SimplePager + PaginationService (universal, replaces per-plugin pagers) |
| EntityQueryService (WP12) | Slug resolution, entity loading, MPTT traversal, i18n (837 lines) |
| SearchService (WP13) | Standalone ES via HTTP curl, DB LIKE fallback, faceted search |
| LightweightResource | Magic wrapper for template compatibility (`__get`, `__isset`, `__toString`) |

---

## Phase 2: Entity CRUD Services (Complete)

| Component | Details |
|-----------|---------|
| UserWriteService (WP14) | createUser, updatePassword, savePasswordResetToken (6 files refactored) |
| ActorWriteService (WP15) | createActor, updateActor, createRelation, saveActor (AI plugin refactored) |
| PhysicalObjectWriteService (WP16) | newPhysicalObject, create/update/save (4 files refactored) |
| FeedbackWriteService (WP17) | createFeedback (ThemeB5 editFeedback refactored) |
| RequestToPublishWriteService (WP17) | createRequest (Display + ThemeB5 refactored) |
| JobWriteService (WP17) | createJob (DataMigration queueJob refactored) |
| Settings/Themes (WP17) | Remaining save() patterns in Settings + ThemeB5 refactored |

**WriteServiceFactory: 12 services total:**
settings, acl, digitalObject, term, accession, import, user, actor, physicalObject, feedback, requestToPublish, job

---

## PaginationService Integration (Complete)

Wired into 12 action files as dual-mode fallback (`class_exists('QubitPager')` branch):

| Plugin | File | Method |
|--------|------|--------|
| ahgStorageManagePlugin | physicalobject/autocompleteAction | execute() |
| ahgStorageManagePlugin | physicalobject/actions | executeAutocomplete() |
| ahgStorageManagePlugin | storageManage/actions | executeAutocomplete() |
| ahgRightsHolderManagePlugin | rightsholder/autocompleteAction | execute() |
| ahgRightsHolderManagePlugin | rightsholder/listAction | execute() |
| ahgDonorManagePlugin | donor/autocompleteAction | execute() |
| ahgDonorManagePlugin | donor/listAction | execute() |
| ahgRequestToPublishPlugin | requesttopublish/browseAction | execute() |
| ahgRequestToPublishPlugin | requesttopublish/receiptAction | execute() |
| ahgSearchPlugin | descriptionUpdatesAction | doAuditLogSearch() |
| ahgSearchPlugin | globalReplaceAction | AhgSearchPager -> SimplePager |
| ahgReportsPlugin | reportTaxomomyAction | doSearch() |

---

## Current Propel Coupling Baseline

```
->save()          : 42    (was 53, -11 via WP14-17)
new Qubit*        : 53    (was 68, -15 via WP14-17)
->delete()        : 128   (unchanged)
->setValue(       : 0     (was 2, -2)
QubitQuery        : 0     (unchanged)
Total coupling    : 223   (was 251, -28)
```

### Classification of Remaining `new Qubit*` References (53)

| Category | Count | Action |
|----------|-------|--------|
| READ-ONLY | 32 | Leave -- validators, pagers, helpers (never saved) |
| WIDGET | 5 | Leave -- form formatters, input widgets |
| DEFERRED | 11 | Leave -- addDigitalObject/multiFileUpload (complex Propel asset pipeline) |
| **WRITE** | **5** | **Remaining wrappable patterns** |

### Classification of Remaining `->save()` Calls (42)

Most are form-bound `$this->resource->save()` patterns (resource loaded from Propel, mutated by sfForm, saved):

- `$this->resource->save()` in edit actions (sfIsaarPlugin, termTaxonomy, library, etc.)
- `$findingAid->save()` in rename actions (Display, Library)
- `->save()` in addDigitalObject/multiFileUpload (Propel asset pipeline -- DEFERRED)
- `->save()` in requestToPublish editAction (form-bound -- RequestToPublishPlugin)

### Classification of Remaining `->delete()` Calls (128)

Mostly in dedicated `deleteAction.class.php` files -- legitimate entity deletions using Propel's cascade mechanism. These work through Propel's cascade chain (`object -> actor -> user`, etc.) and are hard to abstract without replicating the full cascade.

---

## Outstanding Phases

### Phase 3: Delete Services (Low Priority)

128 `->delete()` calls across ~35 delete action files. These use Propel's cascade mechanism.

**Proposed WP18: EntityDeleteService**

```php
class EntityDeleteService
{
    public static function delete(int $objectId): bool;
    // Handles: object -> actor -> user/donor/repository cascade
    // Handles: object -> information_object -> digital_object cascade
    // Handles: property, note, relation, event cleanup
}
```

**Risk:** HIGH -- incorrect cascade can leave orphaned rows or violate FK constraints.
**Recommendation:** Keep using Propel for deletes. Only implement when Propel fully removed.

### Phase 4: Form Framework (Low Priority)

Replace sfForm with Laravel `Illuminate\Validation`.

```php
class FormService
{
    public static function validate(Request $request, array $rules): array;
    // Returns validated data or throws ValidationException
}
```

Not needed while PropelBridge loads Symfony core. sfForm is available even in Heratio mode.

### Phase 5: Propel Bridge Removal (Future)

Replace `Qubit*` Propel models with PHP value objects + repositories:

- `QubitInformationObject` -> `InformationObject` + `InformationObjectRepository`
- `QubitActor` -> `Actor` + `ActorRepository`
- `QubitDigitalObject` -> `DigitalObject` + `DigitalObjectRepository`

Very large effort. Only after all other phases stable.

---

## Propel Coupling by Plugin

| Plugin | save | new | delete | Total | Priority |
|--------|------|-----|--------|-------|----------|
| ahgThemeB5Plugin | 12 | 10 | 8 | 30 | P2 -- locked |
| ahgDisplayPlugin | 9 | 7 | 6 | 22 | P2 -- locked |
| ahgAPIPlugin | 0 | 0 | 14 | 14 | P3 -- delete-only |
| ahgSettingsPlugin | 0 | 7 | 6 | 13 | P2 |
| ahg3DModelPlugin | 0 | 0 | 11 | 11 | P3 -- delete-only |
| ahgTermTaxonomyPlugin | 3 | 2 | 4 | 9 | P2 -- locked |
| ahgLibraryPlugin | 5 | 3 | 1 | 9 | P2 -- locked |
| ahgAccessionManagePlugin | 1 | 2 | 6 | 9 | P2 -- locked |
| ahgDAMPlugin | 2 | 1 | 6 | 9 | P2 |
| ahgResearchPlugin | 0 | 0 | 8 | 8 | P3 -- delete-only |
| ahgExtendedRightsPlugin | 0 | 0 | 8 | 8 | P3 -- delete-only |
| ahgStorageManagePlugin | 2 | 3 | 2 | 7 | P2 -- locked |
| ahgDonorAgreementPlugin | 0 | 0 | 7 | 7 | P3 -- delete-only |
| ahgRightsHolderManagePlugin | 1 | 3 | 2 | 6 | P2 -- locked |
| ahgRequestToPublishPlugin | 2 | 2 | 1 | 5 | P2 |
| ahgReportsPlugin | 0 | 5 | 0 | 5 | P3 -- read-only |
| ahgCorePlugin | 2 | 2 | 0 | 4 | P2 -- locked |
| ahgVendorPlugin | 0 | 0 | 4 | 4 | P3 -- delete-only |
| ahgUiOverridesPlugin | 2 | 2 | 0 | 4 | P2 -- locked |
| ahgICIPPlugin | 0 | 0 | 4 | 4 | P3 -- delete-only |
| ahgActorManagePlugin | 0 | 2 | 1 | 3 | P3 -- read-only |
| ahgMetadataExtractionPlugin | 0 | 0 | 3 | 3 | P3 -- delete-only |
| ahgIiifPlugin | 0 | 0 | 3 | 3 | P3 -- delete-only |
| ahgSearchPlugin | 1 | 1 | 0 | 2 | P2 |
| ahgDonorManagePlugin | 0 | 2 | 0 | 2 | P3 -- read-only |
| ahgSecurityClearancePlugin | 0 | 0 | 2 | 2 | P3 -- locked |
| ahgPrivacyPlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgSpectrumPlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgHeritageAccountingPlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgReportBuilderPlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgProvenancePlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgMuseumPlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgRepositoryManagePlugin | 0 | 1 | 1 | 2 | P3 -- read-only |
| ahgDataMigrationPlugin | 0 | 0 | 1 | 1 | P2 |
| ahgInformationObjectManagePlugin | 1 | 0 | 0 | 1 | P2 |
| ahgAIPlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgDedupePlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgFederationPlugin | 0 | 0 | 1 | 1 | P3 -- future |
| ahgFeedbackPlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgFormsPlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgGalleryPlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgHeritagePlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgSemanticSearchPlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |

### By Category

| Category | Files | Total Coupling | Strategy |
|----------|-------|----------------|----------|
| Write (save + new) | ~20 | 95 | Form-bound Propel (edit actions) + DEFERRED (DO upload) |
| Delete only | ~35 | 128 | Keep Propel (Phase 3) |
| Read only (pagers, validators) | ~15 | 37 | Can use PaginationService/EntityQueryService |
| Widgets | ~5 | 5 | Leave (UI components) |

---

## Framework Service Inventory

| File | Lines | Purpose |
|------|-------|---------|
| Pagination/SimplePager.php | ~150 | Universal pager compatible with _pager.php partial |
| Pagination/PaginationService.php | ~530 | High-level paginate() with entity-aware JOINs |
| EntityQueryService.php | ~837 | Slug resolution, entity loading, MPTT, i18n, relations |
| LightweightResource.php | ~58 | Magic wrapper for template compatibility |
| Search/SearchService.php | ~350 | Standalone ES search, DB fallback, facets |
| MenuService.php | ~100 | MPTT menu tree from database |
| Write/WriteServiceFactory.php | ~291 | 12-service singleton factory |
| Write/*Interface.php (12 files) | ~30 ea | Service contracts |
| Write/Propel*.php (12 files) | ~100 ea | Dual-mode adapters (Propel + Laravel DB) |

---

## Audit Tools

| File | Purpose |
|------|---------|
| audit-propel | Main coupling audit (5 patterns, per-file detail) |
| audit-propel-baseline | Saves JSON baseline to .propel-baseline.json |
| audit-propel-check | CI guardrail -- exit 1 on regression |

---

## Route Classification

| Type | Count | Notes |
|------|-------|-------|
| Native (routes.php) | 2 plugins | ahgSettingsPlugin, ahgDisplayPlugin |
| Bridged (routing.yml) | 39 plugins | Converted by RouteCollector at runtime |
| No routes | 39 plugins | Background/service plugins |

---

## Priority Matrix

```
PHASE 1 (Read Services)     ─── DONE
  WP11: PaginationService       ✓
  WP12: EntityQueryService       ✓
  WP13: SearchService            ✓

PHASE 2 (Entity CRUD)       ─── DONE
  WP14: UserWriteService         ✓
  WP15: ActorWriteService        ✓
  WP16: PhysicalObjectWriteService ✓
  WP17: MiscWriteServices        ✓

INTEGRATION                  ─── DONE
  PaginationService wired into 12 action files  ✓
  AhgSearchPager replaced with SimplePager       ✓

PHASE 3 (Delete Services)   ─── LOW: Keep Propel for now
  WP18: EntityDeleteService

PHASE 4 (Form Framework)    ─── LOW: sfForm works via PropelBridge
  WP19: FormService

PHASE 5 (Full Replacement)  ─── FUTURE: Remove Propel entirely
  WP20: Model Layer
```

---

## Success Criteria

| # | Criterion | Status |
|---|-----------|--------|
| 1 | Settings pages render fully standalone (no Propel) | DONE |
| 2 | Browse pages render standalone with PaginationService | DONE |
| 3 | Search pages render standalone with ES direct | DONE |
| 4 | CRUD pages work standalone with WriteServices | DONE |
| 5 | Delete operations work standalone | PENDING (WP18) |
| 6 | Kill-switch toggles instantly between modes | DONE |
| 7 | Zero base AtoM modifications | DONE |
| 8 | Audit baseline prevents regression | DONE |

---

*Part of the Heratio Framework*
