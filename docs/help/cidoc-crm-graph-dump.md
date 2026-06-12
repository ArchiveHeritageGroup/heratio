> Heratio Help Center article. Category: Federation.

# Whole-collection CIDOC-CRM graph dump

**Version:** 1.0
**Date:** 2026-06-12
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## What it does

This is the dataset-level companion to the per-record CIDOC-CRM export. Where the
per-record export emits one archival record, the graph dump streams the WHOLE
published catalogue into ONE combined CIDOC-CRM (ISO 21127) Turtle document - a
single connected graph in which every record, and optionally every producing
actor and cited subject/place term, share one `@prefix` block and join through
their `#crm-object` / actor / term fragment IRIs.

It reuses the same serializers as the per-record / actor / term downloads
(`CidocCrmSerializer`, `CidocCrmActorSerializer`, `CidocCrmTermSerializer`), so
the combined graph is byte-for-byte consistent with the single-entity documents.

Published records only: the same gate the rest of the platform uses
(publication status published; the synthetic root record excluded). Nothing
draft or private ever appears in the dump.

## Generating the dump (operator / scheduled)

```
php artisan ahg:export-cidoc-graph
php artisan ahg:export-cidoc-graph --actors --terms
php artisan ahg:export-cidoc-graph --culture=af --batch=1000
php artisan ahg:export-cidoc-graph --limit=50        # smoke run
php artisan ahg:export-cidoc-graph --out=/path/to/file.ttl
```

| Option | Default | Notes |
|---|---|---|
| `--out` | `{storage_path}/cidoc-graph/cidoc-crm.ttl` | Output path. Default lands under the configured Heratio storage path, never a hardcoded directory. |
| `--culture` | `en` | i18n culture for labels. |
| `--batch` | `500` | Id page size for the streaming keyset cursor. |
| `--limit` | `0` (no cap) | Cap the record count for smoke runs. |
| `--actors` | off | Also append every actor that produced a published record. |
| `--terms` | off | Also append every subject / place term cited by a published record. |

The command streams: it walks published record ids in ascending id batches and
renders one entity at a time straight to the file, so the whole catalogue is
never held in memory. It is idempotent - each run overwrites the previous dump
atomically (temp file + rename), and prints an accounted summary (records
exported, records skipped, actor/term nodes appended, file size).

Run it on a schedule (for example nightly) so the public download always serves
a current graph.

## Public bulk download

```
GET /data/cidoc-crm.ttl
```

- Unauthenticated open data, published records only, CORS-open
  (`Access-Control-Allow-Origin: *`), `Content-Type: text/turtle`.
- If a scheduled dump exists, it is streamed straight off disk (no per-request
  database work, so a large catalogue costs nothing at request time). The
  response carries `X-Open-Data-Source: prebuilt-dump`.
- If no dump is staged, a BOUNDED graph is generated on the fly, hard-capped at
  2000 records, and streamed as it is produced. The response carries
  `X-Open-Data-Source: on-the-fly` and `X-Open-Data-Cap`; a Turtle comment tells
  the client to fetch the scheduled dump for the complete graph.
- Optional `?culture=` selects the label culture for the on-the-fly path.

The dump is also advertised as a dataset in the platform's capabilities document
(`/open-data/protocol`) and the DCAT data catalogue (`/data/catalog`), so a
generic data-portal harvester discovers it automatically.

## Loading the graph

The output is valid Turtle. Load it into any CIDOC-CRM-aware store or tool -
Apache Jena, ResearchSpace, an Erlangen-CRM importer, or a generic SPARQL
endpoint:

```
riot --validate cidoc-crm.ttl
```

## Notes

- Read-only: the command and the endpoint only ever SELECT. The single write is
  the dump file under the configured storage path.
- International by design: every URI is built from the configured base URL; no
  tenant- or jurisdiction-specific constant is baked in.
