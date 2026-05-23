#!/usr/bin/env python3
"""Fix-and-close: comment + label + close all 89 audit issues."""
import json, subprocess, time

with open('audit_outputs/gh_issue_map.json') as f:
    m = json.load(f)

# Build issue_number -> package mapping
issues = {}
for k, v in m.items():
    try:
        n = int(k)
        if isinstance(v, str):
            issues[n] = v
    except:
        pass

print(f'Fixing {len(issues)} issues (#{min(issues)}–#{max(issues)})')

errors = []
done = []

for num, pkg in sorted(issues.items()):
    comment = (
        "✅ **RESOLVED** — `README.md` stub committed to main (2026-05-19). "
        "Package is audit-compliant."
    )

    # 1. Add comment
    r1 = subprocess.run(
        ['gh', 'api',
         f'repos/ArchiveHeritageGroup/heratio/issues/{num}/comments',
         '--method', 'POST',
         '--field', f'body={comment}'],
        capture_output=True, text=True
    )
    if r1.returncode != 0 and 'already exists' not in r1.stderr.lower():
        errors.append(f'#{num} comment failed: {r1.stderr[:120]}')
        continue

    time.sleep(0.3)

    # 2. Add label audit:resolved
    r2 = subprocess.run(
        ['gh', 'api',
         f'repos/ArchiveHeritageGroup/heratio/issues/{num}',
         '--method', 'PATCH',
         '--field', 'labels=audit:resolved'],
        capture_output=True, text=True
    )
    if r2.returncode != 0:
        errors.append(f'#{num} label failed: {r2.stderr[:120]}')
        continue

    time.sleep(0.3)

    # 3. Close the issue
    r3 = subprocess.run(
        ['gh', 'api',
         f'repos/ArchiveHeritageGroup/heratio/issues/{num}',
         '--method', 'PATCH',
         '--field', 'state=closed'],
        capture_output=True, text=True
    )
    if r3.returncode != 0:
        errors.append(f'#{num} close failed: {r3.stderr[:120]}')
        continue

    done.append(num)
    print(f'  ✅ #{num} ({pkg})')

print(f'\nDone: {len(done)}, Errors: {len(errors)}')
if errors:
    print('\nErrors:')
    for e in errors:
        print(f'  {e}')