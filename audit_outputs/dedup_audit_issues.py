#!/usr/bin/env python3
"""Close duplicate audit issues — keeps highest-numbered per exact title."""
import subprocess, json, time
from collections import defaultdict

REPO = "ArchiveHeritageGroup/heratio"

def gh(args):
    r = subprocess.run(args, capture_output=True, text=True, timeout=30)
    if r.returncode != 0:
        raise RuntimeError(r.stderr.strip())
    return r.stdout.strip()

# Fetch ALL open audit issues
print("Fetching all open audit issues...")
raw = gh(["gh", "issue", "list",
          "--repo", REPO,
          "--state", "open",
          "--label", "audit",
          "--limit", "300",
          "--json", "number,title,labels"])
issues = json.loads(raw)
print(f"Total open audit issues: {len(issues)}")

# Group by exact title
groups = defaultdict(list)
for issue in issues:
    groups[issue["title"]].append(issue["number"])

# For each group with duplicates, close all except the highest number
to_close = []
kept = []
for title, numbers in sorted(groups.items()):
    if len(numbers) > 1:
        numbers_sorted = sorted(numbers, reverse=True)
        keep = numbers_sorted[0]
        close = numbers_sorted[1:]
        to_close.extend(close)
        kept.append((title, keep, close))

print(f"Groups with duplicates: {len(kept)}")
print(f"Issues to close: {len(to_close)}")

for title, keep, close in kept:
    print(f"  KEEP #{keep}: {title[:60]}")
    for n in close:
        print(f"    CLOSE #{n}")

print(f"\nAbout to close {len(to_close)} issues. Continue? (Ctrl-C to abort)")
time.sleep(2)

closed = 0
failed = []
for n in sorted(to_close, reverse=True):
    title = next((t for t, ns in groups.items() if n in ns), "?")
    try:
        body = f"Duplicate of the kept issue for this package. Closing to deduplicate."
        r = subprocess.run(
            ["gh", "issue", "close", str(n), "--repo", REPO,
             "--comment", body],
            capture_output=True, text=True, timeout=30
        )
        if r.returncode == 0:
            closed += 1
            print(f"  Closed #{n}: {title[:60]}")
        else:
            failed.append((n, r.stderr.strip()[:120]))
            print(f"  FAILED #{n}: {r.stderr.strip()[:80]}")
    except Exception as e:
        failed.append((n, str(e)))
        print(f"  ERROR #{n}: {e}")
    time.sleep(0.5)

print(f"\nDone: closed={closed} failed={len(failed)}")
if failed:
    for n, err in failed:
        print(f"  FAILED: #{n} {err}")

with open("/tmp/dedup_summary.json", "w") as f:
    json.dump({"closed": closed, "failed": failed, "kept": [(t,n,list(c)) for t,n,c in kept]}, f, indent=2)
print("Summary written to /tmp/dedup_summary.json")