import subprocess, json
from pathlib import Path
import time

HERATIO = Path("/usr/share/nginx/heratio")
OUT = HERATIO / "audit_outputs"
REPO = "ArchiveHeritageGroup/heratio"

raw = json.loads((OUT / "full_audit_111_raw.json").read_text())
incomplete = [r for r in raw if r["status"] != "FULLY IMPLEMENTED"]
all_pkgs = [r["package"] for r in incomplete]

# Batches 1-6 done: #538-598 (60 packages, items 1-60)
# Remaining: items 61-100 (packages 61-100 in all_pkgs)
remaining = all_pkgs[60:]
print(f"Remaining: {len(remaining)} packages (items 61-100)")

created = []
failed = []

for i, pkg in enumerate(remaining):
    body_file = OUT / f"issue_body_{pkg}.txt"
    r = next(x for x in incomplete if x["package"] == pkg)
    missing = r["missing"]
    title = f"[audit] {pkg}: {'/'.join(missing)} missing"

    cmd = [
        "gh", "issue", "create",
        "--repo", REPO,
        "--title", title,
        "--body-file", str(body_file),
        "--label", "audit",
        "--label", "audit:missing-code",
        "--label", "audit:missing-docs"
    ]

    result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    item_num = 61 + i
    if result.returncode == 0:
        url = result.stdout.strip()
        created.append((pkg, url, missing))
        print(f"  OK item {item_num} {pkg} -> #{url.split('/')[-1]}")
    else:
        err = result.stderr.strip()[:120]
        failed.append((pkg, err))
        print(f"  FAIL item {item_num} {pkg}: {err}")

    time.sleep(0.5)

log = OUT / "gh_issues_created.json"
log.write_text(json.dumps({"created": created, "failed": failed}, indent=2))
print(f"DONE: created={len(created)} failed={len(failed)}")
if failed:
    for pkg, err in failed:
        print(f"  FAILED: {pkg}: {err}")