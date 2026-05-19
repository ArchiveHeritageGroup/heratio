# ahg-authority-resolution - Technical Documentation

The `ahg/authority-resolution` package implements the AHG Authority Resolution Engine on the Laravel Heratio side. It turns NER-extracted name mentions into archivally-defensible authority links via an archivist-driven, evidence-based workflow with RDF-Star provenance. This is the developer reference: package layout, services, controllers, routes, ServiceProvider, schema, and dependencies.

For the user-facing perspective read "AHG Authority Resolution - User Guide" first.

## Package facts

- **Name**: `ahg/authority-resolution`
- **PSR-4 root**: `AhgAuthorityResolution\` -> `src/`
- **Path**: `packages/ahg-authority-resolution/`
- **Licence**: AGPL-3.0-or-later
- **PHP**: ^8.2
- **Service provider**: `AhgAuthorityResolution\Providers\AhgAuthorityResolutionServiceProvider`
- **Dependencies**: `ahg/core`, `ahg/ric`, `illuminate/support`, `illuminate/database`, `illuminate/http`

## Directory layout

```
packages/ahg-authority-resolution/
+- composer.json
+- database/
|  +- install.sql                # all 7 tables, idempotent CREATE TABLE IF NOT EXISTS
|  +- seed_lookup_settings.sql   # default lookup.* rows in ahg_settings
+- docs/
|  +- help/                      # this article and its siblings
+- resources/
|  +- views/                     # blade views (auth-res::)
|     +- queue.blade.php
|     +- review.blade.php
|     +- park.blade.php
|     +- create-new.blade.php
|     +- settings.blade.php
|     +- _candidate-card.blade.php
|     +- _evidence-row.blade.php
|     +- _link-different-modal.blade.php
|     +- _park-modal.blade.php
|     +- _reject-modal.blade.php
|     +- _park-row.blade.php
|     +- _park-dashboard-widget.blade.php
|     +- _prefill-field.blade.php
+- routes/
|  +- admin.php                  # 11 admin routes
+- src/
   +- Console/
   |  +- Commands/               # 11 artisan commands
   +- Http/
   |  +- Controllers/            # AuthorityReviewController, ParkQueueController, etc.
   +- Jobs/
   |  +- ScoreMentionEvidenceJob.php
   +- Providers/
   |  +- AhgAuthorityResolutionServiceProvider.php
   +- Services/
      +- PromoteToMentionService.php
      +- ContextDerivationService.php
      +- CandidateGeneratorService.php
      +- EvidenceScorer.php
      +- DecisionRecorder.php
      +- DecisionProvenanceWriter.php
      +- FieldProvenanceWriter.php
      +- AuthorityCreator.php
      +- ParkQueueService.php
      +- NerFeedbackService.php
      +- Candidate/
      |  +- CandidateAdapterInterface.php
      |  +- MysqlActorAdapter.php
      |  +- MysqlTermAdapter.php
      |  +- FusekiAgentAdapter.php
      |  +- FusekiPlaceAdapter.php
      +- Evidence/
      |  +- EvaluatorInterface.php
      |  +- EvidenceSignal.php
      |  +- EvidenceDateUtil.php
      |  +- TemporalEvaluator.php
      |  +- GeographicEvaluator.php
      |  +- RelationalEvaluator.php
      |  +- RoleEvaluator.php
      |  +- ConflictEvaluator.php
      |  +- HierarchicalEvaluator.php
      |  +- PriorEvaluator.php
      |  +- CoOccurringPersonEvaluator.php
      |  +- PlaceConflictEvaluator.php
      |  +- ScaleEvaluator.php
      |  +- DocumentPriorService.php
      +- Lookup/
         +- LookupAdapterInterface.php
         +- AbstractLookupAdapter.php
         +- PrefillEngine.php
         +- Adapters/
            +- ViafAdapter.php
            +- WikidataAdapter.php
            +- GeoNamesAdapter.php
            +- TgnAdapter.php
            +- GndAdapter.php
            +- IsniAdapter.php
            +- SagncAdapter.php
```

## Service catalogue

Every service is bound as a singleton in `AhgAuthorityResolutionServiceProvider::register()`. Resolve via `app(AhgAuthorityResolution\Services\<Class>::class)` or constructor injection.

### Workflow orchestration

```php
PromoteToMentionService::promoteForObject(int $objectId, array $entityTypes = ['PERSON','ORG','GPE','LOC','PLACE']): int
PromoteToMentionService::fetchSourceText(int $objectId): string

ContextDerivationService::deriveContext(int $mentionId): array
ContextDerivationService::loadTokens(): array
```

`fetchSourceText()` is also reused by `NerFeedbackService` to snapshot the document into `ahg_ner_feedback.source_text` on reject.

### Candidate generation

```php
CandidateGeneratorService::generate(int $mentionId, ?int $topN = null): array  // returns inserted candidate ids
```

Each adapter implements `CandidateAdapterInterface`:

| Class | Source | Entity types |
|---|---|---|
| `MysqlActorAdapter` | local `actor` table | PERSON, ORG, GPE |
| `MysqlTermAdapter` | local `term` table | GPE, LOC, PLACE |
| `FusekiAgentAdapter` | Fuseki `agents` graph | PERSON, ORG, GPE |
| `FusekiPlaceAdapter` | Fuseki `places` graph | GPE, LOC, PLACE |

### Evidence scoring

```php
EvidenceScorer::scoreCandidate(int $candidateId): ?array
EvidenceScorer::scoreAllForMention(int $mentionId): array  // {scored_count: int}
```

Each evaluator implements `EvaluatorInterface`:

```php
public function dimension(): string;
public function supports(string $entityType): bool;
public function evaluate($mention, $context, $candidate): EvidenceSignal;
```

`EvidenceSignal::make($signal, $data)` is the factory. `EvidenceSignal::MATCH`, `CONFLICT`, `SILENT`, `ABSENT` are the four signal constants.

### Decision recording

```php
DecisionRecorder::recordLink(int $mentionId, int $candidateId, int $userId): int
DecisionRecorder::recordLinkDifferent(int $mentionId, int $candidateId, int $userId): int
DecisionRecorder::recordCreateNew(int $mentionId, int $newAuthorityId, int $userId, array $fieldDecisions): int
DecisionRecorder::recordPark(int $mentionId, int $userId, string $reason): int
DecisionRecorder::recordReject(int $mentionId, int $userId, ?string $reason = null): int
```

Each method:

1. Inserts one row into `ahg_mention_decision` with a frozen JSON snapshot of evidence and visible candidates.
2. Updates `ahg_mention.state`.
3. For link / link_different: back-updates `ahg_ner_entity.linked_actor_id`.
4. For park: writes `ahg_mention_park`.
5. For reject: calls `NerFeedbackService::captureFromRejection()` inside a try / catch (best-effort - failure never blocks the audit row).
6. Fires `DecisionProvenanceWriter::write()` to push RDF-Star triples into Fuseki. Failures are logged; `auth-res:write-provenance` backfills.

### Provenance writers

```php
DecisionProvenanceWriter::write(int $decisionId): bool
FieldProvenanceWriter::writeForNewAuthority(int $decisionId, int $authorityId, array $fieldDecisions): int
```

`DecisionProvenanceWriter` reads `authority_resolution.decisions_graph_uri` from `ahg_settings` and has a `DEFAULT_GRAPH_URI` constant.
`FieldProvenanceWriter` is called by `AuthorityCreator` only on `create_new`.

### Authority creation

```php
AuthorityCreator::createForMention(int $mentionId, array $fieldDecisions, int $userId): int  // new authority id
```

Inserts the new `actor` (or `term`) row via the Qubit class-table-inheritance pattern, populates the i18n tables, and fires `FieldProvenanceWriter`. Enforces ISAAR-CPF mandatory fields via `assertIsaarCpf()` for PERSON / ORG.

### Park queue

```php
ParkQueueService::listFor(?int $userId, ?string $entityType, ?bool $newCandidateOnly,
    ?\DateTimeImmutable $sinceParked, ?string $reasonQuery,
    string $sortBy, int $limit): array
ParkQueueService::countsByArchivist(): array
ParkQueueService::unparkAndRereview(int $mentionId, int $userId): array
ParkQueueService::scanForNewCandidates(): int
```

### External lookup

```php
PrefillEngine::prefillForMention(int $mentionId): array
PrefillEngine::search(string $query, string $entityType): array
```

Each adapter implements `LookupAdapterInterface`:

```php
public function supports(string $entityType): bool;
public function search(string $query, string $entityType): array;
public function getName(): string;
public function getRateLimit(): int;
public function getTtlSeconds(): int;
public function getLicenseNote(): string;
```

`AbstractLookupAdapter` handles rate-limit (in-process token bucket), cache hit / miss against `ahg_authority_lookup_cache`, and HTTP-timeout plumbing.

### NER feedback

```php
NerFeedbackService::capture(int $mentionId, int $decisionId, string $rejectionReason, int $userId): int
NerFeedbackService::exportUnexported(string $outputDir, int $limit = 0): array  // {written_path, row_count}
```

## Controllers and routes

All routes mount under `/admin/authority-resolution/` and require admin middleware.

| Verb | Path | Name | Controller method |
|---|---|---|---|
| GET | `.../queue` | `auth-res.queue` | `AuthorityReviewController::queue` |
| GET | `.../review/{mention}` | `auth-res.review.show` | `AuthorityReviewController::show` |
| GET | `.../lookup` | `auth-res.lookup` | `AuthorityReviewController::lookup` |
| POST | `.../review/{mention}/link` | `auth-res.review.link` | `AuthorityReviewController::link` |
| POST | `.../review/{mention}/link-different` | `auth-res.review.linkDifferent` | `AuthorityReviewController::linkDifferent` |
| GET | `.../review/{mention}/create-new` | `auth-res.review.createNew.form` | `AuthorityReviewController::createNewForm` |
| POST | `.../review/{mention}/create-new` | `auth-res.review.createNew` | `AuthorityReviewController::createNew` |
| POST | `.../review/{mention}/park` | `auth-res.review.park` | `AuthorityReviewController::park` |
| POST | `.../review/{mention}/reject` | `auth-res.review.reject` | `AuthorityReviewController::reject` |
| GET | `.../park` | `auth-res.park.index` | `ParkQueueController::index` |
| POST | `.../park/{mention}/unpark` | `auth-res.park.unpark` | `ParkQueueController::unpark` |
| GET | `.../park/dashboard.json` | `auth-res.park.dashboard` | `ParkQueueController::dashboard` |
| GET | `.../settings/lookup` | `auth-res.settings.lookup` | settings views |

## Schema

Seven tables, all InnoDB + `utf8mb4_unicode_ci`. No FKs to base information_object / actor / term tables; this decouples the engine from base schema migrations.

| Table | Purpose | Key columns |
|---|---|---|
| `ahg_mention` | One workflow row per promoted NER entity | `ner_entity_id` UNIQUE, `state` |
| `ahg_mention_context` | Neighbourhood context packet (1:1 with mention) | `mention_id` UNIQUE, `co_occurring_entities`, `nearby_dates`, `nearby_places`, `role_language_tokens` (all JSON) |
| `ahg_mention_candidate` | Ranked candidates per mention | `mention_id`, `rank_position`, `composite_score`, `evidence_signals`, `evidence_data` |
| `ahg_mention_decision` | Immutable audit; one row per decision event | `mention_id`, `decision_type`, frozen `evidence_snapshot` + `candidates_visible_snapshot` |
| `ahg_mention_park` | One active row per parked mention | `mention_id` UNIQUE, `new_candidate_available`, `new_candidate_check_at` |
| `ahg_ner_feedback` | One row per reject decision | `mention_id`, `source_text`, `rejection_reason`, `training_exported` |
| `ahg_authority_lookup_cache` | Cache for external authority lookups | `(source, entity_type, query_text)` UNIQUE, JSON `payload`, `ttl_seconds` |

All `state` and `decision_type` columns are `VARCHAR(N)` with a comment; no MySQL `ENUM` per the Heratio convention. Schema lives in `database/install.sql` (idempotent `CREATE TABLE IF NOT EXISTS`).

### ER summary

```
ahg_ner_entity --1:1-- ahg_mention --1:1-- ahg_mention_context
                              |
                              +--1:N-- ahg_mention_candidate
                              |
                              +--1:N-- ahg_mention_decision --1:0..1-- ahg_ner_feedback
                              |
                              +--1:0..1-- ahg_mention_park

(independent)  ahg_authority_lookup_cache
```

## Service provider boot sequence

`AhgAuthorityResolutionServiceProvider::register()` binds every service as a singleton (workflow orchestration, candidate adapters, ten evaluators, `DocumentPriorService`, `EvidenceScorer`, `DecisionRecorder`, both provenance writers, `AuthorityCreator`, `ParkQueueService`, `NerFeedbackService`, all seven lookup adapters, `PrefillEngine`).

`AhgAuthorityResolutionServiceProvider::boot()`:

- Loads routes from `routes/admin.php`.
- Loads views from `resources/views/` under the `auth-res::` namespace.
- Registers the 11 artisan commands.
- Probes for `ahg_mention` via `Schema::hasTable()` inside an outer try / catch (CI guard - the schema probe must not crash a fresh install before the install SQL has run; see `reference_ci_schema_hastable.md`).
- Idempotent install: if `ahg_mention` is missing, runs the install SQL. Subsequent boots skip.
- Auto-seeds the lookup-settings rows from `database/seed_lookup_settings.sql` when the table is missing the expected keys.

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

## Settings keys

All settings live in `ahg_settings`.

Workflow / scoring:

- `authority_resolution.candidate_top_n` (int, default 5)
- `authority_resolution.role_language_tokens` (JSON array)
- `authority_resolution.prior.<fonds_id>` (JSON, 24-hour TTL cache; written by `DocumentPriorService`)

Provenance:

- `authority_resolution.decisions_graph_uri` (string, default `urn:heratio:auth-res:graph:decisions`)
- `authority_resolution.field_provenance_graph_uri` (string, default `urn:heratio:auth-res:graph:field-provenance`)

External lookup (per source `<src>` in {viaf, wikidata, geonames, tgn, gnd, isni, sagnc}):

- `lookup.<src>.enabled` (bool, default 0)
- `lookup.<src>.rate_limit` (int, calls per minute)
- `lookup.<src>.cache_ttl` (int, seconds)
- `lookup.<src>.license_note` (string)
- `lookup.<src>.license_url` (string)
- `lookup.geonames.username` (string, default `demo`)

Cross-source:

- `lookup.precedence` (JSON array, default `["viaf","wikidata","geonames","tgn","gnd","isni","sagnc"]`)
- `lookup.http_timeout` (int seconds, default 8)

## Tailwind 4 (not Bootstrap)

The `ahg-theme-b5` package name is a historical misnomer; the Laravel Heratio CSS framework is Tailwind 4 (verified in `package.json`). All blade files in this package use Tailwind utilities only (`bg-emerald-600`, `grid grid-cols-12`, `rounded-lg`, etc.). The master layout still ships some Bootstrap-named class wrappers (`container-xxl`, `breadcrumb`) inherited from early scaffolding, but page-level content is Tailwind end to end. Modals are Tailwind-only; open / close runs through tiny inline scripts that toggle `hidden` / `flex`.

## CSP

The review screen loads Leaflet from `unpkg.com` for the PLACE-card map preview. The host CSP allows `unpkg`; no policy change is needed.

## Idempotency invariants

- Re-promoting a mention is a no-op (UNIQUE on `ner_entity_id`).
- Re-generating candidates clears and re-inserts the candidate set.
- Re-scoring a candidate overwrites its signals + data + composite score.
- A re-issued decision is allowed; the audit row is immutable, so both rows remain visible and the newest wins for the `state` column.
- Re-parking is a no-op (UNIQUE on `ahg_mention_park.mention_id`).
- Provenance writes are idempotent: the Fuseki write uses `DELETE { ... } INSERT { ... } WHERE { ... }` shapes keyed on `ahg:decision/<id>`.

## Known gaps and follow-ups

- **NER per-mention confidence**: currently a hardcoded constant (0.85) until the upstream pipeline exposes per-mention scores. Tracked in `heratio#132`. The engine treats `confidence` as advisory only; the evidence layer is the real signal.
- **Place coordinates**: the `term` table has no lat/long columns and the `property` table is empty for place terms, so PLACE-card map previews fall back to a world-view. Coordinate enrichment is tracked separately.
- **Async provenance**: `DecisionRecorder::write()` runs synchronously (adds ~50 ms latency on decide). A future pass can route writes through the `fuseki_queue_enabled` queue.
- **SAGNC adapter**: a stub. Returns `[]` until a stable endpoint is wired.

## Cross-codebase pairing

The same engine ships in two places:

- **Heratio (Laravel 12)** - this package.
- **AtoM Heratio (Symfony 1.4)** - `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/`.

Both share the same six tables (plus `ahg_ner_feedback`), the same five decision outcomes, the same ten evaluators, the same RDF-Star provenance shape, and the same seven external adapters. The UI layer differs (Tailwind 4 here, Bootstrap 5 there) but the data layer and the service contracts converge.

## Related

- "AHG Authority Resolution - User Guide"
- "Authority Resolution - Review Screen Reference"
- "Authority Resolution - Park Queue"
- "Authority Resolution - Creating a New Authority Record"
- "Authority Resolution - Provenance Model"
- "Authority Resolution - Evidence Scoring"
- "Authority Resolution - CLI Commands"
