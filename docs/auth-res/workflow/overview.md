# Workflow overview

## The premise

NER models are noisy. Authority linking matters too much to leave to a
confidence score. The engine's job is to assemble enough evidence around a
mention that an archivist can decide in seconds, not minutes - and to record
*why* the decision was made so it can be defended later.

## The five-step flow

### 1. Extract

NER runs upstream (Stanza / spaCy / a custom transformer model) and writes
rows to `ahg_ner_entity`. Each row carries:

- `entity_value` (the surface form)
- `entity_type` (PERSON, ORG, GPE, LOC, ...)
- `object_id` (the source information_object)
- `confidence` (model self-score; **see note below**)

!!! warning "Hardcoded NER confidence"
    NER confidence is currently a hardcoded constant (0.85) on both
    codebases pending upstream API changes that expose per-mention scores
    end-to-end. Tracked in:

    - atom-ahg-plugins#19
    - heratio#132

    The engine therefore treats `confidence` as advisory only; the
    evidence layer is the real signal.

### 2. Promote

Not every NER row needs human review. The promote step copies *selected*
rows into `ahg_mention` and computes a [neighbourhood context
packet](#neighbourhood-context) (`ahg_mention_context`):

- character + paragraph offsets
- 150 chars before / after
- co-occurring entities, nearby dates, nearby places
- role-language tokens ("son of", "located at", "ruled by", ...)

Triggered by:

- the artisan command `auth-res:promote-sample` (manual / sample)
- the production pipeline, called from the ingest hook (out of scope here)

### 3. Generate candidates

For each mention the engine queries every registered candidate adapter:

| Adapter             | Source                                  | Entity types          |
|---------------------|-----------------------------------------|-----------------------|
| `MysqlActorAdapter` | local `actor` table                     | PERSON, ORG, GPE      |
| `MysqlTermAdapter`  | local `term` table (places)             | GPE, LOC, PLACE       |
| `FusekiAgentAdapter`| `<dataset>/agents/*` Fuseki graph       | PERSON, ORG, GPE      |
| `FusekiPlaceAdapter`| `<dataset>/places/*` Fuseki graph       | GPE, LOC, PLACE       |

Candidates are ranked first by name similarity (Jaro-Winkler), then
persisted to `ahg_mention_candidate` (top-N controlled by
`authority_resolution.candidate_top_n`, default 5).

### 4. Score evidence

The `EvidenceScorer` runs every applicable evaluator over every candidate
and writes the per-dimension signals (`match` / `conflict` / `silent` /
`absent`) plus a `composite_score` back to the candidate row.

For PERSON / ORG mentions:

- `TemporalEvaluator` - date span overlap
- `GeographicEvaluator` - place overlap
- `RelationalEvaluator` - co-occurring entity overlap
- `RoleEvaluator` - role-language consistency
- `ConflictEvaluator` - hard contradictions

For GPE / PLACE mentions:

- `HierarchicalEvaluator` - admin-hierarchy parent/child
- `PriorEvaluator` - document-level place prior
- `CoOccurringPersonEvaluator` - bound-to-person evidence
- `PlaceConflictEvaluator` - hard contradictions
- `ScaleEvaluator` - admin-level vs. context

Final score:

```
composite = clamp( name_similarity_score + Sum(weight(signal_i)),  0, 1 )
  weight(match)    = +0.10
  weight(conflict) = -0.30
  weight(silent)   = 0.0
  weight(absent)   = 0.0
```

Candidates are then re-ranked by `composite_score` (desc), tie-broken by
`name_similarity_score`, then by display name.

### 5. Decide

The archivist opens the review screen, sees:

- the mention in context (highlighted span + before/after)
- each candidate row with composite + per-evaluator badge
- the five action buttons

and clicks one of:

- **Link** - confirms top candidate
- **Link different** - confirms a non-top candidate
- **Create new** - opens the pre-fill wizard (uses lookup cache)
- **Park** - defers the decision, optionally with reason
- **Reject** - this mention is not actually an entity of the claimed type

Each decision writes:

- a row to `ahg_mention_decision` (immutable audit)
- on `link` / `link_different`: updates `ahg_ner_entity.linked_actor_id`
  (back-compat with the discovery pipeline)
- on `create_new`: a new `actor` (or `term`) row plus field-provenance
  triples
- on `park`: a row to `ahg_mention_park`
- on `reject`: a row to `ahg_ner_feedback` (becomes NER training data)
- on any decision: RDF-Star provenance to Fuseki

## Neighbourhood context

The "context packet" is what makes the engine evidence-based. Every
candidate gets ranked against the same packet, so the archivist sees a
fair comparison.

Stored in `ahg_mention_context`:

| column                       | example                                        |
|------------------------------|------------------------------------------------|
| character_offset_start/end   | 412 / 425                                      |
| paragraph_offset_start/end   | 3 / 3                                          |
| surrounding_text_before      | "...the regent witnessed by..."                |
| surrounding_text_after       | "...who also signed the..."                    |
| co_occurring_entities (JSON) | `[{value:"Mzilikazi", type:"PERSON", ...}]`    |
| nearby_dates (JSON)          | `[{value:"1837", normalized:"1837-XX-XX"}]`    |
| nearby_places (JSON)         | `[{value:"Mosega", term_id:42}]`               |
| role_language_tokens (JSON)  | `[{token:"son of", position_offset:-2, kind:"kinship"}]` |

## Idempotency

Every stage is idempotent:

- Re-promoting a mention is a no-op (unique on `ner_entity_id`).
- Re-generating candidates clears and re-inserts the candidate set.
- Re-scoring a candidate just overwrites its signals.
- A re-issued decision is *not* allowed; the audit row is immutable. To
  "change a decision", record a new decision; the audit trail shows both.
