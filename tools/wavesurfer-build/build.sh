#!/usr/bin/env bash
# Heratio WaveSurfer.js vendor bundle build.
#
# WaveSurfer 7.x ships a precompiled UMD bundle on npm — no webpack
# step is required. `npm install` followed by a copy of the dist file
# is enough. Run from this directory:
#
#   ./build.sh
#
# Output:
#   ../../public/vendor/wavesurfer/wavesurfer.min.js
#
# Issue: #101 (bundle WaveSurfer.js for the media_show_waveform toggle).
#
# Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
# Licensed under the GNU AGPL v3.

set -euo pipefail

cd "$(dirname "$0")"

echo "[wavesurfer-build] installing npm deps…"
npm install --no-audit --no-fund --silent

WS_DST="../../public/vendor/wavesurfer"
mkdir -p "$WS_DST"

# The 7.x UMD bundle is at dist/wavesurfer.min.js. Older 6.x called it
# wavesurfer.js — we pin to 7.x in package.json so this stays stable.
echo "[wavesurfer-build] deploying WaveSurfer → $WS_DST"
cp -f node_modules/wavesurfer.js/dist/wavesurfer.min.js "$WS_DST/"

echo "[wavesurfer-build] done."
ls -lh "$WS_DST"
