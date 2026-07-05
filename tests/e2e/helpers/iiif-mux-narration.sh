#!/usr/bin/env bash
# Mux a narration audio track onto the IIIF deep-zoom demo video.
#
# Prereq: generate the narration in Johan's cloned voice (f5:johan) and drop it
# as narration.mp3 (or .wav) next to the video. Generation options:
#   A. Workbench Audio UI (recommended)  - synth narration.txt with voice f5:johan
#   B. Via workbench synthesiseSpeech()  - if driven with proper auth
#   C. Direct F5-TTS (needs explicit OK) - POST {F5_TTS_BASE_URL}/tts {voice_id:"johan", text}
#
# Then:  bash tests/e2e/helpers/iiif-mux-narration.sh
#
set -euo pipefail
DIR="$(cd "$(dirname "$0")/../artifacts/iiif-demo" && pwd)"
VIDEO="$DIR/iiif-deepzoom.webm"
OUT="$DIR/iiif-deepzoom-narrated.mp4"

AUDIO=""
for c in "$DIR/narration.mp3" "$DIR/narration.wav" "$DIR/narration.m4a"; do
  [ -f "$c" ] && AUDIO="$c" && break
done
[ -z "$AUDIO" ] && { echo "No narration audio found in $DIR (expected narration.mp3/.wav). Generate it first (voice f5:johan from narration.txt)."; exit 1; }

VDUR=$(ffprobe -v error -show_entries format=duration -of default=nk=1:nw=1 "$VIDEO")
ADUR=$(ffprobe -v error -show_entries format=duration -of default=nk=1:nw=1 "$AUDIO")
echo "video ${VDUR}s | audio ${ADUR}s"

# Re-encode video to H.264/MP4, add AAC audio. -shortest trims to the shorter
# stream so there's no trailing silence/frozen frame. If the narration is a bit
# longer than the clip, bump the capture sleeps in iiif-demo-capture.mjs to match.
ffmpeg -y -i "$VIDEO" -i "$AUDIO" \
  -c:v libx264 -preset medium -crf 20 -pix_fmt yuv420p \
  -c:a aac -b:a 192k -shortest \
  "$OUT"

echo "narrated video: $OUT"
