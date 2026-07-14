#!/bin/bash
#
# demo-videos.sh - convert the Playwright 'demo' project recordings (.webm) into
# YouTube-ready .mp4 files, one per demo function. Playwright writes a silent
# video.webm per test under test-results/; this transcodes each to H.264 mp4.
#
# Usage:
#   1) record:  HERATIO_URL=http://192.168.0.112:8090 \
#               TEST_ADMIN_EMAIL=johan@theahg.co.za TEST_ADMIN_PASSWORD='...' \
#               npx playwright test --project=demo --workers=1
#   2) convert: scripts/demo-videos.sh [output-dir]
#
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/test-results/demo-videos}"
mkdir -p "$OUT"

command -v ffmpeg >/dev/null || { echo "ffmpeg not found"; exit 1; }

found=0
while IFS= read -r webm; do
  found=1
  dir="$(basename "$(dirname "$webm")")"
  # test-results dir looks like: authority-record-crud.demo-<hash>--<title>-demo
  # dir is "<spec-basename>.demo-<hash>--<title>-demo" (Playwright may truncate);
  # the clean function name is everything before the first dot.
  name="$(printf '%s' "$dir" | sed -E 's/\..*$//; s/[^a-zA-Z0-9]+/-/g; s/-+$//')"
  [ -z "$name" ] && name="demo-$(printf '%s' "$dir" | cut -c1-8)"
  mp4="$OUT/${name}.mp4"
  ffmpeg -nostdin -y -loglevel error -i "$webm" \
    -c:v libx264 -pix_fmt yuv420p -crf 22 -preset medium -movflags +faststart \
    "$mp4"
  dur="$(ffprobe -v error -show_entries format=duration -of default=nw=1:nk=1 "$mp4" 2>/dev/null | cut -d. -f1)"
  printf '  %s  (%ss, %s)\n' "$mp4" "${dur:-?}" "$(du -h "$mp4" | cut -f1)"
done < <(find "$ROOT/test-results" -name "video.webm")

[ "$found" = 1 ] || { echo "  no video.webm found under test-results/ - run the demo project first"; exit 1; }
echo "Done. Upload the .mp4 files in $OUT to YouTube."
