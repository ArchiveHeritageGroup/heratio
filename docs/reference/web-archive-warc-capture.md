# Web archiving: single-page WARC 1.1 capture (ahg-scan)

Heratio's web-archiving slices capture a single web page to a WARC 1.1 file
(ISO 28500) and replay that captured page back from its stored WARC. They live
in the `ahg-scan` package alongside the scanner / capture pipeline. Scope:
single-page capture (no crawl, no embedded-resource harvesting) and
single-document replay (the captured page document only, no embedded-resource
replay yet).

## Components

- **Service** `AhgScan\Services\WebArchiveCaptureService`
  - `capture(string $url, ?int $userId): ?int` fetches the URL via the Laravel
    `Http` client and writes a WARC file, recording one `web_archive_capture`
    row. It never throws: validation errors, network failures, the 50 MB size
    cap, and filesystem errors are all recorded as a `failed` row.
  - `buildWarc(...)` assembles the WARC body (testable in isolation).
  - Fetch bounds: 30 s timeout, max 5 redirects, descriptive `User-Agent`
    (`Heratio-WebArchive/1.0`).
- **Command** `ahg:web-capture {url}` (`AhgScan\Console\WebCaptureCommand`).
- **Controller** `AhgScan\Controllers\WebArchiveController` serving
  `/admin/web-archive` (list + submit form), `/admin/web-archive/{id}` (detail
  with parsed WARC record headers), `/admin/web-archive/{id}/replay` (snapshot
  replay), and `/admin/web-archive/{id}/download`.
- **Routes** `packages/ahg-scan/routes/web-archive.php`, registered by
  `AhgScanServiceProvider::boot()` under middleware `['auth', 'admin']`.
- **Views** `resources/views/admin/web-archive/{index,show,replay-binary,replay-unavailable}.blade.php`.
  The `index`/`show` views extend `theme::layouts.1col`; the two `replay-*` views
  are standalone HTML documents (no theme layout) so they render under the strict
  replay Content-Security-Policy without pulling in live theme assets.

## Replay (single-document)

- **Service** `AhgScan\Services\WarcReplayService` (read-only). Given a stored
  `warc_path`, it resolves and traversal-guards the path, streams the WARC,
  length-frames each record by its `Content-Length`, skips the warcinfo record,
  reads the `response` record block, and parses the archived HTTP status line,
  headers, and body. The public `replay(?string $warcPath): array` never throws:
  every failure (missing file, outside the storage root, unreadable, truncated,
  no response record, malformed framing) returns `['ok' => false, 'error' => ...]`.
  On success it returns `['ok' => true, 'status', 'reason', 'headers', 'body',
  'content_type', 'target_uri']`.
- **Length-framed parser.** The reader takes each record's declared
  `Content-Length` and reads exactly that many bytes as the record block, so a
  body that itself contains a `WARC/1.1` line or blank lines is parsed
  correctly. Header scan is bounded (64 KB) and block reads are capped at 50 MB.
- **Path resolution + traversal guard.** `resolvePath()` realpaths the stored
  `warc_path`, requires it to be a readable file, and requires the canonical path
  to sit under `realpath(config('heratio.storage_path').'/web-archive')` (compared
  with a trailing separator so a sibling prefix like `web-archive-evil` cannot
  match). A `..` traversal or any path outside the root is rejected, returning a
  clean error. The storage root is never hard-coded; it mirrors the capture
  service.
- **Controller serving.** `WebArchiveController::replay($id)`:
  - HTML (`Content-Type` contains `html`): serves the archived body verbatim with
    a fixed inline-styled **ARCHIVED SNAPSHOT** banner injected after `<body>`
    (falling back to after `<html>` or the document top), carrying the original
    content type / charset.
  - Non-HTML: renders `replay-binary` (a metadata page with a download link);
    the raw bytes are streamed only on `?raw=1`, with the original content type
    and a `Content-Disposition: attachment`.
  - Missing / corrupt / unparseable WARC: renders `replay-unavailable` as a clean
    200, never a 500.
- **Safe-serving headers** (every replayed response):
  `Content-Security-Policy: default-src 'none'; img-src 'self' data:;
  style-src 'unsafe-inline'; font-src 'self' data:; form-action 'none';
  base-uri 'none'; frame-ancestors 'none'`, plus `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`,
  `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store`. The CSP blocks
  every live network fetch so a replayed page cannot reach the live web or load
  trackers.

## Table: `web_archive_capture`

Auto-created on first boot by `AhgScanServiceProvider::installWebArchiveSchema()`
using `CREATE TABLE IF NOT EXISTS`, guarded by `Schema::hasTable` inside a
try/catch in the deferred `booted()` callback. No `ALTER` is ever issued. Status
is a `VARCHAR(16)` (`pending` / `captured` / `failed`), never a MySQL `ENUM`.

| Column        | Type             | Notes                                  |
|---------------|------------------|----------------------------------------|
| id            | BIGINT UNSIGNED  | PK                                      |
| url           | VARCHAR(2048)    | captured URL                           |
| title         | VARCHAR(1024)    | best-effort HTML `<title>`             |
| status        | VARCHAR(16)      | pending / captured / failed            |
| http_status   | INT NULL         | HTTP response code                     |
| content_type  | VARCHAR(255) NULL| response Content-Type                  |
| warc_path     | VARCHAR(1024) NULL| absolute path to the WARC file        |
| byte_size     | BIGINT UNSIGNED NULL | stored response body size          |
| captured_by   | INT NULL         | user id                                |
| captured_at   | DATETIME NULL    | success timestamp                      |
| error         | VARCHAR(2048) NULL| failure detail                        |
| created_at    | DATETIME         | row creation                           |

## WARC 1.1 record structure

Each capture produces a WARC file with two records:

1. **warcinfo** - `WARC-Type: warcinfo`, `Content-Type: application/warc-fields`,
   naming the software, `format: WARC file version 1.1`, and `conformsTo` the
   IIPC WARC 1.1 spec URL.
2. **response** - `WARC-Type: response`, `WARC-Target-URI: <url>`,
   `Content-Type: application/http; msgtype=response`. Its block is the verbatim
   HTTP message: the reconstructed status line (`HTTP/1.1 200 OK`), the response
   headers (repeated headers preserved), a blank line, and the body.

Every record carries `WARC-Record-ID` (a `urn:uuid:...`), `WARC-Date` (UTC,
`YYYY-MM-DDThh:mm:ssZ`), and `Content-Length` (the exact byte length of the
record block). Records are framed as `WARC/1.1`, named fields, a blank CRLF line,
the block, then a two-CRLF terminator. The response record is
`WARC-Concurrent-To` the warcinfo record.

## Storage location

WARC files are written under
`config('heratio.storage_path').'/web-archive/<Y>/<m>/'` - never hard-coded.
Filenames are `<host-slug>-<Ymd-His>-<random>.warc`. The directory is created on
demand with a guarded `mkdir`.

## Resilience / safety notes

- The whole fetch path is try/catch wrapped; the remote host being down yields a
  `failed` row, not an exception. The build is not network-dependent.
- Responses over `WebArchiveCaptureService::MAX_BYTES` (50 MB) are skipped and
  noted.
- Routes live under `admin/`, which the `/{slug}` catch-all in
  `ahg-information-object-manage` already excludes, so the admin pages are
  catch-all safe.
- All admin actions are empty-state safe: if the table is not yet installed the
  pages render an informative notice instead of a 500.

## Not yet implemented (future track)

- Crawl / link-following / embedded-resource capture.
- **Multi-resource replay.** Today's replay is single-document: it serves the
  captured page document only. Embedded resources (CSS, JS, images, fonts) are
  not replayed from the archive and nothing live is fetched, so a replayed HTML
  page may render unstyled. A pywb-style multi-resource replay surface (rewriting
  subresource URLs to archived copies) is future work and depends on the capture
  side first harvesting those resources.
- Request records (the WARC `request` record paired with each response).

Reference: WARC 1.1 / ISO 28500, IIPC WARC specification.
