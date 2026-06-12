# Web archiving (WARC 1.1) in Heratio - the single merged surface

**Summary.** Web archiving captures a web page and preserves it in the standard
**WARC** container (Web ARChive format, ISO 28500) so it remains readable after
the live page changes or disappears. Heratio implements a bounded, verifiable
slice of WARC web archiving (heratio#1244) behind **ONE admin surface** at
`/admin/web-archive`, backed by **ONE capture engine**, **ONE replay engine**,
and **ONE register table**. The surface offers BOTH capture modes:

1. **Archive a URL** (url mode) - snapshot any public http/https page.
2. **Capture a record page** (record mode) - snapshot a PUBLISHED record's OWN
   public page on this host, plus that page's direct same-host subresources.

Each capture writes a valid WARC 1.1 file that you can store, download, and
**replay back from the archive** (a minimal in-app Wayback / pywb-style viewer
that serves the page entirely FROM the stored WARC, never from the live site).
Honest gap (keeps #1244 open): off-host assets (third-party CDNs / fonts) are not
captured, so they do not replay.

## Architecture: one surface, one engine, one table

The web-archive feature was consolidated from two duplicate implementations into
one in the #1244 merge. The post-merge layout:

### Surface (the only one) - `ahg-scan`

- **Controller** `AhgScan\Controllers\WebArchiveController` - a thin surface over
  the ahg-core engines. It owns the live `web-archive.*` route names + URIs.
- **Routes** `packages/ahg-scan/routes/web-archive.php`, registered by
  `AhgScanServiceProvider::boot()` under middleware `['auth', 'admin']`, all under
  the `admin/` prefix (which the `/{slug}` catch-all already excludes):

  | Method | URI                              | Name                  | Action          |
  |--------|----------------------------------|-----------------------|-----------------|
  | GET    | `/admin/web-archive`             | `web-archive.index`   | list + 2 forms  |
  | POST   | `/admin/web-archive`             | `web-archive.store`   | archive a URL   |
  | POST   | `/admin/web-archive/capture`     | `web-archive.capture` | capture a record|
  | GET    | `/admin/web-archive/{id}`        | `web-archive.show`    | capture detail  |
  | GET    | `/admin/web-archive/{id}/replay` | `web-archive.replay`  | replay from WARC |
  | GET    | `/admin/web-archive/{id}/asset`  | `web-archive.asset`   | one subresource |
  | GET    | `/admin/web-archive/{id}/download`| `web-archive.download`| stream the .warc |

  The static `/capture` segment is registered before the numeric `/{id}` wildcard
  (and `/{id}` is `whereNumber`) so it is never mistaken for an id. Only ahg-scan
  registers the `web-archive.*` names - there is exactly ONE controller for them,
  so there is no route collision.
- **Views** `resources/views/admin/web-archive/{index,show}.blade.php`
  (Bootstrap 5 + central theme). The replay / unavailable / asset responses are
  rendered inline by the controller (under the strict replay CSP), so there are no
  standalone replay blades.
- **Command** `ahg:web-capture {url}` (`AhgScan\Console\WebCaptureCommand`) - the
  CLI face of url mode; it calls the engine and writes to `warc_capture`.

### Engine (the only one) - `ahg-core` (the base package ahg-scan depends on)

- **Capture** `AhgCore\Services\WarcCaptureService`
  - `capture(int $informationObjectId, ?int $userId): array` - record mode.
    Resolves the record's OWN canonical `url('/'.slug)` on THIS host (published
    gate `status.type_id=158` / `status_id=160`, root id 1 excluded),
    `assertOwnRecordUrl()`-validates it (same scheme/host/path, no port / no
    credentials, http(s) only), fetches it with a bounded cURL GET, then discovers
    + fetches its direct SAME-HOST subresources (depth-1) and appends them to the
    same WARC.
  - `captureUrl(string $submittedUrl, ?int $userId): array` - url mode. Validates
    the URL is http/https AND passes the public-host guard (`isPublicHttpUrl()`
    rejects loopback / link-local / cloud-metadata / private-range hosts and
    embedded credentials - a strict ADDITION, never a loosening), fetches it with
    the same bounded cURL client, and writes a page-only WARC (url mode does not
    crawl subresources - those are host-scoped to the record in record mode).
  - Both modes never throw: a bad / unreachable / blocked / oversize target records
    a `failed` row and returns `ok=false` with a clean message.
  - `buildWarc(...)`, `listCaptures()`, `fileForDownload()`, `statusLabel()` are
    shared by both modes and the surface.
- **Replay** `AhgCore\Services\WarcReplayService` (read-only, the well-tested
  length-delimited WARC 1.1 parser)
  - `captureRow(int $id): ?array` - reads one `warc_capture` row.
  - `safeWarcPath(array $row): ?string` - realpaths the stored `file_path` and
    requires it under `realpath(config('heratio.storage_path').'/web-archive')` (a
    `..` traversal or any path outside the root is rejected).
  - `buildModel(array $row): ?array` - opens + parses the WARC (bounded: 32 MiB
    file cap, 5000-record cap, 8 MiB per-block cap) into a `URI -> response` map,
    picks the main page (the response whose URI == `target_uri`, with a tolerant
    first-`text/html` fallback). A missing / oversize / corrupt / empty WARC
    returns `null` (the surface renders a clean "snapshot unavailable", never a
    500).
  - `findResource(array $row, string $uri): ?array` - one archived subresource by
    URI; a miss is a 404 at the controller, never a live fetch.
  - **Length-framed parser.** Each record's declared `Content-Length` is read
    exactly, so a body that itself contains a `WARC/1.1` line or blank lines parses
    correctly.

### Table (the only one) - `warc_capture`

Owned + installed by `AhgCoreServiceProvider::boot()` (CREATE TABLE IF NOT EXISTS,
guarded by `Schema::hasTable` inside one outer try/catch; no `ALTER`). Status comes
from the Dropdown Manager group `warc_capture_status` (never a MySQL `ENUM`). The
#1244 merge added two additive columns (`mode`, `submitted_url`) so the single
table serves both modes:

| Column                | Type              | Notes                                        |
|-----------------------|-------------------|----------------------------------------------|
| id                    | BIGINT UNSIGNED   | PK                                           |
| information_object_id | INT NULL          | soft ref to the record (record mode); no FK  |
| slug                  | VARCHAR(255) NULL | record slug (record mode)                    |
| **mode**              | **VARCHAR(16)**   | **`record` or `url` (added #1244 merge); never ENUM** |
| **submitted_url**     | **VARCHAR(2048) NULL** | **raw operator-submitted URL (url mode; added #1244 merge)** |
| target_uri            | VARCHAR(2048)     | the exact URL fetched (the WARC-Target-URI)  |
| file_path             | VARCHAR(1024) NULL| absolute path to the stored `.warc`          |
| file_name             | VARCHAR(255) NULL | the `.warc` download name                    |
| byte_size             | BIGINT UNSIGNED NULL | stored `.warc` byte size                  |
| sha256                | CHAR(64) NULL     | hex SHA-256 of the `.warc` (fixity)          |
| http_status           | SMALLINT NULL     | HTTP status of the captured page             |
| status                | VARCHAR(32)       | `captured` / `failed` (Dropdown Manager)     |
| error_message         | VARCHAR(1024) NULL| failure reason, or the subresource-count note|
| captured_by           | INT NULL          | soft ref to the operator; no FK              |
| captured_at           | TIMESTAMP NULL    | capture time                                 |
| created_at/updated_at | TIMESTAMP NULL    |                                              |

The record-mode subresource COUNT is recorded WITHOUT a schema change: a short note
is stored in the existing `error_message` column for successful captures and parsed
back for the list.

## SSRF posture (not loosened by the merge)

- **Record mode** is the strictest: only the record's OWN canonical URL on THIS host
  is ever fetched (`assertOwnRecordUrl`), and same-host subresources pass
  `assertSameHostUrl` (same host as the record page + a literal-host block-list for
  loopback / link-local / cloud-metadata / private ranges). Off-host references are
  dropped honestly.
- **URL mode** keeps the original ahg-scan rule (http/https only) AND adds a
  public-host guard (`isPublicHttpUrl`) that refuses internal / loopback /
  link-local / metadata / private-range hosts and credentialed URLs. This is a
  strict addition, not a relaxation. cURL is additionally pinned to http/https.

## Replay serving + safe headers

`WebArchiveController::replay($id)` serves the archived main page FROM the WARC:
HTML is rewritten so its captured same-host subresource URLs (`<link href>`,
`<script src>`, `<img/source src+srcset>`, inline `<style> url(...)`) point at the
`web-archive.asset` route, an "Archived snapshot - captured <when>" banner is
prepended, and the original `<base href>` is neutralised. Non-HTML payloads are
served verbatim from the WARC. The `web-archive.asset` route serves ONE captured
subresource by URI - a URI not in the WARC is a 404, never a live fetch. Every
replayed response carries a restrictive CSP (`default-src 'self'`,
`connect-src 'self'`, `frame-src 'none'`, `object-src 'none'`, `base-uri 'none'`)
plus `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff`,
`X-Robots-Tag: noindex`, and `Cache-Control: no-store`, so a replayed page cannot
beacon out to the live site. A missing / corrupt / empty WARC degrades to a clean
"snapshot unavailable" page (HTTP 200), never a 500.

## Storage location

WARC files are written under `config('heratio.storage_path').'/web-archive'` - never
hard-coded; the directory is created on demand with a guarded `mkdir`. The replay /
download path is realpath-confined to that root.

## Gaps / not yet (the honest answer - #1244 stays open)

- **Off-host assets not captured.** Record mode archives the page plus its direct
  same-host subresources only; url mode is page-only. Off-host assets (third-party
  CDNs, web fonts, analytics) are not fetched, so they do not replay. Subresource
  discovery is depth-1 (nested `@import` inside a fetched CSS file is not followed).
- **Own pages / public URLs only.** Record mode is scoped to the catalogue's OWN
  published record pages (SSRF-safe); url mode archives a single public page. Neither
  is a general site crawler.
- **No WARC ingest/unpack profile.** There is no step that unpacks a deposited WARC
  into its constituent resources.
- **Recommendation if asked.** For full-site or subresource-faithful archiving, use a
  dedicated tool (Browsertrix, Conifer / Webrecorder, Archive-It, or Heritrix + pywb)
  and deposit the resulting WARC into Heratio as a preserved file (with fixity +
  PREMIS). Off-host capture and a fuller replay remain open under heratio#1244 /
  epic heratio#1243.

Reference: WARC 1.1 / ISO 28500, IIPC WARC specification.
