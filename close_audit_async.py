#!/usr/bin/env python3
import asyncio, subprocess, time, json

REPO = 'ArchiveHeritageGroup/heratio'
BODY = 'Audit complete — package README is present on main branch (verified). Closing.'
WORKERS = 10

def gh_sync(cmd, timeout=25):
    try:
        r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)
        ok = r.returncode == 0
        combined = (r.stdout or '') + (r.stderr or '')
        if 'rate limit' in combined.lower() or r.returncode == 403:
            return False, 'rate_limited'
        return ok, r.stderr.strip()[:80]
    except subprocess.TimeoutExpired:
        return False, 'timeout'

async def close_one(sem, num, results):
    async with sem:
        errors = []
        cmd1 = f'gh api repos/{REPO}/issues/{num}/comments -X POST -f body="{BODY}"'
        ok, err = await asyncio.to_thread(gh_sync, cmd1)
        if not ok: errors.append(f'comment:{err}')
        await asyncio.sleep(2)
        cmd2 = f'gh api repos/{REPO}/issues/{num} -X PATCH -f state=closed -f labels=["audit:resolved"]'
        ok, err = await asyncio.to_thread(gh_sync, cmd2)
        if not ok: errors.append(f'patch:{err}')
        results[num] = errors

async def main():
    with open('/tmp/audit_numbers.json') as f:
        data = json.load(f)
    all_numbers = data.get('ahg',[]) + data.get('dahg_new',[]) + data.get('pre_dahg',[])
    total = len(all_numbers)
    print(f'Total: {total} issues')
    results = {}
    sem = asyncio.Semaphore(WORKERS)
    for label, nums in [('ahg-',data.get('ahg',[])),('dahg-',data.get('dahg_new',[])),('pre_dahg-',data.get('pre_dahg',[]))]:
        print(f'Group {label}: {len(nums)} issues')
        tasks = [close_one(sem, num, results) for num in nums]
        await asyncio.gather(*tasks, return_exceptions=True)
        print(f'  {label} done. Failures so far: {sum(1 for v in results.values() if v)}')
    total_ok = sum(1 for v in results.values() if not v)
    total_fail = sum(1 for v in results.values() if v)
    print(f'TOTAL OK={total_ok} FAIL={total_fail}')
    fails = {num:errs for num,errs in results.items() if errs}
    if fails: print(f'FAILURES: {fails}')
    with open('/tmp/audit_results.json','w') as f: json.dump({'ok':total_ok,'fail':total_fail,'failures':fails},f)
    print('Done')

asyncio.run(main())
