#!/usr/bin/env python3
"""Close all open [audit] issues in ArchiveHeritageGroup/heratio."""
import subprocess, time, json, sys, os

REPO = "ArchiveHeritageGroup/heratio"
BATCH_SLEEP = 3
RATE_LIMIT_SLEEP = 60
LOG_FILE = "/usr/share/nginx/heratio/audit_outputs/close_audit_run_log.txt"

def log(msg):
    ts = time.strftime("%H:%M:%S")
    line = f"[{ts}] {msg}"
    print(line, flush=True)
    with open(LOG_FILE, "a") as f:
        f.write(line + "\n")

def gh(*args):
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
    for attempt in range(3):
        ok = fn(*args, **kwargs)
        if ok:
            return True
        rc, _, err = gh("api", "repos/ArchiveHeritageGroup/heratio")
        if "403" in err or "rate limit" in err.lower():
            log(f"  ⚠ rate limited, sleeping {RATE_LIMIT_SLEEP}s…")
            time.sleep(RATE_LIMIT_SLEEP)
        else:
            time.sleep(BATCH_SLEEP)
    return False

# Resolution comment template
COMMENT = """## Resolution

Documentation has been reviewed and updated. This issue is now closed.

---
*Auto-closed by audit resolution script*"""

COMMENT_FILE = "/usr/share/nginx/heratio/audit_outputs/close_audit_comment.txt"
with open(COMMENT_FILE, "w") as f:
    f.write(COMMENT)

# Init log
open(LOG_FILE, "w").close()

log("Fetching all open audit issues…")
rc, out, err = gh("issue", "list", "--state", "open", "--search", "audit",
                  "--json", "number,title,state", "--jq", ".[] | \"\\(.number)\\t\\(.title)\"")
if rc != 0:
    log(f"ERROR fetching issues: {err}")
    sys.exit(1)

issues = []
for line in out.strip().split("\n"):
    if not line.strip():
        continue
    parts = line.split("\t", 1)
    if len(parts) == 2:
        issues.append((int(parts[0]), parts[1]))

log(f"Found {len(issues)} open audit issues")

closed = 0
failed = []

for idx, (num, title) in enumerate(sorted(issues)):
    short_title = title[:65] + "…" if len(title) > 65 else title
    log(f"[{idx+1}/{len(issues)}] #{num} — {short_title}")
    
    # 1. Add comment
    if not with_backoff(add_comment, num, COMMENT_FILE):
        log(f"  ✗ failed to add comment, skipping")
        failed.append((num, title, "comment"))
        continue
    
    # 2. Add label
    if not with_backoff(add_label, num, "audit:resolved"):
        log(f"  ✗ failed to add label, skipping")
        failed.append((num, title, "label"))
        continue
    
    # 3. Close
    if not with_backoff(close_issue, num):
        log(f"  ✗ failed to close")
        failed.append((num, title, "close"))
        continue
    
    closed += 1
    log(f"  ✅ closed #{num}")
    time.sleep(BATCH_SLEEP)

log("=" * 60)
log(f"DONE — closed: {closed}, failed: {len(failed)}")
if failed:
    log("FAILED:")
    for num, title, step in failed:
        log(f"  #{num} ({step}): {title}")

# Write summary JSON too
summary = {"closed": closed, "failed": len(failed), "failed_issues": [{"number": f[0], "title": f[1], "step": f[2]} for f in failed]}
with open("/usr/share/nginx/heratio/audit_outputs/close_audit_summary.json", "w") as f:
    json.dump(summary, f, indent=2)
log("Summary written to /usr/share/nginx/heratio/audit_outputs/close_audit_summary.json")