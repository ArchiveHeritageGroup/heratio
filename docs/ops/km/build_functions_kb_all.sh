#!/bin/bash
#===============================================================================
# build_functions_kb_all.sh
# Regenerate all 5 KM function/method/route catalogues. Closes #58 (driver).
#
# Each generator writes its own /opt/ai/km/auto_functions_kb*.md and the
# inotify watcher (km-ingest-watcher.service) picks the touched files up on
# its next debounce cycle (~30s). No need to bump km-ingest manually.
#
# Run via /etc/systemd/system/km-build-functions.timer (every 10 minutes,
# Persistent=true so a missed cycle re-fires on next boot).
#
# @copyright Johan Pieterse / Plain Sailing
# @license   AGPL-3.0-or-later
#===============================================================================
set -e
KM=/opt/ai/km

echo "[build-functions] starting at $(date -u +%FT%TZ)"

# Run the four self-contained generators in parallel + the routes one
# sequentially (it shells out to artisan which is the longest leg).
( "$KM"/build_functions_kb.py        2>&1 | sed 's/^/[php]    /' ) &
( "$KM"/build_functions_kb_js.py     2>&1 | sed 's/^/[js]     /' ) &
( "$KM"/build_functions_kb_blade.py  2>&1 | sed 's/^/[blade]  /' ) &
( "$KM"/build_functions_kb_py.py     2>&1 | sed 's/^/[py]     /' ) &
( "$KM"/build_functions_kb_routes.py 2>&1 | sed 's/^/[routes] /' ) &
wait

echo "[build-functions] done at $(date -u +%FT%TZ)"
ls -lh "$KM"/auto_functions_kb*.md | sed 's/^/[build-functions] /'
