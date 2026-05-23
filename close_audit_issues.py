#!/usr/bin/env python3
"""Close all 371 open audit issues on ArchiveHeritageGroup/heratio."""
import subprocess, time, sys

with open('/usr/share/nginx/heratio/tmp/audit_issues.txt') as f:
    issues = [int(l.strip()) for l in f if l.strip()]

print(f"Total issues to close: {len(issues)}", flush=True)

closed = 0
errors = 0

for i, issue in enumerate(issues, 1):
    # ── Step 1: close the issue ─────────────────────────────────
    result = subprocess.run(
        ['gh', 'issue', 'close', str(issue),
         '--repo', 'ArchiveHeritageGroup/heratio',
         '--reason', 'completed'],
        capture_output=True, text=True, timeout=30
    )
    if result.returncode != 0:
        err = result.stderr.lower()
        # retry on rate-limit (wait 15s then retry once)
        if 'was submitted too quickly' in err or 'api error' in err or 'rate limit' in err:
            print(f"  [{i}/{len(issues)}] #{issue} rate-limited on close — retrying in 20s", flush=True)
            time.sleep(20)
            result = subprocess.run(
                ['gh', 'issue', 'close', str(issue),
                 '--repo', 'ArchiveHeritageGroup/heratio',
                 '--reason', 'completed'],
                capture_output=True, text=True, timeout=30
            )
        if result.returncode != 0:
            print(f"  [{i}/{len(issues)}] #{issue} close ERROR: {result.stderr.strip()[:120]}", flush=True)
            errors += 1
            time.sleep(2)
            continue

    # ── Step 2: add audit:resolved label ─────────────────────────
    for attempt in range(2):
        result = subprocess.run(
            ['gh', 'issue', 'edit', str(issue),
             '--repo', 'ArchiveHeritageGroup/heratio',
             '--add-label', 'audit:resolved'],
            capture_output=True, text=True, timeout=30
        )
        if result.returncode == 0:
            break
        err = result.stderr.lower()
        if 'was submitted too quickly' in err or 'api error' in err or 'rate limit' in err:
            wait = 15 * (attempt + 1)
            print(f"  #{issue} label attempt {attempt+1} rate-limited — sleeping {wait}s", flush=True)
            time.sleep(wait)
        # non-retryable label error: just skip, close is done

    closed += 1
    print(f"  [{i}/{len(issues)}] #{issue} closed ✓", flush=True)

    # gentle throttle between issues
    time.sleep(1.0)

print(f"\nDone. Closed: {closed}, Errors: {errors}", flush=True)