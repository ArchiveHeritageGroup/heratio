# 3D Model Preservation

How Heratio preserves 3D models for the long term (master-mesh scope).

## Master vs access copy

Each 3D model is kept in two roles: the **preservation master** (the
authoritative, lossless file) and the **access copy** (the web-delivery
glTF/GLB, often Draco-compressed). Heratio never treats the compressed delivery
copy as the master — where a model was optimised for the web, the uncompressed
original is the master.

## What is recorded

Running `php artisan ahg:3d-preserve` enrols every 3D master as a PREMIS object:

- a SHA-256 **checksum** (fixity) of the master, plus fixity coverage on the
  access copy;
- **PRONOM format identification** (PUID / MIME / risk);
- the **significant properties** read from the master file — geometry (vertices,
  faces), format and version, compression, bounding box, materials / PBR maps,
  animation, plus the curator's scale + units, coordinate system and colour space;
- a flag marking the file as the preservation master.

These significant properties are what any future format migration is checked
against.

## Operating notes

- Run `ahg:3d-preserve` after `ahg:optimize-models`, or on a schedule, so new and
  newly-optimised models are enrolled (`--force` re-reads the master).
- The uncompressed originals kept by the optimiser are the masters - do not delete
  them.

## Out of scope (planned)

Preservation of raw capture data (photo sets, point clouds) and an Archivematica
normalization bridge are future extensions.

## References

- Policy: `docs/3d-preservation-policy.md`
- Source: `packages/ahg-3d-model/` (`ahg:3d-preserve`), `packages/ahg-preservation/`
- Issue: [GH #1179](https://github.com/ArchiveHeritageGroup/heratio/issues/1179)
