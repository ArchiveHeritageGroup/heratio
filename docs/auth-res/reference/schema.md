# Schema

Seven tables. All `InnoDB` + `utf8mb4_unicode_ci`. No FKs to base AtoM
tables (decouples from base schema migrations). All enumerated values
are `VARCHAR(N) + COMMENT` per CLAUDE.md (no MySQL `ENUM`).

Install file:
[`packages/ahg-authority-resolution/database/install.sql`](https://github.com/ArchiveHeritageGroup/heratio/blob/main/packages/ahg-authority-resolution/database/install.sql).

## ahg_mention

One workflow row per promoted NER entity.

| column           | type                | notes                                                   |
|------------------|---------------------|---------------------------------------------------------|
| `id`             | BIGINT UNSIGNED PK  | auto                                                    |
| `ner_entity_id`  | BIGINT UNSIGNED     | UNIQUE; FK to `ahg_ner_entity.id` ON DELETE CASCADE     |
| `object_id`      | INT                 | back-ref to `information_object.id`                     |
| `entity_type`    | VARCHAR(20)         | PERSON, ORG, GPE, PLACE                                 |
| `state`          | VARCHAR(30)         | pending, linked, parked, rejected, new_record_created   |
| `promoted_at`    | DATETIME            | DEFAULT CURRENT_TIMESTAMP                                |
| `updated_at`     | DATETIME            | ON UPDATE CURRENT_TIMESTAMP                              |

Indexes: `uq_mention_ner_entity (ner_entity_id)`,
`idx_mention_object (object_id)`,
`idx_mention_state (state, entity_type)`.

## ahg_mention_context

Neighbourhood context packet (1:1 with mention).

| column                      | type             | notes                                            |
|-----------------------------|------------------|--------------------------------------------------|
| `id`                        | BIGINT UNSIGNED  |                                                  |
| `mention_id`                | BIGINT UNSIGNED  | UNIQUE; FK CASCADE                               |
| `character_offset_start`    | INT UNSIGNED     | NULL ok                                          |
| `character_offset_end`      | INT UNSIGNED     | NULL ok                                          |
| `paragraph_offset_start`    | INT UNSIGNED     | NULL ok                                          |
| `paragraph_offset_end`      | INT UNSIGNED     | NULL ok                                          |
| `surrounding_text_before`   | TEXT             | up to 150 chars before mention                   |
| `surrounding_text_after`    | TEXT             | up to 150 chars after mention                    |
| `ner_model_version`         | VARCHAR(100)     | tag of the upstream NER run                      |
| `real_confidence`           | DECIMAL(6,4)     | NULL until upstream API exposes per-entity score |
| `co_occurring_entities`     | JSON             | array of `{ner_entity_id?, value, type, distance_tokens}` |
| `nearby_dates`              | JSON             | array of `{value, normalized?, distance_tokens}` |
| `nearby_places`             | JSON             | array of `{value, term_id?, distance_tokens}`    |
| `role_language_tokens`      | JSON             | array of `{token, position_offset, kind}` (kinship/witness/location/movement/other) |
| `computed_at`               | DATETIME         | DEFAULT CURRENT_TIMESTAMP                         |

## ahg_mention_candidate

Ranked candidates per mention. Top-N controlled by
`authority_resolution.candidate_top_n` (default 5).

| column                    | type                | notes                                           |
|---------------------------|---------------------|-------------------------------------------------|
| `id`                      | BIGINT UNSIGNED PK  |                                                 |
| `mention_id`              | BIGINT UNSIGNED     | FK CASCADE                                      |
| `rank_position`           | TINYINT UNSIGNED    | 1..N; re-assigned on each scoring pass          |
| `candidate_source`        | VARCHAR(30)         | mysql_actor, fuseki_agent, mysql_term, fuseki_place |
| `candidate_authority_id`  | INT NULL            | actor.id or term.id (NULL when Fuseki-only)     |
| `candidate_fuseki_uri`    | VARCHAR(2048)       | NULL ok                                         |
| `candidate_display_name`  | VARCHAR(1000)       |                                                 |
| `name_similarity_score`   | DECIMAL(6,4)        | Jaro-Winkler base score                         |
| `evidence_signals`        | JSON                | per-dimension match/conflict/silent/absent      |
| `evidence_data`           | JSON                | underlying values per dimension (UI render)     |
| `composite_score`         | DECIMAL(6,4) NULL   | weighted aggregate; set by EvidenceScorer       |
| `computed_at`             | DATETIME            |                                                 |

Indexes: `idx_candidate_mention_rank (mention_id, rank_position)`,
`idx_candidate_authority (candidate_authority_id)`.

## ahg_mention_decision

Immutable audit. One row per decision event. `evidence_snapshot` and
`candidates_visible_snapshot` are **frozen** so "what did the archivist
see" is answerable from this row alone.

| column                          | type                | notes                                              |
|---------------------------------|---------------------|----------------------------------------------------|
| `id`                            | BIGINT UNSIGNED PK  |                                                    |
| `mention_id`                    | BIGINT UNSIGNED     | FK CASCADE                                         |
| `decision_type`                 | VARCHAR(30)         | link, link_different, create_new, park, reject     |
| `chosen_candidate_id`           | BIGINT UNSIGNED NULL| FK SET NULL                                        |
| `chosen_authority_id`           | INT NULL            | actor.id or term.id                                |
| `original_system_top_score`     | DECIMAL(6,4) NULL   | rank-1 composite at decision time                  |
| `archivist_user_id`             | INT                 |                                                    |
| `decided_at`                    | DATETIME            | DEFAULT CURRENT_TIMESTAMP                           |
| `fuseki_graph_uri`              | VARCHAR(2048) NULL  | set asynchronously by Task-8 writer                |
| `evidence_snapshot`             | JSON                | frozen copy of evidence_signals + evidence_data    |
| `candidates_visible_snapshot`   | JSON                | array of `{candidate_id, display_name, rank}`      |

Indexes: `idx_decision_mention (mention_id, decided_at)`,
`idx_decision_archivist (archivist_user_id, decided_at)`.

## ahg_mention_park

One **active** row per mention (UNIQUE on `mention_id`). Deleted on
unpark or on terminal decision.

| column                       | type                | notes                                          |
|------------------------------|---------------------|------------------------------------------------|
| `id`                         | BIGINT UNSIGNED PK  |                                                |
| `mention_id`                 | BIGINT UNSIGNED     | UNIQUE; FK CASCADE                             |
| `parked_by_user_id`          | INT                 |                                                |
| `parked_at`                  | DATETIME            | DEFAULT CURRENT_TIMESTAMP                       |
| `reason`                     | TEXT                | required                                       |
| `new_candidate_available`    | TINYINT(1)          | flipped by `auth-res:scan-parked`              |
| `new_candidate_check_at`     | DATETIME NULL       | last scan timestamp                            |

Indexes: `idx_park_user (parked_by_user_id, parked_at)`,
`idx_park_new_candidate (new_candidate_available, parked_at)`.

## ahg_ner_feedback

One row per `decision_type=reject` decision. Source-of-truth for the
NER retraining pipeline.

| column                  | type                | notes                                              |
|-------------------------|---------------------|----------------------------------------------------|
| `id`                    | BIGINT UNSIGNED PK  |                                                    |
| `mention_id`            | BIGINT UNSIGNED     |                                                    |
| `ner_entity_id`         | BIGINT UNSIGNED     |                                                    |
| `decision_id`           | BIGINT UNSIGNED     |                                                    |
| `source_text`           | MEDIUMTEXT          | the full document text NER saw                     |
| `mention_value`         | VARCHAR(1000)       | rejected surface form                              |
| `mention_entity_type`   | VARCHAR(20)         | what NER said it was                               |
| `mention_offset_start`  | INT UNSIGNED NULL   |                                                    |
| `mention_offset_end`    | INT UNSIGNED NULL   |                                                    |
| `rejection_reason`      | TEXT                | required                                           |
| `archivist_user_id`     | INT                 |                                                    |
| `ner_model_version`     | VARCHAR(100) NULL   |                                                    |
| `training_exported`     | TINYINT(1)          | flipped by `auth-res:export-ner-feedback`          |
| `exported_at`           | DATETIME NULL       |                                                    |
| `created_at`            | DATETIME            |                                                    |

Indexes: `idx_feedback_decision`, `idx_feedback_mention`,
`idx_feedback_unexported (training_exported, created_at)`.

## ahg_authority_lookup_cache

Cache for external authority lookups (VIAF / Wikidata / GeoNames / TGN
/ GND / ISNI / SAGNC).

| column         | type                | notes                                              |
|----------------|---------------------|----------------------------------------------------|
| `id`           | BIGINT UNSIGNED PK  |                                                    |
| `source`       | VARCHAR(30)         | viaf, wikidata, geonames, tgn, gnd, isni, sagnc    |
| `entity_type`  | VARCHAR(20)         | PERSON, ORG, PLACE                                 |
| `query_text`   | VARCHAR(500)        | the literal query string sent to the adapter      |
| `payload`      | JSON                | normalised result + raw_payload                    |
| `license_note` | VARCHAR(500) NULL   | per-source attribution                             |
| `retrieved_at` | DATETIME            | DEFAULT CURRENT_TIMESTAMP                           |
| `ttl_seconds`  | INT UNSIGNED        | per-source from `ahg_settings`                     |

Indexes: `uq_cache_source_query (source, entity_type, query_text)`,
`idx_cache_retrieved (retrieved_at)`.

Eviction policy: lazy. `PrefillEngine` checks `retrieved_at +
ttl_seconds` on read; if stale, refetch + UPSERT. Cron-based eviction
not required.

## No FK to base tables

`ahg_mention.object_id` does **not** carry a FK to `information_object`,
matching the existing `ahg_ner_entity` convention. This decouples the
engine from base-AtoM / base-Heratio schema migrations: a base table can
be rebuilt without dropping all engine rows. Orphan rows are tolerated;
the review UI filters them out.

## ER summary

```
ahg_ner_entity ─1:1─ ahg_mention ─1:1─ ahg_mention_context
                              │
                              ├─1:N─ ahg_mention_candidate
                              │
                              ├─1:N─ ahg_mention_decision ─1:0..1─ ahg_ner_feedback
                              │
                              └─1:0..1─ ahg_mention_park

(independent)  ahg_authority_lookup_cache
```
