#!/usr/bin/env python3
"""Create remaining GitHub issues via REST API (avoids GraphQL rate limit)."""
import subprocess, json
from pathlib import Path

HERATIO = Path("/usr/share/nginx/heratio")
OUT = HERATIO / "audit_outputs"
REPO = "ArchiveHeritageGroup/heratio"

raw = json.loads((OUT / "full_audit_111_raw.json").read_text())
incomplete = [r for r in raw if r["status"] != "FULLY IMPLEMENTED"]
remaining = [r for r in incomplete if r.get("gh_issue") is None]

print(f"Missing GH issues: {len(remaining)}")

created = []
failed = []

for i, r in enumerate(remaining):
    pkg = r["package"]
    missing = r["missing"]
    body_file = OUT / f"issue_body_{pkg}.txt"
    title = f"[audit] {pkg}: {'/'.join(missing)} missing"

    labels = ["audit", "audit:missing-code", "audit:missing-docs"]

    cmd = [
        "gh", "api", "--method", "POST",
        f"repos/{REPO}/issues",
        "-f", f"title={title}",
        "-F", f"body=@{body_file}",
        "-F", f"labels={json.dumps(labels)}",
    ]

    result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    if result.returncode == 0:
        resp = json.loads(result.stdout)
        url = resp.get("html_url", "")
        num = resp.get("number", "")
        created.append((pkg, url, num, missing))
        print(f"  OK #{num} {pkg}")
    else:
        err = result.stderr.strip()[:120]
        failed.append((pkg, err))
        print(f"  FAIL {pkg}: {err}")

    # Be gentle — 0.3s between calls (max 200/hr on core API; we need ~100)
    import time; time.sleep(18)  # 100 calls / 30 min = 18s each = comfortable

log = OUT / "gh_issues_created.json"
log.write_text(json.dumps({"created": created, "failed": failed}, indent=2))
print(f"\nDONE: created={len(created)} failed={len(failed)}")
if failed:
    for pkg, err in failed:
        print(f"  FAILED: {pkg}: {err}")