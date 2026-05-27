> Heratio Help Center article. Category: Viewers & Media.

# IIIF Manifest Metadata - IPTC and EXIF enrichment

## What it does

When an image, document, or A/V file in Heratio carries an IPTC sidecar
(Creator, Copyright, Keywords) or EXIF metadata (capture date), those
values now show up automatically inside the IIIF Presentation 3.0
manifest at `/iiif-manifest/{slug}`. That manifest is what Mirador,
Universal Viewer, and other IIIF-aware clients read - so the file-level
provenance you've already captured in the DAM finally surfaces to every
downstream viewer without any extra clicks.

---

## Where the values come from

| Manifest row | Heratio table / column |
|---|---|
| **Creator** | `dam_iptc_metadata.creator` (IPTC By-line) |
| **Keywords** | `dam_iptc_metadata.keywords` |
| **Date of capture** | `digital_object_metadata.raw_metadata` -> EXIF `DateTimeOriginal` |
| **requiredStatement / Attribution** | `dam_iptc_metadata.copyright_notice` |
| **Provider (creator agent)** | `dam_iptc_metadata.creator`, appended after the institutional provider |

These columns are populated automatically when an asset is ingested via
the Scan / Ingest pipeline - exiftool runs as part of the format-id and
metadata-extraction stages.

---

## What wins when there's a conflict

Heratio always treats ISAD-G archival metadata as authoritative:

1. If the archival description has **reproduction conditions** filled in,
   that text is used for the manifest's `requiredStatement`. The IPTC
   copyright notice is ignored.
2. If the archival description has a **dateCreated** value (when that
   column is added in a future release), the EXIF capture date is
   suppressed.
3. The publishing institution is always the **first** provider in the
   manifest. The IPTC creator is appended as a second agent, never
   substituted - so it's clear who **made** the file vs who's **serving**
   it.

The IPTC creator metadata row is additive to any ISAD-level author the
description carries; both surface side-by-side for the viewer.

---

## How to verify on a record

In a terminal:

```bash
curl -s https://your-heratio/iiif-manifest/<your-slug> | jq '.metadata, .requiredStatement, .provider'
```

You should see entries like:

```json
[
  { "label": { "en": ["Identifier"] }, "value": { "en": ["DOC-1986-0042"] } },
  { "label": { "en": ["Creator"] },    "value": { "en": ["Annemarie van Heerden"] } },
  { "label": { "en": ["Keywords"] },   "value": { "en": ["archive", "manuscript", "1986"] } },
  { "label": { "en": ["Date of capture"] }, "value": { "en": ["1986:07:12 14:32:01"] } }
]
```

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| No Creator / Keywords rows on a known-tagged file | The IPTC sidecar row is missing for that object. Re-run the metadata-extraction stage in the Ingest dashboard, or run `exiftool` against the source file and confirm the byline / keywords are actually written. |
| Copyright statement missing | Check that **Reproduction conditions** in the archival description is empty - if it's filled, that ISAD value wins by design. |
| Manifest 500s after ingest | Should not happen: enrichment is wrapped in a try / catch. Check `storage/logs/laravel-YYYY-MM-DD.log` for "IIIF manifest IPTC/EXIF enrichment skipped" warnings. |

---

## Related articles

- [IIIF Integration User Guide](iiif-integration-user-guide.md)
- [IIIF Content Search](iiif-content-search.md)
- [IIIF AV Playback](iiif-av-playback.md)
- [Scanner User Guide](scanner-capture-user-guide.md)
