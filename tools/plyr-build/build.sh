#!/usr/bin/env bash
# Heratio media-player vendor bundle build.
#
# Plyr and Video.js both ship precompiled UMD/IIFE bundles on npm, so no
# webpack step is required — `npm install` followed by a copy of the
# dist files is enough. Run from this directory:
#
#   ./build.sh
#
# Output:
#   ../../public/vendor/plyr/{plyr.min.js, plyr.css, plyr.svg}
#   ../../public/vendor/videojs/{video.min.js, video-js.min.css}
#
# (Plyr's `blank.mp4` is only needed for YouTube/Vimeo embeds — we only
# enhance native <audio>/<video>, so we skip it.)
#
# Issue: #103 (ship Plyr / Video.js bundles for richer player UIs).
#
# Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
# Licensed under the GNU AGPL v3.

set -euo pipefail

cd "$(dirname "$0")"

echo "[plyr-build] installing npm deps…"
npm install --no-audit --no-fund --silent

PLYR_DST="../../public/vendor/plyr"
VJS_DST="../../public/vendor/videojs"

mkdir -p "$PLYR_DST" "$VJS_DST"

echo "[plyr-build] deploying Plyr → $PLYR_DST"
cp -f node_modules/plyr/dist/plyr.min.js   "$PLYR_DST/"
cp -f node_modules/plyr/dist/plyr.css      "$PLYR_DST/"
cp -f node_modules/plyr/dist/plyr.svg      "$PLYR_DST/"

echo "[plyr-build] deploying Video.js → $VJS_DST"
cp -f node_modules/video.js/dist/video.min.js     "$VJS_DST/"
cp -f node_modules/video.js/dist/video-js.min.css "$VJS_DST/"

echo "[plyr-build] done."
ls -lh "$PLYR_DST" "$VJS_DST"
