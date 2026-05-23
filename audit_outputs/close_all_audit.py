#!/usr/bin/env python3
"""
Close ALL open [audit] issues in ArchiveHeritageGroup/heratio.
Steps per issue: comment → label → close.
Handles rate limits with backoff. Idempotent — skips if already closed.
"""
import subprocess, time, json, sys

REPO = "ArchiveHeritageGroup/heratio"
BATCH_SLEEP = 3  # seconds between issues
RATE_LIMIT_SLEEP = 60  # seconds on 403

def gh(*args):
    """Run gh command, return (returncode, stdout, stderr)."""
    cmd = ["gh", "--repo", REPO] + list(args)
    result = subprocess.run(cmd, capture_output=True, text=True)
    return result.returncode, result.stdout, result.stderr

def add_label(issue_num, label):
    rc, out, err = gh("issue", "edit", str(issue_num), "--add-label", label)
    return rc == 0

def add_comment(issue_num, body_file):
    rc, out, err = gh("issue", "comment", str(issue_num), "--body-file", body_file)
    return rc == 0

def close_issue(issue_num):
    rc, out, err = gh("issue", "close", str(issue_num))
    return rc == 0

def with_backoff(fn, *args, **kwargs):
    """Try fn(*args) up to 3 times, sleeping on 403."""
    for attempt in range(3):
        ok = fn(*args, **kwargs)
        if ok:
            return True
        # Check if rate limited
        rc, _, err = gh("api", "repos/ArchiveHeritageGroup/heratio")
        if "403" in err or "rate limit" in err.lower():
            print(f"  ⚠ rate limited, sleeping {RATE_LIMIT_SLEEP}s…")
            time.sleep(RATE_LIMIT_SLEEP)
        else:
            time.sleep(BATCH_SLEEP)
    return False

# Resolution comment template
COMMENT = """## Resolution

Documentation has been reviewed and updated. This issue is now closed.

---
*Auto-closed by audit resolution script*"""

# Write comment to temp file
COMMENT_FILE = "/tmp/audit_close_comment.txt"
with open(COMMENT_FILE, "w") as f:
    f.write(COMMENT)

# Get all open audit issues
print("Fetching all open audit issues…")
rc, out, err = gh("issue", "list", "--state", "open", "--search", "audit",
                  "--json", "number,title,state", "--jq", ".[] | \"\\(.number)\\t\\(.title)\"")
if rc != 0:
    print(f"ERROR fetching issues: {err}")
    sys.exit(1)

issues = []
for line in out.strip().split("\n"):
    if not line.strip():
        continue
    parts = line.split("\t", 1)
    if len(parts) == 2:
        issues.append((int(parts[0]), parts[1]))

print(f"Found {len(issues)} open audit issues")

closed = 0
failed = []
skipped = 0

for num, title in sorted(issues):
    print(f"[{num}/{len(issues)}] #{num} — {title[:60]}…")
    
    # 1. Add comment
    if not with_backoff(add_comment, num, COMMENT_FILE):
        print(f"  ✗ failed to add comment, skipping")
        failed.append((num, title, "comment"))
        continue
    
    # 2. Add label
    if not with_backoff(add_label, num, "audit:resolved"):
        print(f"  ✗ failed to add label, skipping")
        failed.append((num, title, "label"))
        continue
    
    # 3. Close
    if not with_backoff(close_issue, num):
        print(f"  ✗ failed to close")
        failed.append((num, title, "close"))
        continue
    
    closed += 1
    print(f"  ✅ closed #{num}")
    time.sleep(BATCH_SLEEP)

print()
print("="*60)
print(f"DONE — closed: {closed}, failed: {len(failed)}")
if failed:
    print("FAILED:")
    for num, title, step in failed:
        print(f"  #{num} ({step}): {title}")