#!/usr/bin/env python3
import json, subprocess, sys
from pathlib import Path

REPO = "ArchiveHeritageGroup/heratio"
OUT = Path("/usr/share/nginx/heratio/audit_outputs")
TMP = Path("/tmp/audit_issues.json")

# Step 1: Check rate limit
print("=== Rate Limit ===")
r = subprocess.run(["gh", "api", "rate_limit"], capture_output=True, text=True, timeout=20)
if r.returncode == 0:
    rl = json.loads(r.stdout)
    core = rl["resources"]["core"]
    print(f"  Remaining: {core['remaining']}/{core['limit']}")
    print(f"  Resets at Unix timestamp: {core['reset']}")
else:
    print(f"  ERR: {r.stderr[:200]}")

# Step 2: Get all audit issues
print("\n=== Fetching Audit Issues ===")
r = subprocess.run(
    ["gh", "issue", "list", "--repo", REPO, "--label", "audit",
     "--state", "all", "--limit", "200", "--json", "number,title,state,labels"],
    capture_output=True, text=True, timeout=60
)
if r.returncode != 0:
    print(f"FAILED: {r.stderr[:300]}")
    sys.exit(1)

issues = json.loads(r.stdout)
TMP.write_text(r.stdout)

print(f"Total audit issues: {len(issues)}")
open_issues = [i for i in issues if i["state"] == "OPEN"]
closed_issues = [i for i in issues if i["state"] == "CLOSED"]
print(f"  Open: {len(open_issues)}")
print(f"  Closed: {len(closed_issues)}")

# Save full list
(OUT / "all_audit_issues.json").write_text(json.dumps(issues, indent=2))

# Print first 20 open issues
open_sorted = sorted(open_issues, key=lambda x: x["number"])
print("\nFirst 20 OPEN audit issues:")
for i in open_sorted[:20]:
    print(f"  #{i['number']}\t{i['title'][:70]}")

# Step 3: Create remaining 40 issues (batch 2: items 61-100)
print("\n=== Creating 40 Remaining GitHub Issues ===")
raw = json.loads((OUT / "full_audit_111_raw.json").read_text())
incomplete = [r for r in raw if r["status"] != "FULLY IMPLEMENTED"]
remaining = incomplete[60:]  # items 61-100 in 1-indexed terms

existing_nums = {i["number"] for i in issues}

created = []
failed = []
for idx, r in enumerate(remaining):
    pkg = r["package"]
    missing = r["missing"]
    title = f"[audit] {pkg}: {'/'.join(missing)} missing"
    body_file = OUT / f"issue_body_{pkg}.txt"

    if not body_file.exists():
        # Synthesize body from raw data
        body = f"""## Audit finding for `{pkg}`

**Missing items:** {', '.join(missing)}

### Checklist

| Item | Required | Found |
|------|----------|-------|
| README.md | Yes | ❌ |
| docs/help/*.md | Documentation | ❌ |
| src/Controllers/*.php | Controllers | {'❌' if 'controllers' in missing else '✅'} |
| src/Services/*.php | Services | {'❌' if 'services' in missing else '✅'} |
| src/Models/*.php | Models | {'✅' if 'models' in missing else '❌'} |
| database/migrations/ | Database | {'❌' if 'db' in missing else '✅'} |
| routes/ | Routes | {'❌' if 'routes' in missing else '✅'} |
| resources/views/ | Views | {'❌' if 'views' in missing else '✅'} |

### Action required

Please add the missing files and update this checklist.
"""
        body_file.write_text(body)

    num_guess = 538 + 60 + idx  # start at #598
    print(f"  [{idx+1}/40] {pkg} -> #{num_guess} (title: {title[:60]})")

    cr = subprocess.run(
        ["gh", "issue", "create", "--repo", REPO, "--title", title,
         "--body-file", str(body_file),
         "--label", "audit", "--label", "audit:missing-code"],
        capture_output=True, text=True, timeout=30
    )

    if cr.returncode == 0:
        url = cr.stdout.strip()
        gh_num = int(url.split("/")[-1])
        created.append({"package": pkg, "url": url, "gh": gh_num, "missing": missing})
        print(f"    -> #{gh_num} CREATED")
    else:
        err = cr.stderr.strip()[:100]
        failed.append((pkg, err))
        print(f"    -> FAILED: {err}")

    import time
    time.sleep(0.6)

print(f"\nCreated: {len(created)}, Failed: {len(len(failed))}")

# Save maps
issue_map = {}
for c in created:
    issue_map[c["package"]] = c["gh"]

# Load existing map and merge
existing_map_path = OUT / "gh_issue_map.json"
if existing_map_path.exists():
    existing = json.loads(existing_map_path.read_text())
    existing.update(issue_map)
else:
    existing = issue_map

existing_map_path.write_text(json.dumps(existing, indent=2))

# Log
log = OUT / "gh_issues_created_batch2.json"
log.write_text(json.dumps({"created": created, "failed": failed}, indent=2))

print("\n=== Summary ===")
print(f"Existing audit issues: {len(issues)}")
print(f"New issues created: {len(created)}")
print(f"Failed to create: {len(failed)}")
if failed:
    print("Failed packages:")
    for pkg, err in failed:
        print(f"  {pkg}: {err}")