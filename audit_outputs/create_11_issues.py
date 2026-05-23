#!/usr/bin/env python3
"""Create the 11 remaining GH issues via REST API."""
import subprocess, json, time
from pathlib import Path

HERATIO = Path("/usr/share/nginx/heratio")
OUT = HERATIO / "audit_outputs"
REPO = "ArchiveHeritageGroup/heratio"

packages = [
    "ahg-share-link", "ahg-static-page", "ahg-statistics", "ahg-storage-manage",
    "ahg-term-taxonomy", "ahg-theme-b5", "ahg-translation", "ahg-user-manage",
    "ahg-vendor", "ahg-version-control", "ahg-workflow",
]

created = []
failed = []

for i, pkg in enumerate(packages):
    body_file = OUT / f"issue_body_{pkg}.txt"
    title = f"[Audit] {pkg}: audit missing items"

    payload = {
        "title": title,
        "body": open(body_file).read(),
        "labels": ["audit", "audit:missing-code", "audit:missing-docs"],
    }
    payload_file = f"/tmp/gh_issue_{pkg}.json"
    with open(payload_file, "w") as f:
        json.dump(payload, f)

    cmd = [
        "gh", "api", "--method", "POST",
        f"repos/{REPO}/issues",
        "--input", payload_file,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    if result.returncode == 0:
        resp = json.loads(result.stdout)
        num = resp.get("number")
        url = resp.get("html_url")
        created.append((pkg, num, url))
        print(f"OK  #{num}: {pkg}")
    else:
        err = result.stderr.strip()[:200]
        failed.append((pkg, err))
        print(f"FAIL {pkg}: {err}")

    if i < len(packages) - 1:
        print(f"  sleeping 25s...")
        time.sleep(25)

print(f"\nCreated: {len(created)}, Failed: {len(failed)}")
if failed:
    for p, e in failed:
        print(f"  FAILED: {p}: {e}")

with open(OUT / "gh_issues_created_11.json", "w") as f:
    json.dump({"created": created, "failed": failed}, f, indent=2)