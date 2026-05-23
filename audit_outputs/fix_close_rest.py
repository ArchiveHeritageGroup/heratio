#!/usr/bin/env python3
"""Fix and close audit issues via REST API (bypasses GQL rate limit)."""
import json, subprocess, time
from pathlib import Path

OUT = Path("/usr/share/nginx/heratio/audit_outputs")
REPO = "ArchiveHeritageGroup/heratio"

def gh(*args):
    r = subprocess.run(["gh"] + list(args), capture_output=True, text=True, timeout=30)
    return r.stdout, r.stderr, r.returncode

# ── 1. Rate limit ─────────────────────────────────────────────────────────────
stdout, stderr, code = gh("api", "rate_limit", "--jq", ".rate")
if code == 0:
    rl = json.loads(stdout)
    print(f"Rate limit: {rl['remaining']}/{rl['limit']}, reset: {rl['reset']}")
else:
    print(f"Rate limit check failed: {stderr[:200]}")

# ── 2. Fetch all audit issues via REST (no --paginate, manual pagination) ─────
print("\nFetching audit issues (REST, page by page)...")
issues = []
for page in range(1, 6):
    out, err, code = gh(
        "api", "repos", REPO, "issues",
        "-H", "Accept: application/vnd.github+json",
        "-f", "labels=audit",
        "-f", "state=all",
        "-f", f"page={page}",
        "-f", "per_page=100"
    )
    if code != 0:
        print(f"  Page {page} failed: {err[:200]}")
        break
    batch = json.loads(out)
    issues.extend(batch)
    print(f"  Page {page}: +{len(batch)} = {len(issues)} total")
    if len(batch) < 100:
        break
    time.sleep(1)

open_i  = [i for i in issues if i["state"] == "open"]
closed_i = [i for i in issues if i["state"] == "closed"]
nums = sorted(i["number"] for i in issues)
print(f"\nTotal: {len(issues)} ({len(open_i)} open, {len(closed_i)} closed)")
if nums:
    print(f"Range: #{nums[0]} – #{nums[-1]}")

(OUT / "all_audit_issues.json").write_text(json.dumps(issues, indent=2))

# Print first 25
for i in sorted(issues, key=lambda x: x["number"])[:25]:
    print(f"  #{i['number']} [{i['state']}] {i['title'][:65]}")

# ── 3. Packages still needing issues ──────────────────────────────────────────
raw = json.loads((OUT / "full_audit_111_raw.json").read_text())
incomplete = [r for r in raw if r["status"] != "FULLY IMPLEMENTED"]

already_has = set()
for r in incomplete:
    pkg = r["package"]
    for issue in issues:
        if f"[audit] {pkg}:" in issue["title"]:
            already_has.add(pkg)
            break

still_need = [r for r in incomplete if r["package"] not in already_has]
print(f"\nStill need issues: {len(still_need)}")
for r in still_need:
    print(f"  {r['package']}: {r['missing']}")

# ── 4. Create remaining issues (REST POST) ────────────────────────────────────
created, failed = [], []
for idx, r in enumerate(still_need):
    pkg, missing = r["package"], r["missing"]
    title = f"[audit] {pkg}: {'/'.join(missing)} missing"
    body_file = OUT / f"issue_body_{pkg}.txt"

    if not body_file.exists():
        body = f"## Audit finding for `{pkg}`\n\n**Missing:** {', '.join(missing)}\n\n| Item | Found |\n|------|--------|\n" + "\n".join(
            f"| {m} | ❌ |" for m in missing
        )
        body_file.write_text(body)

    out, err, code = gh(
        "api", "-X", "POST", f"repos/{REPO}/issues",
        "-f", f"title={title}",
        "-f", f"body=@{body_file}",
        "-f", "labels=['audit','audit:missing-code']"
    )

    if code == 0:
        data = json.loads(out)
        gh_num = data["number"]
        created.append({"package": pkg, "url": data["html_url"], "gh": gh_num})
        print(f"  [{idx+1}/{len(still_need)}] #{gh_num} CREATED: {title[:60]}")
    else:
        failed.append((pkg, err[:120]))
        print(f"  [{idx+1}/{len(still_need)}] FAILED: {pkg} — {err[:80]}")

    time.sleep(0.8)

# ── 5. Save maps ──────────────────────────────────────────────────────────────
issue_map = {}
for c in created:
    issue_map[c["package"]] = c["gh"]

map_path = OUT / "gh_issue_map.json"
if map_path.exists():
    m = json.loads(map_path.read_text())
    m.update(issue_map)
else:
    m = issue_map
map_path.write_text(json.dumps(m, indent=2))
(OUT / "gh_issues_created_batch2.json").write_text(json.dumps({"created": created, "failed": failed}, indent=2))

print(f"\nDone. Created: {len(created)}, Failed: {len(failed)}")