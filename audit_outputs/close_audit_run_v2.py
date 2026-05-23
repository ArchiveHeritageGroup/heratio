#!/usr/bin/env python3
"""Robust close of open issues labelled 'audit' in ArchiveHeritageGroup/heratio.

This is a safer, more verbose replacement for close_audit_run.py:
- Filters by label ("audit") instead of free-text search
- Supports --dry-run
- Exponential backoff on rate limits and transient failures
- Better logging of gh stderr and per-request errors
- Writes detailed JSON summary and per-run log

Usage:
  ./audit_outputs/close_audit_run_v2.py [--dry-run]

Note: This script runs `gh` (GitHub CLI) and assumes authentication is configured.
Do not run as part of an automated release without confirming dry-run first.
"""

import subprocess
import time
import json
import sys
import os
import argparse

REPO = "ArchiveHeritageGroup/heratio"
LOG_FILE = "/usr/share/nginx/heratio/audit_outputs/close_audit_run_v2_log.txt"
COMMENT_FILE = "/usr/share/nginx/heratio/audit_outputs/close_audit_comment.txt"
SUMMARY_FILE = "/usr/share/nginx/heratio/audit_outputs/close_audit_summary_v2.json"

# Config
MAX_ATTEMPTS = 5
BASE_SLEEP = 2  # seconds, exponential backoff base
RATE_LIMIT_SLEEP = 60  # seconds when rate limit detected

COMMENT = """## Resolution

Documentation has been reviewed and updated. This issue is now closed.

---
*Auto-closed by audit resolution script (v2)*"""


def log(msg):
    ts = time.strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{ts}] {msg}"
    print(line, flush=True)
    try:
        with open(LOG_FILE, "a") as f:
            f.write(line + "\n")
    except Exception:
        pass


def gh(*args):
    cmd = ["gh", "--repo", REPO] + list(args)
    proc = subprocess.run(cmd, capture_output=True, text=True)
    return proc.returncode, proc.stdout, proc.stderr


def write_comment_file():
    try:
        with open(COMMENT_FILE, "w") as f:
            f.write(COMMENT)
    except Exception as e:
        log(f"ERROR writing comment file: {e}")
        sys.exit(1)


def run_with_backoff(action, *args, action_name=None):
    """Run action(*args) with retries and exponential backoff.
    action must be a callable returning (ok, rc, out, err) where ok is bool.
    """
    for attempt in range(1, MAX_ATTEMPTS + 1):
        try:
            ok, rc, out, err = action(*args)
        except Exception as e:
            ok = False
            rc = 999
            out = ""
            err = str(e)

        if ok:
            return True, rc, out, err

        # Inspect stderr for rate-limit indicators
        stderr_l = (err or "").lower()
        if "rate limit" in stderr_l or "403" in stderr_l or "abuse" in stderr_l:
            log(f"  ⚠ Detected rate limit or permission issue (attempt {attempt}/{MAX_ATTEMPTS}). stderr: {err.strip()}")
            sleep = RATE_LIMIT_SLEEP
        else:
            sleep = BASE_SLEEP * (2 ** (attempt - 1))

        log(f"  ✗ action failed (attempt {attempt}/{MAX_ATTEMPTS}), rc={rc}. Sleeping {sleep}s before retry. stderr: {err.strip()[:300]}")
        time.sleep(sleep)

    return False, rc, out, err


# GH action wrappers that return (ok, rc, out, err)

def action_list_issues():
    # use --label audit to avoid false matches; json output for parsing
    rc, out, err = gh("issue", "list", "--state", "open", "--label", "audit", "--json", "number,title")
    ok = rc == 0
    return ok, rc, out, err


def action_add_comment(issue_num, comment_file):
    rc, out, err = gh("issue", "comment", str(issue_num), "--body-file", comment_file)
    ok = rc == 0
    return ok, rc, out, err


def action_add_label(issue_num, label):
    rc, out, err = gh("issue", "edit", str(issue_num), "--add-label", label)
    ok = rc == 0
    return ok, rc, out, err


def action_close_issue(issue_num):
    rc, out, err = gh("issue", "close", str(issue_num))
    ok = rc == 0
    return ok, rc, out, err


def parse_issues_from_list(out):
    try:
        data = json.loads(out)
        issues = [(int(item["number"]), item.get("title", "")) for item in data]
        return issues
    except Exception as e:
        log(f"ERROR parsing issues JSON: {e}")
        return []


def main(dry_run=False):
    write_comment_file()
    # init log
    try:
        open(LOG_FILE, "w").close()
    except Exception:
        pass

    log("Starting audit-close run (v2)")

    ok, rc, out, err = run_with_backoff(lambda: action_list_issues())
    if not ok:
        log(f"ERROR fetching issue list: rc={rc} stderr={err}")
        sys.exit(1)

    issues = parse_issues_from_list(out)
    log(f"Found {len(issues)} open issues with label 'audit'")

    closed = 0
    failed = []

    for idx, (num, title) in enumerate(sorted(issues)):
        short_title = title[:80] + ("…" if len(title) > 80 else "")
        log(f"[{idx+1}/{len(issues)}] #{num} — {short_title}")

        if dry_run:
            log("  (dry-run) would add comment, add label audit:resolved, and close")
            continue

        # 1. Add comment
        ok, rc, out, err = run_with_backoff(action_add_comment, num, COMMENT_FILE, action_name="comment")
        if not ok:
            log(f"  ✗ failed to add comment to #{num}")
            failed.append((num, title, "comment", rc, err))
            continue

        # 2. Add resolved label
        ok, rc, out, err = run_with_backoff(action_add_label, num, "audit:resolved", action_name="label")
        if not ok:
            log(f"  ✗ failed to add label to #{num}")
            failed.append((num, title, "label", rc, err))
            continue

        # 3. Close issue
        ok, rc, out, err = run_with_backoff(action_close_issue, num, action_name="close")
        if not ok:
            log(f"  ✗ failed to close #{num}")
            failed.append((num, title, "close", rc, err))
            continue

        closed += 1
        log(f"  ✅ closed #{num}")
        # be kind to the API
        time.sleep(1)

    summary = {
        "closed": closed,
        "failed": len(failed),
        "failed_issues": [
            {"number": f[0], "title": f[1], "step": f[2], "rc": f[3], "stderr": f[4][:1000] if f[4] else None}
            for f in failed
        ],
        "dry_run": dry_run,
        "timestamp": time.time()
    }

    try:
        with open(SUMMARY_FILE, "w") as f:
            json.dump(summary, f, indent=2)
        log(f"Summary written to {SUMMARY_FILE}")
    except Exception as e:
        log(f"ERROR writing summary: {e}")

    log("Finished run")
    if failed:
        log("Some issues failed; check the log and summary JSON for details")


if __name__ == '__main__':
    p = argparse.ArgumentParser()
    p.add_argument('--dry-run', action='store_true', help='Do not modify GitHub; only show what would happen')
    args = p.parse_args()
    main(dry_run=args.dry_run)
