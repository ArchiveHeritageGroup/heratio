# Authority Resolution Engine - Task 3: Candidate Generation

Task 3 of the AHG Authority Resolution Engine on the Laravel Heratio side. Given a promoted mention (`ahg_mention` row), the engine asks every adapter that supports the mention's `entity_type` for candidates, scores them by name similarity, and persists the top-N into `ahg_mention_candidate`.

Status: Implemented 2026-05-19. Tasks 0, 1, 2, 8 already shipped; Task 4 (multi-dimensional scoring), Task 5 (review UI), Task 6 (decision write), Task 7 (re-scan) still outstanding.

## Setup

- Package: `packages/ahg-authority-resolution/` (PSR-4 `AhgAuthorityResolution\` -> `src/`).
- Schema: `ahg_mention_candidate` already exists from the package install (Task 0). No new DDL.
- Default top-N: `ahg_settings.authority_resolution.candidate_top_n` = `"5"`, auto-seeded by `AhgAuthorityResolutionServiceProvider::autoSeedCandidateTopN()` on first boot. Fallback to 5 if the setting is missing or NULL. Override per-call via `--top=N` or the `$topN` argument on `CandidateGeneratorService::generate()`.

## File map

| Path | Purpose |
|---|---|
| `src/Services/Adapters/CandidateAdapterInterface.php` | `supports($entityType)` + `search($query, $entityType, $limit)` contract |
| `src/Services/Adapters/MysqlActorAdapter.php` | PERSON/ORG - `actor.entity_type_id IN (132,131)` JOIN `actor_i18n` ON id, `authorized_form_of_name LIKE '%query%'` |
| `src/Services/Adapters/MysqlTermAdapter.php` | GPE/PLACE/LOC - `term.taxonomy_id=42` JOIN `term_i18n` ON id, `name LIKE '%query%'` |
| `src/Services/Adapters/FusekiAgentAdapter.php` | STUB returning `[]`. Wired in Task 8/future against `urn:heratio:auth-res:graph:decisions` sibling dataset. |
| `src/Services/Adapters/FusekiPlaceAdapter.php` | STUB returning `[]`. Same Fuseki story as agent adapter. |
| `src/Services/CandidateGeneratorService.php` | Orchestrator: load mention, filter adapters, score, sort, transaction-wrapped DELETE + INSERT |
| `src/Console/Commands/GenerateCandidatesCommand.php` | `auth-res:generate-candidates {mention_id?} {--object-id=} {--show} {--top=}` |
| `src/Providers/AhgAuthorityResolutionServiceProvider.php` | Updated: 4 adapter singletons, `CandidateGeneratorService` singleton with adapter array, `GenerateCandidatesCommand` registered, `candidate_top_n` auto-seed |

## Scoring spec

Name-only at Task 3 (`composite_score == name_similarity_score`). `evidence_signals` and `evidence_data` remain NULL; Task 4 will populate them.

```php
$q = trim(mb_strtolower($mentionValue, 'UTF-8'));
$c = trim(mb_strtolower($candidateDisplayName, 'UTF-8'));
if ($q === '' || $c === '') return 0.0;
similar_text($q, $c, $percent);
$score = $percent / 100.0;
if (strpos($c, $q) !== false) $score = min(1.0, $score + 0.05);
if ($q === $c) $score = 1.0;
return round($score, 4);
```

Sort descending by score, tie-break ascending by `display_name`. Top-N kept.

## Persistence flow

`CandidateGeneratorService::generate(int $mentionId, ?int $topN = null): array`:

1. Load mention + `entity_value` via JOIN on `ahg_ner_entity`. Empty list if not found.
2. Iterate adapters; for each `supports($entity_type)` adapter call `search($entity_value, $entity_type, 50)`.
3. Score every raw hit. Sort. Take top-N.
4. Inside `DB::transaction`: `DELETE FROM ahg_mention_candidate WHERE mention_id = ?`, then INSERT top-N with `rank_position` 1..N.
5. Return inserted IDs in rank order.

Idempotent. Re-running on the same mention re-derives candidates from current authority-store state.

## Sample demo output (2026-05-19)

Spec sample (5 mentions from objects 901990 / 901851 / 902316):

```
Mention #15 [GPE] "Gdańsk" (object 901990): 1 candidates persisted.
  #1  mysql_term    auth=901897  score=1.0000  composite=1.0000   name : Gdańsk

Mention #24 [GPE] "Kyoto" (object 901990): 2 candidates persisted.
  #1  mysql_term    auth=901909  score=1.0000  composite=1.0000   name : Kyoto
  #2  mysql_term    auth=902257  score=0.5763  composite=0.5763   name : Kyoto Bookshop

Mention #25 [GPE] "London" (object 901990): 1 candidates persisted.
  #1  mysql_term    auth=901059  score=1.0000  composite=1.0000   name : London

Mention #56 [GPE] "U.S." (object 901990): 1 candidates persisted.
  #1  mysql_term    auth=902076  score=1.0000  composite=1.0000   name : U.S.

Mention #67 [GPE] "Baltic Port" (object 901990): 1 candidates persisted.
  #1  mysql_term    auth=902093  score=1.0000  composite=1.0000   name : Baltic Port
```

Cross-type sanity check:

```
Mention #138 [PERSON] "Frederick Douglass": #1 mysql_actor auth=902224 score=1.0000 (exact match)
Mention #955 [PERSON] "Geoffrey Chaucer"  : (no candidates) - no matching actor in DB; correct empty result.
Mention #252 [ORG]    "Baltic Port"       : #1 mysql_actor auth=902860 score=1.0000 (exact match)
```

In every case the obviously-right authority record is rank #1.

## Wrinkles

- `actor_i18n` and `term_i18n` have `(id, culture)` composite PKs - one authority record can have multiple i18n rows (one per culture). Adapters de-duplicate on authority id, preferring the row matching `source_culture` first so the surfaced display name lines up with the authoritative culture for that record.
- `similar_text` uses PHP's similarity algorithm, not Levenshtein. For "Kyoto" vs "Kyoto Bookshop" it produces ~52.6%, then the substring bonus (+0.05) pushes it to 0.5763. That's strong enough for Task 3 ordering; Task 4 will downgrade non-exact substring matches with conflict signals (e.g. wrong scale: city vs bookshop).
- "Baltic Port" appearing as both a GPE candidate (term 902093) and an ORG candidate (actor 902860) is honest: the source text uses the phrase in both senses and the NER produced both readings. The candidate generator just surfaces what each adapter sees.
- Fuseki adapters are deliberately stubs returning `[]`. The generator's adapter-iteration logic stays uniform so wiring the real Fuseki source later is a single-file change. Comment in each stub names the named-graph URI (`urn:heratio:auth-res:graph:decisions`) that Task 8 already writes into.

## Commands

```bash
# Single mention
sudo -u www-data php artisan auth-res:generate-candidates 15 --show

# All mentions on an information object
sudo -u www-data php artisan auth-res:generate-candidates --object-id=901990 --show

# Custom top-N
sudo -u www-data php artisan auth-res:generate-candidates 138 --top=10 --show
```
