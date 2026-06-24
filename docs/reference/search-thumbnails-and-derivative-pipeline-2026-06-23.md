# Search result thumbnails + derivative pipeline (2026-06-23)

Summary of three related fixes to why GLAM/keyword search results showed
placeholder icons instead of real thumbnails, plus the real (small) state of
the derivative backfill gap.

## 1. Search thumbnails were invisible (the actual user-visible bug)

`ahg-search` `ElasticsearchService` DB-fallback enriched each result with a
thumbnail by matching `digital_object.object_id = <io id>` for usage 142/141.
But **derivative rows link to their master by `parent_id`, not `object_id`** -
the master row carries `object_id` (the IO), and the derivative's own
`object_id` is usually NULL. On this corpus **182 of 371 thumbnails had
`object_id = NULL`**, so the query missed them and the row fell back to a
placeholder even though the thumbnail file existed on disk.

Fix: resolve the master chain (`usage_id=140`, `object_id IN (ids)` -> master
`id`), then match derivatives by `parent_id IN (master ids)`, still honouring
the legacy case where a derivative carries `object_id` directly. Live result on
`/search?q=image`: 6 -> 18 real thumbnails, 0 broken.

## 2. No-thumbnail fallback rendered empty (earlier same-day fix)

The fallback used a Font Awesome glyph (`fas fa-file-image`), but the theme
bundle's FA6 webfont subset omits that glyph and Bootstrap Icons is not loaded
on `/search`, so the `<i>` painted nothing. Replaced with an inline SVG (image
icon for digital-object records, document icon for text-only). Inline SVG has no
font/subset dependency. Shipped v1.154.99.

## 3. DerivativeService path bug + regen command bug

- `DerivativeService` anchored master/derivative paths at
  `config('heratio.uploads_path')`. On this install that is
  `/mnt/nas/heratio/archive`, but the `/uploads/*` tree is served by nginx from
  `{storage_path}/uploads` (`/mnt/nas/heratio/uploads`). Result: "master not
  found", zero regeneration. Fixed to anchor at `storage_path` (equal to
  `uploads_path` on a default install, so a no-op there).
- `ahg:regen-derivatives` filtered `usage_id = 1` for "master", but master is
  term **140** here, so it matched nothing. Fixed to `DerivativeService::USAGE_MASTER`
  and made the default an idempotent **only-missing** sweep
  (via `getMastersWithMissingDerivatives`); `--force` re-encodes all.
- `heratio:digitalobject:regen-derivatives` is a stub that shells out to the old
  AtoM symfony (`/usr/share/nginx/archive/symfony`) - wrong app/DB, do not use.
- Moved the weekly `regen-derivatives` cron off the 02:00 demo-reset collision
  to Sunday 04:00.

## 4. The real backfill gap is tiny - and the residue is two encryption schemes

Master inventory on disk (258 with usage_id=140): **253 plain, 3 encrypted,
2 missing files**. Of the rest of the 37 the service flags, most are non-image
media (`.glb` 3D, audio, video, docs) that cannot have a raster thumbnail (3D
has the separate `ahg:3d-derivatives` Blender path); the inline-SVG type icon
covers those rows.

Two distinct encrypted-at-rest envelopes exist - do not confuse them:

- **Heratio's own**: `ahg-core` `EncryptionService`, file sentinel
  `AHG_ENC_DERIV_v1\n` (Laravel `Crypt` body). `regenerateDerivatives()` now
  detects this (`isFileEncrypted`) and decrypts to a private temp file via
  `streamFileDecrypted()` before running `convert`, then unlinks it - so if an
  operator enables Heratio at-rest encryption, derivative regen keeps working.
  Derivatives stay plaintext, matching the served thumbnails.
- **Foreign `AHG-ENC-V2`**: the 3 remaining image masters (obj 553, 768, 829)
  begin with a binary `AHG-ENC-V2` envelope that **no code in this repo
  produces or reads**. They were encrypted by an external tool; Heratio cannot
  decrypt them, so `convert` sees garbage ("Not a JPEG/TIFF"). These need the
  external V2 decryptor (provenance unknown) or re-ingesting the plain masters.
  They show the SVG type icon meanwhile.

Net: 0 new derivative rows were created (the only generation candidates turned
out to be the un-decryptable V2 trio), so no baseline snapshot was needed - the
bulk of thumbnails already exist in the demo baseline and were simply invisible
to search due to the parent_id bug in section 1.
