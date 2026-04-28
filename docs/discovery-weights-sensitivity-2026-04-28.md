<!--
SPDX-License-Identifier: AGPL-3.0-or-later
SPDX-FileCopyrightText: 2026 Johan Pieterse / The Archive and Heritage Group (Pty) Ltd
-->

# Discovery — merger-weight sensitivity sweep (Run #1 simulated qrels)

**Author:** Johan Pieterse, The Archive and Heritage Group (Pty) Ltd
**Date:** 2026-04-28
**Issue:** GitHub #31 (paired with #30 diagnosis)
**Inputs:** `tests/discovery/qrels-simulated.csv` (100 queries, seed=42), `bin/discovery-eval.php` v1.

Tests whether the kw_entity / full_hybrid regression vs baseline (Run #1) is fixable by re-tuning the merger weights, holding everything else (RRF k, entity retrieval logic, qrels) constant.

## Regimes

| Regime | keyword | entity | hierarchical | rationale |
|---|---:|---:|---:|---|
| **A** | 0.35 | 0.40 | 0.25 | current production (control) |
| **B** | 0.55 | 0.15 | 0.30 | halve entity, lift keyword + hier modestly |
| **C** | 0.70 | 0.05 | 0.25 | drop entity to near-zero, lift keyword to 2-way value |

Applied via SQL UPDATE on `ahg_settings.discovery` rows for the 3-way merge weights only. 2-way weights, RRF `k=60`, hierarchical-tier scores, and multi-source bonus are unchanged across regimes. Settings restored to A after the sweep.

Reference Run #1 baseline (keyword-only) — **nDCG@10 = 0.384, MRR = 0.367, R@10 = 0.442**. Any regime that recovers `kw_entity` or `full_hybrid` toward this number is a win.

## `kw_entity` across regimes

| Regime | nDCG@10 | MRR | R@10 | R@50 | R@100 | wall mean (ms) |
|---|---:|---:|---:|---:|---:|---:|
| **A** (kw=0.35, en=0.4, hi=0.25) | 0.183 | 0.159 | 0.322 | 0.398 | 0.398 | 534 |
| **B** (kw=0.55, en=0.15, hi=0.3) | 0.373 | 0.363 | 0.431 | 0.459 | 0.459 | 534 |
| **C** (kw=0.7, en=0.05, hi=0.25) | 0.380 | 0.367 | 0.441 | 0.460 | 0.460 | 527 |

### `kw_entity` — nDCG@10 by query_type

| Regime | title (n=30) | subject (n=40) | scope_np (n=20) | typo (n=10) |
|---|---:|---:|---:|---:|
| **A** | 0.377 | 0.013 | 0.255 | 0.143 |
| **B** | 0.910 | 0.001 | 0.397 | 0.199 |
| **C** | 0.914 | 0.001 | 0.421 | 0.213 |

## `full_hybrid` across regimes

| Regime | nDCG@10 | MRR | R@10 | R@50 | R@100 | wall mean (ms) |
|---|---:|---:|---:|---:|---:|---:|
| **A** (kw=0.35, en=0.4, hi=0.25) | 0.195 | 0.166 | 0.342 | 0.398 | 0.398 | 713 |
| **B** (kw=0.55, en=0.15, hi=0.3) | 0.372 | 0.361 | 0.430 | 0.457 | 0.457 | 688 |
| **C** (kw=0.7, en=0.05, hi=0.25) | 0.380 | 0.365 | 0.441 | 0.458 | 0.458 | 677 |

### `full_hybrid` — nDCG@10 by query_type

| Regime | title (n=30) | subject (n=40) | scope_np (n=20) | typo (n=10) |
|---|---:|---:|---:|---:|
| **A** | 0.415 | 0.013 | 0.255 | 0.144 |
| **B** | 0.910 | 0.000 | 0.397 | 0.194 |
| **C** | 0.914 | 0.000 | 0.421 | 0.213 |

## Headline finding

### `kw_entity` title-query nDCG@10
- baseline (keyword-only, no weights involved): **0.926**
- Regime A: 0.377
- Regime B: 0.910
- Regime C: 0.914

### `full_hybrid` title-query nDCG@10
- baseline (keyword-only, no weights involved): **0.926**
- Regime A: 0.415
- Regime B: 0.910
- Regime C: 0.914

## A → C deltas (current vs entity-near-zero)

| Config | metric | A | C | delta | toward baseline? |
|---|---|---:|---:|---:|---|
| kw_entity | nDCG@10 | 0.183 | 0.380 | +0.197 | ✓ |
| kw_entity | MRR | 0.159 | 0.367 | +0.207 | ✓ |
| kw_entity | R@10 | 0.322 | 0.441 | +0.119 | ✓ |
| kw_entity | R@50 | 0.398 | 0.460 | +0.062 | ✓ |
| kw_entity | R@100 | 0.398 | 0.460 | +0.062 | ✓ |
| full_hybrid | nDCG@10 | 0.195 | 0.380 | +0.185 | ✓ |
| full_hybrid | MRR | 0.166 | 0.365 | +0.200 | ✓ |
| full_hybrid | R@10 | 0.342 | 0.441 | +0.099 | ✓ |
| full_hybrid | R@50 | 0.398 | 0.458 | +0.060 | ✓ |
| full_hybrid | R@100 | 0.398 | 0.458 | +0.060 | ✓ |

## Recommendation

**Drop `weight_entity_3way` from 0.40 to 0.05–0.15** as the immediate production change. Both Regime B (entity=0.15) and Regime C (entity=0.05) recover 95–99 % of `baseline`'s title-query performance. Regime C is marginally better but Regime B is a safer one-shot setting because it preserves a small entity contribution for queries that genuinely need it (typo-handling regime improves from 0.143 → 0.199 in B vs 0.213 in C; that delta is within bootstrap CI noise).

Keyword should rise correspondingly. Hierarchical at 0.25–0.30 is fine — it's not the dominant signal but it doesn't hurt.

**This is the immediate fix, not the long-term answer.** The diagnosis under #30 — "entity strategy is competent at content-mention recall but the wrong target for known-item queries" — still holds. The long-term answer is **per-query-type adaptive weights**: detect known-item intent (e.g. queries that look like a title slice) and switch to a high-keyword, near-zero-entity profile; for genuinely topical queries (subject-AP), keep entity weight up. That's a paper-grade contribution, not a one-line `UPDATE`.

Run #1.5 should re-run the full 5-config ablation under Regime B (or C) to confirm the recovery generalises beyond the kw_entity / full_hybrid pair tested here. Then decide whether to ship the new weights as the production default.

## Source of variance vs Run #1

Regime A in this sweep should be byte-identical to the original Run #1 since weights, qrels, and code are unchanged. Sanity:

| Config | original Run #1 nDCG@10 | this sweep Regime A nDCG@10 |
|---|---:|---:|
| kw_entity | 0.183 | 0.183 |
| full_hybrid | 0.195 | 0.195 |

— matches to 3 decimals, confirming the sweep is comparing like with like.

## Reproducing this sweep

```bash
# 1. Apply weights via SQL UPDATE (env-var override path was added then reverted; this is the reproducible path)
for r in A B C; do
  case $r in
    A) kw=0.35; en=0.40; hi=0.25 ;;
    B) kw=0.55; en=0.15; hi=0.30 ;;
    C) kw=0.70; en=0.05; hi=0.25 ;;
  esac
  mysql -u root heratio -e "
    UPDATE ahg_settings SET setting_value='$kw' WHERE setting_key='ahg_discovery_weight_keyword_3way';
    UPDATE ahg_settings SET setting_value='$en' WHERE setting_key='ahg_discovery_weight_entity_3way';
    UPDATE ahg_settings SET setting_value='$hi' WHERE setting_key='ahg_discovery_weight_hier_3way';"
  for cfg in kw_entity full_hybrid; do
    mkdir -p storage/discovery-eval/run-1-weights-$r
    php bin/discovery-eval.php --qrels=tests/discovery/qrels-simulated.csv \
        --config=$cfg --output=storage/discovery-eval/run-1-weights-$r/$cfg.json --quiet
  done
done

# 2. Restore production weights
mysql -u root heratio -e "
  UPDATE ahg_settings SET setting_value='0.35' WHERE setting_key='ahg_discovery_weight_keyword_3way';
  UPDATE ahg_settings SET setting_value='0.40' WHERE setting_key='ahg_discovery_weight_entity_3way';
  UPDATE ahg_settings SET setting_value='0.25' WHERE setting_key='ahg_discovery_weight_hier_3way';"
```

The sweep took ~5.5 minutes wall total (6 evals × ~50–60 s each).

---

## Validate when GPU returns

This sweep is parked pending GPU availability. The 95–99 % recovery under Regime B/C was measured on CPU-only inference (Ollama at `192.168.0.112`, all-minilm 384-d embeddings, no LLM paraphrase). When GPU access returns, re-validate before applying Regime B to production:

1. **Re-run all three regimes against Run #2** (LLM-paraphrase Generator C.2 enabled, image strategy potentially live, vector embeddings on GPU). The recovery under Regime B may attenuate or strengthen depending on how paraphrased queries interact with the entity strategy. The headline number to watch is **title-query nDCG@10 in Regime B** — currently 0.910; if it stays ≥0.870 against paraphrased queries, ship Regime B.

2. **Run a denser grid** (entity weights 0.05, 0.10, 0.15, 0.20, 0.25, 0.30, 0.35, 0.40) once GPU latency makes 8 evals × 100 queries cheap. The current 3-point grid finds the right corner of the surface but doesn't pinpoint the optimum.

3. **Test per-query-type adaptive weights.** Build a simple intent classifier (length-based, capitalisation-based, or a small LLM call once GPU is available) that routes title-slice queries to a `kw=0.70, en=0.05` profile and topical/scope-NP queries to a `kw=0.45, en=0.30` profile. Compare against the single-weight Regime B baseline. **This is the paper's central architectural contribution** — the sweep here is preparatory, not conclusive.

4. **Sanity-check `full_hybrid` under Run #2.** Currently `full_hybrid` ≈ `kw_entity` because the vector-via-RRF contribution is marginal on this corpus (Ollama CPU embeddings; small effective vector signal). When GPU embeddings + image-on-text-path land, `full_hybrid` should pull ahead of `kw_entity`. If it doesn't, the merger's RRF integration needs another pass.

Until GPU returns, **do not apply Regime B to production**. The current weights (Regime A: kw=0.35, en=0.40, hi=0.25) remain in `ahg_settings.discovery`, verified by the post-sweep restore in this report's run log.
