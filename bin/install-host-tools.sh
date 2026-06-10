#!/usr/bin/env bash
#
# install-host-tools.sh — install Heratio's OPTIONAL per-host binaries.
#
# These are NOT bundled in the repo (they're platform-specific compiled tools) and are
# NOT installed by bin/install. They power optional features:
#
#   - model-tools (obj2gltf + gltf-transform)  -> 3D model optimisation / OBJ->glTF + Draco
#   - FBX2glTF                                  -> FBX uploads -> glTF (ModelCompressionService)
#   - c2patool                                  -> C2PA Content Credentials embedding (ahg-c2pa)
#   - PotreeConverter                           -> point-cloud conversion (#1183) — see docs/pointcloud-setup.md
#
# Every feature degrades gracefully when its tool is absent, so this script is optional —
# but run it on any host that should support 3D ingest / provenance embedding.
#
# Idempotent: each tool is skipped if already installed at the expected path. Re-run safely.
# Override versions/paths with the env vars below. Run as root (writes to /opt + /usr/local/bin).
#
#   sudo bin/install-host-tools.sh
#
# Linux x86_64 only. See docs/model-optimisation-setup.md, docs/fbx2gltf-setup.md,
# docs/c2patool-setup.md, docs/pointcloud-setup.md for per-tool detail.

set -euo pipefail

MODEL_TOOLS_DIR="${HERATIO_MODEL_TOOLS_DIR:-/opt/ahg-model-tools}"
FBX2GLTF_BIN="${HERATIO_FBX2GLTF_BIN:-${MODEL_TOOLS_DIR}/FBX2glTF}"
FBX2GLTF_VERSION="${FBX2GLTF_VERSION:-v0.9.7}"
FBX2GLTF_URL="${FBX2GLTF_URL:-https://github.com/facebookincubator/FBX2glTF/releases/download/${FBX2GLTF_VERSION}/FBX2glTF-linux-x64}"
C2PATOOL_BIN="${HERATIO_C2PATOOL_BIN:-/usr/local/bin/c2patool}"
C2PATOOL_VERSION="${C2PATOOL_VERSION:-v0.9.12}"
C2PATOOL_URL="${C2PATOOL_URL:-https://github.com/contentauth/c2patool/releases/download/${C2PATOOL_VERSION}/c2patool-${C2PATOOL_VERSION}-x86_64-unknown-linux-gnu.tar.gz}"

say()  { printf '\033[0;34m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[0;32m  ok\033[0m %s\n' "$*"; }
skip() { printf '\033[0;33m  skip\033[0m %s\n' "$*"; }
warn() { printf '\033[0;33m  warn\033[0m %s\n' "$*"; }

[[ $EUID -eq 0 ]] || warn "not running as root — installs to /opt and /usr/local/bin may fail (use sudo)"

# ── model-tools: obj2gltf + gltf-transform (npm) ────────────────────────────────────────
say "model-tools (obj2gltf + gltf-transform) -> ${MODEL_TOOLS_DIR}"
if [[ -x "${MODEL_TOOLS_DIR}/node_modules/.bin/obj2gltf" && -x "${MODEL_TOOLS_DIR}/node_modules/.bin/gltf-transform" ]]; then
    skip "already installed (${MODEL_TOOLS_DIR}/node_modules/.bin)"
elif command -v npm >/dev/null 2>&1; then
    mkdir -p "${MODEL_TOOLS_DIR}"
    ( cd "${MODEL_TOOLS_DIR}" && npm install --no-audit --no-fund obj2gltf @gltf-transform/cli >/dev/null 2>&1 )
    [[ -x "${MODEL_TOOLS_DIR}/node_modules/.bin/gltf-transform" ]] && ok "installed" || warn "install ran but binaries not found"
else
    warn "npm not found — skipping model-tools (install Node 20 first). See docs/model-optimisation-setup.md"
fi

# ── FBX2glTF ────────────────────────────────────────────────────────────────────────────
say "FBX2glTF ${FBX2GLTF_VERSION} -> ${FBX2GLTF_BIN}"
if [[ -x "${FBX2GLTF_BIN}" ]] && "${FBX2GLTF_BIN}" --help >/dev/null 2>&1; then
    skip "already installed"
else
    mkdir -p "$(dirname "${FBX2GLTF_BIN}")"
    curl -fsSL -o "${FBX2GLTF_BIN}" "${FBX2GLTF_URL}"
    chmod 0755 "${FBX2GLTF_BIN}"
    "${FBX2GLTF_BIN}" --help >/dev/null 2>&1 && ok "installed (${FBX2GLTF_VERSION})" || warn "downloaded but does not run"
fi

# ── c2patool ────────────────────────────────────────────────────────────────────────────
say "c2patool ${C2PATOOL_VERSION} -> ${C2PATOOL_BIN}"
if [[ -x "${C2PATOOL_BIN}" ]] && "${C2PATOOL_BIN}" --version >/dev/null 2>&1; then
    skip "already installed ($(${C2PATOOL_BIN} --version 2>/dev/null))"
else
    tmp="$(mktemp -d)"
    curl -fsSL -o "${tmp}/c2patool.tar.gz" "${C2PATOOL_URL}"
    tar -xzf "${tmp}/c2patool.tar.gz" -C "${tmp}"
    bin="$(find "${tmp}" -type f -name c2patool | head -1)"
    [[ -n "${bin}" ]] && install -m 0755 "${bin}" "${C2PATOOL_BIN}" && ok "installed ($(${C2PATOOL_BIN} --version 2>/dev/null))" || warn "binary not found in archive"
    rm -rf "${tmp}"
fi

# ── PotreeConverter (point clouds) — pointer only, build is platform-specific ────────────
if command -v PotreeConverter >/dev/null 2>&1 || [[ -x /opt/PotreeConverter/PotreeConverter ]]; then
    skip "PotreeConverter present"
else
    warn "PotreeConverter not installed (optional, point clouds) — see docs/pointcloud-setup.md"
fi

say "done. Heratio reads these via config/heratio.php (model_tools_bin, fbx2gltf_bin, c2patool_bin, pointcloud_bin)."
say "Verify: php artisan ahg:c2pa-provenance-backfill (dry-run) and a 3D / FBX upload."
