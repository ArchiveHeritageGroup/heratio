# DAM ↔ Gallery image viewer — current state and gaps (2026-05-03)

## Where each page renders the image

| Page | View file | What renders | Where in layout |
|---|---|---|---|
| **IO show** (`/{slug}` catch-all, e.g. `/understream-figure`) | `packages/ahg-information-object-manage/resources/views/show.blade.php` line 358 | `@include('ahg-information-object-manage::partials._digital-object-viewer')` — full IIIF / Mirador / OpenSeadragon viewer with zoom, pan, fullscreen, multi-image carousel | Title block / hero |
| **Gallery show** (`/gallery/{slug}`) | `packages/ahg-gallery/resources/views/gallery/show.blade.php` `@section('right')` lines 570-590 | Plain `<img class="img-fluid">` in a `.card` with `card-body p-2 text-center`. Click-through to master in new tab. **No IIIF, no Mirador, no zoom.** | Right column (`col-md-2`) |
| **DAM show** (`/dam/{slug}`) | `packages/ahg-dam/resources/views/dam/show.blade.php` `@section('content')` top | Cloned from Gallery — same plain `<img>` + card pattern. Master-only fallback added because DAM assets often have a master without derivatives. **No IIIF, no Mirador, no zoom.** | Top of middle column (`col-md-8`) |

## What's NOT cloned to DAM

- **Mirador viewer** (`public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js`)
- **OpenSeadragon viewer** (`public/vendor/ahg-theme-b5/js/vendor/openseadragon.min.js`)
- **`ahg-iiif-viewer.js`** (auto-detects TIFF/JP2 and routes to Cantaloupe at `:8182/iiif/`)
- **The `_digital-object-viewer.blade.php` partial** that orchestrates all of the above — only IO show includes it
- **IIIF deep-zoom for TIFF/JP2 masters** — DAM JPEGs render as plain `<img>`, no deep-zoom

Gallery doesn't have these either — only the IO show page does. So the "clone from Gallery" request was satisfied verbatim, but Gallery itself doesn't expose the IIIF viewer.

## If we want IIIF/Mirador on DAM

Add `@include('ahg-information-object-manage::partials._digital-object-viewer')` near the top of `dam/show.blade.php` `@section('content')`. The partial reads `$digitalObjects` (already passed from `DamController::show()`), auto-detects TIFF/JP2 vs ordinary JPEG/PNG, and routes appropriately:

- TIFF / JP2 → Cantaloupe IIIF Image API 3.0 at `:8182/iiif/`, rendered via OpenSeadragon
- IIIF Manifest → Mirador
- Plain raster → standard `<img>` (same as Gallery)

This would also need to apply to Gallery if we want gallery items to support deep-zoom (currently they don't — Gallery is plain `<img>` only).

## DigitalObjectService url helper

Avoid hand-built `/uploads/{path}/{name}` strings. The `path` column in `digital_object` already starts with `/uploads/...`, so concatenating produces a double-prefix bug. Use:

```php
\AhgCore\Services\DigitalObjectService::getUrl($do)        // single object
\AhgCore\Services\DigitalObjectService::getDisplayUrl($digitalObjects)   // array, picks reference > master
\AhgCore\Services\DigitalObjectService::getThumbnailUrl($digitalObjects) // array, picks thumbnail
```

## Files touched (2026-05-03)

- `packages/ahg-dam/resources/views/dam/show.blade.php`
  - Image moved from `@section('right')` to top of `@section('content')`
  - Plain `<img>` cloned from Gallery (replaced `components.digital-object`)
  - Top-right Print button removed (Print is in bottom Actions bar)
  - Bottom Actions bar added in `@section('after-content')` matching IO show
  - Sidebar Actions card removed
  - Marketplace card removed
  - RiC Context panel moved from `@section('right')` to `@section('content')` end
  - Place / Subject / Name access points moved to bottom of `@section('right')`
- `packages/ahg-information-object-manage/resources/views/partials/_right-blocks.blade.php`
  - Export card moved to immediately follow Import card
- `packages/ahg-core/resources/views/partials/_record-sidebar-extras.blade.php`
  - Added `hideExport` flag (mirror of existing `hideRights`, `hideNer`, `hideProvenance`)
- `packages/ahg-dam/src/Controllers/DamController.php`
  - Removed `$marketplaceListing` fetch (sidebar Marketplace card was removed)

## Related issue

GitHub #58 — function/route/blade catalogue → KM. Once shipped, queries like *"Where is the Mirador viewer rendered?"* will return the file:line directly via KM, without grep.
