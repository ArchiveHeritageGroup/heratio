"""Run the RiC workload against the test Fuseki and QLever; stdlib only.

usage: python3 run_bench.py <fuseki_url> <qlever_url> <out.json>
Per query: result-row agreement, then cold (first) run, then 10 warm runs.
Then throughput at 1/4/16 concurrent clients over the whole mix for 30s each.
"""
import json, statistics, sys, time, urllib.parse, urllib.request
from concurrent.futures import ThreadPoolExecutor
from workload import QUERIES

TIMEOUT = 300


def run(url, q):
    data = urllib.parse.urlencode({"query": q}).encode()
    req = urllib.request.Request(url, data=data, headers={
        "Accept": "application/sparql-results+json",
        "Content-Type": "application/x-www-form-urlencoded"})
    t = time.perf_counter()
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            body = json.load(r)
        dt = time.perf_counter() - t
        return dt, body["results"]["bindings"], None
    except Exception as e:  # keep going; record the failure
        return time.perf_counter() - t, None, f"{type(e).__name__}: {str(e)[:160]}"


def fingerprint(rows):
    """Order-independent comparison of result rows (bnode labels ignored)."""
    if rows is None:
        return None
    out = []
    for b in rows:
        out.append(tuple(sorted((k, v["type"], "" if v["type"] == "bnode" else v["value"])
                                for k, v in b.items())))
    return sorted(out)


def pct(xs, p):
    xs = sorted(xs)
    return xs[min(len(xs) - 1, int(round(p / 100 * (len(xs) - 1))))]


def per_query(engines):
    res = {}
    for qid, src, q in QUERIES:
        res[qid] = {"source": src}
        fps = {}
        for name, url in engines.items():
            cold, rows, err = run(url, q)
            warm = []
            if err is None:
                for _ in range(10):
                    dt, _, e2 = run(url, q)
                    if e2:
                        err = e2
                        break
                    warm.append(dt)
            res[qid][name] = {
                "rows": None if rows is None else len(rows),
                "cold_ms": round(cold * 1000, 1),
                "warm_median_ms": round(statistics.median(warm) * 1000, 1) if warm else None,
                "warm_p95_ms": round(pct(warm, 95) * 1000, 1) if warm else None,
                "error": err,
            }
            fps[name] = fingerprint(rows)
        a, b = list(fps.values())
        has_limit = "LIMIT" in q and "ORDER BY" not in q
        res[qid]["results_match"] = (a == b) if (a is not None and b is not None) else None
        res[qid]["rowcount_match"] = res[qid]["fuseki"]["rows"] == res[qid]["qlever"]["rows"]
        res[qid]["limit_without_order"] = has_limit
        print(qid, {n: (res[qid][n]["rows"], res[qid][n]["warm_median_ms"], res[qid][n]["error"])
                    for n in engines}, "match" if res[qid]["results_match"] else "DIFF", flush=True)
    return res


def throughput(url, clients, seconds=30):
    mix = [q for _, _, q in QUERIES]
    stop = time.time() + seconds
    lat, errs = [], 0

    def worker(i):
        nonlocal errs
        n = i
        while time.time() < stop:
            dt, _, err = run(url, mix[n % len(mix)])
            n += 1
            if err:
                errs += 1
            else:
                lat.append(dt)

    with ThreadPoolExecutor(clients) as ex:
        list(ex.map(worker, range(clients)))
    return {"clients": clients, "queries": len(lat), "qps": round(len(lat) / seconds, 2),
            "median_ms": round(statistics.median(lat) * 1000, 1) if lat else None,
            "p95_ms": round(pct(lat, 95) * 1000, 1) if lat else None, "errors": errs}


if __name__ == "__main__":
    engines = {"fuseki": sys.argv[1], "qlever": sys.argv[2]}
    out = {"per_query": per_query(engines), "throughput": {}}
    for name, url in engines.items():
        out["throughput"][name] = [throughput(url, c) for c in (1, 4, 16)]
        print(name, out["throughput"][name], flush=True)
    json.dump(out, open(sys.argv[3], "w"), indent=1)
