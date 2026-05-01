# Discovery: entity-strategy precision audit (issue #32)

_Generated: 2026-04-28. Eval source: `storage/discovery-eval/run-1/kw_entity.json` + `pre_merge_ranks.entity` from `heratio.ahg_discovery_log`. Read-only - no code changes._

## Headline

- **Entity-strategy precision@10 (in isolation, mean across 100 queries): 1.4%** (median 0.0%).
- Mean recall@10 in isolation: 5.2%.
- Mean false-positive count in entity top-10: 9.06 of 10 (i.e. 90.6% of returned slots are docs with qrels relevance = 0).
- Mean NER rows that match a query's tokens (LIKE %t%): 9 rows surfacing 2 distinct `object_id`s. The entity strategy then ranks/truncates this set.

## By query type

| type | n | p@10 mean | p@10 median | r@10 mean | fp@10 mean | mean entity returned | queries with p@10=0 |
|------|---|-----------|-------------|-----------|------------|----------------------|---------------------|
| title | 30 | 0.7% | 0.0% | 6.7% | 9.60 | 88 | 28/30 |
| subject | 40 | 2.2% | 0.0% | 0.4% | 9.53 | 96 | 34/40 |
| scope_np | 20 | 1.5% | 0.0% | 15.0% | 9.85 | 97 | 17/20 |
| typo | 10 | 0.0% | 0.0% | 0.0% | 4.00 | 35 | 10/10 |

_Spread of mean p@10 across query types: 2.2% (0.0% → 2.2%)._

## Sample of 10 random queries (entity in isolation)

### 1. `q098` (typo) - `A.N.C.`
- entity returned-set size: **0** rows; entity top-10 p@10 = **0.0%**, fp@10 = **0/10**
- NER rows that match query tokens: **0** rows surfacing **0** distinct object_ids
- entity_type distribution of the matched NER rows: `{}`

### 2. `q080` (scope_np) - `Manpower Development`
- entity returned-set size: **100** rows; entity top-10 p@10 = **10.0%**, fp@10 = **9/10**
- NER rows that match query tokens: **0** rows surfacing **0** distinct object_ids
- entity_type distribution of the matched NER rows: `{}`

### 3. `q063` (subject) - `AZAPO`
- entity returned-set size: **100** rows; entity top-10 p@10 = **0.0%**, fp@10 = **10/10**
- NER rows that match query tokens: **0** rows surfacing **0** distinct object_ids
- entity_type distribution of the matched NER rows: `{}`

### 4. `q026` (title) - `SOUTH AFRICAN COMMUNIST`
- entity returned-set size: **100** rows; entity top-10 p@10 = **0.0%**, fp@10 = **10/10**
- NER rows that match query tokens: **27** rows surfacing **6** distinct object_ids
- entity_type distribution of the matched NER rows: `{'GPE': 15, 'ORG': 11, 'ISAD_NAME': 1}`
- top-5 most-frequent matched `entity_value` strings:
  - `South Africa` ×4
  - `South Korea` ×3
  - `South` ×2
  - `the African National Congress` ×2
  - `South Africay` ×2

### 5. `q016` (title) - `Subjects Files Finland`
- entity returned-set size: **100** rows; entity top-10 p@10 = **0.0%**, fp@10 = **10/10**
- NER rows that match query tokens: **3** rows surfacing **1** distinct object_ids
- entity_type distribution of the matched NER rows: `{'GPE': 3}`
- top-5 most-frequent matched `entity_value` strings:
  - `Finland` ×3

### 6. `q018` (title) - `Subject Files`
- entity returned-set size: **33** rows; entity top-10 p@10 = **0.0%**, fp@10 = **10/10**
- NER rows that match query tokens: **0** rows surfacing **0** distinct object_ids
- entity_type distribution of the matched NER rows: `{}`

### 7. `q086` (scope_np) - `South Africa`
- entity returned-set size: **100** rows; entity top-10 p@10 = **0.0%**, fp@10 = **10/10**
- NER rows that match query tokens: **30** rows surfacing **5** distinct object_ids
- entity_type distribution of the matched NER rows: `{'GPE': 19, 'ORG': 10, 'ISAD_NAME': 1}`
- top-5 most-frequent matched `entity_value` strings:
  - `South Africa` ×4
  - `South Korea` ×3
  - `South` ×2
  - `the African National Congress` ×2
  - `South Africay` ×2

### 8. `q050` (subject) - `Cathedral`
- entity returned-set size: **100** rows; entity top-10 p@10 = **0.0%**, fp@10 = **10/10**
- NER rows that match query tokens: **3** rows surfacing **2** distinct object_ids
- entity_type distribution of the matched NER rows: `{'ORG': 3}`
- top-5 most-frequent matched `entity_value` strings:
  - `Cathedral` ×3

### 9. `q081` (scope_np) - `National Education Conference`
- entity returned-set size: **100** rows; entity top-10 p@10 = **0.0%**, fp@10 = **10/10**
- NER rows that match query tokens: **41** rows surfacing **7** distinct object_ids
- entity_type distribution of the matched NER rows: `{'ORG': 39, 'PERSON': 2}`
- top-5 most-frequent matched `entity_value` strings:
  - `National Autonomous University of Mexico` ×5
  - `International Red Cross` ×4
  - `National Diet Library` ×4
  - `National Museum` ×4
  - `National Park Service` ×4

### 10. `q025` (title) - `Reports Additions`
- entity returned-set size: **100** rows; entity top-10 p@10 = **0.0%**, fp@10 = **10/10**
- NER rows that match query tokens: **0** rows surfacing **0** distinct object_ids
- entity_type distribution of the matched NER rows: `{}`

## False-positive rate where entity hurt recall

- Queries where `kw_entity` recall@10 < `baseline` recall@10: **15** of 100.
- FP rate among entity-promoted documents in those top-10s: **100.0%** (150 FPs / 150 promoted slots).

## Verdicts

1. **Headline precision (entity in isolation, p@10 mean): 1.4%.**

2. **Substring matching as dominant noise source:** 23 of 100 queries (23.0%) had at least one top-5 matched `entity_value` where a query token appears as a strict substring of a longer word (e.g. `mission` → `commission`). This is NOT obviously dominant in the data - if the rate were ≥ 40% we'd flag it as the primary noise driver. Caveat: since #24 the live `entitySearch()` runs through Elasticsearch `match_phrase` + `match` (not raw MySQL LIKE), so this MySQL-side substring evidence reflects the legacy NER path, not the production code path. The ES `match` clause has its own token-boundary semantics - see #33 for the ES-side reframing.

3. **Query-type stratification:** no - within ~10 points across types. Spread of mean p@10 across `title`/`subject`/`scope_np`/`typo` is 2.2%. Typo queries are a special case: the entity strategy returns far fewer rows (mean 35) because typo'd tokens don't match anything, so it can't even compete; the other three types all return ~88–97 rows of which ~9 in 10 are wrong.

## What's actually wrong (beyond the three required findings)

The dominant noise driver is **broad single-token matching**, not strict substring noise. Two pieces of evidence in the sample:

- Query `q086` "South Africa" matches NER rows for `South Korea` (×3), `South` (×2), and `South Africay` (×2). The shared word "South" is enough for the row to qualify.
- Query `q081` "National Education Conference" matches `National Autonomous University of Mexico` (×5), `International Red Cross` (×4), `National Museum` (×4) - anything with "National" qualifies; nothing with all three query tokens needed.

Combined with the saturation behaviour (entity returns 88–100 rows for almost every non-typo query) and the 90.6% FP rate in the top-10, the entity strategy is essentially **a high-recall, very-low-precision token OR**. It finds documents that share *any* common word with the query and ranks them on whatever signal it has, which by the data is largely noise. The fused `kw_entity` config dilutes the keyword baseline with this noise - that's why it underperformed baseline on 15 of 100 queries with a 100% FP rate among the entity-promoted slots.

The fix space is bounded by the ES-path code (since #24): require all query tokens (`bool/must` instead of `bool/should`), or restrict to `match_phrase` so the query phrase must appear contiguously, or shift to `nerEntityValues.raw` exact-token matching. Issue #33 is set up to A/B these.

## Methodology notes

- Entity-in-isolation results extracted from `pre_merge_ranks.entity` (a 100-id list per log row), not from the fused `retrieved_top10` in `kw_entity.json`. Top-10 = first 10 ids of that list.
- Precision@10 = `count(rel ≥ 1 in top10) / 10`. Counts qrels rows per `query_id` regardless of grade.
- NER LIKE counts run against `atom.ahg_ner_entity` for tokens of length ≥ 4. Cold table since #24.
- Reproducibility: `random.seed(20260428)` for the 10-query sample. Eval `code_git_sha`: `1e5d1fd5`. `qrels_file_hash`: `sha256:1d7ab3bbfd298cc0b48cb811048332375b6bc86e9c24b11faf558f3483150921`.

