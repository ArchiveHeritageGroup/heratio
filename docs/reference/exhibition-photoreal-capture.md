# Exhibition photoreal capture (scan shells + 360 embeds)

Heratio exhibition rooms (the digital-twin builder + 3D walkthrough) can be backed by an
uploaded **photoreal 3D scan** instead of, or on top of, a built room. Shipped for issue
#1156 in `packages/ahg-exhibition`.

## What a curator can do

In the builder's **Photoreal capture** card (`/exhibition-space/{slug}/builder`):

- **Upload scan shell** - a mesh export (`.glb`, `.gltf`, `.obj`, `.stl`, `.ply`) from
  photogrammetry or a 3D scanner. Stored under `{storage}/uploads/exhibition-scans/`.
- **Fit scale** - one uniform multiplier to size the scan to the room's metres.
- **360 / Matterport embed URL** - any 360-tour share URL; surfaces a **360 button** in
  the walkthrough while you are in that room, opening the tour in an overlay iframe.

## How it renders

In the walkthrough (`walkthrough.blade.php`), inside the per-room build loop, if
`rm.scan_shell` is set the shell is loaded with the existing `loadModel(url, ext, ...)`
helper and added **additively** to the room's group at the room's corner origin, scaled by
`scan_shell_scale`. The built floor/walls stay in place, which means:

- **Collision is preserved** - the parametric walls still block you, so you cannot walk
  out through a scanned wall, and the scan needs no colliders of its own.
- **Object placements and the live overlay keep working** - they are added to the same
  room group on top of the scan, exactly as on a built room.
- If the scan fails to load, the built room is the fallback (no blank room).

## Data model

Three columns on `ahg_exhibition_space`, auto-added in
`AhgExhibitionServiceProvider::boot()` (Schema::hasColumn + ALTER):

- `scan_shell_path` VARCHAR(500) - public path to the uploaded mesh
- `scan_shell_scale` DECIMAL(8,3) - fit-scale (default 1.000)
- `scan_embed_url` VARCHAR(500) - 360/Matterport embed URL

Surfaced in `ExhibitionSpaceService::getWalkthroughBuilding()` as `scan_shell`,
`scan_shell_scale`, `scan_embed` on each room. Setters: `setScanShell()`, `setScanMeta()`.
Controller endpoints (all `acl:update`): `exhibition-space.builder.scan-shell` (upload),
`.scan-shell-clear`, `.scan-meta` (scale + embed). Upload mirrors `uploadFloorImage`.

## Limits / not yet done

- **Point clouds** (`.las`, `.e57`, `.pcd`) are not rendered as points. `.ply` is accepted
  but loaded as a mesh (PLYLoader -> greyMesh), not a point cloud. Export a mesh for now.
- Matterport's own SDK is not embedded; only an iframe of the host's share URL is used, so
  licensing for the embedded tour stays with its host.
- The scan is placed at the room corner with a single scale; there is no per-axis offset or
  rotation control yet - decimate/orient the export, then dial in the fit scale.
