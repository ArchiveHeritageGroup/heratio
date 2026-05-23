#!/usr/bin/env python3
"""Create 11 remaining GH issues via REST API with proper labels encoding."""
import subprocess, json
from pathlib import Path

HERATIO = Path("/usr/share/nginx/heratio")
OUT = HERATIO / "audit_outputs"
REPO = "ArchiveHeritageGroup/heratio"

null_packages = [
    "ahg-share-link","ahg-static-page","ahg-statistics","ahg-storage-manage",
    "ahg-term-taxonomy","ahg-theme-b5","ahg-translation","ahg-user-manage",
    "ahg-vendor","ahg-version-control","ahg-workflow",
]

created = []
failed = []

for pkg in null_packages:
    body_file = OUT / f"issue_body_{pkg}.txt"
    if not body_file.exists():
        print(f"SKIP {pkg}: no body file")
        failed.append((pkg, "no body file"))
        continue

    body = body_file.read_text()
    title = f"[audit] {pkg}: readme missing"

    # Use --input to send JSON body so labels are a proper array
    payload = json.dumps({
        "title": title,
        "body": body,
        "labels": ["audit", "audit:missing-code", "audit:missing-docs"]
    })

    result = subprocess.run(
        ["gh", "api", "--method", "POST",
         f"repos/{REPO}/issues",
         "--input", "-"],
        input=payload,
        capture_output=True,
        text=True,
        timeout=30
    )
    if result.returncode == 0:
        data = json.loads(result.stdout)
        num = data["number"]
        print(f"OK {pkg} -> #{num}")
        created.append({"package": pkg, "number": num})
    else:
        err = result.stderr.strip()[:300]
        print(f"FAIL {pkg}: {err}")
        failed.append((pkg, err))

# Update gh_issue_map.json
issue_map = json.loads((OUT / "gh_issue_map.json").read_text())
for item in created:
    issue_map[item["package"]] = item["number"]
(OUT / "gh_issue_map.json").write_text(json.dumps(issue_map, indent=2))

print(f"\nDONE: created={len(created)} failed={len(failed)}")
if failed:
    for pkg, err in failed:
        print(f"  FAILED: {pkg}: {err}")