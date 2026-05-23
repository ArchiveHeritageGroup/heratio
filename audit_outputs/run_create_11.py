#!/usr/bin/env python3
"""Create the 11 remaining GH issues via REST API with JSON input."""
import subprocess, json, time

REPO = "ArchiveHeritageGroup/heratio"
OUT = "/usr/share/nginx/heratio/audit_outputs"

packages = [
    "ahg-share-link", "ahg-static-page", "ahg-statistics", "ahg-storage-manage",
    "ahg-term-taxonomy", "ahg-theme-b5", "ahg-translation", "ahg-user-manage",
    "ahg-vendor", "ahg-version-control", "ahg-workflow",
]

created = []
failed = []

for pkg in packages:
    body_file = f"{OUT}/issue_body_{pkg}.txt"
    try:
        body = open(body_file).read()
    except Exception as e:
        failed.append((pkg, f"No body file: {e}"))
        print(f"FAIL {pkg}: no body file")
        continue

    payload = {
        "title": f"[Audit] {pkg}: audit missing items",
        "body": body,
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
        created.append({"package": pkg, "number": num, "url": url})
        print(f"OK  #{num}: {pkg}")
    else:
        err = result.stderr.strip()[:300]
        failed.append((pkg, err))
        print(f"FAIL {pkg}: {err}")
    
    time.sleep(2)

print(f"\nCreated: {len(created)}, Failed: {len(failed)}")
for c in created:
    print(f"  #{c['number']}: {c['package']}")
for p, e in failed:
    print(f"  FAILED: {p}: {e}")

with open(f"{OUT}/gh_issues_created_11.json", "w") as f:
    json.dump({"created": created, "failed": failed}, f, indent=2)
print("Saved gh_issues_created_11.json")