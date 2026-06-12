# Web archiving: single-page WARC 1.1 capture (ahg-scan)

Heratio's first web-archiving slice captures a single web page to a WARC 1.1
file (ISO 28500). It lives in the `ahg-scan` package alongside the scanner /
capture pipeline. It is single-page only: no crawl, no embedded-resource
harvesting, and no replay yet.

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
  with parsed WARC record headers), and `/admin/web-archive/{id}/download`.
- **Routes** `packages/ahg-scan/routes/web-archive.php`, registered by
  `AhgScanServiceProvider::boot()` under middleware `['auth', 'admin']`.
- **Views** `resources/views/admin/web-archive/{index,show}.blade.php`,
  extending `theme::layouts.1col`.

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
- WARC **replay** (rendering the archived page back inside Heratio, e.g. via a
  pywb-style replay surface).
- Request records (the WARC `request` record paired with each response).

Reference: WARC 1.1 / ISO 28500, IIPC WARC specification.
