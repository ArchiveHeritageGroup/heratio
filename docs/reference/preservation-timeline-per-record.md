# Per-record Preservation Timeline (issue #1244, building on #1201)

Public per-record read surface that shows the PREMIS-style digital-preservation lifecycle of ONE PUBLISHED archival record's digital objects - ingest, fixity checks, format identification, migrations / normalisations, virus scans - in chronological order, each with its recorded outcome and responsible agent. Additive, read-only, in the non-locked `ahg-c2pa` package. Epics #1201 / #1244 stay OPEN. It is the preservation-lifecycle sibling of the per-record Authenticity Report (`/authenticity/{idOrSlug}`) and the AI Inference Provenance Explorer (`/inference-provenance/{idOrSlug}`).

## Routes (all in `packages/ahg-c2pa/routes/web.php`, mounted via the package boot under the `web` group)

- `GET /preservation-timeline/{idOrSlug}` -> `PreservationTimelineController@show` (name `c2pa.preservation.timeline`) - the HTML timeline page. `{idOrSlug}` matcher is `.+` so multi-segment slugs resolve.
- `GET /preservation-timeline/{idOrSlug}.json` -> `@json` (name `c2pa.preservation.timeline.json`) - machine companion, CORS-open. `{idOrSlug}` matcher `[^/]+` (numeric id or single-segment slug).

There is deliberately NO `/badge` / `.svg` surface here: nginx serves `*.svg` statically and would 404 before Laravel. JSON only.

### Catch-all safety

The locked IO slug catch-all `/{slug}` is anchored single-segment. Every preservation-timeline route is two or more segments (`/preservation-timeline/...`), so the catch-all can never intercept them - identical reasoning to the sibling `/inference-provenance/...`, `/authenticity/...`, and `/verify/...` routes in the same file. There is deliberately NO bare `/preservation-timeline` route (a single-segment path would sit in the catch-all's lane); the explorer always needs a record reference. The `.json` literal is declared BEFORE the `{idOrSlug}` `.+` page route so it is never captured as a slug fragment. `.json` keeps its extension because nginx passes `*.json` through to Laravel. No exclusion-list edit was needed (the exclusion list lives in the locked IO package).

## Services / tables READ (read-only, NOT rebuilt; ahg-preservation OWNS + WRITES them)

The `ahg-preservation` package is LOCKED (`.locked-paths`). This surface only READS its tables; it never writes, never ALTERs, and touches no locked file.

- `AhgC2pa\Services\PreservationTimelineService` (NEW, in non-locked `ahg-c2pa`) - the consolidator. Owns no table, writes nothing, runs no preservation action, re-verifies nothing.
- `preservation_event` - the canonical PREMIS event log. Columns read: `information_object_id`, `digital_object_id`, `event_type`, `event_datetime`, `event_outcome`, `event_detail`, `event_outcome_detail`, `linking_agent_type`, `linking_agent_value`. Primary key into the record is `information_object_id`; rows linked only via `digital_object_id` are also picked up. Live DB has ~1,387 rows (event_type values seen: `fixityCheck`, `format_identification`, `ingestion`, `formatIdentification`, `fixity_check`, `normalization`, `virus_check`, `file_missing`). `event_outcome` is `success` / `warning` / `failure` / `unknown`.
- `preservation_fixity_check` - per-digital-object checksum verifications. Columns read: `digital_object_id`, `algorithm`, `status`, `error_message`, `checked_at`, `checked_by`. Status `pass` -> success.
- `preservation_object_format` - per-digital-object format identifications (the per-file FITS/Siegfried-style row). Columns read: `digital_object_id`, `puid`, `mime_type`, `format_name`, `format_version`, `identification_tool`, `identification_date`, `warning`. (NOT `preservation_identification`, which is a generic unlinked table; NOT `preservation_conversion`, a stub.)
- `preservation_format_conversion` - per-digital-object migrations / normalisations. Columns read: `digital_object_id`, `source_format`, `source_mime_type`, `target_format`, `target_mime_type`, `conversion_tool`, `tool_version`, `status`, `error_message`, `started_at`, `completed_at`, `created_at`.
- `preservation_virus_scan` - per-digital-object malware scans. Columns read: `digital_object_id`, `scan_engine`, `status`, `threat_name`, `error_message`, `scanned_at`. A non-empty `threat_name` -> failure; `error` status -> warning (scan could not complete, not a detection).
- `digital_object` - read-only `id WHERE object_id = <ioId>` to map the information object to its digital objects (the per-file tables key on `digital_object_id`).
- `information_object` / `information_object_i18n` / `slug` / `status` - the same resolve + published-gate path the sibling services use.

## Merged timeline model

The service maps the IO -> its `digital_object` ids, then merges five sources into one normalised event list:

| source table                     | stage         | label example                          | outcome from        |
|----------------------------------|---------------|----------------------------------------|---------------------|
| `preservation_event`             | classified*   | "Ingest" / "Fixity check" / ...        | `event_outcome`     |
| `preservation_fixity_check`      | fixity        | "Fixity check (SHA256)"                | `status`            |
| `preservation_object_format`     | format        | "Format identified: WebP" (+PUID/MIME) | warning -> warning  |
| `preservation_format_conversion` | migration     | "Migration: X to Y"                    | `status`            |
| `preservation_virus_scan`        | virus         | "Virus scan (clamav)"                  | `status`+threat     |

\* `preservation_event.event_type` is normalised to a lifecycle stage by substring match (ingest/capture/accession -> ingest; fixity/checksum -> fixity; identification -> format; normal/migrat/conversion -> migration; virus/malware/scan -> virus; else other), tolerating both camelCase and snake_case variants.

Each event is shaped to: `stage`, `stage_label`, `label`, `outcome` (success/warning/failure/unknown), `outcome_label`, `when` (ISO Z), `when_display`, `agent`, `detail`, `object_id` (digital object), `source`, `sort_key`. Events are sorted **oldest first** (the natural lifecycle reading order). Capped at 500 events (`truncated` flag + honest footer when exceeded).

## Published gate + resolution

Identical to the sibling services: numeric id or (possibly multi-segment) slug -> `slug.object_id`; published iff a `status` row with `type_id=158` AND `status_id=160` exists; root object id=1 is excluded. Unknown OR unpublished -> the service returns `null` -> the controller returns a clean 404 (HTML + JSON), so a draft/embargoed record is indistinguishable from a missing one.

## Resilience

`Schema::hasTable` guard + per-source `try/catch` on every load; a missing preservation table simply contributes no events. The page never 500s. A published record with no events degrades to the dignified "No preservation events recorded yet" empty state (still HTTP 200) - absence is shown as absence, never invented.

## Verified against live DB (read-only)

- IO 913851 (`edwardian-oak-dresser-with-mirror`, published) -> 5 `ingestion` events.
- IO 768 (`mobrey-family-archive`, published, DO 773) -> merged timeline: PREMIS `format_identification` events + a `preservation_object_format` row (WebP, PUID fmt/568) + PREMIS `fixity_check` events + a `preservation_fixity_check` row (SHA256), all in one chronological order. This exercises the cross-source merge.

## Trust-surface links (the full picture)

The page footer links to:
- `/authenticity/{idOrSlug}` - the C2PA content-credentials / signing + whole-record provenance report.
- `/inference-provenance/{idOrSlug}` - the AI-inference provenance explorer.

## Constraints honoured

AHG / Plain Sailing / AGPL headers; `@copyright "Plain Sailing Information Systems"`; no em-dashes; international (no jurisdiction assumptions); `url()` not a hardcoded host; bounded (capped events); read-only over the preservation tables (no writes, no ALTER); no locked path touched (`ahg-preservation` read only). Epics #1201 / #1244 left OPEN.
