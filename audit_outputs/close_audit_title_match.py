#!/usr/bin/env python3
"""Close issues whose title contains "[audit]" in ArchiveHeritageGroup/heratio.

- Finds open issues where the title contains the string "[audit]" (case-insensitive).
- Skips a safety whitelist: [143,144,145,146,644].
- For each matched issue: posts a resolution comment, adds label "audit:resolved" (creates it if missing), then closes the issue.
- Implements retries with exponential backoff and writes a run log + JSON summary.

Usage:
  ./audit_outputs/close_audit_title_match.py [--dry-run]

CAUTION: This script will modify issues unless --dry-run is used. It uses the GitHub CLI (gh) and assumes auth is configured.
"""

import subprocess
import time
import json
import sys
import os
import argparse

REPO = "ArchiveHeritageGroup/heratio"
LOG_FILE = "/usr/share/nginx/heratio/audit_outputs/close_audit_title_match_log.txt"
SUMMARY_FILE = "/usr/share/nginx/heratio/audit_outputs/close_audit_title_match_summary.json"
COMMENT_FILE = "/usr/share/nginx/heratio/audit_outputs/close_audit_comment.txt"

# Safety: do NOT close these issues even if they match
WHITELIST = {143,144,145,146,644}

# Config
MAX_ATTEMPTS = 5
BASE_SLEEP = 2
RATE_LIMIT_SLEEP = 60

# Ensure comment text exists
DEFAULT_COMMENT = """## Resolution

Documentation has been reviewed and updated. This issue is now closed.

---
*Auto-closed by audit resolution script (title-match run)*"""


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


def ensure_comment_file():
    if not os.path.exists(COMMENT_FILE):
        with open(COMMENT_FILE, "w") as f:
            f.write(DEFAULT_COMMENT)


def run_with_backoff(func, *args):
    for attempt in range(1, MAX_ATTEMPTS+1):
        try:
            rc, out, err = func(*args)
        except Exception as e:
            rc = 999
            out = ""
            err = str(e)

        ok = (rc == 0)
        if ok:
            return True, rc, out, err

        stderr_l = (err or "").lower()
        if "rate limit" in stderr_l or "403" in stderr_l or "abuse" in stderr_l:
            sleep = RATE_LIMIT_SLEEP
            log(f"  Detected rate limit/permission error: attempt {attempt}, sleeping {sleep}s. stderr: {err.strip()[:200]}")
        else:
            sleep = BASE_SLEEP * (2 ** (attempt-1))
            log(f"  Action failed (attempt {attempt}), rc={rc}. Sleeping {sleep}s. stderr: {err.strip()[:200]}")

        time.sleep(sleep)

    return False, rc, out, err


def list_open_issues():
    rc, out, err = gh("issue", "list", "--state", "open", "--json", "number,title")
    return rc, out, err


def action_comment(issue_num):
    return gh("issue", "comment", str(issue_num), "--body-file", COMMENT_FILE)


def action_add_label(issue_num, label):
    return gh("issue", "edit", str(issue_num), "--add-label", label)


def action_close(issue_num):
    return gh("issue", "close", str(issue_num))


def create_label_if_missing(label):
    # try to create the label; ignore if exists (gh returns non-zero)
    rc, out, err = gh("label", "create", label, "--color", "ffcc00", "--description", "Resolved by audit script")
    return rc, out, err


def parse_issues(out):
    try:
        data = json.loads(out)
        return [(int(item['number']), item.get('title','')) for item in data]
    except Exception as e:
        log(f"ERROR parsing issues JSON: {e}")
        return []


def main(dry_run=False):
    ensure_comment_file()
    try:
        open(LOG_FILE, 'w').close()
    except Exception:
        pass

    log("Starting title-match audit-close run")

    ok, out_rc, out_err = True, None, None
    ok, rc, out, err = run_with_backoff(lambda: list_open_issues())
    if not ok:
        log(f"ERROR: failed to list open issues. rc={rc} stderr={err}")
        sys.exit(1)

    issues = parse_issues(out)
    matches = []
    for num, title in issues:
        if num in WHITELIST:
            continue
        if '[audit]' in title.lower() or 'audit' in title.lower() and title.strip().startswith('['):
            matches.append((num, title))

    log(f"Found {len(matches)} matching open issues by title (excluding whitelist)")

    # create resolved label if running non-dry-run
    if not dry_run:
        rc, o, e = create_label_if_missing('audit:resolved')
        if rc == 0:
            log("Created label 'audit:resolved' or it did not exist previously")
        else:
            log("Label creation attempted; it may already exist (or creation failed). Proceeding anyway.")

    closed = 0
    failed = []

    for idx, (num, title) in enumerate(sorted(matches)):
        short = title[:80] + ('…' if len(title)>80 else '')
        log(f"[{idx+1}/{len(matches)}] #{num} — {short}")
        if dry_run:
            log("  (dry-run) would comment, add label audit:resolved, and close")
            continue

        ok, rc, out, err = run_with_backoff(lambda n: action_comment(n), num)
        if not ok:
            log(f"  Failed to add comment to #{num}")
            failed.append((num,title,'comment',rc,err))
            continue

        ok, rc, out, err = run_with_backoff(lambda n: action_add_label(n, 'audit:resolved'), num)
        if not ok:
            log(f"  Failed to add label to #{num}")
            failed.append((num,title,'label',rc,err))
            continue

        ok, rc, out, err = run_with_backoff(lambda n: action_close(n), num)
        if not ok:
            log(f"  Failed to close #{num}")
            failed.append((num,title,'close',rc,err))
            continue

        closed += 1
        log(f"  ✅ closed #{num}")
        time.sleep(1)

    summary = {
        'closed': closed,
        'failed': len(failed),
        'failed_issues': [
            {'number': f[0], 'title': f[1], 'step': f[2], 'rc': f[3], 'stderr': f[4][:1000] if f[4] else None}
            for f in failed
        ],
        'dry_run': dry_run,
        'timestamp': time.time()
    }

    try:
        with open(SUMMARY_FILE, 'w') as f:
            json.dump(summary, f, indent=2)
        log(f"Summary written to {SUMMARY_FILE}")
    except Exception as e:
        log(f"ERROR writing summary: {e}")

    log("Finished run")
    if failed:
        log("Some issues failed; inspect the log and summary JSON")


if __name__ == '__main__':
    p = argparse.ArgumentParser()
    p.add_argument('--dry-run', action='store_true')
    args = p.parse_args()
    main(dry_run=args.dry_run)
