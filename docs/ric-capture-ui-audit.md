# RiC Capture UI — Completeness Audit (Step 0)

**Last updated:** 2026-04-18 *(G1–G8 closed; G9 deferred)*
**Purpose:** Concrete gap list for the admin UI that captures RiC entities. Step 0 of the Heratio/RiC split plan: every RiC entity must be fully capturable through the GUI before the API is finalised (Step 1–3) and before the split (Step 4+).

**Scope:** `packages/ahg-ric/resources/views/entities/` admin forms for the 4 RiC-native entity types plus generic Relations. Does NOT cover `/api/ric/v1/*` endpoints or the embedded `_ric-view-*.blade.php` partials — those are later phases.

This is a living document. Update it as each gap is closed.

---

## Gap table

| Entity | Action | State | Blade / Source | Notes |
|---|---|---|---|---|
| **Place** | browse | ✓ | `entities/places/browse.blade.php` | Table + search. No "Create" button. |
| Place | show | ✓ | `entities/places/show.blade.php` | Details + relations editor. |
| Place | edit | ✓ | `entities/places/edit.blade.php` | Captures all fields incl. `parent_id` (2026-04-18). |
| Place | create | ✓ | `entities/places/edit.blade.php` + `ric.entities.create`/`ric.entities.store-form` | Dedicated `/admin/ric/entities/places/create` route (2026-04-18). |
| Place | delete | ✓ | `_sidebar.blade.php` | Delete form in sidebar. |
| Place | relations add/remove | ✓ | `_relation-editor.blade.php` | Full editor: autocomplete, type picker, dates, delete. |
| Place | field coverage | 6/6 | — | All columns captured. |
|  |  |  |  |  |
| **Rule** | browse | ✓ | `entities/rules/browse.blade.php` | Table + search. No "Create" button. |
| Rule | show | ✓ | `entities/rules/show.blade.php` | Details + relations. |
| Rule | edit | ✓ | `entities/rules/edit.blade.php` | Captures title, type, jurisdiction, dates, description, legislation, sources, `authority_uri` (2026-04-18). |
| Rule | create | ✓ | `entities/rules/edit.blade.php` | Dedicated create route + browse-page button (2026-04-18). |
| Rule | delete | ✓ | `_sidebar.blade.php` | Delete form. |
| Rule | relations add/remove | ✓ | `_relation-editor.blade.php` | Full editor. |
| Rule | field coverage | 7/7 | — | All columns captured. |
|  |  |  |  |  |
| **Activity** | browse | ✓ | `entities/activities/browse.blade.php` | Table + search. |
| Activity | show | ✓ | `entities/activities/show.blade.php` | Details display place_name (read-only); relations. |
| Activity | edit | ✓ | `entities/activities/edit.blade.php` | Captures all fields incl. `place_id` picker (2026-04-18). |
| Activity | create | ✓ | `entities/activities/edit.blade.php` | Dedicated create route + browse-page button (2026-04-18). |
| Activity | delete | ✓ | `_sidebar.blade.php` | Delete form. |
| Activity | relations add/remove | ✓ | `_relation-editor.blade.php` | Full editor. |
| Activity | field coverage | 5/5 | — | All columns captured. |
|  |  |  |  |  |
| **Instantiation** | browse | ✓ | `entities/instantiations/browse.blade.php` | Table. No "Create" button. |
| Instantiation | show | ✓ | `entities/instantiations/show.blade.php` | Details + relations. |
| Instantiation | edit | ✓ | `entities/instantiations/edit.blade.php` | Captures all fields incl. `record_id` + `digital_object_id` via autocomplete, and `production_technical_characteristics` textarea (2026-04-18). |
| Instantiation | create | ✓ | `entities/instantiations/edit.blade.php` | Dedicated create route + browse-page button (2026-04-18). |
| Instantiation | delete | ✓ | `_sidebar.blade.php` | Delete form. |
| Instantiation | relations add/remove | ✓ | `_relation-editor.blade.php` | Full editor. |
| Instantiation | field coverage | 8/8 | — | All columns captured. |
|  |  |  |  |  |
| **Relation** | browse (global) | ✓ | `relations/browse.blade.php` | Standalone `/admin/ric/relations` list view with filter + pagination (2026-04-18). |
| Relation | show | ✗ | — | No per-relation page. |
| Relation | create | ✓ | `_relation-editor.blade.php` + AJAX | Modal with autocomplete + type picker + date range. |
| Relation | edit | ⚠ | — | Only delete+recreate; no in-place edit. |
| Relation | delete | ✓ | `_relation-editor.blade.php` | Delete button per row; AJAX. |
| Relation | field coverage | 8/8 | — | Certainty + evidence added to modal (2026-04-18). Certainty uses `certainty_level` taxonomy. |

---

## Prioritised gap summary

### P0 — must fix before claiming "full capture"

- [x] **G1. Dedicated create pages** — added 2026-04-18. `GET /admin/ric/entities/{type}/create` and `POST /admin/ric/entities/{type}` routes, plus "Create" buttons on all 4 browse pages. Edit form reused by passing `$entity = null`.
- [x] **G2. Activity: `place_id` picker** — added 2026-04-18. Dropdown using `listPlacesForPicker()` helper on the service.
- [x] **G3. Instantiation: `record_id` and `digital_object_id` fields** — added 2026-04-18. Reusable `_fk-autocomplete.blade.php` partial (using the existing `/admin/ric/entity-api/autocomplete` endpoint, extended to cover `digital_object`).
- [x] **G4. Relation: `certainty` and `evidence`** — added 2026-04-18. Certainty dropdown uses `certainty_level` taxonomy; evidence is a free-text input. Service + controller already accepted the fields.
- [x] **G5. Place: `parent_id`** — added 2026-04-18. Edit form now exposes a parent-place dropdown; service layer already handled persistence.
- [x] **G6. Rule: `authority_uri`** — added 2026-04-18. Edit form now has a URL input; service layer already handled persistence.

### P1 — completeness

- [x] **G7. Instantiation: `production_technical_characteristics`** — added 2026-04-18 as part of the G3 pass.
- [x] **G8. Standalone Relations browse page** — added 2026-04-18. `/admin/ric/relations` with filter + pagination. Per-relation show page still deferred (G9).
- [ ] **G9. In-place Relation edit** — deferred. Requires turning the create modal into an edit modal with pre-populated values, plus a `PATCH /relation-update/{id}` endpoint. Not blocking full capture.

### Root causes

- Edit form reused for both create and update; no dedicated POST-create route.
- Foreign-key pickers (parent_id, place_id, record_id, digital_object_id) not implemented as a reusable autocomplete component.
- Relation-editor modal form is incomplete relative to `ric_relation_meta` columns.
- `authority_uri` simply forgotten in Place and Rule forms.

---

## Change log

| Date | Change |
|---|---|
| 2026-04-18 | Initial audit. 6 P0 gaps, 3 P1 gaps identified. |
| 2026-04-18 | G5 closed: Place `parent_id` picker added to edit form + `listPlacesForPicker()` helper on service. |
| 2026-04-18 | G6 closed: Rule `authority_uri` URL input added to edit form. |
| 2026-04-18 | G4 closed: relation-editor modal now captures certainty (certainty_level dropdown) + evidence. Existing relations in the table do not yet surface these fields — tracked as follow-up in G9 (in-place edit). |
| 2026-04-18 | G2 closed: Activity `place_id` dropdown added using `listPlacesForPicker()` helper. |
| 2026-04-18 | G3 + G7 closed in one pass: reusable `_fk-autocomplete.blade.php` partial for FK fields; `record_id` and `digital_object_id` autocomplete widgets wired up; `production_technical_characteristics` textarea added. Autocomplete service extended to include `digital_object` entity type. |
| 2026-04-18 | G1 closed: dedicated `/admin/ric/entities/{type}/create` routes for Place, Rule, Activity, Instantiation. Controller methods `createEntityForm()` + `storeEntityForm()`. All 4 browse pages now have "Create" buttons. Smoke-tested: all 5 new routes return HTTP 200. |
| 2026-04-18 | G8 closed: standalone `/admin/ric/relations` global browse page with filter + pagination. |
| 2026-04-18 | G9 deferred: in-place relation edit. Current workflow requires delete + recreate; not blocking full capture. |
