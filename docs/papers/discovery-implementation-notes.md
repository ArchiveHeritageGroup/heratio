<!--
SPDX-License-Identifier: AGPL-3.0-or-later
SPDX-FileCopyrightText: 2026 Johan Pieterse / The Archive and Heritage Group (Pty) Ltd

Heratio is free software: you can redistribute it and/or modify it under the
terms of the GNU Affero General Public License version 3 or (at your option)
any later version. See <https://www.gnu.org/licenses/agpl-3.0.html>.
-->

# Discovery - implementation notes

**Author:** Johan Pieterse, The Archive and Heritage Group (Pty) Ltd
**Date:** 2026-04-28
**Heratio version:** 1.18.0+ (post-#28)
**Scope:** Methodology notes that the GenDSs paper's pilot section can cite. Covers the four questions raised in GitHub issue #27.

This write-up is the deliverable for issue #27. It accompanies issues #16 (qrels schema), #17 (`bin/discovery-eval.php`), #20 Part A (`bin/discovery-benchmark.php`), #20 Part B (`bin/discovery-eval-verify.php`), and #28 (controller `?strategies=` param).

---

## 1. Controller refactor - was it needed?

**Decision: no refactor.** The 1,407-line `DiscoveryController` was kept as-is; the ablation switch was threaded through with a request parameter and a static helper. Total change footprint:

- 1 new public class constant: `DiscoveryController::VALID_STRATEGIES`
- 1 new public static method: `resolveEnabledStrategies(string|array|null, string): string[]`
- 5 guard sites in `search()` swapped from `in_array($mode, ['semantic','vector'])` to `in_array('name', $enabledStrategies, true)`
- 1 cache-key extension (added `$strategiesKey` segment + `?nocache=1` bypass)
- 1 response field added (`strategies` - useful for callers to confirm what ran)
- 1 telemetry shape change (`strategy_breakdown` JSON now always carries all 5 retrieval-strategy keys, with `{hits:0, ms:0, top_ids:[]}` for disabled ones)

Diff size against `main` for the controller (verbatim from `git diff --stat`):

```
DiscoveryController.php | 143 ++++++++++++++++-----
1 file changed, 110 insertions(+), 33 deletions(-)
```

- 77 net lines, of which ~50 are the new `resolveEnabledStrategies()` helper (mostly PHPDoc and the legacy mode-mapping fallback). No behaviour change for callers that don't pass `?strategies=`; verified by running the verify-twin (#20 Part B) with the legacy invocation `/discovery/search?q=Department%20of%20Education` and confirming response equality (modulo additive fields).

### Why no refactor

The 4 retrieval strategies are already conceptually separable in the controller - they are private methods (`keywordSearch`, `entitySearch`, `hierarchicalSearch`, plus the two strategy classes `VectorSearchStrategy` / `ImageSearchStrategy` already extracted into `Services/Search/`). The ablation switch did not require breaking them apart further; replacing the per-mode conditionals with per-strategy conditionals was a 1-for-1 swap.

### What is still deferred

Issue #11 records the larger refactor - lifting `keywordSearch`, `entitySearch`, `hierarchicalSearch`, `mergeResults`, `rrfBoostWithVector`, `enrichResults` into their own service classes - as out of scope for the current cycle. That work is ~600 lines of code-shuffle with no behaviour change. **Recommendation: keep it deferred.** Do it when the next architecturally-motivated change touches the controller (most likely candidate: #14 - running the pipeline against the `atom` DB will probably want a per-strategy data-source abstraction, which is the natural place to do the lift).

---

## 2. Qdrant determinism

### Probe

Run a fixed 384-dim unit-normalised query vector through `POST /collections/anc_records/points/search` 10 times consecutively, `limit=20`, identical request body each time:

```python
random.seed(42)
qv = unit_normalize([random.uniform(-1, 1) for _ in range(384)])
for run in range(10):
    resp = qdrant.search(collection="anc_records", vector=qv, limit=20)
    record [(p.id, round(p.score, 8)) for p in resp.result]
```

### Result

| Check | Outcome |
|---|---|
| All 10 runs return identical `id` order | **YES** |
| All 10 runs return identical `(id, score)` tuples (8-decimal precision) | **YES** |

Top-5 from runs 1, 5, and 10 (sample):

```
(10753700, 0.18021830)
( 4162714, 0.17783728)
( 4162752, 0.17741325)
(   79166, 0.17672455)
( 3816951, 0.17670974)
```

- byte-identical across all 10 runs.

### Interpretation

Qdrant's HNSW index is in principle a stochastic data structure (random graph construction at build time, random entry-point selection at search time), but Qdrant fixes both via deterministic seeds: the index is built once and stored, and search-time entry-point selection uses a deterministic procedure over the stored index. As long as the collection is not concurrently being re-indexed during the search, the result is fully deterministic.

Reference: [Qdrant - Indexing concepts](https://qdrant.tech/documentation/concepts/indexing/), specifically the HNSW configuration parameters which seed the construction phase.

### Mitigation applied

None required. As a defensive secondary check, the eval harness compares the `retrieved_top10` list (which is built from the controller's final merged result, not directly from Qdrant) - if Qdrant ever did diverge between calls, the verify-twin would catch it via the `top10_mismatch` divergence kind.

---

## 3. RRF tie-break stability

`DiscoveryController::rrfBoostWithVector()` sorts via:

```php
usort($merged, fn($a,$b) => $b['score'] <=> $a['score']);
```

When multiple items end up with identical fused scores, the question is whether `usort` produces a stable ordering across runs.

### Runtime

Confirmed PHP version on this server: **8.3.30** (per `php --version` and `gendss_status_2026-04-28.md` §5.1).

### Stability guarantee

PHP 8.0 made all sort functions stable. Per [the upstream RFC](https://wiki.php.net/rfc/stable_sorting):

> All PHP sorting functions are stable as of PHP 8.0.0.

Therefore `usort` on an array of items with identical comparison values preserves their input-array order across calls.

### Probe

5 consecutive `usort` calls on a 5-element array where every element has score=0.5 returned identical order each time:

```
run 0: ["a","b","c","d","e"]
run 1: ["a","b","c","d","e"]
run 2: ["a","b","c","d","e"]
run 3: ["a","b","c","d","e"]
run 4: ["a","b","c","d","e"]
```

### Mitigation applied

None required. The PHP runtime carries the guarantee. If the engine is ever downgraded to <8.0, a secondary sort key (`['score','object_id']` two-level comparator) should be added to `rrfBoostWithVector` defensively.

---

## 4. Run-to-run variance - full inventory

Verify-twin (`bin/discovery-eval-verify.php --runs=2`) was run against the v1 stub qrels (`tests/discovery/qrels.csv`, 5 queries) under config `full_hybrid`. Verdict: **PASS** - both runs produced identical aggregate metrics (to 6 decimal places) and identical per-query top-10 ordering. `log_id` values differed across runs, confirming both invocations actually executed the pipeline (rather than one returning a cached row).

### Important caveat

The PASS verdict is **partially trivial** for this snapshot. The Heratio app talks to the `heratio` MySQL database, which holds 743 demo IOs and contains no ANC content. All 5 stub queries return `total: 0` from the production code path; consequently both runs' `retrieved_top10` is `[]`, which is trivially identical.

A real determinism test requires issue #14 (running the discovery pipeline against the `atom` DB, which holds 454,393 IOs including the ANC corpus) to land. Once the data plane is correct, the same `discovery-eval-verify.php` invocation will exercise the actual pipeline, and any nondeterminism will surface immediately.

### Inventory of variance sources

Each row below is a known potential source of variance, the test that detected (or would detect) it, and the mitigation status.

| Source | Test that catches it | Status today | Mitigation applied | Residual risk |
|---|---|---|---|---|
| `ahg_discovery_cache` row from a prior run | verify-twin's `top10_mismatch` (would find run 2 = run 1 cached row) | eliminated | Eval harness appends `?nocache=1`; controller honours it as of #28 | none |
| Qdrant HNSW search | direct probe (§2 above), 10 runs | confirmed deterministic on `anc_records` | none required | none |
| PHP `usort` tie-breaks in RRF merger | direct probe (§3 above) + PHP 8.0+ runtime guarantee | confirmed stable | none required (PHP 8.3 in use) | only if PHP downgrades to <8.0 |
| MySQL `ORDER BY score DESC` ties in `keywordSearch` / `entitySearch` | verify-twin's `top10_mismatch` | not yet observed (heratio-DB queries return 0 rows) | not applied - defer until #14 lands and we have a corpus that actually retrieves | medium - likely to surface once atom-DB queries return real rows; recommended pre-emptive mitigation: add `, object_id ASC` as the secondary sort key in `keywordSearch()` and `entitySearch()` |
| Ollama `/api/embeddings` jitter (vector strategy) | verify-twin's `top10_mismatch` on identical query text | currently null - Ollama is offline at both 192.168.0.112 and 192.168.0.78 (per status doc §3.1) | not applicable today | medium - to be re-checked once #12 (Ollama bring-up) lands; embeddings should be deterministic for fixed model + fixed input but worth verifying |
| `microtime()` jitter in `latency_ms` and `response_ms` | excluded from determinism check by design | n/a - `latency_ms` is a measurement, not a result | n/a | n/a - verify-twin compares retrieval results, not timing |
| Bootstrap CI95 random seed | metric_aggregate check | seed=42 hard-coded in `aggregateMetrics()`, propagated via `--seed` flag | controlled at the flag level | none |

### Recommended pre-emptive mitigation before paper run

When issue #14 lands (atom-DB pipeline), add a secondary sort key to the SQL ranking sites in `DiscoveryController::keywordSearch()` and `DiscoveryController::entitySearch()`:

```php
// before
->orderBy('score', 'desc')
// after
->orderBy('score', 'desc')->orderBy('object_id', 'asc')
```

This is cheap defence against a high-likelihood variance source. Apply before generating the paper's Run #1 numbers.

### Recommended next determinism run

After #14 lands and the v1 qrels (~30 graded queries, per #16 acceptance) is curated:

```
php bin/discovery-eval-verify.php \
  --qrels=tests/discovery/qrels.csv \
  --config=full_hybrid \
  --runs=10 \
  --output-dir=storage/discovery-eval/verify-pre-paper
```

Expected verdict: **PASS** with all 10 runs producing identical `retrieved_top10` per query and identical aggregate metrics to 6 decimal places. If FAIL, the diff at `<output-dir>/diff.json` localises which query and which strategy is the source.

---

## Summary

| Question | Answer |
|---|---|
| Controller refactor needed? | No. Added a request param + static helper; ~60-line diff. Larger strategy-class lift remains deferred per #11. |
| Is Qdrant deterministic? | Yes. 10/10 byte-identical (id, score) tuples on `anc_records` for a fixed query vector. |
| Is RRF tie-break stable? | Yes. PHP 8.3 (in use here) guarantees stable `usort` per the 8.0 RFC. |
| Sources of run-to-run variance? | One eliminated (controller cache, via `?nocache=1`). One pre-emptively recommended (secondary sort key on MySQL `score DESC` rankings) - to apply before paper run. Two confirmed deterministic (Qdrant, PHP usort). One unknown until #12 lands (Ollama embedding determinism). One excluded by design (`latency_ms`). |

The paper's methodology section can cite this document as the source of the determinism claim. The verify-twin invocation given above is the reproducible procedure for re-confirming each metric run.
