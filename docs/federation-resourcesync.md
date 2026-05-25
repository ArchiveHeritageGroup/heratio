# ResourceSync Source endpoint — operator runbook

> Heratio operator documentation. Module: federation. Spec: ResourceSync 1.1
> (NISO Z39.99-2017, https://www.openarchives.org/rs/1.1/resourcesync).

Heratio exposes its archival inventory in two cross-repository surfaces:

1. **OAI-PMH 2.0** at `/oai` — the long-established protocol for archive +
   library aggregators.
2. **ResourceSync 1.1** at `/.well-known/resourcesync` and
   `/resourcesync/*.xml` — the newer sitemap-style protocol favoured by
   research-data, IIIF, and web-archiving aggregators that already speak
   sitemaps.

This runbook covers the ResourceSync surface. For OAI-PMH see
`oai-user-guide.md`.

## Discovery

A polite ResourceSync aggregator always starts at the well-known URL:

```
GET https://your-heratio.example.org/.well-known/resourcesync
```

This returns a **SourceDescription** document that links to the
**CapabilityList**:

```
GET https://your-heratio.example.org/resourcesync/capabilitylist.xml
```

The CapabilityList lists the two capabilities Heratio offers as a Source:

- **ResourceList** at `/resourcesync/resourcelist.xml` — the full inventory
  of published archival records.
- **ChangeList** at `/resourcesync/changelist.xml` — recent updates and
  tombstones, configurable horizon.

## What's published

The publication-status filter mirrors the OAI-PMH endpoint exactly:

- `information_object` rows joined to the `status` table on
  `type_id=158, status_id=160` (publication status = published).
- The synthetic root node (`parent_id IS NULL OR parent_id = 0`) is
  excluded.

This means a record that is invisible to anonymous OAI harvesting is also
invisible to ResourceSync — and vice versa. If a record turns up missing
from a ResourceSync aggregator, check its publication status in Heratio
admin first.

## Pagination

ResourceList and ChangeList are paged via `?page=N` (1-indexed). Each page
emits sitemap-style `<rs:ln rel="next">` and `rel="prev"` links so an
aggregator can walk the chain without guessing the total count.

Page size is configurable two ways, in priority order:

1. The OAI `resumption_token_limit` setting in Admin → Settings → OAI (so
   operators only have to tune one knob for both protocols).
2. The package config / env var `RESOURCESYNC_PAGE_SIZE`. Default: `1000`.

## ChangeList horizon

The ChangeList only reports records whose `updated_at` (or `deleted_at` for
tombstones) falls within the last `RESOURCESYNC_CHANGELIST_DAYS` days.
Default: `30`.

Picking the horizon:

- **High-churn site, aggregators poll daily** → 7 days is plenty. Smaller
  document, faster sweep.
- **Quiet site, aggregators poll weekly** → leave at 30.
- **Aggregators that poll once a month** → raise to 60.

A polite aggregator that misses a poll window can always fall back to the
full ResourceList, so the horizon is a performance tuning knob, not a
correctness one.

### Change semantics

Each ChangeList entry carries one of three `<rs:md change="...">` values:

| Value | Meaning |
| --- | --- |
| `created` | Row's `created_at == updated_at` (no later edits since insert). |
| `updated` | Row's `created_at < updated_at` (at least one edit). |
| `deleted` | Row is in `oai_deleted_record` (the same tombstone table the OAI-PMH endpoint uses). |

Heratio does not version individual edits at the inventory level, so the
created-vs-updated distinction is a heuristic, not a full audit. For full
edit history, the `audit_trail` and `version_control` packages have
per-field detail.

## Tombstones

Deleted records flow through the same path as OAI-PMH:

1. Operator (or a workflow) hard-deletes the archival record.
2. The `php artisan oai:mark-deleted <oai_local_identifier>` command (or
   the equivalent service call) inserts a row into `oai_deleted_record`.
3. The next ResourceSync ChangeList that covers that `deleted_at`
   timestamp surfaces a `change="deleted"` entry.

The tombstone `loc` URL is synthetic — the slug is gone after a hard delete,
so we emit `/informationobject/by-oai/<oai_local_id>` as a stable identifier
the aggregator can de-dupe on. Aggregators do not need a live URL here; they
only need to know which previously-published record went away.

## Rate limiting

All four endpoints use `throttle:120,1` (120 requests per minute per IP),
matching the OAI-PMH endpoint. A polite aggregator walking the full chain
(SourceDescription → CapabilityList → ResourceList pages → ChangeList) will
not come close to the limit. A misbehaving scraper that pegs the endpoint
will start getting 429s.

## Disabling

ResourceSync is enabled by default once the package is installed. To turn
it off without removing the package, drop the four routes by overriding
the service provider (or by adding a route-level middleware that rejects
the requests). There is no `resourcesync_enabled` settings flag at this
time — open a feature request if you need one.

## Compliance checklist

- [x] Sitemap default namespace `http://www.sitemaps.org/schemas/sitemap/0.9`
- [x] `xmlns:rs="http://www.openarchives.org/rs/terms/"` extension namespace
- [x] `<rs:md capability>` on every document
- [x] `<rs:ln rel="up">` on every document below the SourceDescription
- [x] `<rs:ln rel="next">` / `rel="prev"` on every paged document
- [x] `<rs:md change>` on every ChangeList entry
- [x] `<lastmod>` element on every `<url>` so plain sitemap consumers can
       still use the documents
- [x] `application/xml; charset=UTF-8` content type per the spec
- [x] HTTP 200 only for successful responses (no soft errors in XML)

## Related

- `oai-user-guide.md` — the older OAI-PMH 2.0 endpoint
- `federation-user-guide.md` — Heratio's federation module (search +
  vocabulary sync between peers)
- Phase 1 + Phase 2 of issue #670 — qualified Dublin Core in OAI and the
  `oai_deleted_record` tombstone table that this endpoint reuses
