# Service API

Every service is bound as a singleton in
`AhgAuthorityResolutionServiceProvider::register()`. Resolve with
`app(AhgAuthorityResolution\Services\<Class>::class)` or inject through
the constructor.

## Workflow orchestration

### PromoteToMentionService

Copies selected `ahg_ner_entity` rows into `ahg_mention` and computes
`ahg_mention_context`. The promotion is the workflow's entry point.

```php
public function promoteForObject(int $objectId, array $entityTypes = ['PERSON','ORG','GPE','LOC','PLACE']): int;
public function fetchSourceText(int $objectId): string;
```

`fetchSourceText()` is also used by `NerFeedbackService` to snapshot the
document text into `ahg_ner_feedback.source_text` on reject.

### ContextDerivationService

Builds the neighbourhood context packet stored in
`ahg_mention_context`. Reads `authority_resolution.role_language_tokens`
to detect kinship / witness / location / movement / other tokens.

```php
public function deriveContext(int $mentionId): array;
public function loadTokens(): array;
```

## Candidate generation

### CandidateGeneratorService

Walks every registered adapter, ranks by name similarity, persists
top-N to `ahg_mention_candidate`.

```php
public function generate(int $mentionId, ?int $topN = null): array;  // returns inserted candidate ids
```

Adapters (each implements `CandidateAdapterInterface`):

| Class                | Source              | Entity types     |
|----------------------|---------------------|------------------|
| `MysqlActorAdapter`  | local actor table   | PERSON, ORG, GPE |
| `MysqlTermAdapter`   | local term table    | GPE, LOC, PLACE  |
| `FusekiAgentAdapter` | Fuseki agents graph | PERSON, ORG, GPE |
| `FusekiPlaceAdapter` | Fuseki places graph | GPE, LOC, PLACE  |

## Evidence scoring

### EvidenceScorer

Task-4 orchestrator. Runs every applicable evaluator over every
candidate, writes signals + composite_score, re-ranks.

```php
public function scoreCandidate(int $candidateId): ?array;
public function scoreAllForMention(int $mentionId): array;  // returns {scored_count: int}
```

### Evaluators

Each evaluator implements `EvaluatorInterface` (`supports`, `evaluate`).
Returns an `EvidenceSignal` per (dimension, candidate).

**Person / Org:**

| Class                  | Dimension                                              |
|------------------------|--------------------------------------------------------|
| `TemporalEvaluator`    | date-span overlap                                      |
| `GeographicEvaluator`  | place overlap                                          |
| `RelationalEvaluator`  | co-occurring entity overlap                            |
| `RoleEvaluator`        | role-language consistency                              |
| `ConflictEvaluator`    | hard contradictions (e.g. wrong sex, wrong death date) |

**Place:**

| Class                       | Dimension                                            |
|-----------------------------|------------------------------------------------------|
| `HierarchicalEvaluator`     | admin-hierarchy parent / child                       |
| `PriorEvaluator`            | document-level place prior (via `DocumentPriorService`) |
| `CoOccurringPersonEvaluator`| bound-to-person evidence                             |
| `PlaceConflictEvaluator`    | hard contradictions                                  |
| `ScaleEvaluator`            | admin-level vs. context (continent / country / city) |

### DocumentPriorService

Lazy per-object cache of "what places does this document talk about, in
aggregate". Used by `PriorEvaluator`.

```php
public function priorFor(int $objectId): array;
public function reset(int $objectId): void;
```

### EvidenceDateUtil

Pure helper for normalising date strings (`"1837"` ->
`"1837-XX-XX"`, `"c. 1840s"` -> range, etc.). No state.

## Decision recording

### DecisionRecorder

Writes `ahg_mention_decision`, updates `ahg_mention.state`, fires
`DecisionProvenanceWriter`. On reject, fires `NerFeedbackService` inside
a try/catch.

```php
public function recordLink(int $mentionId, int $candidateId, int $userId): int;
public function recordLinkDifferent(int $mentionId, int $candidateId, int $userId): int;
public function recordCreateNew(int $mentionId, int $newAuthorityId, int $userId, array $fieldDecisions): int;
public function recordPark(int $mentionId, int $userId, string $reason): int;
public function recordReject(int $mentionId, int $userId, string $reason): int;
```

Each returns the new `ahg_mention_decision.id`.

### DecisionProvenanceWriter

Emits RDF-Star to the decisions named graph. Reads
`authority_resolution.decisions_graph_uri`. Has a default constant
`DEFAULT_GRAPH_URI` for the seeded value.

```php
public function write(int $decisionId): bool;
```

Synchronous on decide. Backfillable via
`php artisan auth-res:write-provenance {decision_id}`.

### FieldProvenanceWriter

Emits per-field RDF-Star for `create_new` decisions to the
field-provenance named graph. Called by `AuthorityCreator`.

```php
public function writeForNewAuthority(int $decisionId, int $authorityId, array $fieldDecisions): int;
```

### AuthorityCreator

Creates a fresh `actor` (or `term`) row, populates i18n tables, fires
`FieldProvenanceWriter`. Used only on `create_new`.

```php
public function createForMention(int $mentionId, array $fieldDecisions, int $userId): int;  // new authority id
```

## Park queue

### ParkQueueService

```php
public function listFor(?int $userId, ?string $entityType, ?bool $newCandidateOnly,
                        ?\DateTimeImmutable $sinceParked, ?string $reasonQuery,
                        string $sortBy, int $limit): array;
public function countsByArchivist(): array;
public function unparkAndRereview(int $mentionId, int $userId): array;
public function scanForNewCandidates(): int;
```

Called from `ParkQueueController` + `auth-res:scan-parked` +
`auth-res:reprocess-parked`.

## External lookup

### PrefillEngine

Walks `lookup.precedence`, queries each enabled adapter, returns
normalised field map for the new-authority form. Caches via
`ahg_authority_lookup_cache`.

```php
public function prefillForMention(int $mentionId): array;
public function search(string $query, string $entityType): array;
```

### LookupAdapterInterface

Implemented by every external adapter:

```php
public function supports(string $entityType): bool;
public function search(string $query, string $entityType): array;
public function getName(): string;
public function getRateLimit(): int;
public function getTtlSeconds(): int;
public function getLicenseNote(): string;
```

### AbstractLookupAdapter

Shared base implementing rate-limit (token bucket in cache),
cache hit/miss against `ahg_authority_lookup_cache`, HTTP-timeout
plumbing.

### Concrete adapters

- `ViafAdapter`
- `WikidataAdapter`
- `GeoNamesAdapter`
- `TgnAdapter`
- `GndAdapter`
- `IsniAdapter`
- `SagncAdapter` (stub)

Each is a singleton; each respects per-source settings keys.

## NER feedback

### NerFeedbackService

```php
public function capture(int $mentionId, int $decisionId, string $rejectionReason, int $userId): int;
public function exportUnexported(string $outputDir, int $limit = 0): array;
```

`exportUnexported()` returns `{written_path: string, row_count: int}`.

## Wiring summary

```
PromoteToMentionService
    \-> ContextDerivationService

CandidateGeneratorService
    \-> [MysqlActorAdapter, MysqlTermAdapter, FusekiAgentAdapter, FusekiPlaceAdapter]

EvidenceScorer
    \-> [Temporal, Geographic, Relational, Role, Conflict,
         Hierarchical, Prior, CoOccurringPerson, PlaceConflict, Scale]
    \-> DocumentPriorService

ParkQueueService
    \-> CandidateGeneratorService
    \-> EvidenceScorer

DecisionRecorder
    \-> DecisionProvenanceWriter
    \-> NerFeedbackService    (on reject only)

AuthorityCreator
    \-> PrefillEngine
    \-> FieldProvenanceWriter

PrefillEngine
    \-> [ViafAdapter, WikidataAdapter, GeoNamesAdapter, TgnAdapter,
         GndAdapter, IsniAdapter, SagncAdapter]
```
