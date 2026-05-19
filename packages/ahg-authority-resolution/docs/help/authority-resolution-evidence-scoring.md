# Authority Resolution - Evidence Scoring

The evidence layer is what makes the engine archivally defensible. Given the ranked candidates that the candidate-generator service has persisted to `ahg_mention_candidate`, the `EvidenceScorer` asks every registered evaluator that supports the mention's `entity_type` for a per-dimension `EvidenceSignal`, persists the collected signals plus their underlying data, recomputes the composite score, and re-ranks the candidate list.

This article documents the four signal kinds, the ten evaluators (five for persons / orgs, five for places), the composite-score formula, the persistence shape, and the honest behaviour you should expect on a freshly imported corpus.

## The four signals

Every evaluator emits exactly one signal per (candidate, dimension):

| Signal | Meaning | Composite-score weight |
|---|---|---|
| **match** | Overlap between mention context and candidate authority data on this dimension | **+0.10** |
| **conflict** | Direct contradiction (for example: candidate end-year strictly less than nearby mention year) | **-0.30** |
| **silent** | Data exists on both sides; no overlap and no contradiction | 0.00 |
| **absent** | Data missing entirely on one or both sides | 0.00 |

`silent` and `absent` carry the same scoring weight today but are deliberately distinct in the `evidence_signals` JSON. The distinction is essential for the review UI ("evidence considered but no overlap" vs "no evidence to consider") and lets a later tuning pass weight them differently without re-shaping the JSON schema.

## Person and organisation evaluators

| Evaluator | Dimension | What it does |
|---|---|---|
| `TemporalEvaluator` | `temporal` | Parses the candidate's `actor_i18n.dates_of_existence` and compares the range against the years in `mention_context.nearby_dates`. Match when any nearby year falls inside the candidate's date span. |
| `GeographicEvaluator` | `geographic` | Joins `event JOIN object_term_relation JOIN term_i18n` for known event-places, plus the free-text `actor_i18n.places`, and compares against `nearby_places`. Match on overlap. |
| `RelationalEvaluator` | `relational` | Walks the `relation` table bidirectionally where both sides resolve to an `actor` row. Match when an other-side name aligns with a PERSON / ORG entry in `co_occurring_entities`. |
| `RoleEvaluator` | `role` | Substring scan of `actor_i18n.{history, functions, mandates, legal_status}` for any token in `role_language_tokens`. Match on substring hit. |
| `ConflictEvaluator` | `conflict` | Hard exclusion. `nearby_date_year > candidate.end_year` emits `conflict`. Birth-year-after-mention is **not** checked because birth year is often missing in archival records. |

## Place evaluators

| Evaluator | Dimension | What it does |
|---|---|---|
| `HierarchicalEvaluator` | `hierarchical` | Walks `term.parent_id` upward (skipping place taxonomy root id 110) and compares ancestor `term_i18n.name` to `nearby_places`. Match on overlap. |
| `PriorEvaluator` | `prior` | Wraps `DocumentPriorService`. Returns `match` when the candidate term is in the top 3 of the fonds-level distribution with at least 2 hits. |
| `CoOccurringPersonEvaluator` | `co_occurring` | Resolves PERSON / ORG entries in `co_occurring_entities` against `actor.authorized_form_of_name` and checks for `relation` rows linking any resolved actor to the candidate term. |
| `PlaceConflictEvaluator` | `place_conflict` | Stub returning `silent` until term-level dates exist in the taxonomy. |
| `ScaleEvaluator` | `scale` | Regex token map (city / town / village / province / kingdom / ... / bookshop / etc.) over `surrounding_text` vs the candidate's display name. Emits `conflict` when the text describes a place family but the candidate name suffix is `facility`. |

## Composite-score formula

```
composite = clamp(name_similarity + Sum(weight(signal_i)),  0, 1)
```

where:

- `name_similarity` is the candidate's Jaro-Winkler score against the mention surface form (already on the row as `name_similarity_score`).
- `weight(match) = +0.10`, `weight(conflict) = -0.30`, `weight(silent) = weight(absent) = 0`.
- The result is clamped to `[0, 1]`.

After scoring, candidates are re-ranked by `(composite_score DESC, name_similarity_score DESC, candidate_display_name ASC)` and `rank_position` is rewritten.

### Worked examples

- name_similarity 1.0000, one `match` signal: `clamp(1.0 + 0.10, 0, 1) = 1.0000`.
- name_similarity 0.5763, one `conflict` signal: `0.5763 - 0.30 = 0.2763`.
- name_similarity 0.84, two `match` signals and one `conflict`: `0.84 + 0.20 - 0.30 = 0.74`.

A single conflict is usually enough to flip a tight ranking.

## Persistence shape

`evidence_signals` JSON column - one flat map `{ "dimension": "signal" }` per candidate:

```json
{
  "temporal":   "absent",
  "geographic": "absent",
  "relational": "absent",
  "role":       "absent",
  "conflict":   "absent"
}
```

`evidence_data` JSON column - one flat map `{ "dimension": <evaluator-specific-payload> }` per candidate. Used by the review UI to render the badge tooltips:

```json
{
  "temporal":   { "reason": "no_candidate_dates_and_no_nearby_dates" },
  "geographic": { "reason": "no_candidate_locations", "nearby_places": [...] }
}
```

`composite_score` DECIMAL(6,4) - the value above, NULL until scoring has run.

## What fires on a fresh corpus (honest assessment)

On a freshly imported corpus most signals will be `absent`. This is the expected outcome, not a bug. Typical conditions on a fresh corpus:

- A small handful of actors have `dates_of_existence` populated; most do not, so the temporal evaluator has nothing to compare on.
- A small handful of actors have `history` text; most do not, so the role evaluator has no substring corpus.
- Few or no `event` rows with `actor_id IS NOT NULL`, so the geographic evaluator has no event-place to compare.
- Most actor-to-actor relations are not yet captured (the dominant pattern in early imports is object-to-actor, the NameAccessPoint shape), so the relational evaluator finds nothing.
- All place terms sit directly under taxonomy root 110, so the hierarchical evaluator skips up immediately and emits `absent`.
- No mention has been LINKED yet inside a fonds, so the place prior is an empty distribution and the prior evaluator returns `absent`.

As soon as place hierarchies, actor dates, and a few linked resolutions are populated, the match / conflict signals start materially shifting composite scores and rank positions. The most useful dimensions on a partially populated corpus tend to be `scale` and `co_occurring` (both PLACE) plus the silent / absent distinction.

## Idempotency

Every call to `auth-res:score-evidence` for a mention truncates its prior `evidence_*` columns by overwrite and re-ranks. There is no append behaviour. Re-running after the candidate set changes (a Task-3 re-run) does the right thing automatically.

The `DocumentPriorService` cache is keyed per fonds with a 24-hour TTL; manual invalidation is:

```sql
DELETE FROM ahg_settings WHERE setting_key LIKE 'authority_resolution.prior.%';
```

## Usage

```bash
# Score one mention
sudo -u www-data php artisan auth-res:score-evidence 138 --show

# Score one candidate row
sudo -u www-data php artisan auth-res:score-evidence --candidate-id=12 --show

# Score every mention on an information object
sudo -u www-data php artisan auth-res:score-evidence --object-id=901990 --show

# Dispatch as a queue job (sync connection just runs inline; database / redis enqueues)
sudo -u www-data php artisan auth-res:score-evidence 138 --async
```

The async path requires `php artisan queue:work` running unless `QUEUE_CONNECTION=sync`. The job retries up to 2 times with a 180-second timeout and logs progress under the `auth-res.score-evidence.job` channel.

## End-to-end verification

Out-of-band test against an actor with synthetic dates (1945-2020) and history "Prominent archivist. Pioneer in digital preservation":

| Evaluator | Synthetic input | Expected signal | Reason |
|---|---|---|---|
| TemporalEvaluator | nearby_dates `[1980]` | `match` | 1980 in [1945, 2020] |
| ConflictEvaluator | nearby_dates `[2030]` | `conflict` | 2030 > 2020 end-year |
| RoleEvaluator | role_language_tokens `["pioneer in"]` | `match` | Substring hit in history |
| ScaleEvaluator | surrounding "kingdom of ...", candidate "Kyoto Bookshop" | `conflict` | Facility vs nation family |

Composite-score verification: name_similarity 0.5763 with one conflict -> `0.5763 - 0.30 = 0.2763`. This validates the formula and would change rank positions in any mention with multiple candidates of overlapping name scores.

## File map (for developers)

- `src/Services/Evidence/EvidenceSignal.php` - constants (MATCH / CONFLICT / SILENT / ABSENT) + weight table + `make($signal, $data)` factory
- `src/Services/Evidence/EvaluatorInterface.php` - `dimension()`, `supports($entityType)`, `evaluate($mention, $context, $candidate)` contract
- `src/Services/Evidence/EvidenceDateUtil.php` - shared 4-digit-year regex parser + `nearby_dates` JSON walker
- `src/Services/Evidence/TemporalEvaluator.php` ... `ScaleEvaluator.php` - the ten evaluators
- `src/Services/Evidence/DocumentPriorService.php` - lazy per-fonds place-prior cache (24-hour TTL)
- `src/Services/EvidenceScorer.php` - orchestrator. `scoreCandidate($id)`, `scoreAllForMention($mentionId)`; persists and transactional re-rank by `composite_score DESC`
- `src/Jobs/ScoreMentionEvidenceJob.php` - `ShouldQueue` wrapper used by `--async`
- `src/Console/Commands/ScoreEvidenceCommand.php` - the artisan command above

## Related

- "AHG Authority Resolution - User Guide" - how the signals surface on the review screen.
- "Authority Resolution - Review Screen Reference" - per-dimension badges and tooltips.
- "Authority Resolution - CLI Commands" - the artisan surface, including `auth-res:reprocess` which chains generate + score in one call.
- "ahg-authority-resolution - Technical Documentation" - the full service catalogue.
