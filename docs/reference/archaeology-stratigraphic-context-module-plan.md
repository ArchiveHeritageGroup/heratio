# Stratigraphic context module - implementation plan

## Why

`ahg-archaeology` today records **sites** and **finds**, but a find's stratigraphic position is a free-text `context_reference` - the migration itself flags it *"until excavation recording lands."* There is no context entity, no top/bottom elevation, no stratigraphic relationships, and the module has **no data-entry UI** at all (the controller is read-only: index, sites, site, objects, object).

That means "layers" - the stratigraphic units an excavation is actually dug and recorded in - cannot be modelled as first-class things. You can approximate them today with a child record per context under the site description (each carrying its plan and section drawings; finds hang beneath their context). That works and is demonstrable, but it does not capture which context sits above, cuts, or fills which - and it cannot produce a Harris Matrix.

This plan adds the context as a proper entity, links finds and drawings to it, records the stratigraphic relationships, and gives the module the entry forms it lacks.

## Scope

Additive, inside the existing `ahg-archaeology` package. No change to how digital objects are stored - a context's contour plan and section drawings remain ordinary digital objects on the context's linked description (`information_object_id`), exactly as the current site/object records already link. The demo dig (`RFS-2026`, Rietfontein Shelter) is the reference shape.

## Data model

### `archaeology_context` - the stratigraphic unit ("layer")

| Column | Type | Note |
|--------|------|------|
| `id` | bigint pk | |
| `information_object_id` | uint, nullable, idx | descriptive record: title, ACL, notes, and the **plan/section drawings** (digital objects) |
| `site_id` | bigint FK → archaeology_site, idx | which dig |
| `context_number` | string(50), idx | e.g. "1002"; unique **within a site** |
| `context_type_id` | uint FK → term, idx | taxonomy: deposit / cut / fill / layer / surface / masonry / skeleton / structure |
| `description` | text | the recorder's context sheet description |
| `interpretation` | text | what it is read as |
| `top_elevation_m` | decimal(8,3), nullable | upper surface (the "top of layer") |
| `bottom_elevation_m` | decimal(8,3), nullable | lower surface |
| `excavation_reference` | string(100) | trench / square / spit |
| `excavator` | string(255) | |
| `excavation_date` | date, nullable | |
| `phase_id` | uint FK → term, nullable, idx | site phasing / period grouping |
| `date_earliest` / `date_latest` | string(50) | dates as strings (archaeological convention) |
| `dating_note` | text | |
| `status` | string(30), default 'active' | |
| timestamps | | |

Unique index on `(site_id, context_number)`.

### `archaeology_context_relationship` - the Harris Matrix edges

| Column | Type | Note |
|--------|------|------|
| `id` | bigint pk | |
| `context_id` | bigint FK, idx | |
| `related_context_id` | bigint FK, idx | |
| `relationship_type` | string(20) | `above` / `below` / `cuts` / `cut_by` / `fills` / `filled_by` / `same_as` / `bonds_with` / `abuts` |
| `note` | string(255) | |
| timestamps | | |

Unique `(context_id, related_context_id, relationship_type)`. **Reciprocity is enforced in the service**: writing `A above B` also writes `B below A`; `cuts`/`cut_by` and `fills`/`filled_by` mirror; `same_as`, `bonds_with`, `abuts` are symmetric. Deleting one side deletes its mirror. A guard rejects a relationship that would create a stratigraphic cycle (A above B above A).

### Link finds to context

Add `context_id` (uint FK → archaeology_context, nullable, idx) to `archaeology_object`. Keep `context_reference` as the display string. A one-off backfill matches each `context_reference` to a `context_number` within the same site and sets `context_id`; unmatched rows are listed for manual tidy-up. New/edited finds pick their context from a dropdown.

## UI - the entry forms the module lacks

The controller is read-only today; this adds the write side.

1. **Site create/edit** - the sidecar fields that exist but have no form (number, coords, permit, excavator, dating). Links/creates the descriptive record.
2. **Context create/edit + context sheet** - all `archaeology_context` fields; a stratigraphic-relationships editor (pick a context, pick a relationship, save - the mirror is automatic); the standard digital-object uploader on the context's description for **plan and section drawings**; the list of finds in the context.
3. **Find create/edit** - existing object fields plus a **context picker** (replacing free-text entry).
4. **Site view gains a "Stratigraphy" tab** - the context list ordered by elevation, and the Harris Matrix.

## Harris Matrix visualisation

Compute the layering server-side (topological sort of the `above`/`below` + `cuts`/`fills` edges into stratigraphic tiers), then render. First pass: emit a **Mermaid flowchart** (Heratio already renders Mermaid in help/artifacts), which gives a clean, zoomable matrix with no new JS dependency. Later pass if needed: a dedicated SVG/d3-dag renderer for large matrices and print. `same_as` nodes merge; unphased contexts float to the side.

## Phasing

- **Phase 1 - the layer entity. [BUILT - v1.154.445]** `archaeology_context` migration + `context_type`/`phase` taxonomy terms (`Archaeological Context Type` / `Archaeological Phase`) + context CRUD (`ArchaeologyController::contexts/context/contextCreate/contextEdit/contextSave`, `ArchaeologyService::contextsForSite/context/saveContext`) + context sheet view + a "Stratigraphy" entry on the site view. Creating a context auto-makes a child description under the site so plan/section drawings upload to it. `context_id` added to `archaeology_object` with `backfillContextIds()` from the legacy free-text `context_reference`. Remaining for a later pass: a context picker on the find form (needs the find data-entry form, Phase 4) and slotting the auto-created context descriptions into the tree on save (today they appear after the next nested-set/closure rebuild). **This delivers structured layers with drawings and finds.**
- **Phase 2 - stratigraphy.** `archaeology_context_relationship` + reciprocal/cycle logic + the relationship editor on the context sheet.
- **Phase 3 - Harris Matrix.** Topological layering + Mermaid render on the site's Stratigraphy tab.
- **Phase 4 - fill-out.** Site and find data-entry forms; CSV import of contexts + relationships (Harris Matrix data commonly arrives from single-context recording spreadsheets); context-sheet PDF export; Elasticsearch + spatial indexing so contexts are searchable and mappable.

Each phase is independently shippable and its own release.

## Verification per phase

- **P1:** create site → add Context 1002 (deposit, top/bottom elevation) → upload its plan → catalogue a find into it via the picker → context sheet renders drawing + finds; find's context resolves to the entity, not free text.
- **P2:** set "1002 below 1001, 1002 cuts 1003" → the mirror edges appear on 1001 and 1003; a cyclic entry is refused.
- **P3:** Stratigraphy tab renders a Harris Matrix with 1001 over 1002 over the natural, the cut shown correctly.
- **P4:** import a 30-context CSV → all contexts + relationships land; each is searchable; the site maps at its coordinates.

## Notes

- Cultural-protocol integration is already available: `context_type` terms can carry ICIP protocols via `term_protocol` (burials/sensitive contexts), so the visibility gates apply to contexts for free.
- The child-record-per-context approach demonstrated in `RFS-2026` remains valid and is not thrown away - Phase 1 simply promotes those context records to carry the structured `archaeology_context` sidecar, the same way sites and finds already pair a description with a sidecar row.
