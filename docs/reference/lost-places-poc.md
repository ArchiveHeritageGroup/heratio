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

## Pilot: Crystal Palace (real PD evidence pack)

`php artisan ahg:lost-place-demo` builds a runnable pilot from **public-domain
Wikimedia Commons** evidence for a genuinely vanished place - the Crystal Palace
(Sydenham), destroyed by fire 30 November 1936:

```
php artisan ahg:lost-place-demo --count=14            # download + catalogue PD evidence
php artisan ahg:lost-place-demo --remove              # reverse it (records, relations, files)
```

What it does (idempotent, reversible; descriptive User-Agent per Wikimedia policy):
1. Pulls candidate files from a Commons category + its subcategories, keeps only
   **public-domain / CC0** images, downloads them to
   `{storage_path}/uploads/lost-place-demo/<slug>/`.
2. Catalogues them as **one lost-place record** (a `rico:Record`) linked to a
   place access point, with each image a master `digital_object`.
3. Flags the record **`owl:deprecated`** (destroyed - deprecate-not-delete, #1321)
   so its RiC export distinguishes a vanished subject from a live one.

Verified 2026-06-20: 129 candidates -> 14 PD images -> coverage **WORKABLE**;
the reconstruct seed resolves to a real photo; the record exports
`owl:deprecated=true`. Actual TripoSR 3D generation still needs the gateway
endpoint live; CLIP discovery on the new images needs them indexed (#1272).

## CLIP discovery - functional today where imagery is indexed

`ahg:lost-place-gather "<place>" --discover` surfaces unlinked look-alike photos
by seeding from the place's already-linked master images and querying the
`archive_images` Qdrant index. It works **today** for any place with indexed
linked imagery - no dependency on #1272.

Validated 2026-06-19 on "Cape Town" (3 linked images -> 50 ranked candidates,
scores 1.000 exact-dupes down to ~0.61 related). `#1272` (the `.78` GPU embed
service) is only needed for the *other* seed path: discovering candidates for a
place that has **no** linked imagery to seed from (text-query or fresh-image
embedding). That path returns an explicit "needs #1272" note today.

## Next increments (#1323)

1. **Fresh/text-seed discovery (#1272)** - surface candidates for places with NO
   linked imagery (the `.78` GPU embed service is still pending). Image-seeded
   discovery already works (above).
2. **3D rebuild** - feed gathered imagery to `ahg-3d-model` (TripoSR today;
   photogrammetry / gaussian-splat are GPU-service concerns).
3. **Present + provenance** - the `ahg-exhibition` reconstruction surfaces with a
   documented-vs-inferred layer toggle; every inferred element carries an
   `AhgInferenceReceipt` (governance pin section 6).
