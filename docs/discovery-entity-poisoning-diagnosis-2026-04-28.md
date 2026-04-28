<!--
SPDX-License-Identifier: AGPL-3.0-or-later
SPDX-FileCopyrightText: 2026 Johan Pieterse / The Archive and Heritage Group (Pty) Ltd
-->

# Discovery — entity-poisoning diagnosis (Run #1)

**Author:** Johan Pieterse, The Archive and Heritage Group (Pty) Ltd
**Date:** 2026-04-28
**Issue:** GitHub #30 (read-only investigation)
**Inputs:** `storage/discovery-eval/run-1/{baseline,kw_entity}.json`, `tests/discovery/qrels-simulated.csv`, atom DB.

---

## TL;DR

The aggregate `kw_entity` nDCG@10 = 0.183 vs `baseline` = 0.384 looks like a 0.2 drop, but it isn't a uniform degradation across query types. The drop is **concentrated entirely in title queries** (baseline 0.926 → kw_entity 0.377; drop = 0.550). Subject queries — where the brief assumed the problem lived — show a **slight improvement** (0.000 → 0.013). The investigation pivoted to title queries.

For the 5 most-degraded title queries, every single ground-truth IO that baseline found at rank 1 was demoted out of top-10 by `kw_entity`. The promoted IDs that took their place are **legitimate ORG-typed entity matches** (not low-quality NORP/CARDINAL/DATE noise) — so the failure is precision-of-target, not precision-of-type.

**Diagnosis: cause (C) dominates with an (A) co-factor.** Entity strategy correctly finds IOs whose NER values mention the query terms, but content-mention is the wrong retrieval target for title-based known-item queries; and the merger weights (kw=0.35, entity=0.40) over-weight the resulting tie-broken bulk-leaf hits. Cause (B) — substring-matching noise — is **ruled out**: 0/39 promoted IDs across all 5 queries had matching entities of low-relevance types.

---

## Pivot — degradation isn't where the brief assumed

The brief asked for the 5 most-degraded **subject** queries. The data says subject queries don't degrade at all:

| query_type | n | baseline nDCG@10 | kw_entity nDCG@10 | drop | per-query: degraded / improved / unchanged |
|---|---:|---:|---:|---:|---|
| **title** | 30 | **0.926** | **0.377** | **0.550** | 26 / 0 / 4 |
| subject | 40 | 0.000 | 0.013 | -0.013 | 0 / 6 / 34 |
| scope_np | 20 | 0.417 | 0.255 | 0.163 | 7 / 3 / 10 |
| typo | 10 | 0.228 | 0.143 | 0.085 | 3 / 0 / 7 |

The aggregate `0.384 → 0.183` is essentially a `0.926 → 0.377` regression on titles, partly diluted by the other types.

The remaining steps therefore use the 5 most-degraded **title** queries — i.e. where the actual problem lives.

---

## §1. The 5 most-degraded title queries

| qid | query_text | baseline nDCG@10 | kw_entity nDCG@10 | drop |
|---|---|---:|---:|---:|
| q001 | `Correspondence French Mission` | 1.000 | 0.000 | 1.000 |
| q010 | `Newsletters French Mission` | 1.000 | 0.000 | 1.000 |
| q013 | `Reports Department Education` | 1.000 | 0.000 | 1.000 |
| q021 | `and Ireland Mission` | 1.000 | 0.000 | 1.000 |
| q028 | `Department Education Discussion` | 1.000 | 0.000 | 1.000 |

All five: baseline ranks the ground-truth fonds at rank 1; `kw_entity` drops it off the top-10 entirely (and out of top-100 — see §5).

---

## §2. Per-query top-10 set difference + promoted-document inspection

### q001 — `Correspondence French Mission` (gt=42665, "Correspondence - French Mission" fonds)

**baseline top-10:** `[42665, 295968, 91132, 19614, 7160, 460, 16455, 83611, 41819, 82212]`
**kw_entity top-10:** `[41819, 44633, 46021, 46082, 47602, 48552, 48859, 46481, 48363, 69752]`

Promoted (in kw_entity top-10, not in baseline top-10): **9 IDs**, all leaf items under the "French Mission Office" fonds:

| oid | title | scope |
|---|---|---|
| 44633 | French Mission Office BOX0002 FLR0022 NOITEM | NULL |
| 46021 | French Mission Office BOX0002 FLR0026 NOITEM | NULL |
| 46082 | French Mission Office BOX0002 FLR0026 NOITEM | NULL |
| 47602 | French Mission Office BOX0003 FLR0037 NOITEM | NULL |
| 48552 | French Mission Office BOX0003 FLR0041 NOITEM | NULL |

### q010 — `Newsletters French Mission` (gt=83611, "Newsletters - French Mission")

**baseline top-10:** `[83611, 41819, 42665, 82212, 78810, 81356, 84779, 42434, 41827, 41886]`
**kw_entity top-10:** `[41819, 44633, 46021, 46082, 47602, 48552, 48859, 46481, 48363, 49751]`

Promoted: 9 IDs. **Identical pattern** — same French Mission Office BOX/FLR leaves.

### q013 — `Reports Department Education` (gt=6454, "Reports - Department of Education")

**baseline top-10:** `[6454, 168100, 450, 460, 176405, 3878, 6274, 5626, 3888, 30013]`
**kw_entity top-10:** `[450, 460, 5626, 3888, 3028, 718, 1568, 1588, 1608, 1618]`

Promoted: 6 IDs, all "Department of Education BOX/FLR" leaves. The parent fonds (450) and a few intermediate IOs are still in top-10, but the GT (6454) was demoted off it.

### q028 — `Department Education Discussion` (gt=3878, "Department of Education Discussion Documents")

Same pattern as q013 — promoted IDs are box-folder leaves from the same Department of Education hierarchy.

### q021 — `and Ireland Mission` (gt=341072, "UK and Ireland Mission")

**baseline top-10:** `[341072, 361820, 16450, 19606, 7152, 29293, 83611, 19614, 30013, 32363]`
**kw_entity top-10:** `[61922, 16450, 51272, 3306630, 9690262, 88709, 361832, 1063478, 1063493, 10077407]`

Promoted: 9 IDs. Mix of French Mission Office leaves (61922, 51272), Belgian Mission (88709), and bulk PDF identifiers (3306630, 9690262, 1063478) — every IO whose entities mention "Ireland" or "Mission".

---

## §3. atom.ahg_ner_entity matches per query

LIKE %term% counts on the source NER table (~9.79 M rows total):

| qid | tokens | total match | ORG | PERSON | GPE | DATE |
|---|---|---:|---:|---:|---:|---:|
| q001 | Correspondence/French/Mission | 18,816 | 16,983 | 1,509 | 321 | 3 |
| q010 | Newsletters/French/Mission | 18,722 | 16,899 | 1,499 | 321 | 3 |
| q013 | Reports/Department/Education | 24,313 | 23,887 | 281 | 143 | 2 |
| q021 | Ireland/Mission | 23,510 | 16,238 | 1,076 | 6,193 | 3 |
| q028 | Department/Education/Discussion | 24,399 | 24,015 | 243 | 139 | 2 |

**Notable**: 90%+ of matches are ORG-typed across every query. PERSON / GPE / DATE are minorities. There's no NORP/CARDINAL/ORDINAL volume noise.

### Sample matched (entity_value, entity_type) per query — character of the matches

**q001 ("Correspondence French Mission"):**
- `Natjonal Manpower Commission` (ORG) — substring hit on "Mission" via "Commission"
- `Commissioner of Police` (ORG) — substring hit via "Commissioner"
- `Human Rights Commission` (ORG) — substring via "Commission"
- `RSA Commission for Administration` (ORG) — substring via "Commission"

**q010 ("Newsletters French Mission"):**
- `Economic Commission for Africa` (ORG)
- `United Nations High Commission for Refugees` (ORG)
- `Standing Commission of Inquiry on Violence` (ORG)
- `Negotiations Commission` (ORG)

**q013 ("Reports Department Education"):**
- `US State Department` (ORG)
- `Maintenance Department` (ORG)
- `Labour Department` (ORG)
- `Fine Art Department` (ORG)

**q028 ("Department Education Discussion"):**
- `Department of Forestry` (ORG)
- `Department of Agriculture` (ORG)
- `Educational Innovation & School Improvement` (ORG)
- `Educational Training School` (ORG)

**q021 ("and Ireland Mission"):**
- `Northern Ireland` (GPE) — clean match
- `Ireland` (GPE) — clean match
- `Reformed Mission Church` (ORG) — substring on "Mission"
- `Functions of Commission` (ORG) — substring on "Mission" via "Commission"

The atom-DB inspection confirms substring noise exists in the **source** data — `Mission` matches `Commission`, `Department of X` matches everywhere. **However, this matters only if the live entity strategy uses LIKE substring matching, which it no longer does** (issue #24 moved entity to ES BM25 over the denormalised `nerEntityValues` field with the standard analyzer; tokens are matched at word-boundary granularity). See the diagnosis paragraph for why the live ES path nevertheless exhibits the same demotion behaviour.

---

## §4. Promoted-IDs ∩ low-relevance-entity-types

Asked: of the IDs `kw_entity` promoted into top-10, what fraction had matching entities only of low-relevance types (`NORP`, `CARDINAL`, `ORDINAL`, `DATE`, `TIME`, `PERCENT`, `MONEY`, `QUANTITY`, `LANGUAGE`)?

| qid | promoted IDs | with NER match | low-relevance majority | percentage |
|---|---:|---:|---:|---:|
| q001 | 9 | 9 | **0** | **0.0 %** |
| q010 | 9 | 9 | **0** | **0.0 %** |
| q013 | 6 | 6 | **0** | **0.0 %** |
| q028 | 6 | 6 | **0** | **0.0 %** |
| q021 | 9 | 9 | **0** | **0.0 %** |

**0 % across all 5 queries.** Every promoted IO was promoted because it had a legitimate `ORG` (or `GPE` for q021) entity that matched the query — not because of `DATE`/`CARDINAL`/`NORP` substring accidents.

This is a strong, structural rule-out of the "entity-type noise" hypothesis.

---

## §5. False-positive rate of promoted IDs

For each promoted ID, what's its qrels relevance?

| qid | promoted | qrels=0 | qrels≥1 | false-positive % |
|---|---:|---:|---:|---:|
| q001 | 9 | 9 | 0 | 100 % |
| q010 | 9 | 9 | 0 | 100 % |
| q013 | 6 | 6 | 0 | 100 % |
| q028 | 6 | 6 | 0 | 100 % |
| q021 | 9 | 9 | 0 | 100 % |

**100 % across the board.** Every promoted ID had qrels relevance = 0 — i.e. none of them were the user's intended target.

Cross-check: was the ground-truth IO anywhere in `kw_entity`'s top-10?

| qid | gt | found in kw_entity top-10? |
|---|---:|---|
| q001 | 42665 | **NO** |
| q010 | 83611 | **NO** |
| q013 | 6454 | **NO** |
| q028 | 3878 | **NO** |
| q021 | 341072 | **NO** |

In all 5 cases, the entity strategy demoted the GT fonds completely off the user-visible top-10 and replaced it with leaf-level descendants of structurally-related fonds.

---

## Diagnosis

**Cause (C) dominates, with (A) as co-factor. (B) is ruled out.**

The pattern across all 5 queries is identical:

1. The query is a **known-item title query** — it asks for a specific archival description (the parent fonds-level IO). The qrels mark only that one IO as relevance=2.
2. The entity strategy correctly finds tens of thousands of IOs whose extracted NER values mention the query terms — overwhelmingly `ORG` entities like "French Mission Office", "Department of Education", "Department of Agriculture", "Northern Ireland". These are **legitimate** semantic matches; they're not low-quality substring accidents (§4 = 0 %).
3. But every fonds in atom propagates its title down into the NER values of **every descendant** (leaf box-folder items get the parent's `ORG` extracted into their NER set during the AI pipeline's per-IO NER step). So `Correspondence French Mission` produces ~18,800 NER row matches across thousands of leaf items belonging to the French Mission Office hierarchy.
4. The merger sees entity reporting 200 BM25-tied hits at strong scores, applies the configured weights (kw=0.35, entity=0.40), and the entity signal swamps the keyword signal. The original rank-1 fonds gets ranked below dozens of leaf items that share its NER terms.
5. **Cause (C):** entity strategy is being asked to answer a known-item query (where the right target is one IO at fonds level), but its competence is content-mention recall (where the right target is "every IO that mentions X"). The strategy is doing exactly what it was designed for — and that's the wrong shape for this query type. Evidence: §4 shows 0 % low-relevance noise and §5 shows 100 % qrels=0 false positives, while §2 shows the promoted IDs are all *structurally appropriate* for an entity-mention query (descendants of the same hierarchy).
6. **Cause (A) co-factor:** even if entity is the wrong strategy for the query, the merger weights make the demotion worse than it has to be. With kw=0.35, entity=0.40, even a small entity-set will outweigh a single-IO keyword peak. Halving entity to 0.15 and lifting keyword to 0.55 (regime B in #31) is a plausible mitigation; this run can't tell us whether it suffices.
7. **Cause (B) explicitly ruled out:** the "LIKE %term% noise" hypothesis assumes the matching set is contaminated by `DATE`/`CARDINAL`/`NORP` accidents. §3 + §4 show that's wrong on this corpus — the matching entities are 90%+ `ORG`, and 0 % of promoted IDs are dominated by low-relevance types. The substring-matching pathology that the original brief assumed exists in the source data (§3 examples like "Mission"→"Commission") but doesn't drive the failure mode being diagnosed: the live entity strategy uses ES with token-boundary analysis, not MySQL LIKE, and even with token-boundary matching the demotion still happens because the underlying `ORG` entity inheritance is real.

The honest paper framing is: **for this corpus, entity strategy is structurally a bad fit for known-item retrieval, regardless of how the entity matching is implemented.** The fix isn't better entity matching; it's per-query-type weighting of entity contribution, or detecting query intent (known-item vs topical) before merging. That's a real architectural finding — it makes #31 (the weight-sweep) the right next experiment, and #33 (exact-match intervention) probably won't move the needle.

**Run #31 next** to confirm whether knocking entity weight down to 0.05–0.15 recovers most of baseline's title-query performance. If it does, the per-query-type adaptive weighting becomes the paper's central contribution.

---

## Reproducing this report

```bash
# All inputs already exist:
ls storage/discovery-eval/run-1/{baseline,kw_entity}.json
ls tests/discovery/qrels-simulated.csv

# The Python analysis scripts are inline in the corresponding bash blocks
# in the issue thread; no permanent code added by this investigation.
# atom DB queries used:
#   SELECT COUNT(*) FROM ahg_ner_entity WHERE status IN ('approved','pending') AND (entity_value LIKE '%TOK%' OR ...);
#   SELECT entity_type, COUNT(*) FROM ... GROUP BY entity_type ORDER BY COUNT(*) DESC;
#   SELECT object_id, entity_type, COUNT(*) FROM ahg_ner_entity WHERE object_id IN (PROMOTED_IDS) AND (...) GROUP BY object_id, entity_type;
```

No code was modified. No new evaluation configs were run.

---

## Validate when GPU returns

This diagnosis is parked pending GPU availability. The findings below are derived from the v1 simulated qrels run on CPU-only inference (Ollama on `192.168.0.112`, no `192.168.0.78`). When GPU access returns, re-validate the following before promoting any production change:

1. **Re-run §4 with the post-GPU corpus.** The 0 % low-relevance-type finding depends on the current NER population. If the AI pipeline regenerates NER on GPU and adds DATE/CARDINAL/NORP types at higher volume, the noise distribution may shift. Re-execute the per-query LIKE-type breakdown against `atom.ahg_ner_entity` post-reindex.

2. **Re-run §5 false-positive rate** under Run #2 (with LLM-paraphrase Generator C.2 enabled). The GT primary IO may behave differently against paraphrased queries than against the title-slice queries that drove this diagnosis. If the GT IO also drops out of `kw_entity`'s top-10 under paraphrased title queries, the (C) framing holds; if it doesn't, paraphrase-style queries may benefit from entity weighting that title-slice queries don't.

3. **Re-confirm cause (C) dominance** by repeating the 5-most-degraded analysis on the Run #2 outputs. If the ranking of degraded queries flips (e.g. subject queries become the dominant degradation under paraphrased Run #2), the per-query-type adaptive-weights conclusion may need revisiting.

4. **Validate the recommendation** that the immediate fix is weight-tuning, not an entity-strategy code change. Specifically: confirm that **Regime B from #31 still recovers ≥95 % of baseline title nDCG@10** after Run #2 swaps Generator C.1 → C.2. If recovery drops to <80 %, the fix is more subtle than weight tuning alone.

Until GPU returns, the production weights stay at Regime A (kw=0.35, entity=0.40, hier=0.25) — the current setting that this report measured against. The recommended Regime B change is **not** applied to production yet.
