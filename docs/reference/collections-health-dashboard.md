# Collections Health Dashboard (ahg-reports)

A single read-only admin dashboard that aggregates collection-wide "health"
signals into one cross-collection KPI overview. Built for issue #1215 as the
first slice: pure aggregates over existing tables, no new schema, no writes.

## Where it lives

- Package: `packages/ahg-reports`
- URL: `/admin/reports/collections-health` (route name `reports.collections-health`)
- Middleware: `admin` (it sits inside the existing `admin/reports` route group)
- Controller: `AhgReports\Controllers\CollectionsHealthController@index`
- Service: `AhgReports\Services\CollectionsHealthService` (singleton)
- View: `ahg-reports::collections-health` (Bootstrap 5 cards + progress bars,
  central theme via `theme::layouts.2col`)

## What it shows

Four headline cards (total objects, archival descriptions, % digitised,
% condition assessed) plus four panels:

1. Records by domain - counts grouped by `object.class_name` (GLAM domain),
   friendly labels for the four primary archival classes.
2. Publication status - published / draft / never-assessed split of real
   archival descriptions, as a stacked progress bar + table.
3. Digital-object coverage - distinct real IOs carrying a `digital_object`.
4. Preservation-assessment coverage - distinct real IOs with a
   `condition_report`.

## Aggregate logic (the load-bearing bits)

- "Real archival descriptions" = `information_object` rows with
  `parent_id != 1` (1 is the synthetic root). This is the denominator for all
  coverage percentages so they are directly comparable.
- Publication status comes from the generic `status` table, not from
  `information_object`: `type_id = 158` (publication status type), with
  `status_id = 160` = Published and `status_id = 159` = Draft. Joined to
  `information_object` on `status.object_id` and DISTINCT-counted. Anything not
  published or draft falls into "never assessed".
- Digital-object coverage joins `digital_object.object_id` to the IO id (the
  digital object attached directly to the record), DISTINCT-counted. Digital
  objects can also hang off descendant child records; the direct link on the
  record itself is the right "is this record digitised" measure.
- Condition coverage joins `condition_report.information_object_id` to the IO
  id, DISTINCT-counted; guarded by `Schema::hasTable('condition_report')`.
- TermId constants used: `TermId::STATUS_TYPE_PUBLICATION` (158),
  `TermId::PUBLICATION_STATUS_PUBLISHED` (160),
  `TermId::PUBLICATION_STATUS_DRAFT` (159) from `ahg-core`.

## First-slice live numbers (smoke test)

Total objects 6077; archival descriptions 390 (390 rows of class
QubitInformationObject, 370 of them real records under the root). Publication
of the 370 real records: 358 published (96.8%), 12 draft (3.2%), 0 never
assessed. Digital coverage 360/370 (97.3%). Condition coverage 3/370 (0.8%) -
the obvious preservation-data gap this dashboard is meant to surface.

## Extending it

`CollectionsHealthService::getHealthStats()` returns one nested array; add new
KPI blocks as new private aggregate methods and new view panels. Keep it
read-only - this is an overview, not a report-builder.
