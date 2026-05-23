#!/usr/bin/env python3
"""
Targeted close: audit stub issues only.
Patterns: 'readme missing', 'services/readme missing', 'docs_help missing',
          'incomplete implementation/docs'
No comments — just close + add label.
"""
import subprocess, json, time

REPO = "ArchiveHeritageGroup/heratio"
LABEL = "audit"
STUB_KEYWORDS = [
    "readme missing",
    "services/readme missing",
    "docs_help missing",
    "incomplete implementation",
]

def gh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    return r.stdout.strip(), r.stderr.strip(), r.returncode

# Fetch all open issues with 'audit' label
print("Fetching open [audit] issues...")
out, err, _ = gh(f'gh issue list --repo {REPO} --label "{LABEL}" --state open --json number,title --limit 300')
if not out:
    print("No output:", err)
    exit(1)

issues = json.loads(out)
print(f"Found {len(issues)} open [audit] issues")

# Filter to stub patterns
stubs = []
for issue in issues:
    title = issue['title'].lower()
    if any(kw in title for kw in STUB_KEYWORDS):
        stubs.append(issue)

print(f"Stub issues to close: {len(stubs)}")
for s in stubs:
    print(f"  #{s['number']} {s['title']}")

# Close stubs
closed = 0
skipped = 0
errors = 0

for issue in stubs:
    num = issue['number']
    title = issue['title']
    print(f"\n[{closed+1}/{len(stubs)}] Closing #{num}: {title}")

    # Close
    out, err, rc = gh(f'gh issue close {num} --repo {REPO}')
    if rc != 0 and "rate limit" in err.lower():
        print(f"  ⚠️  Rate limit on close, sleeping 60s...")
        time.sleep(60)
        out, err, rc = gh(f'gh issue close {num} --repo {REPO}')

    if rc == 0:
        print(f"  ✅ Closed")
        closed += 1
    else:
        print(f"  ❌ Error: {err}")
        errors += 1

    # Add resolution label
    gh(f'gh issue edit {num} --repo {REPO} --add-label audit:resolved')
    time.sleep(1.5)

print(f"\n\nDone: {closed} closed, {errors} errors")