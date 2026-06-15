# Library SRU server (#1281)

Heratio exposes its library catalogue over **SRU** (Search/Retrieve via URL), the
HTTP/CQL discovery protocol, so other libraries and discovery layers can query the
catalogue in a standard way. This is the server-side counterpart to the existing
Z39.50/copy-cataloguing **client** (`CopyCataloguingService`).

- **Endpoint:** `GET /library/sru`
- **Versions:** SRU 1.1 and 1.2
- **Access:** read-only over the public library catalogue (information objects with
  `source_standard = 'library'` joined to `library_item`). No authentication required;
  CORS-enabled so browser-based discovery clients can call it cross-origin.
- **Record schemas:** Dublin Core (`dc`, default) and MARC 21 XML (`marcxml`).

## Operations

### explain (default)
Returns the SRU capability document (server info, schemas, indexes, limits).
```
GET /library/sru?operation=explain
```

### searchRetrieve
```
GET /library/sru?operation=searchRetrieve&query=<CQL>&maximumRecords=20&startRecord=1&recordSchema=dc
```

| Parameter | Notes |
|-----------|-------|
| `query` | CQL query (required). Use `cql.allRecords` for everything. |
| `version` | `1.1` (default) or `1.2`. |
| `startRecord` | 1-based offset (default 1). |
| `maximumRecords` | page size, default 20, max 500. |
| `recordSchema` | `dc` (default) or `marcxml`. |
| `sortKeys` | e.g. `dc.title ascending`, `dc.date descending`. |

## CQL supported

- **Indexes:** `dc.title`, `dc.creator`, `dc.subject`, `dc.identifier` (ISBN),
  `dc.publisher`, `dc.date`. A bare term (no `dc.*` qualifier) searches title + creator.
- **Relations:** `=` (contains), `<>` / `!=` (not), `<`, `<=`, `>`, `>=`.
- **Boolean:** top-level `AND` / `OR` (and a single layer of outer parentheses).

Examples:
```
query=dc.title = "annual report"
query=dc.creator = smith AND dc.date > 2000
query=mandela
```

A missing/empty query, an unsupported version, or a backend error returns a proper
SRU `<srw:diagnostics>` block rather than an HTTP error.

## Logging

Each `searchRetrieve` is logged best-effort to `library_sru_log`
(query, parsed CQL, result count, duration, remote address, and a SHA-256 *hint* of
any `X-API-Key` supplied - never the key itself). Logging is guarded: if the table is
absent (backbone migration not yet run) the endpoint still serves normally.

## Implementation

- Service: `AhgLibrary\Services\SruService` (CQL parsing, query building, XML output).
- Controller: `AhgLibrary\Controllers\SruController` (operation dispatch, CORS, logging).
- Primary creator is resolved via `library_item_creator.sort_order` (lowest = primary).

The binary Z39.50 server (port 210, BER/APDU - a long-running socket daemon) is a
separate, heavier follow-up; SRU covers modern HTTP-based discovery interop.
