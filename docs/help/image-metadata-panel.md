# Image Metadata Panel

When an archival description has an image digital object attached (JPEG, PNG, TIFF, WebP, etc.) Heratio surfaces an "Embedded Image Metadata" panel directly under the viewer on the record show page.

## Overview

The panel reads from three sidecar tables that Heratio populates whenever a digital object is ingested or has its metadata extracted:

- `digital_object_metadata` is the EXIF section. Title, creator, description, keywords, copyright, date created, pixel dimensions, camera make and model, application, GPS, and so on.
- `dam_iptc_metadata` is the IPTC section. Headline, caption, creator and creator job title, credit line, source, copyright notice, rights and usage terms, license type and URL, location (city, state, country, sublocation), camera body, lens, focal length, aperture, shutter speed, ISO speed, color space, bit depth, and orientation.
- `media_metadata` is the XMP section. Title, artist, album, genre, year, copyright, comment, make, model, software, format, and gps_coordinates.

The panel header card collapses on click. Inside, each of the three families gets its own accordion section with a small Bootstrap badge showing how many populated fields exist in that family. Empty families are hidden.

## GPS

If either EXIF or IPTC has a `gps_latitude` and `gps_longitude` value, a fourth accordion section is added with the formatted coordinates and two outbound links (OpenStreetMap, Google Maps). No map is embedded inline. The panel never makes an outbound HTTP request on render. Users have to click an outbound link to open a map.

## When the panel does not appear

The panel suppresses itself when none of the three sidecar tables has a row for the digital object. This is the common case for very old AtoM ports where extraction was never run, or for non-image digital objects. The audio and video "Media Information" panel above it covers those cases separately.

## Field-level icons

Each row shows a Bootstrap Icons `bi-*` glyph that hints at the value type:

- `bi-fonts` plain text
- `bi-calendar3` dates
- `bi-geo-alt` GPS coordinates
- `bi-123` numeric values (pixel dimensions, ISO speed, bit depth)
- `bi-camera` camera body / lens / exposure
- `bi-person` people (creator, artist)
- `bi-c-circle` rights / copyright / license
- `bi-tag` keywords

## Triggering extraction

Image metadata is normally extracted at ingest. If a record was ported before the metadata-extraction pipeline existed, the operator can re-run extraction from the Digital Object admin area; the panel will appear on the next page load once the sidecar rows exist.

## References

- Source: `packages/ahg-information-object-manage/resources/views/partials/_image-metadata-panel.blade.php`
- Include: `packages/ahg-information-object-manage/resources/views/partials/_digital-object-viewer.blade.php`
- Test: `tests/Feature/ImageMetadataPanelTest.php`
- Issue: [GH #746](https://github.com/ArchiveHeritageGroup/heratio/issues/746)
