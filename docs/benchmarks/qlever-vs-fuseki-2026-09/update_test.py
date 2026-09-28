"""Write-path test: the three update shapes Heratio sends, timed on both engines.

usage: python3 update_test.py <fuseki_query> <fuseki_update> <qlever_url> <qlever_token> <phase>
phase "write": 200 single-record INSERT DATA into a named graph, 50 DELETE WHERE,
               one DROP GRAPH, then verify counts.
phase "verify": count what should still be there (run after restarting QLever).
"""
import json, statistics, sys, time, urllib.parse, urllib.request

G = "urn:ahg:bench:graph"
S = "urn:ahg:bench:record:"
R = "https://www.ica.org/standards/RiC/ontology#"


def post(url, form, token=None):
    headers = {"Content-Type": "application/x-www-form-urlencoded",
               "Accept": "application/sparql-results+json"}
    if token:
        headers["Authorization"] = "Bearer " + token
    req = urllib.request.Request(url, data=urllib.parse.urlencode(form).encode(), headers=headers)
    t = time.perf_counter()
    with urllib.request.urlopen(req, timeout=300) as r:
        body = r.read()
    return time.perf_counter() - t, body


def count(url, g):
    q = f"SELECT (COUNT(*) AS ?n) WHERE {{ GRAPH <{g}> {{ ?s ?p ?o }} }}"
    _, body = post(url, {"query": q})
    return int(json.loads(body)["results"]["bindings"][0]["n"]["value"])


def write(name, qurl, uurl, token):
    ins, dels = [], []
    for i in range(200):
        u = (f"INSERT DATA {{ GRAPH <{G}> {{ <{S}{i}> a <{R}Record> ; "
             f"<{R}title> \"Bench record {i}\" ; <{R}identifier> \"B-{i}\" . }} }}")
        dt, _ = post(uurl, {"update": u}, token)
        ins.append(dt)
    after_insert = count(qurl, G)
    for i in range(50):
        u = f"DELETE WHERE {{ GRAPH <{G}> {{ <{S}{i}> ?p ?o }} }}"
        dt, _ = post(uurl, {"update": u}, token)
        dels.append(dt)
    after_delete = count(qurl, G)
    # a second graph that is dropped whole, as DROP GRAPH is used on sync
    post(uurl, {"update": f"INSERT DATA {{ GRAPH <{G}:drop> {{ <{S}x> <{R}title> \"x\" . }} }}"}, token)
    t = time.perf_counter()
    post(uurl, {"update": f"DROP GRAPH <{G}:drop>"}, token)
    drop_ms = (time.perf_counter() - t) * 1000
    print(json.dumps({
        "engine": name,
        "insert_median_ms": round(statistics.median(ins) * 1000, 1),
        "insert_p95_ms": round(sorted(ins)[int(0.95 * (len(ins) - 1))] * 1000, 1),
        "delete_median_ms": round(statistics.median(dels) * 1000, 1),
        "drop_graph_ms": round(drop_ms, 1),
        "triples_after_insert": after_insert, "expected_after_insert": 600,
        "triples_after_delete": after_delete, "expected_after_delete": 450,
        "dropped_graph_left": count(qurl, G + ":drop"),
    }))


if __name__ == "__main__":
    fq, fu, ql, tok, phase = sys.argv[1:6]
    if phase == "write":
        write("fuseki", fq, fu, None)
        write("qlever", ql, ql, tok)
    else:
        print(json.dumps({"fuseki_after_restart": count(fq, G),
                          "qlever_after_restart": count(ql, G), "expected": 450}))
