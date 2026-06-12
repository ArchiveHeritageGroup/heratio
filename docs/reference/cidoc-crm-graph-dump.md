# Whole-collection CIDOC-CRM graph dump (issue #1197 / #1204)

Summary: a dataset-level CIDOC-CRM (ISO 21127) dump in `ahg-metadata-export` that
streams the WHOLE published catalogue into ONE combined Turtle graph, plus a
public bulk-download route `GET /data/cidoc-crm.ttl`. It is the dataset companion
to the per-record / actor / term CIDOC-CRM exporters, and a slice of the Open
Memory Protocol open-data line (north-star #1204) on the unified G/L/A/M
knowledge-graph epic (#1197). Both epics stay OPEN.

## What shipped

- Console command `ahg:export-cidoc-graph`
  (`packages/ahg-metadata-export/src/Console/Commands/ExportCidocGraphCommand.php`).
  Streams every published `information_object` through the existing
  `CidocCrmSerializer` (one record at a time) into a single combined Turtle file.
  Optional `--actors` / `--terms` append every producing actor and every cited
  subject/place term via `CidocCrmActorSerializer` / `CidocCrmTermSerializer`.
- Public controller
  `packages/ahg-metadata-export/src/Controllers/CidocGraphController.php` serving
  `GET /data/cidoc-crm.ttl`.
- Surface advertised from `ProtocolController::surfaces()` in `ahg-api`
  (`dataset-cidoc-crm`), so it appears in both the capabilities document
  (`/open-data/protocol`) and the DCAT catalogue (`/data/catalog`) automatically.

## Reuse, not reimplementation

The command and the controller do NO CRM modelling of their own. They call the
shipped serializers verbatim, one entity at a time, and concatenate the output:

- a SINGLE shared `@prefix` block is written once at the top (rdf / rdfs / xsd /
  crm / ecrm, identical to the serializers);
- each entity's own `@prefix` header is stripped (`stripPrefixBlock()` drops the
  leading run of `@prefix` / `@base` lines) and its triple body appended.

Because each serializer mints stable fragment IRIs off the public entity URL (a
record's `<base>/<slug>#crm-object`, an actor's `<base>/actor/<id>`, a term's
place/type node), the cross-entity references (a record's `P50 has current
keeper`, an actor's `P14 carried out by`) resolve to nodes elsewhere in the same
file. One dump, one connected graph.

## Streaming + bounding (never the whole catalogue in memory)

- Published ids are walked with a keyset cursor
  (`WHERE object_id > :last ORDER BY object_id LIMIT :batch`), default batch 500.
  Only one id page plus one entity's Turtle is in memory at a time.
- Output is streamed to disk via a file handle and written atomically (temp file
  + `rename`) so a reader never sees a half-written dump. Idempotent overwrite.
- `--limit` caps records for smoke runs; `--batch` tunes the page size.
- The public endpoint streams the pre-built dump straight off disk when present
  (`X-Open-Data-Source: prebuilt-dump`); otherwise it generates a BOUNDED graph
  on the fly, hard-capped at 2000 records (`X-Open-Data-Source: on-the-fly`,
  `X-Open-Data-Cap`), and streams it as produced.

## Where the dump lands

`config('heratio.storage_path').'/cidoc-graph/cidoc-crm.ttl'` - never hardcoded.
On this server `heratio.storage_path` is `/mnt/nas/heratio`, so the default dump
is `/mnt/nas/heratio/cidoc-graph/cidoc-crm.ttl`. `--out` overrides it.

## Published gate

Same gate as every CRM surface: `status.type_id = 158 AND status.status_id =
160`, synthetic root id 1 excluded. Actors / terms are emitted with
`publicOnly = true` so their linked-record lists never leak an unpublished title.

## Catch-all safety

`/data/cidoc-crm.ttl` is a TWO-segment, dotted path. The archival-record
`/{slug}` catch-all in `ahg-information-object-manage` matches a single
no-dot segment (`^[a-z0-9][a-z0-9-]*$`), so it can never capture this URL. The
route is registered at the root with the bare `web` group (no `auth`) so external
Linked Data clients reach it without a session cookie.

## Read-only / no ALTER

Every query is a SELECT (via the serializers + the keyset cursor). The only write
is the dump file under the configured storage path. No INSERT / UPDATE / DELETE /
ALTER. The controller only reads the dump file; the command owns writing it.

## Validation

- `php -l` clean on the command, the controller, and the edited provider / routes
  / ProtocolController.
- Command registers as `ahg:export-cidoc-graph`.
- `/data/cidoc-crm.ttl` returns `text/turtle`; Turtle is well-formed (single
  prefix block, concatenated entity bodies).
- `bin/check-locked` exits 0; changes confined to `packages/ahg-metadata-export`,
  `packages/ahg-api` (additive surface entry), and `docs/`.

## Constraints honoured

AGPL header, `@copyright Plain Sailing Information Systems`; no em-dashes;
international (every URI from `url()` / config, no jurisdiction constant).
Bounded + streamed. Read-only except the dump file. Epics #1197 / #1204 stay OPEN.
