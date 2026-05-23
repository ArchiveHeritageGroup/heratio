#!/usr/bin/env python3
"""
Create 11 remaining GitHub issues via REST API.
Handles secondary rate limits with retry + backoff.
"""
import subprocess, json
from pathlib import Path
import time

HERATIO = Path("/usr/share/nginx/heratio")
OUT = HERATIO / "audit_outputs"
REPO = "ArchiveHeritageGroup/heratio"

null_packages = [
    "ahg-share-link", "ahg-static-page", "ahg-statistics",
    "ahg-storage-manage", "ahg-term-taxonomy", "ahg-theme-b5",
    "ahg-translation", "ahg-user-manage", "ahg-vendor",
    "ahg-version-control", "ahg-workflow",
]

created = []
failed = []

def call_api(cmd):
    """Call gh api with retry on secondary rate limit."""
    for attempt in range(5):
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        err = result.stderr.strip()
        if "secondary rate limit" in err.lower():
            wait = (attempt + 1) * 30
            print(f"    Secondary rate limit — sleeping {wait}s (attempt {attempt+1}/5)")
            time.sleep(wait)
            continue
        return result
    return result

for pkg in null_packages:
    body_file = OUT / f"issue_body_{pkg}.txt"
    if not body_file.exists():
        print(f"  SKIP {pkg}: no body file")
        failed.append((pkg, "no body file"))
        continue

    body = body_file.read_text()
    title = f"[audit] {pkg}: readme missing"

    cmd = [
        "gh", "api", "--method", "POST",
        f"repos/{REPO}/issues",
        "-f", f"title={title}",
        "-f", f"body={body}",
        "-f", "labels[]=audit",
        "-f", "labels[]=audit:missing-code",
        "-f", "labels[]=audit:missing-docs",
    ]

    print(f"  Creating {pkg}...")
    result = call_api(cmd)

    if result.returncode == 0:
        data = json.loads(result.stdout)
        num = data["number"]
        created.append({"package": pkg, "number": num, "url": data["html_url"]})
        print(f"  OK {pkg} -> #{num}")
    else:
        failed.append((pkg, result.stderr.strip()[:200]))
        print(f"  FAIL {pkg}: {result.stderr.strip()[:100]}")

    time.sleep(3)  # 3s between calls to avoid secondary limits

# Update gh_issue_map.json
issue_map = json.loads((OUT / "gh_issue_map.json").read_text())
for item in created:
    issue_map[item["package"]] = item["number"]
(OUT / "gh_issue_map.json").write_text(json.dumps(issue_map, indent=2))

log = OUT / "rest_issue_creation_log.json"
log.write_text(json.dumps({"created": created, "failed": failed}, indent=2))

print(f"\nDONE: created={len(created)} failed={len(failed)}")
if failed:
    for pkg, err in failed:
        print(f"  FAILED: {pkg}: {err}")