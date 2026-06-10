# Museum 3D ingest, glTF decoders, and per-object visitor attention

Reference for three related pieces of work (2026-06-10): how 3D objects are ingested into
the museum sector, how compressed-glTF decoders are self-hosted, and the per-object
attention layer added to the exhibition heatmap (#1187).

## 1. Ingesting 3D objects into the museum sector

Pattern script: `stuff/ingest-museum-glb.php` (furniture) and `stuff/ingest-helmets.php`
(the "Helmets over Time" collection). Run as www-data:

```
sudo -u www-data php stuff/ingest-helmets.php
```

Per record the script:
1. `MuseumService::create($data)` - inserts `object` + `information_object` (+ nested-set
   lft/rgt via `parent_id`, default root = 1) + `information_object_i18n` + `museum_metadata`
   + `slug` + `display_object_config`. `create()` maps known keys with `?? null`, so unknown
   keys are silently ignored. Returns the slug; resolve the IO id from `slug.object_id`.
2. Publication status: insert `status(object_id, type_id=158, status_id=160)` = Published.
3. Copy the access model into `<uploads_path>/<ioId>/` and insert the master
   `digital_object` (usage_id=140, media_type_id=139, mime `model/gltf-binary`,
   path `/uploads/r/<ioId>/`).
4. `ModelMetadataExtractor::extract($path,'glb')` for vertex/face/format, then insert
   `object_3d_model` (is_primary=1, is_public=1) merged with the extracted meta + paradata.

Levels of description (taxonomy 34): Collection = 238, Item = 242, "3D Model" = 1757.
Show pages select the displayed object by `digital_object.usage_id = 140` (Master);
reference = 141, thumbnail = 142 are children via `parent_id`.

### Gotchas
- **BC / pre-1000 dates**: `museum_metadata.creation_date_earliest/latest` are MySQL `DATE`
  columns and cannot store BC or sub-year-1000 dates. Leave them null and put the human
  range in `creation_date_display` (varchar).
- **Direct DB inserts skip Elasticsearch.** Reindex afterwards
  (`php artisan ahg:es-reindex --index=informationobject --id=<id>`).
- **`gis` mapping conflict (fixed 2026-06-10).** The IO index had `gis` auto-mapped as an
  object `{lat,lon}` while the reindex command (#650) PUTs `gis` as `geo_point` - ES can't
  change a field type in place, so every reindex aborted with
  `can't merge a non object mapping [gis] with an object mapping`. `--drop` does NOT fix it:
  the recreate path clones the mapping from `archive_qubitinformationobject`, which carries
  the same object-`gis`. Fix: recreate `heratio_qubitinformationobject` from its own current
  mapping with `gis` forced to `{type:geo_point}` (no custom analyzers, `dynamic:true`, so a
  straight recreate is safe), then `ahg:es-reindex --index=informationobject` (no `--drop`,
  index pre-exists so the archive clone is skipped and `ensureGeoPointMapping` is a no-op).
  geo_point accepts the `{lat,lon}` object value the doc emits, and the `ElasticsearchService`
  live-update path stays compatible. Same pattern would fix a recurrence after any archive
  re-clone.
- Run ingest **as www-data**, never root, so files/log entries aren't root-owned.

## 2. Source-master vs access-derivative file handling

Decision (Johan, 2026-06-10), aligned to the 3D preservation plan (WS2):
- **glTF/GLB = access derivative** -> the live, public viewer object (master digital_object
  + object_3d_model). model-viewer / three.js consume glTF/GLB natively.
- **Source bundle (zip with FBX / RAR / OBJ + lossless PNG PBR maps) = preservation master**
  -> copied verbatim to `<uploads>/<ioId>/source/` and documented in the 3D row's
  `derivation_note` + `pbr_maps` + `is_lossless_master=0` (CRMdig derivation chain). It is
  NOT registered as a second digital_object (a second usage=140 row would hijack the viewer).
- FBX is preserved as the authoring master; it is not run through `obj2gltf` (OBJ-only).
  Nested archives (e.g. a `.rar` under `source/`) are kept as delivered.

## 3. Self-hosted Draco + KTX2 decoders for compressed glTF

Vendored under `public/vendor/three/0.137.5/` (matched to the walkthrough's three.js r137.5):
- `draco/` - draco_decoder.wasm, draco_wasm_wrapper.js, draco_decoder.js
- `basis/` - basis_transcoder.wasm, basis_transcoder.js (KTX2/Basis)
- `loaders/KTX2Loader.js` - non-module global build for the walkthrough

Wiring:
- **model-viewer** (`ahg-information-object-manage/.../partials/_digital-object-viewer.blade.php`):
  a module script sets the static `ModelViewerElement.dracoDecoderLocation` =
  `/vendor/three/0.137.5/draco/` and `.ktx2TranscoderLocation` = `/vendor/three/0.137.5/basis/`.
  Static = set once, applies to every `<model-viewer>` on the page. The museum show page
  uses this partial.
- **three.js walkthrough** (`ahg-exhibition/.../walkthrough.blade.php`): DRACOLoader path
  switched from jsdelivr to the self-hosted draco dir; a KTX2Loader is added
  (`setTranscoderPath('/vendor/three/0.137.5/basis/')`, `detectSupport(renderer)`).

CSP (`app/Csp/HeratioCspPreset.php`) already allows `self` + WASM execution + blob workers,
so self-hosted decoders need no CSP change. Decoders are a no-op for uncompressed models, so
this is forward-cover for `ahg:optimize-models` (Draco) output and KTX2-textured uploads.

## 4. Per-object visitor attention in the exhibition heatmap (#1187)

Extends the per-room dwell heatmap down to individual objects.
- New `ahg_exhibition_visit` columns `cur_object`, `object_entered_at`, `object_seconds_json`
  (added in the service-provider boot migration).
- `recordVisitBeat()` banks per-object dwell exactly like per-room dwell, driven from the
  object the visitor currently has open (`cur_object` sent in the presence beat from the
  walkthrough when an object popup is open).
- `visitorHeatmap()` emits per-object dwell seconds + `max_object_seconds`; the analytics
  canvas sizes/shades object dots by dwell (large/opaque = draws attention, faint = seen but
  skipped) and labels the dwell. `visitorAnalytics()` adds dwell to the object list.

Attention = dwell, the companion to raw view counts: many views + little dwell = an object
that draws the eye but loses attention.
