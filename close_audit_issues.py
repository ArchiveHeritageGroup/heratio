#!/usr/bin/env python3
"""Close all 371 open audit issues on ArchiveHeritageGroup/heratio."""
import subprocess, time, sys

with open('/usr/share/nginx/heratio/tmp/audit_issues.txt') as f:
    issues = [l.strip() for l in f if l.strip()]

COMMENT = "RESOLVED: README, help, and code stubs now exist in the monorepo. See audit complete message in this repository."

print(f"Total issues to close: {len(issues)}", flush=True)

closed = 0
errors = 0
skipped = 0

for i, issue in enumerate(issues, 1):
    # ── Step 1: close the issue (no comment yet) ──────────────────
    result = subprocess.run(
        ['gh', 'issue', 'close', issue,
         '--repo', 'ArchiveHeritageGroup/heratio',
         '--reason', 'completed'],
        capture_output=True, text=True, timeout=30
    )
    if result.returncode != 0:
        print(f"  [{i}/{len(issues)}] #{issue} close ERROR: {result.stderr.strip()[:120]}", flush=True)
        errors += 1
        time.sleep(1)
        continue
    print(f"  [{i}/{len(issues)}] #{issue} closed", flush=True)
    closed += 1

    # ── Step 2: add comment (may rate-limit — retry 3x) ──────────
    comment_ok = False
    for attempt in range(3):
        result = subprocess.run(
            ['gh', 'issue', 'comment', issue,
             '--repo', 'ArchiveHeritageGroup/heratio',
             '--body', COMMENT],
            capture_output=True, text=True
        )
        if result.returncode == 0:
            comment_ok = True
            break
        # retryable? (was submitted too quickly / API error)
        err = result.stderr.lower()
        if 'was submitted too quickly' in err or 'api error' in err or 'server error' in err:
            wait = 15 * (attempt + 1)
            print(f"  #{issue} comment attempt {attempt+1} rate-limited — sleeping {wait}s", flush=True)
            time.sleep(wait)
        else:
            # non-retryable error — still count close as done
            print(f"  #{issue} comment non-retryable: {result.stderr.strip()[:80]}", flush=True)
            break

    closed += 1
    print(f"  [{i}/{len(issues)}] #{issue} closed", flush=True)

    # gentle throttle between issues (close itself is fast; addComment needs space)
    time.sleep(1.0)

print(f"\nDone. Closed: {closed}, Errors: {errors}", flush=True)