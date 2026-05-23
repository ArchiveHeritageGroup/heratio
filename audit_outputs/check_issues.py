import json, subprocess, time, sys
from pathlib import Path

REPO = "ArchiveHeritageGroup/heratio"
OUT = Path("/usr/share/nginx/heratio/audit_outputs")

def gh(*args):
    r = subprocess.run(["gh"] + list(args), capture_output=True, text=True, timeout=30)
    return r.stdout, r.stderr, r.returncode

# Check rate limit
out, err, code = gh("api", "rate_limit", "--json", "rate")
if code == 0:
    rl = json.loads(out)
    remaining = rl["resources"]["core"]["remaining"]
    reset_ts = rl["resources"]["core"]["reset"]
    print(f"Rate limit: {remaining} remaining, resets at {reset_ts}")
else:
    print(f"Rate limit check failed: {err}")
    remaining = 0

# Get all audit issues
out, err, code = gh("issue", "list", "--repo", REPO, "--label", "audit", "--state", "all", "--limit", "200", "--json", "number,title,state")
if code != 0:
    print(f"Failed to list issues: {err}")
    sys.exit(1)

issues = json.loads(out)
print(f"\nTotal audit issues found: {len(issues)}")
open_issues = [i for i in issues if i["state"] == "OPEN"]
closed_issues = [i for i in issues if i["state"] == "CLOSED"]
print(f"Open: {len(open_issues)}")
print(f"Closed: {len(closed_issues)}")

# Save full list
(OUT / "all_audit_issues.json").write_text(json.dumps(issues, indent=2))
print(f"Saved to all_audit_issues.json")

# Build audit number ranges
audit_open = sorted([i["number"] for i in open_issues])
print(f"\nAudit issue numbers (open): {audit_open[0] if audit_open else 'none'} - {audit_open[-1] if audit_open else 'none'} ({len(audit_open)} total)")