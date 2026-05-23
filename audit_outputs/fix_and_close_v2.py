#!/usr/bin/env python3
import json, subprocess, time, sys
from pathlib import Path

REPO = "ArchiveHeritageGroup/heratio"
OUT = Path("/usr/share/nginx/heratio/audit_outputs")
GH_AUTH = "Bearer gho_************************************"  # gh has it configured already

print("=== Rate Limit ===")
r = subprocess.run(
    ["gh", "api", "rate_limit", "--jq", ".rate"],
    capture_output=True, text=True, timeout=20
)
if r.returncode == 0:
    rl = json.loads(r.stdout)
    print(f"  Remaining: {rl['remaining']}/{rl['limit']}, reset: {rl['reset']}")
else:
    print(f"  ERR: {r.stderr}")

# Step 2: Get all audit issues via REST (bypasses GQL rate limit)
print("\n=== Fetching Audit Issues (REST) ===")
issues = []
page = 1
while True:
    r = subprocess.run(
        ["gh", "api", "repos", REPO, "issues",
         "--method", "GET",
         "-f", "labels=audit",
         "-f", "state=all",
         "-f", f"page={page}",
         "-f", "per_page=100"],
        capture_output=True, text=True, timeout=30
    )
    if r.returncode != 0:
        print(f"  FAILED: {r.stderr[:200]}")
        break
    batch = json.loads(r.stdout)
    issues.extend(batch)
    print(f"  Page {page}: +{len(batch)} issues (total: {len(issues)})")
    if len(batch) < 100:
        break
    page += 1
    time.sleep(1)

print(f"\nTotal audit issues: {len(issues)}")
open_issues = [i for i in issues if i["state"] == "open"]
closed_issues = [i for i in issues if i["state"] == "closed"]
print(f"  Open: {len(open_issues)}, Closed: {len(closed_issues)}")

nums = sorted([i["number"] for i in issues])
print(f"  Issue numbers: {nums[0] if nums else 0} - {nums[-1] if nums else 0}")

# Save full list
(OUT / "all_audit_issues.json").write_text(json.dumps(issues, indent=2))

# Print first 20
print("\nFirst 20 audit issues:")
for i in sorted(issues, key=lambda x: x["number"])[:20]:
    print(f"  #{i['number']}\t{i['state']}\t{i['title'][:70]}")

# Step 3: Identify packages that still need GH issues
print("\n=== Packages still needing GitHub issues ===")
raw = json.loads((OUT / "full_audit_111_raw.json").read_text())
incomplete = [r for r in raw if r["status"] != "FULLY IMPLEMENTED"]

existing_nums = {i["number"] for i in issues}
existing_titles = {i["title"] for i in issues}
print(f"  Packages missing README or code: {len(incomplete)}")

# Filter to packages not already covered by an existing issue
already_has_issue = set()
for r in incomplete:
    pkg = r["package"]
    for i in issues:
        if f"[audit] {pkg}:" in i["title"] or i["title"] == f"[audit] {pkg}":
            already_has_issue.add(pkg)
            break

still_need = [r for r in incomplete if r["package"] not in already_has_issue]
print(f"  Packages already covered by existing issues: {len(already_has_issue)}")
print(f"  Packages still needing issues: {len(still_need)}")

for r in still_need:
    print(f"    {r['package']}: {r['missing']}")

# Step 4: Create remaining issues
if still_need:
    print(f"\n=== Creating {len(still_need)} remaining GitHub issues (REST) ===")
    created = []
    failed = []

    for idx, r in enumerate(still_need):
        pkg = r["package"]
        missing = r["missing"]
        title = f"[audit] {pkg}: {'/'.join(missing)} missing"
        body_file = OUT / f"issue_body_{pkg}.txt"

        if not body_file.exists():
            body = f"""## Audit finding for `{pkg}`

**Missing items:** {', '.join(missing)}

### Checklist

| Item | Required | Found |
|------|----------|-------|
| README.md | Yes | ❌ |
| docs/help/*.md | Documentation | {'❌' if 'docs_help' in missing else '✅'} |
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

        print(f"  [{idx+1}/{len(still_need)}] {pkg}...")

        cr = subprocess.run(
            ["gh", "api", "repos", REPO, "issues",
             "--method", "POST",
             "-f", f"title={title}",
             "-f", f"body=@{body_file}",
             "-f", "labels=['audit','audit:missing-code']"],
            capture_output=True, text=True, timeout=30
        )

        if cr.returncode == 0:
            data = json.loads(cr.stdout)
            gh_num = data["number"]
            created.append({"package": pkg, "url": data["html_url"], "gh": gh_num, "missing": missing})
            print(f"    -> #{gh_num} CREATED")
        else:
            err = cr.stderr.strip()[:120]
            failed.append((pkg, err))
            print(f"    -> FAILED: {err}")

        time.sleep(1)

    print(f"\nCreated: {len(created)}, Failed: {len(failed)}")

    # Update issue map
    issue_map = {}
    for c in created:
        issue_map[c["package"]] = c["gh"]

    existing_map_path = OUT / "gh_issue_map.json"
    if existing_map_path.exists():
        existing = json.loads(existing_map_path.read_text())
        existing.update(issue_map)
    else:
        existing = issue_map

    existing_map_path.write_text(json.dumps(existing, indent=2))

    log = OUT / "gh_issues_created_batch2.json"
    log.write_text(json.dumps({"created": created, "failed": failed}, indent=2))

    if failed:
        print("Failed packages:")
        for pkg, err in failed:
            print(f"  {pkg}: {err}")

print("\n=== DONE ===")
print(f"Total audit issues on GitHub: {len(issues) + len(created)}")