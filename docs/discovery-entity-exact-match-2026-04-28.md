# Discovery: entity-strategy exact-match vs OR-of-tokens (issue #33)

_Generated: 2026-04-28. Builds on #32's diagnosis (broad single-token OR is the dominant noise driver). Read-only intervention; the controller patch is reverted before this issue closes._

## TL;DR

**Replace the entity strategy's `bool/should` of `match_phrase` + `match` with a `term` query against `nerEntityValues.raw`.** It is decisively better on every metric and ~3.6× faster.

| Config | Mode | nDCG@10 | MRR | Recall@10 | Recall@50 | Total latency | Entity-strategy latency |
|---|---|---:|---:|---:|---:|---:|---:|
| `kw_entity` | control (current) | 0.1832 | 0.1594 | 0.3224 | 0.3982 | 545 ms | 36 ms |
| `kw_entity` | phrase-only | 0.1869 | 0.1742 | 0.3124 | 0.3984 | 483 ms | 29 ms |
| `kw_entity` | **raw exact-match** | **0.3339** | **0.3304** | **0.3772** | **0.4302** | **458 ms** | **10 ms** |
| `full_hybrid` | control (current) | 0.1949 | 0.1656 | 0.3422 | 0.3982 | 616 ms | 31 ms |
| `full_hybrid` | phrase-only | 0.1993 | 0.1787 | 0.3422 | 0.3984 | 626 ms | 29 ms |
| `full_hybrid` | **raw exact-match** | **0.3247** | **0.3305** | **0.3652** | **0.4308** | **623 ms** | **10 ms** |

Relative to control:

| Metric | `kw_entity` raw vs control | `full_hybrid` raw vs control |
|---|---:|---:|
| nDCG@10 | **+82.3%** | **+66.6%** |
| MRR | **+107.3%** | **+99.6%** |
| Recall@10 | **+17.0%** | **+6.7%** |
| Recall@50 | +8.0% | +8.2% |
| Entity-strategy latency | **−72%** (36 → 10 ms) | **−68%** (31 → 10 ms) |

Phrase-only is within noise on `kw_entity` (nDCG@10 +2%, MRR +9%, recall@10 −3%) and slightly better on `full_hybrid` (nDCG@10 +2.3%). It is **not** a meaningful improvement on its own — the win is in the keyword-subfield exact-match.

## Recommendation

Default the entity strategy to **`term` against `nerEntityValues.raw`**.

This validates #32's diagnosis: the production OR-of-tokens (`match_phrase` + `match`) was a high-recall, very-low-precision token OR that pulled in any document mentioning any token in the query. Replacing it with exact-token-on-keyword-subfield doubles the ranking quality (MRR), nearly doubles nDCG@10, and lifts recall@10 — there is no precision/recall trade-off in this comparison; both improve.

### Why raw beats phrase

A phrase query like `match_phrase: "South Africa"` still matches `nerEntityValues = ["South African Communist Party"]` because `South African` is a token sequence inside the longer string. The `.raw` keyword subfield treats each entity value as a single non-tokenised string — `"South Africa"` only matches an entity value that is exactly `"South Africa"` (within the 256-char `ignore_above` cap, which is fine for entity strings).

This is exactly the boundary semantics the entity strategy needs: NER produced the entity strings as discrete labels; the search should find documents tagged with the *same* label, not documents tagged with a label that happens to share words.

### Why phrase alone wasn't enough

Even after dropping the `match` clause, `match_phrase` against the `text` field still uses the standard analyzer's tokenisation. Multi-word entity values with punctuation, hyphens or apostrophes can drift between query and index. The keyword `.raw` field bypasses analysis entirely, which is the right choice for entity-label retrieval.

## Methodology

- **Eval harness**: `bin/discovery-eval.php`. New `--extra-query=` flag added so the harness can append `&exactMatch=phrase` or `&exactMatch=raw` to every `/discovery/search` call.
- **Controller patch (temporary)**: `DiscoveryController::entitySearch` reads `?exactMatch=phrase|raw` from the request (or `DISCOVERY_ENTITY_EXACT_MATCH` from env). Default mode (no flag) is unchanged from production.
- **Modes**:
  - `control` — `bool/should` of `match_phrase(boost=2)` + `match(boost=1)` per term (current production behaviour).
  - `phrase` — `bool/should` of `match_phrase(boost=1)` per term, no `match` clauses.
  - `raw` — `bool/should` of `term` queries against `nerEntityValues.raw` (the keyword subfield, which already exists in the index mapping).
- **Configs**: `kw_entity` (keyword + entity) and `full_hybrid` (keyword + entity + hierarchical + vector). Both bring in additional strategies, so the entity-mode change effect is partially diluted — the kw_entity numbers isolate the entity contribution best, full_hybrid shows it doesn't get washed out by the other strategies.
- **Workload**: 100 simulated queries from `tests/discovery/qrels-simulated.csv` (30 title, 40 subject, 20 scope_np, 10 typo).
- **Endpoint**: live `https://heratio.theahg.co.za/discovery/search` with `nocache=1` so each query bypasses `ahg_discovery_cache`.

## Caveat

Latency for `full_hybrid` is dominated by the keyword/hierarchical/vector strategies, so the 3.6× speedup of the entity step shows up as a 545 → 458 ms wall-clock improvement on `kw_entity` (where entity is one of two strategies) but only 616 → 623 ms on `full_hybrid` (essentially flat — entity is one of four). This is expected; the entity-strategy improvement is real, just not visible in the total when other strategies are bigger.

## Reproducibility

- Eval timestamp: 2026-04-30 14:39–14:43 SAST.
- Eval `code_git_sha` (per output JSON): see `storage/discovery-eval/run-33-{control,phrase,raw}/{kw_entity,full_hybrid}.json`.
- qrels file hash: `sha256:1d7ab3bbfd298cc0b48cb811048332375b6bc86e9c24b11faf558f3483150921`.
- Live ES cluster: `localhost:9200` (single-node).
- Index: `heratio_qubitinformationobject`. Field: `nerEntityValues` (text, standard analyzer) with `.raw` (keyword, ignore_above 256) subfield — both already in the production mapping.

## Next step (out of scope for #33)

The controller patch in this issue is reverted (env-var/query-flag mode left out of main). The follow-up production change should:

1. Replace the `bool/should` of `match_phrase` + `match` in `DiscoveryController::entitySearch` with a single `bool/should` of `term` queries on `nerEntityValues.raw`, optionally with a `match_phrase` fallback for the case where the index hasn't been refreshed since #24.
2. Re-run the eval against production after rollout to confirm the gain holds.
3. Watch `ahg_discovery_log.pre_merge_ranks.entity` for any regression in queries that *should* benefit from broader matching (typos, ambiguous entity strings).
