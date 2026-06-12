# Open Memory Protocol: ResourceSync (NISO Z39.99) web sync

Summary: Heratio exposes a ResourceSync 1.1 (NISO Z39.99-2017) sync framework over the published corpus, the modern web-sync complement to OAI-PMH (`/api/oai`) and the XML sitemaps (`/sitemap.xml`, `/sitemap-data.xml`). It is implemented in the dedicated `packages/ahg-resourcesync` package (Issue #670 Phase 3). A Source Description at `/.well-known/resourcesync` links to a Capability List (`/resourcesync/capabilitylist.xml`) that advertises a Resource List (`/resourcesync/resourcelist.xml`) and a Change List (`/resourcesync/changelist.xml`). Read-only (SELECT / COUNT only; no writes, no ALTER, no new table). No hardcoded host: every `<loc>`/`<href>` is built from `url()` / `route()`. Built on the sitemaps.org 0.9 namespace plus `xmlns:rs="http://www.openarchives.org/rs/terms/"`. The surface is also advertised in the Open Memory Protocol index (`/open-data/protocol`) via `ProtocolController::surfaces()` (id `resourcesync`, route `resourcesync.source-description`).

## Where it lives

The implementation is the `ahg-resourcesync` package, NOT `ahg-api`:

- `packages/ahg-resourcesync/src/Controllers/ResourceSyncController.php` - the four document handlers (XMLWriter-based).
- `packages/ahg-resourcesync/routes/web.php` - the four routes (each `throttle:120,1`).
- `packages/ahg-resourcesync/config/resourcesync.php` - `changelist_days` (default 30) + `page_size` (default 1000).
- `packages/ahg-resourcesync/src/Providers/AhgResourceSyncServiceProvider.php` - loads routes + config.

`ahg-api`'s only involvement is the discovery entry in `ProtocolController::surfaces()` so ResourceSync shows up in `/open-data/protocol` and the DCAT catalogue alongside OAI-PMH and the sitemaps.

## Why ResourceSync (alongside OAI-PMH + sitemaps)

ResourceSync (NISO Z39.99-2017) is the modern successor framework to OAI-PMH for web-scale synchronisation. It reuses the sitemaps XML format and adds the `rs:` terms so an aggregator can do a full baseline sync (Resource List) and incremental syncs (Change List, including deletes). The existing surfaces each cover a different need:

- `/api/oai` (ahg-oai) - classic OAI-PMH 2.0 metadata harvest (Dublin Core).
- `/sitemap.xml` (ahg-api PublicSitemapController) - the human `/{slug}` record PAGES for search engines.
- `/sitemap-data.xml` (ahg-api DataSitemapController) - the dereferenceable `/id/...` ENTITY URIs for LOD crawlers.
- `/feed.atom` + `/feed.rss` (ahg-api FeedController) - recency-window syndication.
- `/.well-known/resourcesync` (ahg-resourcesync) - the ResourceSync sync framework: baseline + incremental + tombstones.

## The four documents (ahg-resourcesync ResourceSyncController.php)

- `sourceDescription()` -> `GET /.well-known/resourcesync` - a `<urlset>` with `<rs:md capability="description"/>`, an `<rs:ln rel="describedby">` to `/oai/docs`, and one `<url>` whose `<loc>` is the Capability List (`<rs:md capability="capabilitylist"/>`).
- `capabilityList()` -> `GET /resourcesync/capabilitylist.xml` - a `<urlset>` with `<rs:ln rel="up">` to the Source Description, `<rs:md capability="capabilitylist"/>`, and one `<url>` each for the Resource List (`capability="resourcelist"`) and the Change List (`capability="changelist"`).
- `resourceList()` -> `GET /resourcesync/resourcelist.xml` - a `<urlset>` with `<rs:md capability="resourcelist" at="...">` (the document generation time), then one `<url>` per PUBLISHED record: `<loc>` = the canonical record page (`route('informationobject.show')` / `url('/'.slug)`), `<lastmod>` + `<rs:md datetime="...">` from `object.updated_at`. Paged via `?page=N`.
- `changeList()` -> `GET /resourcesync/changelist.xml` - a `<urlset>` with `<rs:md capability="changelist" from="..." until="...">` (the window), then one `<url>` per record changed in the window (`<rs:md change="created|updated" datetime="...">`) followed by tombstones (`<rs:md change="deleted" datetime="...">`). Paged via `?page=N`.

## The timestamp used for lastmod (DESCRIBE-verified)

`object.updated_at` (and `object.created_at` for the created/updated classification). Both are real `datetime` columns on the CTI parent `object` table (`information_object.id` = `object.id`). DESCRIBE-confirmed:

```
object: class_name varchar(255) | created_at datetime | updated_at datetime | id int | serial_number int
```

Verified populated for the published corpus: all 377 published records carry a non-null `updated_at` (225 distinct values; newest 2026-06-10). This is the same `object.updated_at` that the ahg-api DataSitemapController and FeedController already use.

## Changelist advertised (and why)

Yes. Because `object.updated_at` is a real, populated modified timestamp, the Change List is genuine and IS advertised in the Capability List. A record is reported `change="created"` only when `created_at == updated_at` (created in the window and not modified since), otherwise `change="updated"`. Deletes (`change="deleted"`) come from the `oai_deleted_record` tombstone table - the SAME table the OAI-PMH `php artisan oai:mark-deleted` worker populates - so ResourceSync and OAI report an identical deletion set. No change events are fabricated; if a future deployment lacked a reliable modified timestamp, the honest behaviour would be to omit the Change List rather than invent one - but on this schema it exists.

## Paging approach (no silent truncation)

- Page size: `resourcesync.page_size` (default 1000), but it first honours the OAI `resumption_token_limit` setting so operators tune one knob; the ResourceSync 50000-line sitemap ceiling sits well above the default.
- Resource List: `offset = (page-1)*pageSize`, ordered by `io.id`. When `page < totalPages` the document emits `<rs:ln rel="next" href="...?page=N+1"/>` (and `rel="prev"` when `page > 1`), so a harvester walks the whole chain - nothing dropped without a next-link.
- Change List: a trailing window over `object.updated_at >= now()-changelist_days` (`from`/`until` declared on the `rs:md`), live changes ordered by `updated_at` then tombstones ordered by `deleted_at`, paged across the boundary with the same honest `rel="next"`/`rel="prev"`.
- Bounded queries only - a single `SELECT ... LIMIT/OFFSET` per page; the whole catalogue is never materialised.

## Published-only gate (mirrors ahg-oai)

`information_object` JOIN `object` (for `updated_at`) JOIN `status` on `type_id=158 AND status_id=160` (Published), LEFT JOIN `slug`, `parent_id IS NOT NULL AND parent_id <> 0` (synthetic root excluded). This mirrors the OAI ListRecords filter exactly so the two federation surfaces stay in sync. Drafts are never listed. Tombstone + setting lookups are guarded by `Schema::hasTable(...)` + `try/catch`; a fresh install without `oai_deleted_record` / `setting` degrades gracefully.

## Routes (packages/ahg-resourcesync/routes/web.php)

Registered at the ROOT (no group prefix), each under `throttle:120,1`:

- `GET /.well-known/resourcesync` (name `resourcesync.source-description`).
- `GET /resourcesync/capabilitylist.xml` (name `resourcesync.capability-list`).
- `GET /resourcesync/resourcelist.xml` (name `resourcesync.resource-list`).
- `GET /resourcesync/changelist.xml` (name `resourcesync.change-list`).

## Catch-all safety

`/.well-known/resourcesync` is multi-segment and begins with `.well-known` (exactly like the existing `/.well-known/void`), and the `/resourcesync/...` paths are multi-segment too. The single-segment `/{slug}` archival-record catch-all in `ahg-information-object-manage` is constrained to `[a-z0-9][a-z0-9-]*$` (single segment, no slash, no leading dot), so it can NEVER capture any of these. A normal record slug still resolves. Verified on the live route table: all four register under their names with no collision.

## Surfaces() entry (ahg-api)

Added to `ProtocolController::surfaces()` as id `resourcesync` (title "ResourceSync (NISO Z39.99) web sync", media type `application/xml`), resolved via `resolve('resourcesync.source-description', '/.well-known/resourcesync')` so a slimmer install that lacks the `ahg-resourcesync` package degrades gracefully (the surface drops rather than dead-links). It therefore appears in `/open-data/protocol` and (via the shared surface list) in the DCAT catalogue.

## Standards + safety

- Sitemaps.org 0.9 namespace + ResourceSync `rs:` terms; `<rs:md capability="...">` on every list; `<rs:ln rel="up"|"next"|"prev"|"describedby">` links.
- Content-Type `application/xml; charset=UTF-8`. XMLWriter emits well-formed, escaped XML.
- Every URL via `url()` / `route()` - no hardcoded host. Jurisdiction-neutral.
- Read-only end to end; no DB writes, no ALTER, no new table.
