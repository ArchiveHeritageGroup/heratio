> Heratio Help Center article. Category: Plugin Reference.

# GIS Spatial Search - User Guide

## Find Records by Where They Are on the Map

The GIS module adds geographic search to the catalogue. Where archival
descriptions have been given coordinates, you can search them by drawing a
geographic box, by setting a centre point and radius, or by pulling the whole set
out as GeoJSON for use in a map.

Coordinates are held in a dedicated geolocation table linked to each record, so
spatial search works even though the base description record has no built-in
location columns.

---

## Overview

The module provides three administrator screens:

- **Bounding box** - find geolocated records that fall inside a north / south /
  east / west rectangle.
- **Radius** - set a centre point and a distance in kilometres.
- **GeoJSON** - export geolocated records as a GeoJSON FeatureCollection, or view
  them, for plotting on a map.

Each result carries the record's title, slug, latitude, and longitude, so it can
be linked back to the full description.

---

## Key features

- Bounding-box search across north, south, east, and west coordinates
  (returns up to 500 records).
- Radius search around a latitude / longitude centre point, with a configurable
  distance in kilometres.
- GeoJSON output as a standard FeatureCollection of Point features, with each
  feature carrying the record id, title, and slug (returns up to 1,000 records).
- Only records that have both a latitude and a longitude are included.
- Admin-only; read-only against the catalogue.

---

## How to use

All three screens live under the **GIS** area and require an administrator login.

### Bounding-box search

Go to **`/admin/gis/bbox`**. Supply the bounds as query parameters - `north`,
`south`, `east`, and `west` - for example
`/admin/gis/bbox?north=-25&south=-26&east=29&west=28`. The screen returns the
geolocated records inside that rectangle, with their coordinates and a link back
to each record.

### Radius search

Go to **`/admin/gis/radius`**. Provide a centre point and distance as `lat`,
`lng`, and `radius` (kilometres), for example
`/admin/gis/radius?lat=-25.7&lng=28.2&radius=10`. The default radius is 10 km.

### GeoJSON export and map view

Go to **`/admin/gis/geojson`**. Requesting the page as JSON returns a GeoJSON
FeatureCollection - each feature is a Point with `[longitude, latitude]`
coordinates and properties for the record id, title, and slug. Requesting it as a
normal page returns a view of the same features. This output drops straight into
any tool that reads GeoJSON.

---

## Configuration

There are no module settings. To make a record searchable by location, it needs a
latitude and a longitude recorded in its geolocation entry. Records without
coordinates are simply skipped by every GIS screen.

Result counts are capped for performance - bounding-box returns up to 500 records
and GeoJSON returns up to 1,000.

---

## References

- Source package: `packages/ahg-gis/`
- GitHub issue: [GH #577](https://github.com/ArchiveHeritageGroup/heratio/issues/577)
