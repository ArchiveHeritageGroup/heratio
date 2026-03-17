#!/bin/bash
# Usage: ./generate-thumbnail.sh <input.glb> <output.png> [width] [height]

INPUT="$1"
OUTPUT="$2"
WIDTH="${3:-512}"
HEIGHT="${4:-512}"

if [ -z "$INPUT" ] || [ -z "$OUTPUT" ]; then
    echo "Usage: $0 <input.glb> <output.png> [width] [height]"
    exit 1
fi

SCRIPT_DIR="$(dirname "$(realpath "$0")")"

# Try snap blender first, fallback to apt blender
if [ -x /snap/bin/blender ]; then
    BLENDER=/snap/bin/blender
else
    BLENDER=blender
fi

echo "Using: $BLENDER"

# Snap Blender can't run as www-data (home dir restriction) — use sudo
if [ "$(id -u)" -ne 0 ] && sudo -n "$BLENDER" --version >/dev/null 2>&1; then
    sudo "$BLENDER" --background --python "$SCRIPT_DIR/blender_thumbnail.py" -- "$INPUT" "$OUTPUT" "$WIDTH" "$HEIGHT" 2>&1 | grep -v "^Blender\|^Read\|^Fra:\|^Color management"
    sudo chown www-data:www-data "$OUTPUT" 2>/dev/null
else
    $BLENDER --background --python "$SCRIPT_DIR/blender_thumbnail.py" -- "$INPUT" "$OUTPUT" "$WIDTH" "$HEIGHT" 2>&1 | grep -v "^Blender\|^Read\|^Fra:\|^Color management"
fi

if [ -f "$OUTPUT" ] && [ $(stat -c%s "$OUTPUT") -gt 5000 ]; then
    echo "Success: $OUTPUT ($(du -h "$OUTPUT" | cut -f1))"
else
    echo "Blender render failed, generating placeholder..."
    # Generate a placeholder with ImageMagick
    convert -size ${WIDTH}x${HEIGHT} \
        -define gradient:angle=135 \
        gradient:'#667eea-#764ba2' \
        -gravity center \
        -font DejaVu-Sans -pointsize 48 -fill white \
        -annotate +0+0 "3D" \
        -font DejaVu-Sans -pointsize 18 -fill white \
        -annotate +0+40 "$(basename "$INPUT")" \
        "$OUTPUT"
    
    if [ -f "$OUTPUT" ]; then
        echo "Placeholder created: $OUTPUT ($(du -h "$OUTPUT" | cut -f1))"
    else
        echo "Error: Failed to generate any thumbnail"
        exit 1
    fi
fi
