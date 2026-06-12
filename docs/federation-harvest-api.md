# Federation Harvest API (union catalogue)

The harvest API is the public read surface that lets partner / aggregator
systems pull the shared discovery records out of the Heratio federated GLAM
network (issue #1203). It paginates over the union index
(`federation_union_record`) and exposes each record as Dublin-Core-ish fields.

Two surfaces, both anonymous-readable and CORS-open:

| Route | Format | Purpose |
|---|---|---|
| `GET /union-catalogue/harvest` | JSON | Paginated harvest with pagination metadata + `next` link |
| `GET /union-catalogue/harvest.xml` | XML | OAI-DC-style `ListRecords` with a `resumptionToken` |

> **Path note.** The harvest API is mounted under `/union-catalogue/` (not
> `/federation/harvest`). `GET /federation/harvest` is already taken by the F3
> admin harvest-client page (auth+admin gated, named `federation.harvest`, in
> the locked `FederationController`), so the public surface lives beside the
> sibling `/union-catalogue` search route instead. Both harvest paths are
> two-segment, so the locked single-segment `/{slug}` catch-all does not
> intercept them.

Only records contributed by **enabled** members
(`federation_member.is_enabled = 1`) are harvestable, matching the public union
search surface. No authentication is required: this is a discovery / harvest
surface by design.

## Record fields (Dublin Core mapping)

| Harvest field | Union column | DC element |
|---|---|---|
| `identifier` | `record_ref` | `dc:identifier` |
| `title` | `title` | `dc:title` |
| `type` | `level` (level of description) | `dc:type` |
| `date` | `dates` (display date string) | `dc:date` |
| `source` | member name + holding repository | `dc:source` |
| `url` | `url` (source permalink) | `dc:identifier` |
| `datestamp` | `indexed_at` (UTC ISO 8601) | OAI `<datestamp>` |

`member` / `member_id` / `repository` are also returned in the JSON for
convenience.

## Query parameters

| Param | Default | Notes |
|---|---|---|
| `page` | `1` | 1-based page number |
| `per_page` | `100` | Bounded; hard cap **500**. Out-of-range values are clamped |
| `member` | (all enabled) | Restrict to one contributing institution (`federation_member.id`). Ignored if that member is not enabled |
| `from` | (none) | Incremental harvest - only records with `indexed_at >= from`. Accepts an ISO date or datetime; an unparseable value is ignored, not an error |

## JSON response shape

```json
{
  "harvest": "glam-federation",
  "format": "dc-json",
  "total": 1234,
  "count": 100,
  "page": 1,
  "per_page": 100,
  "last_page": 13,
  "member": null,
  "from": null,
  "next": "https://host/union-catalogue/harvest?page=2&per_page=100",
  "records": [
    {
      "identifier": "my-record-slug",
      "title": "Minute book, 1894-1901",
      "type": "File",
      "date": "1894-1901",
      "source": "National Archive / Records Office",
      "member": "National Archive",
      "member_id": 3,
      "repository": "Records Office",
      "url": "https://host/my-record-slug",
      "datestamp": "2026-06-12T04:00:00Z"
    }
  ]
}
```

`next` is `null` on the last page. It is built with `url()` so the host is
never hardcoded, and it preserves the active `member` / `from` / `per_page`
filters.

## XML response shape (OAI-DC `ListRecords`)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" ...>
  <responseDate>2026-06-12T04:00:00Z</responseDate>
  <request verb="ListRecords" metadataPrefix="oai_dc">https://host/union-catalogue/harvest.xml</request>
  <ListRecords>
    <record>
      <header>
        <identifier>my-record-slug</identifier>
        <datestamp>2026-06-12T04:00:00Z</datestamp>
      </header>
      <metadata>
        <oai_dc:dc ...>
          <dc:title>Minute book, 1894-1901</dc:title>
          <dc:type>File</dc:type>
          <dc:date>1894-1901</dc:date>
          <dc:source>National Archive / Records Office</dc:source>
          <dc:identifier>my-record-slug</dc:identifier>
          <dc:identifier>https://host/my-record-slug</dc:identifier>
        </oai_dc:dc>
      </metadata>
    </record>
    <resumptionToken completeListSize="1234" cursor="0">2</resumptionToken>
  </ListRecords>
</OAI-PMH>
```

The `resumptionToken` carries the next page number while more pages remain;
it is absent on the last page. An empty result returns a valid
`<error code="noRecordsMatch">` document, never an HTTP fault.

## Empty state

A fresh install (tables not yet created), no enabled members, or an empty
union index all return a **valid empty harvest** - `total: 0` with an empty
record list for JSON, and a `noRecordsMatch` OAI error document for XML.
Neither surface ever returns a 500.

## Implementation

- `packages/ahg-federation/src/Services/UnionHarvestService.php` - bounded,
  forward-keyed read over `federation_union_record` joined to
  `federation_member`. Page size clamped to 1..500. Schema-guarded.
- `packages/ahg-federation/src/Controllers/UnionHarvestController.php` -
  `json()` and `xml()` actions, CORS headers, `next` link / `resumptionToken`.
- Routes registered in `AhgUnionCatalogueServiceProvider::register()` via
  `callAfterResolving('router')`, alongside the existing union-catalogue and
  network-directory routes. Two-segment paths, so the locked single-segment
  `/{slug}` catch-all does not intercept them.

Read-only: no writes, no `ALTER`, no new table. Built entirely additively,
never touching the four locked F3 SharePoint files.
