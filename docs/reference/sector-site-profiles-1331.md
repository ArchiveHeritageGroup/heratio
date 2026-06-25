# Sector site profiles + sample content (#1331)

One-click provisioning of an opinionated starting point per GLAM sector
(archive / museum / gallery / library / dam / research). Jurisdiction-NEUTRAL
(sector only, no compliance regime). A profile sets defaults; it removes no
package and is re-applicable any time.

## What a profile applies (defaults only)
`AhgCore\Support\SectorProfiles` (declarative) + `AhgCore\Services\SectorProfileService::apply()`:
- **Theme palette** -> `ahg_settings` (group `theme`): coordinated primary /
  header / card / button / sidebar colours per sector.
- **Identifier mask** -> `setting`/`setting_i18n` as `sector_<code>_identifier_mask`
  (+ `_enabled`=1), read by `SectorIdentifierService`. e.g. `ARC/%Y%/%04i%`.
- **`sector_default`** marker -> `ahg_settings`.
Idempotent + re-applicable (switch sectors freely). Admins only.

## Three entry points (shared engine, no duplicated logic)
1. **CLI:** `php artisan ahg:apply-sector-profile <sector> [--with-sample]`.
2. **Fresh install:** `bin/install --sector=<sector> [--with-sample]` (Stage 8b).
3. **Admin UI:** `/admin/sector-profile` (`SectorProfileController`, admin-gated)
   - dropdown + "Also load sample content" checkbox.

## Sample content (slice 4, `--with-sample`)
`AhgCore\Services\SectorSampleService::load(sector)` seeds a small set of
representative PUBLISHED records so a freshly-provisioned site isn't empty.
Idempotent: each record keyed by slug `sample-<sector>-<n>` (re-run skips
existing). Per record it builds a full CTI information_object (object +
information_object + _i18n + slug + published status type 158/160), inserted
with `lft/rgt=0` + correct `parent_id`, with `ClosureMaintenanceService::addNode`
dual-writing the closure; after the batch it runs `openric:rebuild-nested-set`
+ `ahg:build-closure` so lft/rgt + closure are correct.

Depth per sector ("bundled media"):
- **archive:** a 3-level hierarchy (fonds -> file -> item) showing ISAD(G).
- **museum:** 3 objects, each with a `museum_metadata` row (object_type,
  materials, techniques, dimensions, etc.; the museum object number is the IO
  `identifier`, not a museum_metadata column).
- **library:** 3 catalogue records, each with a `library_item` row (material_type,
  isbn, call_number, publisher; `frbr_override_type='none'` - never null).
- **gallery / dam:** 2 records each with a real digital object - a bundled
  `packages/ahg-core/resources/sample-content/sample.jpg` attached via
  `DigitalObjectService::upload()`, which generates master (usage 140) +
  reference (141) + thumbnail (142) derivatives (pure GD, no ImageMagick needed
  for a JPEG). The image is copied to a temp file first because `upload()` MOVES
  the file.
- **research:** 2 described records.

Sector-specific rows are guarded by `Schema::hasTable` so the loader is safe when
a package isn't installed. The level term id is resolved by name from taxonomy 34
with AtoM-standard fallbacks (Fonds 236 / File 241 / Item 242).

## Verified on dev (340k-corpus box, 2026-06-25)
- `load('library')`: 3 IOs + 3 library_item rows; re-run -> created=0, skipped=3.
- `load('gallery')`: 2 IOs + 6 digital_object rows (2x master/reference/thumbnail,
  correct parent_id chain) with the three derivative files on disk under
  `/uploads/r/<masterId>/`, www-data-owned.
- All sample IOs published (158/160), lft/rgt rebuilt (no zeros).

## Fragilities captured
- `digital_object.id` is NOT auto-increment (CTI shared key) - `upload()` handles
  it; don't hand-roll. Store `path` with a leading `/uploads/` and trailing slash.
- Storage dir must be www-data-writable (NAS has no ACLs).
- `frbr_override_type` NOT NULL DEFAULT 'none' - an explicit null fails; the
  loader always writes 'none'.
- `museum_metadata.object_id` is UNIQUE (one row per IO); loader guards with an
  exists() check.

#1331 status: engine + bin/install wiring + admin UI + sample content (incl.
bundled media) all DONE on dev.
