# Embedded Image Metadata (Full Capture)

Heratio extracts and displays the **complete** embedded metadata of an image — every EXIF, IPTC, XMP, GPS, MakerNotes and ICC tag — not just a curated handful. This is the metadata cameras, scanners and editing software write into the file itself.

## Where it appears

On a digital object's show page, the **Embedded Image Metadata** panel has collapsible sections:

- **EXIF / IPTC / XMP** — the curated, human-friendly highlights (title, creator, dates, camera, rights, location).
- **All metadata (N tags)** — the *complete* set, grouped by ExifTool family (EXIF, IPTC, XMP-dc, GPS, MakerNotes, ICC_Profile, Composite, File, …), with a **filter box** to search across every tag.
- **GPS Location** — map links (OpenStreetMap / Google), shown only when coordinates are present and only expanded on demand.

## How it is captured

Metadata is read with ExifTool using `-G1 -a -u` — grouped by family, including duplicate and unknown tags — and stored complete in `digital_object_metadata.raw_metadata`. The curated columns (title, creator, camera, GPS, etc.) are derived from the same extraction.

Extraction runs automatically when a master file is processed. To populate the full set for files ingested earlier, run the backfill:

```
php artisan ahg:backfill-embedded-metadata          # all masters
php artisan ahg:backfill-embedded-metadata --id=123 # one master
```

## Privacy / GPS

GPS coordinates are sensitive. In the **All metadata** section the entire GPS group is **redacted for non-administrator viewers** (shown as `[redacted]`); administrators see the full values. The curated GPS row and map links follow the page's existing access rules.

## Notes

- The full set flows through to the IIIF metadata enricher (which reads `raw_metadata`), so deep-zoom viewers can surface it too.
- The panel only appears when an image actually carries embedded metadata.

Tracked as issue #1106 (PSIS twin: atom-ahg-plugins#113).
