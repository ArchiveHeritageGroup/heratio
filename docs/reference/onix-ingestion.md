# ONIX Ingestion (library acquisitions)

**Summary:** Heratio ingests EDItEUR ONIX for Books metadata (release 3.0 and
2.1) from publishers/vendors. Each ONIX `<Product>` is parsed and validated into
a review queue, then committed to create a catalogue bibliographic record plus a
matching acquisitions order line. Built for heratio#1094 (NLSA ILMS tender).
Lives in the `ahg-library` package. UI at `/library-manage/onix`.

## Components

- `AhgLibrary\Services\OnixIngestService` - parse / validate / ingest / commit / CRUD.
  - `parse($xml)` - namespace-agnostic (local-name XPath), handles 3.0 + 2.1.
  - `validateRecord()` - mandatory title, ISBN-13/ISBN-10/ISSN checksums, duplicate detection against `library_item`.
  - `commit($ingestId)` - reuses `LibraryService::create()` for the bib record (object/IO/i18n/slug/library_item/creators) and `LibraryAcquisitionService::createOrder()/addLine()` for the order line. One order of type `deposit` per commit.
- `AhgLibrary\Controllers\OnixIngestController` - web UI + `POST /api/library/ingest/onix`.
- Views: `ahg-library::onix.index`, `ahg-library::onix.show`.

## Tables

- `library_onix_ingest` - one row per uploaded message / API call (filename, source, onix_version, status parsed|committed|failed, record/valid/error/imported counts, order_id).
- `library_onix_ingest_line` - one row per `<Product>` (the review queue): identifiers, bib fields, supply price, status parsed|valid|invalid|duplicate|imported|skipped, error, library_item_id, order_line_id, raw `<Product>` XML.

Statuses are plain VARCHAR (no ENUM), workflow-internal (not operator dropdowns).

## Key implementation note

`LibraryService::create()` returns the **information_object id**, not
`library_item.id`. ONIX commit resolves the `library_item.id` via
`library_item.information_object_id` before linking the order line and queue row.

## Routes

- `GET  /library-manage/onix` - upload + history
- `POST /library-manage/onix` - parse & stage
- `GET  /library-manage/onix/{id}` - review queue
- `POST /library-manage/onix/{id}/commit` - import valid lines
- `DELETE /library-manage/onix/{id}` - delete ingest log (keeps committed bibs)
- `POST /library-manage/onix/line/{lineId}/status` - skip/include a line
- `POST /api/library/ingest/onix` - API ingest (raw body / `onix` field / `onix_file`); `?commit=1` to commit inline

## Status

Shipped end-to-end (parser, validation, review queue, commit -> catalogue +
order, web UI, API, unit tests). PSIS/AtoM-AHG parity twin tracked at
atom-ahg-plugins#107.
