# Plan: 100% AtoM-to-Heratio Clone Parity

## Context

Heratio has 361 blade views with 12,291 controls. AtoM has ~2,183 templates across 227 plugin directories. Audit script now maps 194/361 views (Phase 1 done). Field badges and button classes are done. Storage-manage is the reference pattern for extended data wiring. ~350 custom DB tables exist; only `physical_object_extended` is wired. The goal is pixel-perfect parity with AtoM on every page.

---

## THE 12 RULES (mandatory checklist for EVERY page fix)

Every page fix/clone operation MUST follow ALL 12 rules. No exceptions.

| # | Rule | What to check |
|---|------|---------------|
| 1 | **Read CSS/theme rule** | Apply central theme: `var(--ahg-primary)`, `atom-btn-*` classes, card headers |
| 2 | **Count controls, report in table** | AtoM count vs Heratio count vs delta, per type (buttons, links, fields, headings, labels, badges, icons, text) |
| 3 | **Clone exactly** | Same number of controls, same look/feel, same text, same buttons, same CSS |
| 4 | **No asking permission** | Just fix it |
| 5 | **No "future enhancements"** | Everything gets done now, in one pass |
| 6 | **Text = controls** | Headings, labels, static text, help text, section titles all count |
| 7 | **All field badges** | Required (bg-danger), Recommended (bg-warning), Optional (bg-secondary) on every form label |
| 8 | **Report again after fixes** | Regenerate comparison table to confirm 0 deltas |
| 9 | **Layout template** | 1col vs 2col vs 3col — must match AtoM |
| 10 | **Sidebar** | Right or left — must match AtoM |
| 11 | **Page width/structure** | container vs container-fluid, column ratios — must match AtoM |
| 12 | **Button/link URLs** | Every href must route to correct Heratio equivalent of AtoM URL |

**Workflow per page:** BEFORE (read AtoM + Heratio, generate table) → FIX (clone all 12 dimensions) → AFTER (regenerate table, confirm 0 delta)

---

## PHASE 1: Fix Audit Script AtoM Mapping (unblocks everything)

**Problem:** `bin/audit-controls.php` uses wrong path `plugins/atom-ahg-plugins/modules/` — real path is `atom-ahg-plugins/<Plugin>/modules/<module>/templates/`.

**Task:** Rewrite the `$mapping` array (lines 130–243) with correct paths for all 51 Heratio packages. Each package maps to 2–4 AtoM source directories.

**Key mappings (verified):**

| Heratio Package | AtoM Source Directories |
|---|---|
| ahg-actor-manage | `ahgActorManagePlugin/modules/actorManage`, `ahgThemeB5Plugin/modules/actor`, `ahgThemeB5Plugin/modules/sfIsaarPlugin`, `ahgCorePlugin/modules/actor` |
| ahg-information-object-manage | `ahgInformationObjectManagePlugin/modules/ioManage`, `ahgThemeB5Plugin/modules/informationobject`, `ahgThemeB5Plugin/modules/sfIsadPlugin`, `ahgCorePlugin/modules/informationobject` |
| ahg-repository-manage | `ahgRepositoryManagePlugin/modules/repositoryManage`, `ahgThemeB5Plugin/modules/repository`, `ahgThemeB5Plugin/modules/sfIsdiahPlugin` |
| ahg-accession-manage | `ahgAccessionManagePlugin/modules/accessionIntake`, `ahgAccessionManagePlugin/modules/accessionManage`, `ahgAccessionManagePlugin/modules/accessionAppraisal`, `ahgThemeB5Plugin/modules/accession` |
| ahg-settings | `ahgSettingsPlugin/modules/ahgSettings` (110 files), `ahgCorePlugin/modules/settings` (27 files) |
| ahg-donor-manage | `ahgDonorManagePlugin/modules/donorManage`, `ahgDonorManagePlugin/modules/donor` |
| ahg-rights-holder-manage | `ahgRightsHolderManagePlugin/modules/rightsHolderManage`, `ahgThemeB5Plugin/modules/rightsholder`, `ahgExtendedRightsPlugin/modules/extendedRights` |
| *(+ all remaining 44 packages — full table in agent output)* | |

**Also:** Improve view name matching — normalize camelCase→kebab-case, handle `_` prefix partials, subdirectory awareness.

**Files:** `bin/audit-controls.php` (1 file)
**Deliverable:** Re-run produces accurate delta report with ~250–300 matched views (up from 43).

---

## PHASE 2: Extended Data Wiring (follow storage-manage pattern)

**Pattern:** Service reads `*_extended` table → Controller passes `$extendedData` → View renders fields in card sections.

**Reference:** `StorageService::getExtendedData()`, `StorageController::edit()`, `edit.blade.php`

| Priority | Table(s) | Package | Columns | Files to modify |
|---|---|---|---|---|
| 1 | `contact_information_extended` | ahg-actor-manage | 13 cols (title, role, dept, cell, id_number, alt email/phone, preferred contact, language) | 3 |
| 2 | `ahg_actor_completeness`, `ahg_actor_identifier`, `ahg_actor_occupation` | ahg-actor-manage | completeness scores, VIAF/ISNI identifiers, occupations | 3 |
| 3 | `extended_rights` + `extended_rights_i18n` | ahg-rights-holder-manage, ahg-information-object-manage | 13+4 cols, TK labels, batch log | 6 |
| 4 | `museum_metadata` | ahg-museum / ahg-information-object-manage | 96 CCO fields | 4 |
| 5 | `ahg_settings` + `ahg_dropdown` | ahg-settings | settings store, dropdown manager | 3 |
| 6 | Research tables (85+) | ahg-research | researcher, rooms, bookings, projects, reports | 20+ |
| 7 | Heritage tables (63) | ahg-heritage-manage | assets, accounting, discovery, contributions | 15+ |
| 8 | Workflow tables (9) | ahg-workflow | definitions, steps, tasks, history, SLA | 5 |
| 9 | Loan tables (20) | ahg-loan | loans, objects, conditions, shipments, costs | 5 |
| 10 | Security tables (16) | ahg-acl | classifications, clearances, compartments, audit | 5 |

**Script:** Create `bin/wire-extended-data.php` — for each `*_extended` table, checks if Service queries it and if View renders the fields. Outputs missing wiring report.

**Estimated files:** ~80

---

## PHASE 3: View-by-View Field Parity (largest phase)

For each edit/create/show form, compare field-by-field against AtoM and close the delta.

**Script:** Create `bin/parity-check.php` — extracts `name=` attributes from both AtoM template and Heratio blade, outputs fields present in AtoM but missing in Heratio.

**Priority order:**

| # | Package | Views | Controls | AtoM templates | Key gaps |
|---|---|---|---|---|---|
| 1 | ahg-information-object-manage | 34 | 1,272 | ~70 templates | Missing: alt identifiers, child levels, creators, dates, finding aids, reports, multi-file upload, storage locations, publication status update (~25 new views) |
| 2 | ahg-actor-manage | 7 | 468 | ~15 templates | Missing: context menu, occupations, search result, contact area view, rename (~8 new views) |
| 3 | ahg-repository-manage | 5 | 267 | ~10 templates | Missing: context menu, holdings, logo, search results (~5 new views) |
| 4 | ahg-accession-manage | 4 | 189 | ~29 templates | Missing: appraisal, container, intake sub-views (~20 new views) |
| 5 | ahg-settings | 37 | 856 | ~114 templates | Missing: ~40 AtoM settings pages not ported |
| 6 | All remaining entities | ~50 | ~1,500 | varies | Term, function, donor, rights-holder, user, jobs, search, static pages |

**Estimated:** ~135 new views, ~80 new controller/service methods, ~200 modified files

---

## PHASE 4: Missing Views — Full Template Coverage

After Phase 3, AtoM plugins with no Heratio equivalent remain:

| AtoM Plugin | Templates | Heratio Status |
|---|---|---|
| ahgRegistryPlugin | 137 | No package |
| ahgHeritagePlugin | 97 | Partial (6 views) |
| ahgPrivacyPlugin | 93 | Partial (3 IO views) |
| ahgSpectrumPlugin | 64 | Partial (2 IO views) |
| ahgMarketplacePlugin | 56 | No package |
| ahgICIPPlugin | 48 | No package |
| ahgPreservationPlugin | 46 | Partial (12 views) |
| ahgExtendedRightsPlugin | 44 | Partial (2 IO views) |
| ahgSecurityClearancePlugin | 38 | Partial (9 ACL views) |
| ahgIntegrityPlugin | 28 | Partial (1 view) |
| ahgVendorPlugin | 29 | No package |
| ahgCDPAPlugin | 20 | No package |
| ahgNAZPlugin | 19 | No package |
| *(+ 15 more small plugins)* | | |

**Estimated:** ~300 new views, ~100 new controller/service/route files

---

## PHASE 5: Show Page Parity

Every show page must replicate AtoM sections, field order, sidebar, action buttons.

**Script:** Create `bin/show-parity.php` — section-by-section comparison.

**Priority:** IO show → Actor show → Repository show → Accession show → all others.

**Estimated:** ~50 files modified + ~30 new partials

---

## PHASE 6: Browse Page Parity

Every browse page: same columns, sort options, facets, card/table views, pagination.

**Script:** Create `bin/browse-parity.php` — column/sort/facet comparison.

**Estimated:** ~30 files modified

---

## Dependency Graph

```
Phase 1 (fix audit) ← MUST BE FIRST
    ↓
Phase 2 (extended data) ──→ Phase 3 (field parity)
    ↓                            ↓
Phase 4 (missing views) ──→ Phase 5 (show pages)
                                 ↓
                           Phase 6 (browse pages)
```

---

## Scripts to Create

| Script | Purpose | Phase |
|---|---|---|
| `bin/audit-controls.php` (fix) | Correct AtoM path mapping, produce accurate delta | 1 |
| `bin/wire-extended-data.php` | Detect unwired extended DB tables | 2 |
| `bin/parity-check.php` | Field-name comparison (AtoM `name=` vs Heratio `name=`) | 3 |
| `bin/missing-views.php` | List AtoM templates with no Heratio blade | 4 |
| `bin/show-parity.php` | Section/sidebar comparison for show pages | 5 |
| `bin/browse-parity.php` | Column/sort/facet comparison for browse pages | 6 |

---

## Totals

| Metric | Estimate |
|---|---|
| New blade views | ~465 |
| New controller/service/route files | ~180 |
| Modified existing files | ~400 |
| **Grand total file operations** | **~1,045** |

---

## Verification

After each phase, re-run `php bin/audit-controls.php` to verify:
- Phase 1: Mapped views jumps from 43 → 250+
- Phase 2: Extended data fields appear in audit
- Phase 3: Control deltas approach 0 on edit/create forms
- Phase 4: Missing views count drops
- Phase 5: Show page section counts match
- Phase 6: Browse column/facet counts match
