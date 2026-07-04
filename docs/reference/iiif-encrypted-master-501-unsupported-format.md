# IIIF: Encrypted-at-Rest Masters Return 501 "Unsupported source format"

Operational finding. Digital-object masters that are encrypted at rest (magic bytes `AHG-ENC-V2`) do not render through the Heratio IIIF pipeline. Cantaloupe receives ciphertext, cannot identify a source format, and returns **HTTP 501 "Unsupported source format"**. The deep-zoom viewer (OpenSeadragon / Mirador) then shows nothing. Tracked as heratio#1396.

## Symptom

- IIIF `info.json` for the object returns HTTP 501.
- Body: `edu.illinois.library.cantaloupe.processor.SourceFormatException: Unsupported source format`.
- Viewer is blank / fails silently to the end user; the 501 is only visible on the raw `info.json` request.
- Path resolution is NOT the problem - `delegates.rb` resolves the file correctly (base path plus `_SL_`-decoded identifier). Cantaloupe finds the file; it just cannot read the bytes.

## Root cause

The served master on disk is a Heratio encryption envelope, not the declared image format. The `.tiff` (or other) extension is intact but the leading bytes are `AHG-ENC-V2`:

```
$ file  <master>.tiff   -> data
$ xxd   <master>.tiff | head -1
  00000000: 4148 472d 454e 432d 5632   AHG-ENC-V2...
$ tiffinfo <master>.tiff -> (no output; libtiff cannot parse it)
```

Cantaloupe has no decrypt step, so encryption-at-rest and IIIF rendering are mutually exclusive as currently wired.

## How to diagnose quickly

1. Probe the live IIIF endpoint: `GET https://<host>/iiif/3/<identifier>/info.json`. A 501 with "Unsupported source format" on a file that exists on disk points straight at this.
2. Inspect the served master's magic bytes (`xxd | head -1`). `AHG-ENC-V2` confirms it.
3. Contrast with a known-good object: an unencrypted master (for example the de Bry engraving, digital_object 1055, 2339x1698) returns 200 through the same pipeline.

Note: `identify` / `file` may report real image dimensions if a DIFFERENT, unencrypted copy of the same file exists elsewhere on the volume (for example an offline-package staging copy). Always inspect the exact served path from `digital_object.path` + `name`, not just any file with the same name.

## Options to resolve (from heratio#1396, not yet chosen)

1. Decrypt-on-read shim in front of Cantaloupe (custom source/delegate streaming plaintext to the processor). Keeps encryption-at-rest, adds IIIF support.
2. Serve an unencrypted IIIF-safe derivative (tiled TIFF or JP2) alongside the encrypted master; point the IIIF identifier at the derivative.
3. Publish-gate / validation check that flags encrypted-master objects as IIIF-incompatible so the failure is surfaced instead of silent.

## Reusable technique: verify an image renders through IIIF end-to-end

Do not trust byte-size or `identify` alone when choosing demo / production imagery. Probe the actual pipeline:

```
curl -s -o /tmp/ij.json -w '%{http_code}' \
  "https://<host>/iiif/3/<identifier>/info.json"
# 200 + valid width/height in info.json = renders; 501 = broken source format
```

The identifier uses `_SL_` as the path separator (for example `uploads_SL_r_SL_<repo>_SL_...`), resolved host-based via `delegates.rb` `INSTANCE_PATHS`.

Verified 2026-07-04.
