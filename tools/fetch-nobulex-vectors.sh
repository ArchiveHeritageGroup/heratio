#!/usr/bin/env bash
# Fetch the latest nobulex test vectors so the conformance suite can verify
# byte-compatibility. Run from the package root.
#
# Vectors are pulled from https://github.com/arian-gogani/nobulex/tree/main/spec/vectors
# and converted into the simpler [{input, expected}, ...] shape this suite
# expects. If upstream changes the layout, this script needs updating.
set -euo pipefail

cd "$(dirname "$0")/.."
TARGET="tests/fixtures/nobulex"
mkdir -p "$TARGET"

NOBULEX_RAW="https://raw.githubusercontent.com/arian-gogani/nobulex/main/spec/vectors"

echo "Fetching nobulex test vectors into ${TARGET}/ ..."

if curl -fsSL --max-time 20 -o "$TARGET/ctef-vectors.json" "$NOBULEX_RAW/ctef-vectors.json" 2>/dev/null; then
    echo "  - ctef-vectors.json"
else
    echo "  ! ctef-vectors.json not reachable (offline or upstream renamed)"
fi

if curl -fsSL --max-time 20 -o "$TARGET/aps-fixture-v1.json" "$NOBULEX_RAW/aps-fixture-v1.json" 2>/dev/null; then
    echo "  - aps-fixture-v1.json"
else
    echo "  ! aps-fixture-v1.json not reachable"
fi

echo
echo "Done. If a file failed, check the upstream repo path manually."
echo "JCS vectors (jcs-vectors.json) are not yet published by nobulex upstream"
echo "as a standalone file - the conformance test for them remains skipped"
echo "until either upstream publishes one or we extract one from ctef-vectors.json."
