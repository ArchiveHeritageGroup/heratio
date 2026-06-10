# FBX2glTF setup (FBX -> glTF for 3D uploads)

Heratio accepts **FBX** 3D model uploads and converts them to a web-ready GLB using the
**FBX2glTF** binary (Autodesk FBX -> glTF). The converted GLB is then texture-capped and
Draco-compressed by the same pipeline as OBJ (`ModelCompressionService` /
`ahg:optimize-models`). The original FBX is kept as the preservation master; the GLB is what
the viewer shows. When the binary is absent, FBX simply isn't converted (the rest works).

## Install (per host)

Easiest: run the consolidated installer, which also handles model-tools + c2patool:

```bash
sudo bin/install-host-tools.sh
```

Or install FBX2glTF on its own (Linux x86_64):

```bash
sudo mkdir -p /opt/ahg-model-tools
sudo curl -fsSL -o /opt/ahg-model-tools/FBX2glTF \
  https://github.com/facebookincubator/FBX2glTF/releases/download/v0.9.7/FBX2glTF-linux-x64
sudo chmod 0755 /opt/ahg-model-tools/FBX2glTF
/opt/ahg-model-tools/FBX2glTF --help    # verify
```

## Configuration

Read from `config/heratio.php`:

| Config key | Env var | Default |
|---|---|---|
| `heratio.fbx2gltf_bin` | `HERATIO_FBX2GLTF_BIN` | `/opt/ahg-model-tools/FBX2glTF` |

Point the env var elsewhere if you install the binary at a different path.

## How it's used

`AhgCore\Services\ModelCompressionService::compressToGlb($src, 'fbx')` runs
`FBX2glTF --binary --input <fbx> --output <dir>/m`, picks up the produced `.glb`, then runs
the standard texture-resize + Draco steps. `fbx` is in `ModelCompressionService::SUPPORTED`,
so `ahg:optimize-models` processes FBX masters automatically.

## Notes

- Linux x86_64 prebuilt binary. For other platforms, build from
  https://github.com/facebookincubator/FBX2glTF or use a matching release asset.
- Not vendored in the repo (it's a ~13 MB compiled binary), installed per host like the
  model-tools (obj2gltf/gltf-transform), PotreeConverter and c2patool.
- See also: `docs/model-optimisation-setup.md` (obj2gltf + gltf-transform + Draco),
  `docs/c2patool-setup.md` (C2PA), `docs/pointcloud-setup.md` (PotreeConverter).
