# Lost Places POC - evidence gather (#1323, increment 1)

Reconstruct a no-longer-extant place from its archival evidence:
**gather -> 3D rebuild -> provenance-tagged digital twin**. Increment 1 ships
the *gather* step - the rest of the pipeline is tracked on issue #1323.

## What increment 1 does

Given a place, collect every archival record linked to it and the media those
records hold, then score whether there is enough imagery to attempt a 3D
reconstruction. Read-only.

```
php artisan ahg:lost-place-gather "Notre-Dame"        # human-readable table + coverage
php artisan ahg:lost-place-gather 901212 --json       # machine manifest (hand-off to 3D step)
php artisan ahg:lost-place-gather "Paris" --limit=50
```

The place argument is a partial name **or** a place-taxonomy term id.

## How it resolves + joins

- **Place** = an AtoM place access point: a `term` in the places taxonomy
  (`Taxonomy::PLACE_ID = 42`), which the RiC graph mirrors as a `rico:Place`.
- **Linked records**: `object_term_relation.term_id = <place term>` ->
  `information_object`.
- **Media**: master `digital_object` rows (`parent_id IS NULL`) per record,
  split into image / document / other by `mime_type`.
- **Graph persistence**: reports whether the place exists as a `rico:Place`
  (`ric_place_i18n`) - the deprecate-not-delete guarantee from the #1319
  governance pin means a vanished place still has a stable node.

## Coverage metric (master image count)

| Band | Images | Meaning |
|---|---|---|
| `strong` | >= 40 | photogrammetry / gaussian-splat viable |
| `workable` | >= 12 | rough model achievable; expect gap-fill |
| `sparse` | >= 1 | reconstruction heavily inferred |
| `insufficient` | 0 | no linked imagery; try CLIP discovery (#1272) |

## Code

- `AhgExhibition\Services\LostPlaceGatherService` - `gather()`, `resolvePlace()`,
  `recordsForPlace()`.
- `AhgExhibition\Console\Commands\LostPlaceGatherCommand` - `ahg:lost-place-gather`.
- Tests: `packages/ahg-exhibition/tests/Feature/LostPlaceGatherTest.php`.

## Next increments (#1323)

1. **CLIP candidate discovery (#1272)** - surface UNLINKED photos *of* the place
   (the `.78` GPU embed service is still pending; gather quality depends on it).
2. **3D rebuild** - feed gathered imagery to `ahg-3d-model` (TripoSR today;
   photogrammetry / gaussian-splat are GPU-service concerns).
3. **Present + provenance** - the `ahg-exhibition` reconstruction surfaces with a
   documented-vs-inferred layer toggle; every inferred element carries an
   `AhgInferenceReceipt` (governance pin section 6).
