# 3D Model Metadata & Paradata

This guide explains how to record and view the technical, capture and rights
metadata that travels with a 3D model in Heratio.

## Overview

A 3D model is both a surrogate of a physical object and a record in its own
right. Beyond the viewer, Heratio captures **what the model is** (format,
geometry, scale), **how it was made** (capture method, device, processing) and
**what may be done with it** (licence, attribution). Some of this is filled in
automatically; the rest you add.

## What is captured

**Technical / structural**
- Real-world dimensions and **units** (mm/cm/m/in/ft) and a scale note
- Coordinate system / up-axis, model-space bounding box (auto)
- Format and version, compression (none / Draco / meshopt / KTX2), lossless-master flag
- Vertices, faces, textures, animations, materials, rig, PBR map set, level-of-detail

**Capture & processing (paradata)**
- Capture method (photogrammetry, laser scan, structured-light, CT, CAD, procedural, manual)
- Capture device, date, operator; number of source items; point density; **accuracy (mm)**
- Processing software + version, processing notes, georeference

**Provenance & rights**
- Model author / creator; derivation note (raw scan -> master -> access copy)
- A **licence for the 3D model itself**, distinct from the physical object's rights;
  licence holder; attribution

## Automatic extraction

When a 3D file (glTF/GLB, OBJ, PLY, STL) is uploaded or attached to a record,
Heratio reads its header and fills in what it can automatically: format version,
compression, bounding box, vertex and face counts, texture and animation counts,
materials/rig flags and the PBR map set. This happens for both models uploaded
through the 3D module and 3D files attached as ordinary digital objects.

To backfill existing models, an administrator can run:

```
php artisan ahg:3d-extract-metadata
```

## Adding the human-judgement fields

1. Open the record and click **Edit 3D metadata** (or open the model from the 3D
   admin list and choose *Edit*).
2. In the **Technical, capture & rights metadata** panel, complete the
   dimensions and units, capture method and device, accuracy, processing
   software, author, licence and attribution as known.
3. Save. The controlled lists (units, coordinate system, capture method,
   compression, licence) are managed in **Settings -> Dropdown Manager** and can
   be extended or localised.

## Where it appears

- A **3D technical metadata** panel shows beside the model on the record page
  (archival, DAM, gallery and museum show pages).
- The full table is on the dedicated 3D model page.

## Standards

The model aligns with CIDOC-CRM / CRMdig (digitisation provenance), CARARE /
3D-ICONS and the Europeana 3D recommendations, and the London Charter / Seville
Principles for paradata and transparency.

## References

- Source: `packages/ahg-3d-model/`
- Issue: [GH #1178](https://github.com/ArchiveHeritageGroup/heratio/issues/1178)
- Companion: 3D Model viewer guide; 3D viewer tools
