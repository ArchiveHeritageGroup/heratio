> Heratio Help Center article. Category: Integration.

# DataCite Event Data

Heratio publishes usage and citation events to DataCite Event Data (https://api.datacite.org/events). Once a record has a minted DOI, every show-page view, every digital-object download, and every recorded citation/relation is reported to DataCite so the record's "events" tab on DataCite Commons stays current and so OpenAIRE and the broader scholarly citation graph pick up your archival material.

This article is for operators - archivists do not need to do anything; the integration is hands-off once configured.

---

## What gets reported

| Heratio surface                       | DataCite relation-type-id                       | Source       |
|---------------------------------------|-------------------------------------------------|--------------|
| Information-object show page is loaded| `unique-dataset-investigations-regular`         | heratio-counter |
| A digital object is downloaded        | `unique-dataset-requests-regular`               | heratio-counter |
| A RelatedIdentifier is written by the mint pipeline | `is-referenced-by`, `is-part-of`, `references`, etc. (kebab-case map of the DataCite Kernel-4 relationType) | heratio-archive |

Each event is keyed by `subj-id | relation-type-id | obj-id | source-id`. Repeats of the same logical event are deduplicated server-side via a UNIQUE index on the dedupe hash, so reloading a record page does not double-count.

---

## Configuration

1. Generate a Bearer JWT in the DataCite Fabrica console (https://commons.datacite.org / Settings / API tokens).
2. In Heratio, open **Admin / Settings** and find the **DOI** group.
3. Set `datacite_api_token` to the Bearer JWT value. (If you cannot generate a Bearer JWT, the integration will fall back to the basic-auth credentials already used for DOI minting - this works but DataCite documents Bearer tokens as the long-term path.)
4. Set `DATACITE_TEST_MODE=true` in `.env` while you smoke-test against `https://api.test.datacite.org`. Flip back to `false` for production.

---

## Operations

- **Queue:** Heratio submits events asynchronously via the standard Laravel queue. Make sure a queue worker is running (`php artisan queue:work`); otherwise events will accumulate in `ahg_datacite_event` with `state=pending`.
- **Rate limit:** Submissions are capped at 30/minute by default (`config/queue.php` -> `rate_limits.datacite_events`). Tune via env `QUEUE_RATE_LIMIT_DATACITE_EVENTS`.
- **Retry:** Run `php artisan doi:events-flush --limit=500 --max-attempts=5` to retry pending and failed rows. Add `--dry-run` to preview.
- **Audit:** every row is logged to `ahg_datacite_event` with full payload, HTTP status, and response body. Inspect via `SELECT state, COUNT(*) FROM ahg_datacite_event GROUP BY state` for a quick health check.

---

## Per-collection accuracy metrics

A companion command, `php artisan metrics:back-fill <csv> [--dry-run]`, lets you pivot AI accuracy measurements per collection into the model registry. The CSV header must be `service,collection,accuracy`. The command merges a `per_collection` map into `ai_model_registry.accuracy_metrics_json` for each active service row without disturbing existing keys.

This is useful when a regulator (or your own Annex IV bundle) asks "what is the HTR character-error rate for the Mission Archive fonds specifically?" - record the measurement once, back-fill it, and it appears in the Annex IV documentation generator automatically.

---

## Troubleshooting

- **No events show up on DataCite Commons.** Check `state` in `ahg_datacite_event`. If everything is `pending`, your queue worker is not running. If everything is `failed`, inspect `response_status` / `response_body` - usually a stale Bearer token (HTTP 401) or a missing source-id permission (HTTP 403).
- **Submissions are slow / backed up.** That is the rate limiter working. The limiter releases jobs back to the queue when DataCite caps would be exceeded; raise the limit only if your DataCite contract permits.
- **A particular DOI is missing.** Make sure the DOI status is `minted` or `active` in `ahg_doi` - the view middleware filters on this. If the record was unpublished, no event will fire.

---

## Related

- See `datacite-enrichment` for what metadata Heratio sends at MINT time (the corresponding question on the metadata side, not the events side).
- DataCite Event Data documentation: https://support.datacite.org/docs/eventdata-guide
- Heratio reference: `docs/reference/datacite-phase-3-events-api.md`
