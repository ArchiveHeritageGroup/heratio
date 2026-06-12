# Preservation health report - technical reference

Summary: an admin, read-only preservation-integrity dashboard at `/admin/preservation-health` in `packages/ahg-reports`, mirroring the catalogue-growth / collection data-quality / ai-usage report pattern (Schema::hasTable-guarded aggregate COUNTs, Route::has-gated links, `theme::layouts.2col`, a two-segment `/admin/...` route). Shipped in the same `ahg-reports` wave as the trust-console, data-quality, ai-usage and catalogue-growth slices. It is an operational metric (what needs attention in the digital collection's integrity) and is deliberately distinct from the data-quality report (descriptive completeness), the ai-usage report (where AI assisted) and the catalogue-growth report (size/growth/composition). Reads the canonical preservation stores owned by the locked `ahg-preservation` package; writes nothing, runs no ALTER, creates no table.

## What it reads (read-only, no ALTER, no new table)

The preservation stores are owned by the `ahg-preservation` package. This report only SELECTs from them. The denominator throughout is `digital_object`.

| Table | Columns used (DESCRIBE-verified) | Used for |
|---|---|---|
| `digital_object` | `id` (`COUNT(*)`) | The denominator: total digital objects. |
| `preservation_fixity_check` | `id`, `digital_object_id`, `status` | Integrity. Latest check per object (`MAX(id)` per `digital_object_id`); `status` is the result (`pass` / fail-class). |
| `preservation_event` | `event_type`, `event_outcome`, `event_datetime`, `id`, `digital_object_id`, `information_object_id`, `event_outcome_detail`, `event_detail` | Missing files (`event_type` in the file_missing set) and the recent failures/warnings list (`event_outcome` in failure/warning). |
| `preservation_object_format` | `digital_object_id`, `puid`, `format_name` | Format-identification coverage: an object is identified when it has a row with a non-empty `puid` or `format_name`. |
| `preservation_virus_scan` | `id`, `digital_object_id`, `status`, `threat_name` | Virus posture. Latest scan per object (`MAX(id)` per `digital_object_id`); flagged when a `threat_name` is present or the `status` is not a known-clean value. |

## Alignment with the fixity dashboard

The integrity metric reads `preservation_fixity_check` exactly the way `AhgCore\Services\FixityService::coverage()` reads it - the LATEST check per object via a `MAX(id)` grouped sub-join on `digital_object_id`, so re-checks are not double-counted. The two surfaces therefore agree rather than diverging. This report does not duplicate or write to FixityService's store; it is a thin read-only view over the same canonical table.

## The event-type spelling duality

The `preservation_event.event_type` vocabulary on this store carries BOTH a snake_case and a camelCase spelling for the same concept, verified by GROUP BY: `fixity_check` / `fixityCheck`, `format_identification` / `formatIdentification`, plus `file_missing`, `virus_check`, `ingestion`, `normalization`. Each metric that filters on `event_type` matches a SET of spellings (e.g. the file_missing set `['file_missing','fileMissing']`) rather than a single literal, so neither spelling is silently missed. The recent-failures list does not filter on `event_type` at all (it filters on `event_outcome`), and humanises the type label by normalising camelCase to snake_case before lookup.

## Metrics (each a single grouped/aggregate COUNT or one LIMITed list)

| Metric | Query shape |
|---|---|
| Total digital objects | `COUNT(*)` over `digital_object` (the denominator) |
| Fixity pass / fail | Latest check per object (`MAX(id)` per `digital_object_id`), `GROUP BY status`; fail-class statuses = `fail/failed/failure/mismatch/error` (case-insensitive), everything else counts as a pass |
| Never fixity-checked | `digital_object` total minus the DISTINCT count of `digital_object_id` present in `preservation_fixity_check` |
| Missing files | DISTINCT `digital_object_id` in `preservation_event` where `event_type` in the file_missing set; plus a small LIMITed sample of the most recent such events |
| Format identified | DISTINCT `digital_object_id` in `preservation_object_format` with a non-empty `puid` OR `format_name`; not-identified = total minus identified |
| Virus clean / flagged | Latest scan per object (`MAX(id)` per `digital_object_id`), `GROUP BY status, threat_name`; flagged when `threat_name` is set or the `status` is not in the clean set `clean/ok/pass/passed/no_threat` (so an `error` status is flagged for re-scan, not counted clean) |
| Recent failures / warnings | `preservation_event` where `event_outcome` in `failure/warning`, `ORDER BY event_datetime DESC, id DESC`, `LIMIT 12`; rendered as a small table (outcome, type, when, digital object, detail) |

## Properties

- Read-only: SELECT / aggregate only; no writes, no ALTER, no new table. The preservation tables are owned by the locked `ahg-preservation` package and are only read here.
- Bounded: grouped aggregate COUNTs plus one LIMITed recent list; no per-row PHP scan of the collection.
- Resilient: the whole build is wrapped in try/catch and every table is Schema::hasTable-guarded; a missing table or an empty collection degrades to a calm empty state, never a 500. Two empty states: "No digital collection yet" (no `digital_object` table / zero rows) and "No preservation data yet" (objects exist but no fixity/format/virus/event activity recorded). The virus card is omitted entirely when `preservation_virus_scan` is absent.
- Admin-gated route; two-segment `/admin/preservation-health` (catch-all-safe against the `/{slug}` archival-record route).
- Links (back to Reports, across to the fixity dashboard, the preservation maturity assessment, the format-identification log, the virus-scan log, the event log and the Trust & Transparency console) are Route::has-gated so no dead links render.
- International: no jurisdiction-specific framing. Honest framing: it surfaces what needs attention and changes nothing.

## Files

- `packages/ahg-reports/src/Controllers/PreservationHealthController.php`
- `packages/ahg-reports/resources/views/preservation-health/index.blade.php`
- route in `packages/ahg-reports/routes/web.php` (`reports.preservation-health`)
