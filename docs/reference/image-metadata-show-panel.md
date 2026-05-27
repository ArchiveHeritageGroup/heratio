# Image Metadata Show Panel (Issue #746)

Heratio's archival-description show page renders an "Embedded Image Metadata" panel for image digital objects directly under the viewer. The panel reads EXIF, IPTC, and XMP-style data from three sidecar tables that the ingest / extraction pipeline maintains. Audio and video DOs continue to use the existing "Media Information" panel above this one.

## Data sources

| Section | Table | Lookup column | Notes |
| --- | --- | --- | --- |
| EXIF | `digital_object_metadata` | `digital_object_id` | Single row per DO (UNIQUE KEY). |
| IPTC | `dam_iptc_metadata` | `object_id` | Note: this table uses `object_id`, not `digital_object_id`. |
| XMP | `media_metadata` | `digital_object_id` | Same table the audio/video panel reads; image rows have `media_type` of `image` or are NULL. |

The partial does `DB::table(...)->where(...)->first()` against each table. If all three return null the partial returns early and renders nothing.

## Files

- New: `packages/ahg-information-object-manage/resources/views/partials/_image-metadata-panel.blade.php`
- Modified, one-line include only: `packages/ahg-information-object-manage/resources/views/partials/_digital-object-viewer.blade.php`. The include is added immediately after the existing `@if($isMediaFile) ... @endif` block (line 756 region) wrapped in an `@if(!$isMediaFile)` guard. Image DOs (and any other non-audio/video type) get the panel; media DOs are unchanged.
- Test: `tests/Feature/ImageMetadataPanelTest.php`
- Help: `docs/help/image-metadata-panel.md`

## Why a separate partial

The archival-record show subtree is `.locked-paths` locked. The lock-safe approach is:

1. Add new content in a new partial file (locked path, but the file is brand new, not a structural edit).
2. Wire it in with exactly one new `@includeIf` line in the existing `_digital-object-viewer.blade.php`.

That keeps the show page's render-tree diff minimal and reviewable.

## GPS

If either `digital_object_metadata.gps_latitude/gps_longitude` or `dam_iptc_metadata.gps_latitude/gps_longitude` is populated, a fourth accordion entry is added with outbound links to OpenStreetMap and Google Maps. No iframe / embed is rendered. The map links carry `target="_blank" rel="noopener noreferrer"`.

## Field count badge

Each section's header carries a `<span class="badge bg-secondary">{N}</span>` showing how many populated fields the partial found in that table after dropping internal columns (`id`, FK, timestamps, the `raw_metadata` / `consolidated_metadata` JSON blobs, and the `extraction_*` columns).

## Lock-safety check

After the change:

```text
git diff --stat HEAD -- 'show*'
```

should return nothing. The show page (`show.blade.php`) and every sibling partial besides `_digital-object-viewer.blade.php` is untouched. The single edit to `_digital-object-viewer.blade.php` is the one include line plus a guarding `@if(!$isMediaFile)`.

## Issue link

[GH #746](https://github.com/ArchiveHeritageGroup/heratio/issues/746)
