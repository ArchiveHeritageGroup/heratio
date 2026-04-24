#!/usr/bin/env bash
# heratio-scan.sh — upload a scanned file to Heratio via the Scan API.
#
# Designed to be called from a scanner application's "post-scan" hook
# (e.g. NAPS2's External tool, SANE's scanadf script).
#
# Environment variables (read from env or config file):
#   HERATIO_URL            e.g. https://heratio.theahg.co.za
#   HERATIO_API_KEY        API key with the scan:write scope
#   HERATIO_PARENT_ID      existing IO id to file scans under (or use HERATIO_PARENT_SLUG)
#   HERATIO_PARENT_SLUG    URL slug of the parent IO (alternative to PARENT_ID)
#   HERATIO_SECTOR         archive (default) | library | gallery | museum
#   HERATIO_STANDARD       isadg (default) | marc21 | lido | spectrum | ...
#
# Config file (optional): ~/.heratio-scan.conf — KEY=value lines, shell-sourced.
#
# Usage:
#   heratio-scan.sh <file-to-upload> [identifier] [title] [sidecar.xml]
#
# Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
# Licensed under the GNU AGPL v3.

set -euo pipefail

CONFIG_FILE="${HERATIO_SCAN_CONFIG:-${HOME}/.heratio-scan.conf}"
if [ -f "$CONFIG_FILE" ]; then
    # shellcheck source=/dev/null
    . "$CONFIG_FILE"
fi

: "${HERATIO_URL:?HERATIO_URL required (env or ${CONFIG_FILE})}"
: "${HERATIO_API_KEY:?HERATIO_API_KEY required (env or ${CONFIG_FILE})}"
: "${HERATIO_SECTOR:=archive}"
: "${HERATIO_STANDARD:=isadg}"

if [ $# -lt 1 ]; then
    echo "Usage: $0 <file> [identifier] [title] [sidecar.xml]" >&2
    exit 2
fi

FILE="$1"
IDENTIFIER="${2:-}"
TITLE="${3:-}"
SIDECAR="${4:-}"

if [ ! -f "$FILE" ]; then
    echo "File not found: $FILE" >&2
    exit 1
fi

need() { command -v "$1" >/dev/null 2>&1 || { echo "Missing dependency: $1" >&2; exit 1; }; }
need curl
need jq

# Build session payload
SESSION_JSON=$(jq -n \
    --arg sector "$HERATIO_SECTOR" \
    --arg standard "$HERATIO_STANDARD" \
    --arg parent_slug "${HERATIO_PARENT_SLUG:-}" \
    --argjson parent_id "${HERATIO_PARENT_ID:-null}" \
    '{sector: $sector, standard: $standard, auto_commit: true} |
     if $parent_id != null then .parent_id = $parent_id else . end')

# Resolve parent slug → id if needed
if [ -z "${HERATIO_PARENT_ID:-}" ] && [ -n "${HERATIO_PARENT_SLUG:-}" ]; then
    PARENT_ID=$(curl -sS -H "X-API-Key: $HERATIO_API_KEY" \
        "$HERATIO_URL/api/v2/scan/destinations?q=$HERATIO_PARENT_SLUG" \
        | jq -r --arg s "$HERATIO_PARENT_SLUG" '.data[] | select(.slug == $s) | .id' | head -1)
    if [ -z "$PARENT_ID" ]; then
        echo "Could not resolve parent slug '$HERATIO_PARENT_SLUG'" >&2
        exit 1
    fi
    SESSION_JSON=$(echo "$SESSION_JSON" | jq --argjson p "$PARENT_ID" '.parent_id = $p')
fi

# Create session
SESSION_RESP=$(curl -sS -H "X-API-Key: $HERATIO_API_KEY" -H "Content-Type: application/json" \
    -X POST "$HERATIO_URL/api/v2/scan/sessions" -d "$SESSION_JSON")
TOKEN=$(echo "$SESSION_RESP" | jq -r '.data.token // empty')
if [ -z "$TOKEN" ]; then
    echo "Failed to create session:" >&2
    echo "$SESSION_RESP" >&2
    exit 1
fi

# Build metadata dict
META_JSON='{}'
[ -n "$IDENTIFIER" ] && META_JSON=$(echo "$META_JSON" | jq --arg v "$IDENTIFIER" '.identifier = $v')
[ -n "$TITLE" ]      && META_JSON=$(echo "$META_JSON" | jq --arg v "$TITLE" '.title = $v')

# Upload file (and sidecar if provided)
UPLOAD_ARGS=(-F "file=@${FILE}" -F "metadata=${META_JSON}")
[ -n "$SIDECAR" ] && [ -f "$SIDECAR" ] && UPLOAD_ARGS+=(-F "sidecar=@${SIDECAR}")

UPLOAD_RESP=$(curl -sS -H "X-API-Key: $HERATIO_API_KEY" \
    -X POST "$HERATIO_URL/api/v2/scan/sessions/$TOKEN/files" "${UPLOAD_ARGS[@]}")

SUCCESS=$(echo "$UPLOAD_RESP" | jq -r '.success // false')
if [ "$SUCCESS" != "true" ]; then
    echo "Upload failed:" >&2
    echo "$UPLOAD_RESP" >&2
    exit 1
fi

echo "Uploaded to Heratio. Session: $TOKEN"
echo "Status: $HERATIO_URL/api/v2/scan/sessions/$TOKEN"
