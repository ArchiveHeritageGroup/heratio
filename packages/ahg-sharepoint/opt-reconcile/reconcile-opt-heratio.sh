#!/usr/bin/env bash
#
# reconcile-opt-heratio.sh - bring the /opt canonical F3 (heratio) source in line
# with the repo after the #1221 SharePoint cutover (Heratio v1.142.72).
#
# Operator-run, on the /opt tree. After the cutover the repo's ahg-federation is
# the canonical shape: connectorClassFor() is registry-first and the SharePoint
# connector lives in the ahg-sharepoint package (contributed back via
# config('federation.connectors')). This script makes /opt match.
#
# Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.

set -euo pipefail

OPT="${OPT_F3_HERATIO:-/opt/ahg-sp-integration/F3/heratio}"
HERE="$(cd "$(dirname "$0")" && pwd)"

if [ ! -f "$OPT/src/Services/FederatedSearchService.php" ]; then
  echo "ERROR: $OPT/src/Services/FederatedSearchService.php not found. Set OPT_F3_HERATIO." >&2
  exit 1
fi

cd "$OPT"

# 1) Make connectorClassFor() registry-first (drops the hardcoded SharePoint FQCN).
echo "[1/2] Applying registry-first connectorClassFor patch..."
patch -p1 < "$HERE/heratio-federatedsearchservice-registry-first.patch"

# 2) Remove the SharePoint connector - it now lives in the ahg-sharepoint package.
echo "[2/2] Removing now-relocated SharePointGraphConnector.php..."
rm -f src/Connectors/SharePointGraphConnector.php

echo
echo "Done. /opt heratio F3 now matches the repo's ahg-federation"
echo "(registry-first dispatch, SharePoint-free source)."
echo "If /opt is a git tree, review the diff and commit there."
