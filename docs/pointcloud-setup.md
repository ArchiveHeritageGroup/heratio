# Point-cloud viewer setup (#1183)

Heratio converts LiDAR / photogrammetry point clouds (`.las`/`.laz`/`.ply`) into a
[Potree](https://github.com/potree/potree) octree and streams them in the browser. Built for
documenting fragile heritage surfaces (rock-art panels, shelters, sites, large objects), where
a scan also serves as a dated conservation baseline.

The converter binary and the Potree viewer libs are **per-host** (not in the repo), like the
3D model-optimisation tools. Provision them once per server.

## 1. Build PotreeConverter

```bash
sudo apt-get install -y cmake g++ git libtbb-dev liblaszip-dev
sudo git clone --recursive --depth 1 https://github.com/potree/PotreeConverter.git /opt/PotreeConverter
cd /opt/PotreeConverter
sudo cmake -B build -S . -DCMAKE_BUILD_TYPE=Release
sudo cmake --build build -j"$(nproc)"
# binary: /opt/PotreeConverter/build/PotreeConverter
# viewer libs: /opt/PotreeConverter/resources/page_template/libs
```

Override the locations with `HERATIO_POINTCLOUD_BIN`, `HERATIO_POINTCLOUD_LIBS`,
`HERATIO_POINTCLOUDS_PATH` in `.env` if they differ (defaults in `config/heratio.php`).

## 2. Publish the symlinks

```bash
php artisan ahg:pointcloud-setup
```

Creates `public/vendor/potree -> <libs parent>` and `public/pointclouds -> <octree storage>`
(both git-ignored, host-specific). Re-run any time the paths change.

## 3. Restart the queue worker

Web uploads convert via the `ProcessPointCloud` queued job. After deploying new code/config the
long-running worker must be bounced so it sees the new `heratio.pointcloud_*` config keys and
job classes:

```bash
php artisan queue:restart
```

(If the worker predates the config it reports "Point-cloud converter is not installed".)

## Usage

- **Admin -> Media -> Point clouds** (`/admin/pointclouds`): upload `.las/.laz/.ply`, watch it
  flip to Ready, then View opens the Potree viewer at `/pointcloud/<slug>` (public for Ready
  clouds; staff can preview pending/failed).
- **Large scans** (multi-GB rock-art captures) bypass the browser upload limit - convert on the
  server:

  ```bash
  php artisan ahg:pointcloud-convert /path/to/scan.laz --title="Shelter 3 - main panel"
  ```

## Formats

- `.las`, `.laz`, `.ply` - converted directly by PotreeConverter.
- `.e57` - **not yet supported**; needs a PDAL pre-pass to `.laz`. PDAL is not in the Ubuntu
  noble archive, so it requires a source build (follow-up). The UI flags `.e57` clearly rather
  than failing silently.

## Notes

- Octrees live under `HERATIO_POINTCLOUDS_PATH` (default `<storage>/pointclouds/cloud-<id>/`)
  and are served statically via the `public/pointclouds` symlink (nginx range requests).
- The viewer is currently public for Ready clouds (suits public engagement). Gating per cloud
  is a follow-up.
