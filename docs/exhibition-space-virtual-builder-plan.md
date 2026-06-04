# Exhibition Space - Virtual Collection Builder + Virtual Walkthrough (Plan)

**Summary.** Add two spatial features on top of the existing exhibition-space
module (`/exhibition-space/{slug}`): (1) a drag-and-drop **virtual collection
builder** for arranging objects visually on a floorplan, and (2) a **2.5D
pannable floorplan walkthrough** for visitors. The current module is
capacity/timeline-only and has no spatial coordinate layer; both features hang
off a new spatial layer added to the existing tables. All client libraries are
loaded by CDN, matching the house pattern (TomSelect, vis-timeline). This is a
plan only - no code has been written.

Status: PLANNED. Target walkthrough style: 2.5D pannable floorplan (option 2b in
the original proposal). International-neutral; no new jurisdiction-specific
defaults introduced.

---

## 1. Current state

The exhibition space is a **capacity + timeline** tool, not a spatial one.

- `ahg_exhibition_space` - space with `capacity_value` / `capacity_unit`
  (linear_wall_meters, display_cases, plinths, square_meters) and
  `lighting_lux_target`. No floorplan, no 2D dimensions.
- `ahg_exhibition_placement` - `information_object_id` + `size_units_used` +
  `starts_at` / `ends_at` + `notes`. No x/y/rotation; placements are table rows,
  not canvas positions.
- The richer curated `exhibition` model already has
  `exhibition_object.display_position` (free text) and
  `exhibition_storyline` + `exhibition_storyline_stop`
  (`sequence_order`, `stop_number`, narrative, audio) - a guided-tour data
  structure the walkthrough can reuse.

**Gap:** no spatial coordinate layer and no visual canvas. That is what both
features require.

## 2. Data model (new migration in `packages/ahg-exhibition`)

Add a spatial layer without disturbing the capacity/timeline logic.

On `ahg_exhibition_placement` (each placement = one object instance in a space):

| Column | Type | Purpose |
|--------|------|---------|
| `pos_x` | DECIMAL(6,5) | normalized 0-1 X (survives canvas resize) |
| `pos_y` | DECIMAL(6,5) | normalized 0-1 Y |
| `rotation_deg` | DECIMAL(6,2) | object rotation on canvas |
| `scale` | DECIMAL(6,3) | display scale factor |
| `z_order` | INT | stacking order |
| `wall_or_zone` | VARCHAR(100) | optional wall/zone label |
| `label_visible` | TINYINT(1) | show/hide label in walkthrough |

On `ahg_exhibition_space` (the canvas itself):

| Column | Type | Purpose |
|--------|------|---------|
| `floorplan_image_path` | VARCHAR(500) | uploaded floorplan background |
| `floorplan_width_m` | DECIMAL(8,2) | real-world width (capacity tie-in) |
| `floorplan_height_m` | DECIMAL(8,2) | real-world height |
| `walls_json` | JSON | drawn wall/zone polygons |
| `walkthrough_path_json` | JSON | ordered route of placement ids |

Migration ships as `database/install-spatial.sql` (idempotent
`ALTER TABLE ... ADD COLUMN IF NOT EXISTS` pattern) plus the service-provider
boot auto-apply used elsewhere in the repo.

## 3. Phase 1 - Virtual collection builder (drag and drop)

- New view `exhibition-space/builder.blade.php`; route
  `GET /exhibition-space/{slug}/builder` behind `acl:update`.
- Canvas: **Konva.js** (CDN). A stage shows the floorplan (uploaded image or
  blank grid). Each placed object is a draggable, rotatable, resizable node with
  its thumbnail (IIIF / digital object) and label.
- Object tray (left): searchable via the existing
  `informationobject/autocomplete` endpoint (the same lookup now wired into the
  Add-a-placement form). Drag from tray onto canvas creates a placement; drag on
  canvas moves it.
- Save: debounced AJAX `POST /exhibition-space/{slug}/layout` writing
  `{placement_id: {x, y, rotation, scale, z}}`. A live capacity meter reuses
  `ExhibitionSpaceService::capacityOverflow()` so over-capacity layouts warn.
- Floorplan upload: `POST /exhibition-space/{slug}/floorplan`, stored under the
  centralised storage path from `config/heratio.php` (no hardcoded paths).

## 4. Phase 2 - Virtual walkthrough (2.5D pannable floorplan)

- New view `exhibition-space/walkthrough.blade.php`; route
  `GET /exhibition-space/{slug}/walkthrough` (auth optional - decide at build
  time).
- Render the floorplan as a **pan/zoom canvas** (Konva or OpenSeadragon, which
  is already used in the repo for deep-zoom). Each placed object is a clickable
  **hotspot** at its `pos_x`/`pos_y`.
- Clicking a hotspot opens the object panel: large image, label_text,
  label_credits, extended_label, optional audio (reusing the
  storyline-stop fields where an object is part of a storyline).
- Optional "guided route" overlay draws `walkthrough_path_json` as a numbered
  path and offers Next/Prev stepping between hotspots.
- Pan/zoom via pointer + wheel; mobile pinch-zoom. No headset/VR dependency in
  this phase (that was the heavier 360/A-Frame option, deferred).

## 5. Technology choices (all CDN, house style)

| Need | Library |
|------|---------|
| Drag/move/rotate builder canvas | Konva.js |
| Pannable walkthrough | OpenSeadragon (in repo) or Konva |
| Hotspot panels / stepping | plain JS + Bootstrap 5 |

## 6. Integration and safety notes

- `ahg-exhibition` is not hard-locked, but new blades + controller/service/
  migration edits follow the normal release flow (`./bin/release`).
- Reuses the IO autocomplete endpoint and `ExhibitionSpaceService` capacity
  logic - no backend duplication.
- International-neutral: the schema's existing `ZAR` / `South Africa` defaults
  are pre-existing; no new market-specific defaults added.
- Help + docs: ship a `docs/exhibition-space-virtual-builder-user-guide.md` and
  an in-app help article on build, per the project rule that code-only changes
  are incomplete.

## 7. Rough effort

| Phase | Scope | Estimate |
|-------|-------|----------|
| 1 | Builder: migration + Konva canvas + tray + save + floorplan upload | ~1-1.5 days |
| 2 | Walkthrough: 2.5D pannable floorplan + hotspots + panels + guided route | ~1 day |

## 8. Routes summary (new)

```
GET  /exhibition-space/{slug}/builder       acl:update   builder UI
POST /exhibition-space/{slug}/layout        acl:update   save positions (AJAX)
POST /exhibition-space/{slug}/floorplan     acl:update   upload floorplan
GET  /exhibition-space/{slug}/walkthrough   (auth TBD)   2.5D walkthrough
```
