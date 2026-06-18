# Library SRU server (#1281, consolidated under #1312)

Heratio exposes its library catalogue over **SRU** (Search/Retrieve via URL), the
HTTP/CQL discovery protocol, so other libraries and discovery layers can query the
catalogue in a standard way. This is the server-side counterpart to the existing
Z39.50/copy-cataloguing **client** (`CopyCataloguingService`).

> **#1312 consolidation:** there used to be two SRU responders - a richer one in
> `ahg-library` at `/library/sru` (SRU 1.1/1.2) and the canonical one in
> `ahg-z3950` at `/sru` (SRU 2.0). They had diverged. The duplicate was retired and
> its strengths (catalogue scoping, `dc:description`, CORS, `library_sru_log`
> logging) were merged into the single canonical `/sru` endpoint owned by
> `ahg-z3950`, which also owns the Z39.50 server surface. The legacy
> `/library/sru` URL now **301-redirects to `/sru`** (query string preserved) so
> existing discovery-layer bookmarks keep working.

- **Endpoint:** `GET /sru` (legacy `GET /library/sru` 301-redirects here)
- **Version:** SRU 2.0
- **Access:** read-only over the public library catalogue (information objects with
  `source_standard = 'library'` joined to `library_item`). No authentication required;
  CORS-enabled so browser-based discovery clients can call it cross-origin.
- **Record schemas:** MARC 21 XML (`marcxml`, default) and Dublin Core (`dc`).

## Operations

### explain (default)
Returns the SRU capability document (server info, schemas, indexes, limits).
```
GET /sru?operation=explain
```

### searchRetrieve
```
GET /sru?operation=searchRetrieve&query=<CQL>&maximumRecords=10&startRecord=1&recordSchema=marcxml
```

| Parameter | Notes |
|-----------|-------|
| `query` | CQL query (required). |
| `version` | `2.0`. |
| `startRecord` | 1-based offset (default 1). |
| `maximumRecords` | page size, default 10, max 100. |
| `recordSchema` | `marcxml` (default) or `dc`. |
| `recordPacking` | `xml` (default) or `string`. |

## CQL supported

- **Indexes:** `dc.title`/`title`, `dc.creator`/`creator`/`author`,
  `dc.subject`/`subject`, `dc.identifier`/`identifier`, `bath.isbn`/`isbn`,
  `bath.issn`/`issn`, `dc.publisher`/`publisher`, `dc.date`. `cql.anywhere` /
  `cql.serverChoice` (or a bare term) searches title + ISBN + ISSN + publisher.
- **Relations:** `=` (contains), `==`/`exact` (exact match), `<>`, `<`, `>`.
- **Boolean:** `AND` / `OR` / `NOT`.

Subjects are matched against the real subject taxonomy via
`object_term_relation` + `term_i18n` (not a free-text material-type column).

Examples:
```
query=dc.title = "design patterns"
query=author = freeman AND dc.date > 2000
query=mandela
```

A missing/empty query, an unsupported operation, or a CQL parse error returns a
proper SRU 2.0 `<diagnostics>` block rather than an HTTP error.

## Logging

Each `searchRetrieve` is logged best-effort to `library_sru_log`
(query, parsed CQL, result count, duration, optional error, remote address, and a
SHA-256 *hint* of any `X-API-Key` supplied - never the key itself). Logging is
guarded: if the table is absent (backbone migration not yet run) the endpoint still
serves normally. The `library_sru_log` table and its migration remain in
`ahg-library` (it is the log sink); `ahg-z3950` writes to it cross-package.

## Implementation

- Service: `AhgZ3950\Services\SruService` (CQL parsing, query building, XML output;
  exposes `lastResultCount` + `lastCql` accessors for request logging).
- Controller: `AhgZ3950\Controllers\SruController` (operation dispatch, CORS,
  `library_sru_log` logging).
- Route: registered in `App\Providers\AppServiceProvider` (pre-registered there so
  it beats the locked `/{slug}` catch-all). The `/library/sru` redirect lives in
  `ahg-library/routes/web.php`.

The binary Z39.50 server (port 210, BER/APDU - a long-running socket daemon) is a
separate `z3950:server` command; SRU covers modern HTTP-based discovery interop.
