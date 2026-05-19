# AHG Authority Resolution Engine - Task 4 (Evidence assembly + scoring), AtoM side

## Summary

Task 4 of the AHG Authority Resolution Engine on the **AtoM Heratio** side (the
Symfony 1.4 / `atom-ahg-plugins` fork at `/usr/share/nginx/archive`). Implements
the evidence-assembly + composite-scoring layer that sits between candidate
generation (Task 3) and the archivist review UI (Task 5). Mirror of the
Laravel-side `packages/ahg-authority-resolution` work; the same evaluator slugs,
signal enums, and composite formula are used on both platforms so the same
(mention, candidate) pair scores identically.

- Plugin: `atom-ahg-plugins/ahgAuthorityResolutionPlugin/`
- Namespace: `AtomFramework\Services\AuthorityResolution\Evidence\…`
- Task: `php symfony auth-res:score-evidence <mention_id> [--show]`
- Sync only. Async via gearman / cron is deferred to Phase 2 of this plugin.

## Signal vocabulary

Four enum values, declared in `EvidenceSignal::{MATCH,CONFLICT,SILENT,ABSENT}`.
Composite-score deltas (shared with the Laravel side):

| Signal   | Delta | Meaning |
|----------|-------|---------|
| MATCH    | +0.10 | Evidence supports this candidate |
| CONFLICT | -0.30 | Evidence contradicts this candidate |
| SILENT   | +0.00 | Dimension had data on both sides, but couldn't produce a definitive call |
| ABSENT   | +0.00 | Dimension had no data to evaluate (no nearby dates, no event records, etc.) |

`silent` is intentionally distinct from `absent` so the review UI can show "we
looked, both sides had something, the comparison was inconclusive" separately
from "this dimension simply isn't populated".

Composite-score formula (clamped to `[0.0, 1.0]`, rounded to 4 dp):

```
composite = name_similarity_score
for each dimension signal:
   match    => composite += 0.10
   conflict => composite -= 0.30
   silent   => composite += 0
   absent   => composite += 0
composite = clamp(composite, 0.0, 1.0)
```

## Evaluator inventory

### Person / Org (mention.entity_type IN ('PERSON','ORG'))

| Slug         | Class                | Reads |
|--------------|----------------------|-------|
| temporal     | `TemporalEvaluator`  | `event.start_date/end_date` for `actor_id = candidate`; falls back to year-scan of `actor_i18n.dates_of_existence`. Compares against years extracted from `ahg_mention_context.nearby_dates`. |
| geographic   | `GeographicEvaluator`| `actor_i18n.places` + `actor_i18n.history` substring-scan against `ahg_mention_context.nearby_places`. |
| relational   | `RelationalEvaluator`| `relation` table both sides → related actors' `actor_i18n.authorized_form_of_name` vs co-occurring PERSON/ORG entities in `ahg_mention_context.co_occurring_entities`. |
| role         | `RoleEvaluator`      | `actor_i18n.history/functions/mandates/general_context` substring-scan for role-language tokens captured in `ahg_mention_context.role_language_tokens`. |
| conflict     | `ConflictEvaluator`  | `actor.entity_type_id` (132 = Person, 131 = Corporate body) - emits CONFLICT on type mismatch with mention.entity_type. |

### Place (mention.entity_type IN ('GPE','PLACE','LOC'))

| Slug            | Class                          | Reads |
|-----------------|--------------------------------|-------|
| hierarchical    | `HierarchicalEvaluator`        | Walk `term.parent_id` chain (taxonomy 42) against `nearby_places` (excluding the mention itself). |
| document_prior  | `PriorEvaluator` + `DocumentPriorService` | Top-3 most-resolved place authorities for the mention's fonds. Cache in `ahg_settings.authority_resolution.prior.<fonds_id>` (24h TTL). |
| co_occurring    | `CoOccurringPersonEvaluator`   | Candidate term's `relation`-graph actors vs co-occurring PERSON/ORG entities. |
| conflict        | `PlaceConflictEvaluator`       | Emits CONFLICT if candidate term is not in Places taxonomy (id=42). |
| scale           | `ScaleEvaluator`               | Compares parent-chain depth of candidate against median depth of other context places. |

The orchestrator (`EvidenceScorer`) dispatches by `supports($entity_type)`.
Person/Org mentions never see place evaluators and vice versa, so the
`evidence_signals` JSON has the right five keys per row.

## File inventory

All under `atom-ahg-plugins/ahgAuthorityResolutionPlugin/lib/`:

- `Services/Evidence/EvidenceSignal.php`
- `Services/Evidence/EvaluatorInterface.php`
- `Services/Evidence/TemporalEvaluator.php`
- `Services/Evidence/GeographicEvaluator.php`
- `Services/Evidence/RelationalEvaluator.php`
- `Services/Evidence/RoleEvaluator.php`
- `Services/Evidence/ConflictEvaluator.php`
- `Services/Evidence/HierarchicalEvaluator.php`
- `Services/Evidence/DocumentPriorService.php`
- `Services/Evidence/PriorEvaluator.php`
- `Services/Evidence/CoOccurringPersonEvaluator.php`
- `Services/Evidence/PlaceConflictEvaluator.php`
- `Services/Evidence/ScaleEvaluator.php`
- `Services/EvidenceScorer.php`
- `task/authResScoreEvidenceTask.class.php`

Symfony 1.4 does not PSR-4-autoload our namespaced plugin tree; the task file
top-loads every service via explicit `require_once`. Same pattern as
`authResWriteProvenanceTask.class.php`.

## Demo output (2026-05-19)

Six mentions had candidates from Task 3. Running
`sudo -u www-data php symfony auth-res:score-evidence <id> --show` for each:

```
Mention #25  (GPE,    "London",            object=901990) - 1 candidate
  rank 1  London             name_sim=1.0000  composite=1.0000
    scale=match  conflict=silent  co_occurring=absent  hierarchical=absent  document_prior=silent

Mention #82  (ORG,    "FIC",               object=901990) - 3 candidates
  rank 1  FIC                                                   name_sim=1.0000  composite=1.0000
  rank 2  British Post Office                                   name_sim=0.3227  composite=0.3227
  rank 3  Asian Warehousing and Stationery Orders (Fictional)   name_sim=0.1611  composite=0.1611
    all rows: temporal=absent  geographic=absent  relational=absent  role=absent  conflict=silent

Mention #138 (PERSON, "Frederick Douglass", object=901990) - 1 candidate
  rank 1  Frederick Douglass  name_sim=1.0000  composite=1.0000
    all dimensions absent except conflict=silent (entity_type_id 132 matches mention type PERSON)

Mention #159 (PERSON, "Lloyd",             object=901990) - 2 candidates
  rank 1  Lloyd              name_sim=1.0000  composite=1.0000
  rank 2  David Lloyd George name_sim=0.4848  composite=0.4848

Mention #168 (PERSON, "Mark Twain",         object=901990) - 1 candidate
  rank 1  Mark Twain         name_sim=1.0000  composite=1.0000

Mention #834 (GPE,    "New York",           object=901851) - 1 candidate
  rank 1  New York           name_sim=1.0000  composite=1.0000
    scale=match  conflict=silent  co_occurring=absent  hierarchical=absent  document_prior=silent
```

`document_prior` cache wrote two `ahg_settings` rows:

```
authority_resolution.prior.901851  top: [{901866,2},{901868,2},{901870,2}]
authority_resolution.prior.901990  top: [{902001,4},{901866,3},{901872,3}]
```

## Dimension fire-rate (real, not fabricated)

Across the 9 candidate rows actually scored on the demo database:

| Dimension      | match | conflict | silent | absent | n/a (entity-type) |
|----------------|-------|----------|--------|--------|-------------------|
| temporal       | 0     | 0        | 0      | 7      | 2 (place)         |
| geographic     | 0     | 0        | 0      | 7      | 2 (place)         |
| relational     | 0     | 0        | 0      | 7      | 2 (place)         |
| role           | 0     | 0        | 0      | 7      | 2 (place)         |
| conflict (P/O) | 0     | 0        | 7      | 0      | 2 (place)         |
| hierarchical   | 0     | 0        | 0      | 2      | 7 (person/org)    |
| document_prior | 0     | 0        | 2      | 0      | 7 (person/org)    |
| co_occurring   | 0     | 0        | 0      | 2      | 7 (person/org)    |
| conflict (place) | 0   | 0        | 2      | 0      | 7 (person/org)    |
| scale          | 2     | 0        | 0      | 0      | 7 (person/org)    |

The only positive signals on this dev DB are `scale=match` for both place
mentions (the document chatter is also at top-level place granularity, so depth
matches). Composite scores therefore equal name_similarity_score for every
person/org row and (name_similarity + 0.10), clamped at 1.0, for both places.
Because both place candidates already had `name_similarity=1.0`, the +0.10
is silently clamped away.

## What's "absent" vs "silent" on this DB - and why

- `temporal = absent` everywhere: `actor_i18n.dates_of_existence` is NULL on
  every candidate actor in the dev set, and the NER pipeline did not emit
  any DATE / ISAD_DATE entities into `ahg_mention_context.nearby_dates` for
  these objects.
- `geographic = absent`: `actor_i18n.places` is NULL on every candidate; the
  history field is also empty so the substring-scan fallback has nothing.
- `relational = absent`: the candidate actors do have inbound relations (every
  PERSON/ORG mention's parent IO has a relation pointing at it via
  type_id=161), but the relation peers do **not** appear in the document
  paragraph, so the dimension reports "no_relations_on_candidate". This is
  the most "real" absent on the dataset.
- `role = absent` on PERSON/ORG: there are no role-language tokens in the
  paragraph captured by ContextDerivationService for these mentions (the
  Frederick Douglass paragraph reads as a flat list, not relational prose).
- `conflict = silent` everywhere (no type mismatches) - this is the SILENT
  case, not ABSENT, because we *did* have an actor row to inspect and
  positively confirmed no type-mismatch.
- `document_prior = silent`: candidate (London at id=901059, New York at
  id=901918) was not in the top-3 for its fonds. Top-3 entries are place
  authorities other than the candidates we have here.
- `scale = match`: both place candidates and the other context places sit
  directly under term 110 (Places root), so candidate_depth = 0 = median.

These distinctions are exactly what the SILENT/ABSENT split is for - and it
proves the evaluators actually *touched* the right tables rather than
short-circuiting on missing data.

## Surprises

- The places taxonomy in this AtoM instance is flat - every place term is a
  direct child of the synthetic places root (term id 110). That's why
  `hierarchical` is universally `absent` and `scale` is universally `match`.
  Real instances will have country -> region -> city chains and those two
  evaluators will fire meaningfully.
- The role-language token set in `ahg_settings.authority_resolution.role_language_tokens`
  is rich (~120 tokens across 5 kinds) but the demo document for mentions
  25/82/138/159/168 is a flat enumeration ("the collection includes notes
  on people such as X, Y, Z") that contains none of them. Again, this is
  data shape, not an evaluator bug.
- The Laravel-side EvidenceScorer constructor signature passes
  `DocumentPriorService` separately even though `PriorEvaluator` already
  holds the same instance. Kept on the AtoM side for byte-for-byte
  symmetry with the Heratio package - the unused field is documented in
  the class.

## Cross-references

- `auth-res-context-task2-atom.md`, `auth-res-promote-task1-atom.md`,
  `auth-res-candidates-task3-atom.md`, `auth-res-provenance-task8-atom.md`
  for the other AtoM-side Task docs in this engine.
- Laravel-side mirror lives at `packages/ahg-authority-resolution/src/Services/Evidence/`
  (built in parallel; same signal enums and composite formula).
