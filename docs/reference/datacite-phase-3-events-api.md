> Heratio reference: DataCite v4.5 Phase 3 - Events API client + metrics back-fill.

# DataCite Events API client - Phase 3 (#654)

**Shipped:** 2026-05-26 (Heratio v1.100.0).
**Scope:** Final two items of the original #654 acceptance list - the DataCite Events API client and the per-collection accuracy metrics back-fill command.

## Summary

Heratio now publishes domain events to the DataCite Event Data service (https://api.datacite.org/events, sandbox at https://api.test.datacite.org/events). The client is queue-backed, rate-limited, idempotent, and deliberately defence-in-depth: a DataCite outage cannot ever 500 a Heratio response, and a burst of view/download traffic cannot exceed the per-minute submission cap.

## Architecture

```
       (HTTP request to /{slug})                (DoiService writes a relation)
                |                                          |
   RecordDoiView middleware                           DoiCitation event
                |                                          |
        DoiViewed event                            (dispatched in-app)
                |                                          |
                +-> RegisterDoiEventsListener <-+
                                |
                 DataciteEventsService::register()
                                |
                 1) hash subj+rel+obj+source
                 2) UPSERT ahg_datacite_event by hash (UNIQUE)
                 3) dispatch RegisterDataciteEventJob (queued)
                                |
                 (Laravel queue worker, throttled to 30/min via
                  App\Jobs\Middleware\RateLimited('datacite_events', 30))
                                |
                 DataciteEventsService::submit()
                                |
                 POST https://api.datacite.org/events
                       Authorization: Bearer <ahg_settings.datacite_api_token>
                       Content-Type: application/vnd.api+json
```

## Files (in `packages/ahg-doi-manage/`)

- `src/Services/DataciteEventsService.php` - the client (`register()`, `submit()`, `buildPayload()`, `endpoint()`)
- `src/Jobs/RegisterDataciteEventJob.php` - queued submitter (uses `RateLimited('datacite_events', 30)`)
- `src/Events/DoiViewed.php`, `DoiDownload.php`, `DoiCitation.php`
- `src/Listeners/RegisterDoiEventsListener.php`
- `src/Http/Middleware/RecordDoiView.php` - pushed onto the global stack by the provider
- `src/Console/DoiEventsFlushCommand.php` - `doi:events-flush`
- `src/Console/MetricsBackfillCommand.php` - `metrics:back-fill`
- `database/install.sql` - extended with `ahg_datacite_event`

## ahg_datacite_event table

| Column            | Notes                                                              |
|-------------------|--------------------------------------------------------------------|
| `dedupe_hash`     | `sha256(subj\|rel\|obj\|source)`. UNIQUE - guards against doubles. |
| `subj_id`         | DOI on the Heratio side (bare DOI, not URI).                       |
| `relation_type_id`| Event Data slug (kebab-case), e.g. `unique-dataset-investigations-regular`. |
| `obj_id`          | Other end of the relation (DOI or URL).                            |
| `obj_id_type`     | `doi` / `url` / `uri` / `other`. Drives URI canonicalisation.      |
| `source_id`       | Reported source - `heratio-counter` for view/download, `heratio-archive` for relations. |
| `state`           | `pending` (queued) / `sent` (HTTP 2xx) / `failed`.                 |
| `attempts`        | Submission attempts. `doi:events-flush --max-attempts` skips rows past N. |

## Auth model

DataCite's Events API accepts Bearer JWT tokens issued from the DataCite Fabrica console. Heratio reads the token from `ahg_settings` row `setting_key=datacite_api_token`. If no Bearer token is set the client falls back to the basic-auth credentials in `ahg_doi_config` that DOI minting already uses - functional but DataCite documents this as a transition path; operators should rotate to a dedicated Bearer token.

## Test mode

`config('datacite.test_mode')` (env `DATACITE_TEST_MODE=true`) routes ALL Event submissions to `https://api.test.datacite.org/events`. The flag also short-circuits the legacy `ahg_doi_config.environment` column when computing the endpoint, so a single env flip switches the integration.

## Relation-type-id reference

Heratio uses these Event Data relation slugs:

| Heratio surface  | relation-type-id                                    | source-id         |
|------------------|-----------------------------------------------------|-------------------|
| IO show view     | `unique-dataset-investigations-regular`             | `heratio-counter` |
| Digital download | `unique-dataset-requests-regular`                   | `heratio-counter` |
| RelatedIdentifier writes | mapped from `IsReferencedBy` -> `is-referenced-by`, `IsPartOf` -> `is-part-of`, etc. | `heratio-archive` |

Full list: https://api.eventdata.crossref.org/v1/types.

## Operational notes

- **Idempotency:** the UNIQUE on `dedupe_hash` means a re-fired DoiViewed for the same subj+rel+obj+source is a no-op upsert; we still call `dispatch()` because the job-handler re-checks `state` and short-circuits on `sent`.
- **Rate limit:** the named limit `datacite_events` lives in `config/queue.php`, default 30/min, env `QUEUE_RATE_LIMIT_DATACITE_EVENTS`. The job middleware releases-back-to-queue when the limit is hit (no failure, just delayed re-attempt).
- **Operator retry:** `php artisan doi:events-flush --limit=200 --max-attempts=5` walks pending+failed rows and re-submits. Use `--dry-run` to preview.
- **Defence in depth:** `RecordDoiView::handle()` is wrapped in try/catch with a swallow-all - a DataCite outage cannot 500 a show page.

## Per-collection accuracy back-fill

`php artisan metrics:back-fill <csv> [--dry-run]` reads a CSV with header `service,collection,accuracy` and merges a `per_collection` map into the current (`retired_at IS NULL`) `ai_model_registry.accuracy_metrics_json` for each service. Existing JSON keys are preserved; a `per_collection_last_updated` ISO-8601 timestamp is set on each pivot.

Example CSV:

```
service,collection,accuracy
htr,fonds-mission-archive,0.91
htr,fonds-public-records,0.84
ner,fonds-mission-archive,0.79
```

## Phase 3 close criteria (and #654 close)

After Phase 3 lands and a sandbox smoke succeeds, all 7 items on the original #654 acceptance list are shipped: subjects, descriptions, dates, language, alternateIdentifiers, smarter publicationYear (Phase 1); Creator-ORCID, RelatedIdentifier, GeoLocation, FundingReference, Kernel-4 XML (Phase 2); Events API + per-collection metrics back-fill (Phase 3).
