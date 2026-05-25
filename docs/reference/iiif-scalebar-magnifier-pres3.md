# IIIF Scalebar, Magnifier and Presentation 3 Manifests

Heratio's IIIF viewer (issue #698) now ships three runtime additions and a manifest-format migration:

1. A real-world scalebar overlay (both Mirador 4 and OpenSeadragon-only mode)
2. A circular magnifier loupe (both Mirador 4 and OpenSeadragon-only mode)
3. A Material-UI v7 theme override for Mirador 4 so its chrome matches the Bootstrap 5 palette
4. A move from IIIF Presentation API 2.1 to IIIF Presentation API 3.0 on the manifest endpoint

## What ships

### Scalebar plugin

The scalebar reads the IIIF "physical dimensions" service block from the canvas resource:

```json
"service": [{
  "@context": "http://iiif.io/api/annex/services/physdim/1/context.json",
  "profile":  "http://iiif.io/api/annex/services/physdim",
  "type":     "PhysicalDimensions",
  "physicalScale": 0.005,
  "physicalUnits": "mm"
}]
```

`physicalScale` is the size of one IIIF image-pixel in `physicalUnits`. The scalebar overlay tracks zoom and pan, picks a "nice" round physical length that fits roughly one sixth of the viewer width, and renders a 4-pixel-high bar with a label (e.g. "5 mm", "20 um", "1 m"). It supports `um`, `mm`, `cm`, `m` and `in`.

When no physdim service block is present on the canvas the scalebar hides itself entirely (it never guesses).

Implementation files:

- `tools/mirador-build/src/heratio-scalebar-plugin.js` - Mirador 4 plugin wrapper, registers a `WindowTopMenu` MenuItem that toggles a per-window scalebar.
- `public/vendor/openseadragon/6.0.2/openseadragon-heratio-scalebar.js` - standalone OSD plugin that attaches `Viewer.prototype.addHeratioScalebar(opts)`.

### Magnifier (loupe) plugin

The magnifier is a 180-pixel circle that follows the cursor over the canvas and renders a 3x zoom of the underlying pixels. It depends on OSD's `canvas` drawer (the WebGL drawer's GPU surface is not 2d-readable); `ahg-iiif-viewer.js` already pins the canvas drawer for the filter plugin so this is safe.

Toggle UI:

- In Mirador 4, the magnifier is a `WindowTopMenu` MenuItem with a Switch control.
- In OSD-only mode, `ahg-iiif-viewer.js` injects a "Magnifier" button in the top-right toolbar that the user can click to toggle on/off.
- On the standalone IIIF viewer page (`/iiif-viewer/{slug}`) a Bootstrap 5 button is injected directly.

Implementation files:

- `tools/mirador-build/src/heratio-magnifier-plugin.js`
- `public/vendor/openseadragon/6.0.2/openseadragon-heratio-magnifier.js`

### MUI theming

Mirador 4's chrome uses MUI v7. The Mirador config accepts a `theme` key that is deep-merged into Mirador's stock theme - we resolve a Heratio palette from `window.AHG_IIIF.theme` and inject it into every viewer the bundle creates:

```js
window.AHG_IIIF = {
  theme: {
    mode: 'light',
    palette: {
      primary:   { main: '#2c3e50' },
      secondary: { main: '#6c757d' },
      error:     { main: '#dc3545' },
      success:   { main: '#198754' }
    }
  }
};
```

Defaults match Heratio's Bootstrap 5 palette so the operator doesn't need to configure anything for the chrome to feel native.

Implementation file:

- `tools/mirador-build/src/heratio-mui-theme.js`

### Pres 3 manifest emission

The single-object manifest endpoint (`/iiif-manifest/{slug}`) and the collection endpoint (`/manifest-collection/{slug}/manifest.json`) now emit IIIF Presentation API 3.0 by default. The shape switches:

- `@context` becomes `http://iiif.io/api/presentation/3/context.json`
- `@id` / `@type` become `id` / `type`
- `sequences[0].canvases[i]` collapses to `items[i]`
- Each canvas carries `items: [{ type: 'AnnotationPage', items: [{ type: 'Annotation', motivation: 'painting', body: {...} }] }]`
- `images[0].on` is replaced by `target: canvasId`
- Labels, summaries and metadata values become language maps `{ "en": ["..."] }`
- New top-level fields: `homepage`, `provider`, `behavior`
- The painting body's `service` array includes a `PhysicalDimensions` entry when the digital object exposes a physical scale (looked up from `digital_object_property` and falling back to `ahg_settings` keys `iiif_default_physical_scale` + `iiif_default_physical_units`).

Legacy callers that need the old v2 shape can pass `?version=2`:

```
GET /iiif-manifest/foo                  - Presentation API 3.0
GET /iiif-manifest/foo?version=2        - Presentation API 2.1 (legacy)
```

Implementation files:

- `packages/ahg-iiif-collection/src/Services/IiifCollectionService.php`
  - `generateObjectManifest($slug, $version)` - public entry, defaults to v3
  - `generateObjectManifestV3($slug)` - new Pres 3 builder
  - `generateObjectManifestV2($slug)` - legacy Pres 2 builder (kept for the `?version=2` fallback)
  - `generateCollectionJson($collectionId, $version)` - already v3, now also routes to V2 when asked
  - `resolvePhysDim($digitalObject)` - lookup helper for the PhysicalDimensions service block
- `packages/ahg-iiif-collection/src/Controllers/IiifCollectionController.php` - threads the `?version=` query into both manifest endpoints.

## How to test

1. Visit any archival show page that has TIFF or JP2 media (e.g. `/object/show/<slug>` if the record carries a multi-page TIFF). The OSD viewer should render with a "Magnifier" button in its top right; clicking it should pop up a circular loupe under the cursor. The bottom-left should show a scalebar **iff** the digital object has physdim metadata.
2. Switch to Mirador mode in the same viewer. The window's top menu should now include Scalebar and Magnifier entries with Switch controls. The Mirador AppBar should use Heratio's slate-blue primary colour rather than Mirador's default blue.
3. Fetch the manifest directly:
   ```
   curl https://heratio.local/iiif-manifest/<slug> | jq .
   ```
   The top-level `@context` must be `http://iiif.io/api/presentation/3/context.json`. There should be no `sequences` key; there should be an `items` array of Canvas objects each containing an AnnotationPage.
4. Validate against the official IIIF Presentation 3 validator at https://presentation-validator.iiif.io/. Paste the manifest URL and confirm it returns "valid".
5. Confirm the legacy v2 fallback still works:
   ```
   curl 'https://heratio.local/iiif-manifest/<slug>?version=2' | jq '."@context"'
   ```
   Should print `"http://iiif.io/api/presentation/2/context.json"`.

## Known limitations

- The physdim service block is only emitted when the operator configures `iiif_default_physical_scale` (and optionally `iiif_default_physical_units`) in `ahg_settings`, or when a digital_object_property row carries `physicalScale` / `physical_scale`. There is no UI yet for entering per-object physical scale; that surface is a follow-up item.
- The Cantaloupe back end still serves IIIF Image API 2 tiles. The Pres 3 manifest correctly declares `ImageService2` on the painting body so this is spec-legal, but a future migration to IIIF Image API 3 would tighten the round trip.
- Mirador's `WindowTopMenu` MenuItem slot was new in Mirador 4.x; pre-4 hosts won't see the scalebar/magnifier toggles.
- The OSD-only scalebar reads physdim from the IIIF info.json's service block when available. When physdim only exists on the canvas resource (not the image-service info.json) callers can pass `window.AHG_IIIF.physdim = { physicalScale, physicalUnits }` and `ahg-iiif-viewer.js` will pass it through to the scalebar plugin.
- The OSD magnifier requires CORS-clean tiles; if the upstream image server omits `Access-Control-Allow-Origin: *` the canvas becomes tainted and the loupe hides itself rather than throwing.

## References

- IIIF Presentation API 3.0: https://iiif.io/api/presentation/3.0/
- IIIF Cookbook (reference manifests): https://iiif.io/api/cookbook/
- IIIF Physical Dimensions service: https://iiif.io/api/annex/services/#physical-dimensions
- Mirador 4 custom theming wiki: https://github.com/ProjectMirador/mirador/wiki/Mirador-Custom-Theming
- Presentation 3 validator: https://presentation-validator.iiif.io/
