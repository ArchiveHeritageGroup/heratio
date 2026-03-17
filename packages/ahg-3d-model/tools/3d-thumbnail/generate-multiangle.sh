#!/bin/bash
# Generate 6 multi-angle renders of a 3D model via Blender.
# Usage: ./generate-multiangle.sh <input.obj> <output_dir> [size]
#
# Falls back to ImageMagick placeholders if Blender fails.
#
# @author Johan Pieterse <johan@theahg.co.za>

INPUT="$1"
OUTPUT_DIR="$2"
SIZE="${3:-1024}"

if [ -z "$INPUT" ] || [ -z "$OUTPUT_DIR" ]; then
    echo "Usage: $0 <input.glb> <output_dir> [size]"
    exit 1
fi

SCRIPT_DIR="$(dirname "$(realpath "$0")")"

# Try snap blender first, fallback to apt blender
if [ -x /snap/bin/blender ]; then
    BLENDER=/snap/bin/blender
elif command -v blender &>/dev/null; then
    BLENDER=blender
else
    BLENDER=""
fi

mkdir -p "$OUTPUT_DIR"

VIEWS="front back left right top detail"

if [ -n "$BLENDER" ]; then
    echo "Using Blender: $BLENDER"
    # Snap Blender can't run as www-data (home dir restriction) — use sudo
    if [ "$(id -u)" -ne 0 ] && sudo -n "$BLENDER" --version >/dev/null 2>&1; then
        sudo "$BLENDER" --background --python "$SCRIPT_DIR/render_multiangle.py" -- "$INPUT" "$OUTPUT_DIR" "$SIZE" 2>&1 | grep -v "^Blender\|^Read\|^Fra:\|^Color management"
        # Fix ownership back to www-data (need sudo since files are owned by root)
        sudo chown www-data:www-data "$OUTPUT_DIR"/*.png 2>/dev/null
    else
        $BLENDER --background --python "$SCRIPT_DIR/render_multiangle.py" -- "$INPUT" "$OUTPUT_DIR" "$SIZE" 2>&1 | grep -v "^Blender\|^Read\|^Fra:\|^Color management"
    fi

    # Verify at least one render succeeded
    SUCCESS=0
    for VIEW in $VIEWS; do
        if [ -f "$OUTPUT_DIR/$VIEW.png" ] && [ "$(stat -c%s "$OUTPUT_DIR/$VIEW.png")" -gt 500 ]; then
            SUCCESS=$((SUCCESS + 1))
        fi
    done

    if [ "$SUCCESS" -gt 0 ]; then
        echo "Success: $SUCCESS/6 views rendered"
        exit 0
    fi

    echo "Blender render failed, generating placeholders..."
fi

# Fallback: generate 6 placeholder images with ImageMagick
COLORS=("#667eea:#764ba2" "#764ba2:#f093fb" "#4facfe:#00f2fe" "#43e97b:#38f9d7" "#fa709a:#fee140" "#a18cd1:#fbc2eb")
IDX=0
for VIEW in $VIEWS; do
    COLOR="${COLORS[$IDX]}"
    FROM="${COLOR%:*}"
    TO="${COLOR#*:}"
    convert -size ${SIZE}x${SIZE} \
        -define gradient:angle=135 \
        gradient:"${FROM}-${TO}" \
        -gravity center \
        -font DejaVu-Sans -pointsize 48 -fill white \
        -annotate +0-20 "3D" \
        -font DejaVu-Sans -pointsize 24 -fill white \
        -annotate +0+30 "$VIEW" \
        -font DejaVu-Sans -pointsize 14 -fill white \
        -annotate +0+60 "$(basename "$INPUT")" \
        "$OUTPUT_DIR/$VIEW.png" 2>/dev/null

    if [ -f "$OUTPUT_DIR/$VIEW.png" ]; then
        echo "Placeholder: $VIEW.png"
    fi
    IDX=$((IDX + 1))
done

echo "Placeholders generated in $OUTPUT_DIR"
exit 0
