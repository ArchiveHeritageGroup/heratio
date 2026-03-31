#!/bin/bash
#
# Add AGPL license header to all PHP files that don't already have one.
# Usage:
#   ./bin/add-license-header.sh                    # dry run (default)
#   ./bin/add-license-header.sh --apply            # actually modify files
#   ./bin/add-license-header.sh --apply --archive  # run on Archive too
#

set -euo pipefail

DRY_RUN=true
DO_ARCHIVE=false

for arg in "$@"; do
  case "$arg" in
    --apply)   DRY_RUN=false ;;
    --archive) DO_ARCHIVE=true ;;
  esac
done

HERATIO_DIR="/usr/share/nginx/heratio"
ARCHIVE_DIR="/usr/share/nginx/archive"

# Directories to skip (vendor, node_modules, .git, storage, cache)
SKIP_DIRS="vendor|node_modules|\.git|storage/framework|bootstrap/cache|cache/qubit"

add_headers() {
  local BASE_DIR="$1"
  local PROJECT_NAME="$2"
  local PROJECT_URL="$3"

  local HEADER
  HEADER=$(cat <<ENDHEADER
/**
 * [SHORT_DESC]
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailinginformationsystems.co.za
 *
 * This file is part of ${PROJECT_NAME}.
 *
 * ${PROJECT_NAME} is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * ${PROJECT_NAME} is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with ${PROJECT_NAME}. If not, see <https://www.gnu.org/licenses/>.
 */
ENDHEADER
)

  local COUNT=0
  local SKIPPED=0
  local TOTAL=0

  while IFS= read -r -d '' file; do
    TOTAL=$((TOTAL + 1))

    # Skip if file already has a license header (check for common markers)
    if grep -q "GNU Affero General Public License\|Copyright (C).*Johan Pieterse\|Plain Sailing Information Systems" "$file" 2>/dev/null; then
      SKIPPED=$((SKIPPED + 1))
      continue
    fi

    COUNT=$((COUNT + 1))

    if $DRY_RUN; then
      echo "[DRY RUN] Would add header: $file"
    else
      # Read the file
      local CONTENT
      CONTENT=$(cat "$file")

      # Check if file starts with <?php
      if [[ "$CONTENT" == "<?php"* ]]; then
        # Insert header after <?php line
        local REST
        REST="${CONTENT#*$'\n'}"
        local FIRST_LINE="${CONTENT%%$'\n'*}"

        # Check if there's already a docblock right after <?php
        local AFTER_PHP
        AFTER_PHP=$(echo "$REST" | head -c 20)

        printf '%s\n%s\n\n%s\n' "$FIRST_LINE" "$HEADER" "$REST" > "$file"
      else
        # No <?php tag (unlikely but handle it)
        printf '<?php\n%s\n\n%s\n' "$HEADER" "$CONTENT" > "$file"
      fi

      echo "[ADDED] $file"
    fi

  done < <(find "$BASE_DIR" -name '*.php' -type f -not -path "*/vendor/*" -not -path "*/node_modules/*" -not -path "*/.git/*" -not -path "*/storage/framework/*" -not -path "*/bootstrap/cache/*" -not -path "*/cache/qubit/*" -print0)

  echo ""
  echo "=== ${PROJECT_NAME} ==="
  echo "Total PHP files: $TOTAL"
  echo "Already have header: $SKIPPED"
  echo "Need header: $COUNT"
  if $DRY_RUN; then
    echo "(Dry run — no files modified. Use --apply to write changes.)"
  else
    echo "Headers added: $COUNT"
  fi
  echo ""
}

echo "License Header Tool"
echo "==================="
if $DRY_RUN; then
  echo "Mode: DRY RUN (use --apply to modify files)"
else
  echo "Mode: APPLY (files will be modified)"
fi
echo ""

# Heratio
add_headers "$HERATIO_DIR" "Heratio" "heratio.theahg.co.za"

# Archive (optional)
if $DO_ARCHIVE; then
  add_headers "$ARCHIVE_DIR" "Archive" "psis.theahg.co.za"
fi
