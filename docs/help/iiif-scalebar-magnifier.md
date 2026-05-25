> Heratio Help Center article. Category: Viewers & Media.

# IIIF Scalebar and Magnifier

## User Guide

The Heratio IIIF viewer ships two tools that help researchers work with high-resolution images: a real-world scalebar overlay and a circular magnifier (loupe) that follows the cursor.

---

## Scalebar

When the underlying digital object carries physical-dimension metadata, the viewer shows a bar in the bottom-left corner of the image. The bar's length always corresponds to a "nice" round physical distance (for example `5 mm`, `1 cm`, `1 m`). As you zoom in or out the bar's pixel-width and label update so the displayed length stays accurate.

### Supported units

- micrometres (um)
- millimetres (mm)
- centimetres (cm)
- metres (m)
- inches (in)

### How it works

The scalebar reads the IIIF "physical dimensions" service block embedded in the manifest:

```json
"service": [{
  "profile": "http://iiif.io/api/annex/services/physdim",
  "type":    "PhysicalDimensions",
  "physicalScale": 0.005,
  "physicalUnits": "mm"
}]
```

`physicalScale` is the size of one image-pixel in `physicalUnits`. If the object does not carry physdim metadata the scalebar is silently hidden (Heratio never guesses a scale).

Operators can set a site-wide default in **Admin > Settings > IIIF**:

- `iiif_default_physical_scale` (number, e.g. `0.005`)
- `iiif_default_physical_units` (string, default `mm`)

Per-object overrides live in the `digital_object_property` table under the keys `physicalScale` / `physical_scale` and `physicalUnits` / `physical_units`.

### Toggling the scalebar

- **OpenSeadragon mode** - the scalebar renders automatically when physdim metadata is present. No toggle.
- **Mirador 4 mode** - open the window's top menu, look for the **Scalebar** entry and flip the switch. Each Mirador window has its own toggle state.

---

## Magnifier (loupe)

The magnifier is a 180-pixel circular loupe that follows the cursor and shows a 3x zoom of the underlying pixels. Useful for inspecting hairline detail without zooming the whole canvas.

### Turning the magnifier on/off

- **OpenSeadragon mode** - click the "Magnifier" button in the top-right corner of the viewer. The button highlights when the loupe is active.
- **Mirador 4 mode** - open the window's top menu and toggle **Magnifier**.

### Limitations

- The magnifier needs CORS-clean image tiles. If the upstream image server does not return `Access-Control-Allow-Origin: *`, the loupe disappears rather than showing a tainted image.
- The magnifier works on the viewer's currently-rendered canvas, not the full-resolution source. For very deep inspection it is usually better to zoom the OSD canvas first, then use the loupe.

---

## Presentation API 3.0

Heratio's IIIF manifest endpoint (`/iiif-manifest/{slug}`) emits IIIF Presentation API 3.0 by default. Older consumers that need the legacy 2.1 shape can request it explicitly:

```
GET /iiif-manifest/<slug>            (Presentation 3.0, default)
GET /iiif-manifest/<slug>?version=2  (Presentation 2.1, legacy)
```

The same `?version=2` switch applies to the collection endpoint at `/manifest-collection/{slug}/manifest.json`.

You can validate the v3 manifest at https://presentation-validator.iiif.io/.

---

## See also

- [IIIF Integration](iiif-integration-user-guide.md)
- [IIIF Compliance and Validation](iiif-compliance-validation.md)
- [IIIF Cantaloupe Setup](iiif-cantaloupe-setup.md)
- [IIIF Collection User Guide](iiif-collection-user-guide.md)

## References

- IIIF Presentation API 3.0: https://iiif.io/api/presentation/3.0/
- IIIF Physical Dimensions service: https://iiif.io/api/annex/services/#physical-dimensions
- Issue tracker: GH #698
