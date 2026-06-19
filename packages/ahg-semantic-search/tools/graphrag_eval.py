#!/usr/bin/env python3
# =============================================================================
# graphrag_eval.py - GraphRAG grounding A/B eval (#1320 increment 3)
# =============================================================================
# Measures the hallucination/disambiguation delta from the RiC-graph grounding
# layer: runs a pilot question set through KM /api/ask twice - graph_ground=0
# (baseline) and graph_ground=1 (grounded) - and scores a few proxies.
#
# Requires the KM-side injection (fetch_graph_grounding in app.py) deployed, and
# the increment-2 /api/ric/ground endpoint live.
#
# Usage:
#   KM_BASE=https://km.theahg.co.za KM_API_KEY=<key> python3 graphrag_eval.py
#   (KM_BASE defaults to http://localhost:5050)
#
# Proxies (POC-level - not a research-grade hallucination metric):
#   fact_present : answer mentions the authoritative label(s) from the graph
#   abstained    : answer is the "knowledge base does not contain..." fallback
#   chars        : answer length
# Reports per-question baseline-vs-grounded + aggregate deltas + side-by-side.
# =============================================================================

import os
import sys
import json
import time
import urllib.request

KM_BASE = os.getenv("KM_BASE", "http://localhost:5050").rstrip("/")
KM_API_KEY = os.getenv("KM_API_KEY", "")

# Pilot slice (#1320): entities known to exist in the RiC graph. `expect` = the
# authoritative labels the grounded answer should anchor on.
PILOT = [
    {"q": "What is the Pieterse Archive?",                 "expect": ["Pieterse Archive"]},
    {"q": "Tell me about Egypt in this collection.",       "expect": ["Egypt"]},
    {"q": "Who are the agents in the Benson collection?",  "expect": ["agent"]},
    {"q": "Describe the funeral boat model's place of origin.", "expect": ["Egypt"]},
]

ABSTAIN_MARK = "does not contain specific information"


def ask(question: str, ground: bool) -> dict:
    body = json.dumps({"question": question, "graph_ground": ground}).encode()
    req = urllib.request.Request(KM_BASE + "/api/ask", data=body,
                                 headers={"Content-Type": "application/json",
                                          "X-API-Key": KM_API_KEY})
    t0 = time.time()
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            d = json.load(r)
    except Exception as e:
        return {"answer": f"[ERROR: {e}]", "ms": int((time.time() - t0) * 1000)}
    return {"answer": (d.get("answer") or d.get("response") or ""),
            "ms": int((time.time() - t0) * 1000)}


def score(answer: str, expect: list) -> dict:
    low = answer.lower()
    return {
        "fact_present": any(e.lower() in low for e in expect),
        "abstained": ABSTAIN_MARK in low,
        "chars": len(answer),
    }


def main() -> int:
    if not KM_API_KEY:
        print("WARN: KM_API_KEY not set - /api/ask may reject the request.", file=sys.stderr)

    agg = {"base_fact": 0, "grnd_fact": 0, "base_abstain": 0, "grnd_abstain": 0, "n": 0}
    for item in PILOT:
        q = item["q"]
        base = ask(q, False)
        grnd = ask(q, True)
        bs = score(base["answer"], item["expect"])
        gs = score(grnd["answer"], item["expect"])
        agg["n"] += 1
        agg["base_fact"] += int(bs["fact_present"])
        agg["grnd_fact"] += int(gs["fact_present"])
        agg["base_abstain"] += int(bs["abstained"])
        agg["grnd_abstain"] += int(gs["abstained"])

        print("=" * 78)
        print("Q:", q)
        print(f"  baseline : fact={bs['fact_present']} abstain={bs['abstained']} chars={bs['chars']} ({base['ms']}ms)")
        print(f"  grounded : fact={gs['fact_present']} abstain={gs['abstained']} chars={gs['chars']} ({grnd['ms']}ms)")
        print("  --- baseline answer ---\n   " + base["answer"][:500].replace("\n", "\n   "))
        print("  --- grounded answer ---\n   " + grnd["answer"][:500].replace("\n", "\n   "))

    n = max(1, agg["n"])
    print("\n" + "#" * 78)
    print(f"AGGREGATE over {agg['n']} questions:")
    print(f"  authoritative-fact-present:  baseline {agg['base_fact']}/{n}  ->  grounded {agg['grnd_fact']}/{n}  "
          f"(delta {agg['grnd_fact'] - agg['base_fact']:+d})")
    print(f"  abstained (no-answer):       baseline {agg['base_abstain']}/{n}  ->  grounded {agg['grnd_abstain']}/{n}  "
          f"(delta {agg['grnd_abstain'] - agg['base_abstain']:+d})")
    print("Review the side-by-side answers above for fabricated entities/dates the grounding removed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
