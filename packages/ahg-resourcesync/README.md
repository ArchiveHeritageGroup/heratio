# ahg-resourcesync

ResourceSync 1.1 (NISO Z39.99-2017) Source-role endpoint for Heratio.

Exposes four sitemap-style XML documents under HTTP for federation aggregators
that prefer the lightweight sitemap profile to the older OAI-PMH protocol.

## Endpoints

| URL | Capability | Notes |
| --- | --- | --- |
| `GET /.well-known/resourcesync` | `description` | Discovery file. Points at the CapabilityList. |
| `GET /resourcesync/capabilitylist.xml` | `capabilitylist` | Lists `resourcelist` + `changelist`. |
| `GET /resourcesync/resourcelist.xml?page=N` | `resourcelist` | Full inventory of published archival records. Paged. |
| `GET /resourcesync/changelist.xml?page=N` | `changelist` | Updates + tombstones inside the horizon (default 30 days). Paged. |

All four are rate-limited at `throttle:120,1` (120 req/min/IP) to match the
OAI-PMH endpoint shape.

## Configuration

`config/resourcesync.php` reads two env keys:

| Env | Default | Purpose |
| --- | --- | --- |
| `RESOURCESYNC_CHANGELIST_DAYS` | `30` | ChangeList horizon in days. |
| `RESOURCESYNC_PAGE_SIZE` | `1000` | Page size for ResourceList + ChangeList. |

Page size also honours the OAI `resumption_token_limit` setting when set, so
operators only have to tune one knob.

## What's published

Mirrors the OAI-PMH publication-status filter exactly:

- `information_object` joined to `object` (for `updated_at`)
- `status` join on `type_id = 158` (publication-status taxonomy) and
  `status_id = 160` (published)
- non-null, non-zero `parent_id` (excludes the synthetic root node)

## Tombstones

Sourced from `oai_deleted_record` — the same table the OAI-PMH
`php artisan oai:mark-deleted` worker populates. ResourceSync and OAI report
the same deletion set.

## Aggregator compatibility

The documents validate against the ResourceSync 1.1 schemas. Tested-shape
fields:

- sitemap default namespace `http://www.sitemaps.org/schemas/sitemap/0.9`
- `xmlns:rs="http://www.openarchives.org/rs/terms/"` extension namespace
- `<rs:md capability="...">` declares each document type
- `<rs:ln rel="up">` chains the CapabilityList back to the SourceDescription
  and the ResourceList/ChangeList back to the CapabilityList
- `<rs:ln rel="next">` / `rel="prev"` paginate ResourceList + ChangeList
- ChangeList entries carry `<rs:md change="created|updated|deleted">`

## Tests

`tests/Feature/ResourceSyncTest.php` covers all four endpoints. The tombstone
test seeds an `oai_deleted_record` row, asserts it appears in ChangeList with
`change="deleted"`, and tears the row back down.
