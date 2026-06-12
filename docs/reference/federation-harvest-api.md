# Federation harvest API - union catalogue public pull surface

The Heratio federated GLAM network (#1203) exposes a public HARVEST API so
partner / aggregator systems can pull the shared union-catalogue records. It is
the read counterpart to the publish pass that pushes this institution's opt-in
records into `federation_union_record`.

## Surfaces

- `GET /union-catalogue/harvest` - paginated JSON harvest, CORS-open.
- `GET /union-catalogue/harvest.xml` - OAI-DC-style `ListRecords` XML with a
  `resumptionToken`, CORS-open.

Mounted under `/union-catalogue/` rather than `/federation/harvest`: the latter
is already taken by the F3 admin harvest-client page (auth+admin gated, named
`federation.harvest`, in the locked `FederationController`). The public read
home sits beside the sibling `/union-catalogue` search route. Both harvest
paths are two-segment, so the locked single-segment `/{slug}` catch-all in
`ahg-information-object-manage` does not intercept them. Anonymous-readable.
Only records from enabled members (`federation_member.is_enabled = 1`) are
harvestable.

## Records (Dublin Core mapping)

`identifier`=`record_ref`, `title`=`title`, `type`=`level`, `date`=`dates`,
`source`=member name + repository, `url`=source permalink, `datestamp`=
`indexed_at` as UTC ISO 8601. JSON also returns `member`, `member_id`,
`repository`.

## Query parameters

- `page` (default 1), `per_page` (default 100, hard cap 500, clamped).
- `member=<id>` - restrict to one contributing institution (must be enabled).
- `from=<stamp>` - incremental harvest, `indexed_at >= from`; an unparseable
  value is ignored, not an error.

JSON carries pagination metadata (`page`, `per_page`, `total`, `last_page`)
plus a `next` url built with `url()` (host never hardcoded) that preserves the
active filters; `next` is null on the last page. XML emits a `resumptionToken`
holding the next page number while pages remain.

## Empty state

Fresh install (no tables), no enabled members, or an empty index all return a
valid empty harvest (JSON `total: 0`; XML `noRecordsMatch` error document).
Never a 500.

## Files

- `packages/ahg-federation/src/Services/UnionHarvestService.php` - bounded
  forward-keyed query over `federation_union_record` joined to
  `federation_member`; Schema::hasTable-guarded.
- `packages/ahg-federation/src/Controllers/UnionHarvestController.php` -
  `json()` + `xml()`.
- Routes registered in `AhgUnionCatalogueServiceProvider::register()` via
  `callAfterResolving('router')`, beside the existing union-catalogue and
  network-directory routes.

Read-only: no DB writes, no ALTER, no new table (reads
`federation_union_record` + `federation_member`). Additive new files only -
never touches the four locked F3 SharePoint files (`src/Connectors/`,
`FederatedSearchService.php`, `FederationController.php`, `edit-peer.blade.php`).
