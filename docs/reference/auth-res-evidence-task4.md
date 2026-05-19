# Authority Resolution Engine - Task 4: Evidence Assembly and Scoring

Task 4 of the AHG Authority Resolution Engine on the Laravel Heratio side. Given every candidate persisted by Task 3 in `ahg_mention_candidate`, the engine asks every registered Evaluator that supports the mention's `entity_type` for a per-dimension Signal, writes the collected signals + raw data back to the row, recomputes the composite score, and re-ranks the mention's candidate list.

Status: Implemented 2026-05-19. Tasks 0, 1, 2, 3, 8 already shipped; Task 4 adds the multi-dimensional scoring layer. Tasks 5 (review UI), 6 (decision-write), 7 (re-scan) remain.

## Setup

- Package: `packages/ahg-authority-resolution/` (PSR-4 `AhgAuthorityResolution\` -> `src/`).
- Schema: `ahg_mention_candidate.evidence_signals`, `evidence_data`, `composite_score` already exist from Task 0. No new DDL.
- Cache: `ahg_settings.authority_resolution.prior.<fonds_id>` rows are written by `DocumentPriorService` on first call per fonds. TTL 24h. Rows accumulate as new fonds get touched; they are never deleted automatically.
- The Provider registers every evaluator as a singleton, the `DocumentPriorService` as a singleton, and the `EvidenceScorer` as a singleton with the evaluator iterable + prior service injected.

## Signal semantics

The four canonical signal kinds, each emitted by every evaluator:

| Signal | Meaning | Composite-score weight |
|---|---|---|
| `match` | Overlap between mention context and candidate authority data | **+0.10** |
| `conflict` | Direct contradiction (e.g. candidate end-year < mention year) | **-0.30** |
| `silent` | Data exists on both sides; no overlap and no contradiction | 0.0 |
| `absent` | Data MISSING entirely on one or both sides | 0.0 |

`silent` and `absent` carry the same scoring weight today but are deliberately distinct in the `evidence_signals` JSON. Distinguishing them is essential for the review UI ("evidence considered but no overlap" vs "no evidence to consider") and lets a later phase weight them differently without re-shaping the JSON schema.

## File map

| Path | Purpose |
|---|---|
| `src/Services/Evidence/EvidenceSignal.php` | Constants (MATCH/CONFLICT/SILENT/ABSENT) + weight table + `::make($signal, $data)` factory |
| `src/Services/Evidence/EvaluatorInterface.php` | `dimension()`, `supports($entityType)`, `evaluate($mention, $context, $candidate)` contract |
| `src/Services/Evidence/EvidenceDateUtil.php` | Shared 4-digit-year regex parser + nearby_dates JSON walker |
| `src/Services/Evidence/TemporalEvaluator.php` | PERSON/ORG. `actor_i18n.dates_of_existence` parsed against `mention_context.nearby_dates` years |
| `src/Services/Evidence/GeographicEvaluator.php` | PERSON/ORG. `event JOIN object_term_relation JOIN term_i18n` for known event-places + `actor_i18n.places` free text, compared against `nearby_places` |
| `src/Services/Evidence/RelationalEvaluator.php` | PERSON/ORG. `relation` table walked bidirectionally where both sides resolve to an `actor` row; other-side names matched against PERSON/ORG entries in `co_occurring_entities` |
| `src/Services/Evidence/RoleEvaluator.php` | PERSON/ORG. Substring scan of `actor_i18n.{history,functions,mandates,legal_status}` for any `role_language_tokens.token` value |
| `src/Services/Evidence/ConflictEvaluator.php` | PERSON/ORG hard-exclusion. `nearby_date_year > candidate.end_year` => `conflict`. Birth-year-after-mention is NOT checked (birth year often missing). |
| `src/Services/Evidence/HierarchicalEvaluator.php` | PLACE. `term.parent_id` walk skipping place taxonomy root id 110; ancestor `term_i18n.name` compared to `nearby_places` |
| `src/Services/Evidence/DocumentPriorService.php` | Computes fonds-level distribution of LINKED PLACE mentions (state='linked', rank=1, source='mysql_term'). Cached in `ahg_settings` for 24h. |
| `src/Services/Evidence/PriorEvaluator.php` | PLACE. Wraps `DocumentPriorService`. Returns `match` when candidate term is in top-3 with >= 2 hits. |
| `src/Services/Evidence/CoOccurringPersonEvaluator.php` | PLACE. Resolves PERSON/ORG entries in `co_occurring_entities` against `actor.authorized_form_of_name`; checks for `relation` rows linking any resolved actor to the candidate term |
| `src/Services/Evidence/PlaceConflictEvaluator.php` | PLACE. Stub returning `silent` until term-level dates exist in the taxonomy. |
| `src/Services/Evidence/ScaleEvaluator.php` | PLACE. Regex token map (city/town/village/province/kingdom/.../bookshop/etc.) over surrounding_text vs candidate display_name; `conflict` when text describes a place family but candidate name suffix is `facility`. |
| `src/Services/EvidenceScorer.php` | Orchestrator. `scoreCandidate($id)`, `scoreAllForMention($mentionId)`; persists + transactional re-rank by composite_score desc. |
| `src/Jobs/ScoreMentionEvidenceJob.php` | `ShouldQueue` wrapper for `scoreAllForMention`. Used by `--async`. |
| `src/Console/Commands/ScoreEvidenceCommand.php` | `auth-res:score-evidence {mention_id?} {--candidate-id=} {--object-id=} {--show} {--async}` |
| `src/Providers/AhgAuthorityResolutionServiceProvider.php` | Updated: 10 evaluator singletons, `DocumentPriorService` singleton, `EvidenceScorer` factory, `ScoreEvidenceCommand` registered. Existing Task 0-3 wiring untouched. |

## Composite-score formula

```
composite = clamp(name_similarity_score + Sum(weight(signal_i)), 0, 1)
```

A candidate with name_similarity=1.0000 and one `match` lands at 1.0000 (clamped). A candidate with name_similarity=0.58 and one `conflict` drops to 0.28. Re-ranking after scoring uses (composite_score desc, name_similarity_score desc, candidate_display_name asc).

## Persistence shape

`evidence_signals` JSON column (per row): a flat map `{ "dimension": "signal" }`.

```json
{
  "temporal": "absent",
  "geographic": "absent",
  "relational": "absent",
  "role": "absent",
  "conflict": "absent"
}
```

`evidence_data` JSON column (per row): a flat map `{ "dimension": <evaluator-specific-payload> }`.

```json
{
  "temporal": { "reason": "no_candidate_dates_and_no_nearby_dates" },
  "geographic": { "reason": "no_candidate_locations", "nearby_places": [...] }
}
```

## Usage

```bash
# Score one mention
sudo -u www-data php artisan auth-res:score-evidence 138 --show

# Score one candidate row
sudo -u www-data php artisan auth-res:score-evidence --candidate-id=12 --show

# Score every mention on an information object
sudo -u www-data php artisan auth-res:score-evidence --object-id=901990 --show

# Dispatch as a queue job (sync connection just runs inline; database/redis enqueues)
sudo -u www-data php artisan auth-res:score-evidence 138 --async
```

Async path requires `php artisan queue:work` running unless QUEUE_CONNECTION=sync.

## What fires on this DB today

Demo on the 7 mentions in Heratio that have Task-3 candidates (object 901990):

| Mention | Type | Value | Evaluators that fired | All-absent? |
|---|---|---|---|---|
| 15 | GPE | Gdańsk | hierarchical, document_prior, co_occurring, conflict, scale | 2/5 absent, 3/5 silent |
| 24 | GPE | Kyoto (2 candidates: Kyoto + Kyoto Bookshop) | same 5 | 3/5 absent, 2/5 silent |
| 25 | GPE | London | same 5 | 2/5 absent, 3/5 silent |
| 56 | GPE | U.S. | same 5 | 3/5 absent, 2/5 silent |
| 67 | GPE | Baltic Port | same 5 | 3/5 absent, 2/5 silent |
| 138 | PERSON | Frederick Douglass | temporal, geographic, relational, role, conflict | 5/5 absent |
| 252 | ORG | Baltic Port | same 5 person/org evaluators | 5/5 absent |

Net effect on composite_score: zero across all 8 candidates. Name similarity dominates; no rank changes.

This is the **expected** outcome on this dev DB:

- All PERSON/ORG candidates (Frederick Douglass, Baltic Port actor) sit at `actor_i18n.dates_of_existence IS NULL`, `history IS NULL`, no `event` rows, no actor-to-actor `relation` rows.
- All PLACE candidates (Gdańsk, Kyoto, Kyoto Bookshop, London, U.S., Baltic Port term) sit directly under the place taxonomy root (parent_id=110), so `HierarchicalEvaluator` skips up immediately.
- No mention under object 901990 has been LINKED yet (state=`linked`), so `DocumentPriorService` returns an empty distribution for the fonds and `PriorEvaluator` returns `absent`.
- `nearby_dates` is empty on every context row scored (the NER pipeline left DATE entities outside the chosen paragraph).
- `surrounding_text` has at most one scale token (`port` from "Baltic Port") that maps to the `settlement` family but candidate names also map there, so the result is `silent`, not match.

The engine **correctly distinguishes** `absent` from `silent`:

- mention 15 -> `hierarchical: absent` (Gdańsk has no useful ancestors), `co_occurring: silent` (mention has 7 resolved PERSON/ORG co-occurring entities, candidate has no relation rows linking any of them)
- mention 138 -> `temporal: absent` (BOTH candidate dates AND nearby_dates empty), `geographic: absent` (candidate location list empty even though nearby_places has 5 entries)

## End-to-end behaviour verified with synthetic context

Out-of-band test against actor 900001 (Dr. Sarah van der Merwe, dates "1945-2020", history "Prominent South African archivist and historian. Pioneer in digital preservation."):

| Evaluator | Synthetic input | Signal | Notes |
|---|---|---|---|
| TemporalEvaluator | nearby_dates `[1980]` | **match** | 1980 in [1945,2020] |
| ConflictEvaluator | nearby_dates `[2030]` | **conflict** | 2030 > 2020 end-year |
| RoleEvaluator | role_language_tokens `["pioneer in"]` | **match** | substring hit in history |
| ScaleEvaluator | surrounding "kingdom of ...", candidate "Kyoto Bookshop" | **conflict** | facility vs nation family |

Composite-score weight verification: candidate at name_similarity 0.5763 with one conflict signal -> 0.5763 - 0.30 = 0.2763 (validates the formula and would change rank in any mention with multiple candidates of overlapping name scores).

## Why most signals are absent today (honest assessment)

The Heratio dev DB has:

- 18 actors with `dates_of_existence` (out of ~960 actors)
- 20 actors with `history` text
- 3 `event` rows with `actor_id IS NOT NULL`
- 724 `relation` rows but most are object<->actor (the AtoM NameAccessPoint pattern) - actor<->actor relation count is in single digits
- All `term` rows in taxonomy 42 with `parent_id` = 110 (root) - no place hierarchy yet

So the dimensions that fire most usefully right now are `scale` and `co_occurring` (both PLACE), and the `silent`/`absent` distinction. As soon as place hierarchies, actor dates, and a few LINKED resolutions are populated, the match/conflict signals will start materially shifting composite_scores and rank positions.

## Re-running and idempotency

Task 4 is fully idempotent: every call to `auth-res:score-evidence` for a mention truncates its prior `evidence_*` columns by overwrite and re-ranks. There is no append behaviour. Re-running after the candidate set changes (Task 3 re-run) does the right thing automatically.

The DocumentPriorService cache is keyed per fonds with a 24h TTL; manual invalidation is `DELETE FROM ahg_settings WHERE setting_key LIKE 'authority_resolution.prior.%';`.

## Async path

`--async` dispatches `ScoreMentionEvidenceJob`. With `QUEUE_CONNECTION=sync` (the Heratio default today) this runs inline. With `database` or `redis`, the job sits on the queue until `php artisan queue:work` picks it up. The job retries up to 2 times, timeout 180s, logs progress via `Log::info('auth-res.score-evidence.job', ...)`.
