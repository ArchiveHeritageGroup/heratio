#!/usr/bin/env python3
"""
create_11_final.py
=================
Create the remaining GH issues and update gh_issue_map.json.
Known issues already created by earlier script: 628-642 (10 packages).
Need to create: ahg-workflow (#643 needed)
"""
import subprocess, json, time
from pathlib import Path

HERATIO = Path("/usr/share/nginx/heratio")
OUT = HERATIO / "audit_outputs"
REPO = "ArchiveHeritageGroup/heratio"
GH_MAP = OUT / "gh_issue_map.json"

# Known mappings from earlier script (already on GitHub)
KNOWN = {
    "ahg-share-link":       628,
    "ahg-static-page":      629,
    "ahg-statistics":        630,
    "ahg-storage-manage":    631,
    "ahg-term-taxonomy":     632,
    "ahg-theme-b5":          634,
    "ahg-translation":       636,
    "ahg-user-manage":       638,
    "ahg-vendor":            640,
    "ahg-version-control":   642,
    # ahg-workflow: need to create
}

def create_issue(pkg, body_file, labels):
    """Create a GH issue via REST API."""
    title = f"[Audit] {pkg}: audit missing items"
    payload = {
        "title": title,
        "body": open(body_file).read(),
        "labels": labels,
    }
    tmp = f"/tmp/gh_payload_{pkg}.json"
    with open(tmp, "w") as f:
        json.dump(payload, f)

    cmd = [
        "gh", "api", "--method", "POST",
        f"repos/{REPO}/issues",
        "--input", tmp,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    if result.returncode == 0:
        resp = json.loads(result.stdout)
        return resp.get("number"), resp.get("html_url")
    else:
        err = result.stderr.strip()[:200]
        print(f"  ERROR: {err}")
        return None, None

def update_gh_map(new_entries):
    """Merge new GH numbers into gh_issue_map.json."""
    current = json.loads(GH_MAP.read_text())
    for pkg, num in new_entries.items():
        current[pkg] = num
    GH_MAP.write_text(json.dumps(current, indent=2))
    print(f"Updated gh_issue_map.json with {len(new_entries)} entries")

# ── Update map with known issues (Phase 2 prep) ────────────────────────────────
print("=== Updating gh_issue_map.json with known GH# ===")
update_gh_map({pkg: num for pkg, num in KNOWN.items()})

# ── Create ahg-workflow issue ────────────────────────────────────────────────
print("\n=== Creating ahg-workflow issue ===")
labels = ["audit", "audit:missing-code", "audit:missing-docs"]
body_file = OUT / "issue_body_ahg-workflow.txt"
num, url = create_issue("ahg-workflow", body_file, labels)
if num:
    print(f"Created: #{num} {url}")
    update_gh_map({"ahg-workflow": num})
else:
    print("FAILED to create ahg-workflow issue")

# ── Verify final map ───────────────────────────────────────────────────────────
final_map = json.loads(GH_MAP.read_text())
null_count = sum(1 for v in final_map.values() if v is None)
print(f"\n=== Final map: {len(final_map)} packages, {null_count} still null ===")
if null_count:
    for k, v in final_map.items():
        if v is None:
            print(f"  NULL: {k}")