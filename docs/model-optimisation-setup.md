# 3D model optimisation (Draco) - setup for new / client instances

## Why

The exhibition walkthrough loads placed 3D models in the browser. Large meshes
(e.g. a 66 MB OBJ) parse on the main thread and **freeze the browser**. Heratio
guards against this two ways:

1. **Hard cap** - any model file over 20 MB is not loaded; the walkthrough shows
   a clickable placeholder instead of freezing. (No setup needed; always on.)
2. **Optimisation** - convert OBJ to glTF and apply **Draco** mesh compression,
   producing a small `.glb` the viewer loads instantly (a 66 MB OBJ becomes
   ~1.7 MB). This needs the Node tools below.

The walkthrough already bundles a client-side `DRACOLoader`, so Draco-compressed
`.glb` files load with no extra browser config.

## One-time host setup (required for optimisation + the `ahg:optimize-models` command)

Install the Node CLI tools into `/opt/ahg-model-tools` (kept out of the repo, and
on a path php-fpm can execute under `ProtectSystem=full`):

```bash
sudo mkdir -p /opt/ahg-model-tools
cd /opt/ahg-model-tools
sudo npm init -y
sudo npm install obj2gltf @gltf-transform/cli draco3dgltf
```

Verify:

```bash
/opt/ahg-model-tools/node_modules/.bin/gltf-transform --version   # e.g. 4.4.0
/opt/ahg-model-tools/node_modules/.bin/obj2gltf --help | head -1
```

Requires Node 18+ (`node -v`). If you install the tools elsewhere, set the path:

```dotenv
# .env (optional override)
HERATIO_MODEL_TOOLS_BIN=/opt/ahg-model-tools/node_modules/.bin
```

(`config/heratio.php` reads `model_tools_bin`, default `/opt/ahg-model-tools/node_modules/.bin`.)

## Pipeline

- `.obj`  -> `obj2gltf` -> `.glb` -> `resize` -> `webp` -> `draco` -> compressed `.glb`
- `.glb` / `.gltf` -> `resize` -> `webp` -> `draco` -> compressed `.glb`
- `.stl` / `.ply` are not handled yet (re-export as glb/obj to optimise).

Three texture/geometry steps via `gltf-transform`:

1. **resize** - downscale oversized textures to a 2048 cap.
2. **webp** - re-encode textures to WebP. This is decisive for texture-heavy
   models: a 2048^2 PNG is ~4-5MB and neither resize nor Draco touches it, but
   WebP cuts it ~10-20x (a 5MB chair-and-table glb becomes ~0.4MB).
3. **draco** - compress geometry.

Each step falls back gracefully if its codec is unavailable. A **no-inflation
guard** discards the result and keeps the original whenever compression would
produce a *larger* file than the source (Draco overhead on an already-small,
texture-light model), so the pipeline can never make a model bigger.

## Automatic optimisation (scheduled)

Once the host tools are installed, `ahg:optimize-models --commit --min-mb=20` runs
**hourly** via the Laravel scheduler (registered in `AhgCoreServiceProvider`). Any
oversized OBJ/GLB master uploaded — exhibition furniture *or* archival digital
object — is compressed within the hour and then loads in the walkthrough. It is a
no-op when the tools are absent, skips already-optimised `*-opt.glb` outputs, and
runs as `www-data` (the scheduler's user). No per-upload action is required.

## Backfill existing oversized models

`ahg:optimize-models` finds 3D masters over a size threshold, compresses them,
re-points the `digital_object` row at the new `.glb`, and **keeps the original
file on disk**. It writes a restore `.sql` to `storage/app/` first.

```bash
# Dry run (lists what would be compressed)
sudo -u www-data php artisan ahg:optimize-models --min-mb=20

# Apply
sudo -u www-data php artisan ahg:optimize-models --commit --min-mb=20

# One object only
sudo -u www-data php artisan ahg:optimize-models --commit --id=905245
```

Run as `www-data` (not root) so the created `.glb` and any Laravel log are owned
by the web user. Reversible via the `storage/app/optimize-models-restore-*.sql`.

## Notes

- Tools live outside the repo (`/opt`), so they are **not** captured by a release
  or a Docker image build - they must be installed per host (this doc + the
  install script step).
- The Docker stack does not include these tools by default (3D optimisation is an
  optional host capability, like Cantaloupe/Ollama).
