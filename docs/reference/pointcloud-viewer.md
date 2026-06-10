# Point-cloud viewer (.las/.laz -> Potree octree)

**Heratio issue #1183.** Converts LiDAR / photogrammetry point clouds into a streaming Potree
octree and views them in the browser. Driver: a rock-art site deployment - scanned panels and
shelters are both a record and a dated conservation baseline for a fragile, irreplaceable
surface.

## Stack

- **PotreeConverter** (built from source into `/opt`, per host) turns `.las/.laz/.ply` into an
  octree (`metadata.json` + `octree.bin` + `hierarchy.bin`).
- **Potree viewer** (the libs that ship with PotreeConverter's page template) renders it with
  level-of-detail streaming - the only practical way a browser shows hundreds of millions of
  points. Vendored once via a symlink, not per cloud.
- `.e57` needs a PDAL pre-pass (PDAL isn't in noble's archive) and is reported as unsupported,
  never converted half-way.

## Where it lives (ahg-core)

- `PointCloudConverterService` - `convert()` runs PotreeConverter into
  `pointclouds_path/cloud-<id>/`; `createPending()` / `process()` (with a duplicate-dispatch
  guard) / `getBySlug()` / `list()`. Format allow-list + `.e57` guard.
- `ProcessPointCloud` job (queued, `tries=1`, 1h timeout) - the web upload path; removes the
  uploaded source after converting.
- Commands: `ahg:pointcloud-setup` (publish symlinks), `ahg:pointcloud-convert` (sync, for big
  server-side scans).
- `PointCloudController` - `/admin/pointclouds` (manage + upload, auth), `/pointcloud/{slug}`
  (public Potree viewer for Ready clouds; two-segment path, safe from the `/{slug}` catch-all),
  `/pointcloud/{slug}/status` (poll). Menu: Admin -> Media -> Point clouds.
- Table `ahg_point_cloud` (auto-created by the provider): `slug, title, source_filename,
  octree_dir, status (pending|processing|ready|failed), point_count, error, created_by`.
- Config keys: `heratio.pointcloud_bin`, `heratio.pointcloud_libs`, `heratio.pointclouds_path`.

## Serving

Octrees are served statically through `public/pointclouds -> pointclouds_path`; viewer libs
through `public/vendor/potree -> <PotreeConverter page_template>`. Both symlinks are git-ignored
and created by `ahg:pointcloud-setup`. Verified over HTTPS: viewer route, `metadata.json`,
`octree.bin`, and `potree.js`/`.css` all 200.

## Deploy gotcha

The long-running queue worker caches config/classes at boot - after deploying this it reports
"Point-cloud converter is not installed" until `php artisan queue:restart`. Web uploads convert
only once the worker is bounced; the `ahg:pointcloud-convert` CLI path is unaffected.

## Follow-ups

- `.e57` support via a PDAL source build.
- Per-cloud access control (viewer is public for Ready clouds today).
- Phase 3: surface the viewer on the archival-record show page (that 3D viewer is in the locked
  IO show tree - needs an unlock).

Full host setup: `docs/pointcloud-setup.md`.
