# Web archive (WARC)

Snapshot a published record's own public page into a valid WARC 1.1 (ISO 28500)
file, so the catalogue can web-archive its own record pages.

## What web archiving is

Web archiving captures a web page and preserves it in the standard **WARC**
container (Web ARChive format, ISO 28500) so it remains readable after the live
page changes or disappears. A WARC file concatenates the HTTP request and
response records, headers and payloads captured during a fetch, so the archived
page is self-describing.

Heratio's slice of this lets the catalogue web-archive **its own** record pages.
Each capture performs a server-side request for one published record's own public
page on this host, then for that page's direct **same-host** subresources (CSS,
JavaScript, images, icons), and writes a single valid WARC 1.1 file you can store
and download, so the captured page is more self-contained.

## The admin surface: /admin/web-archive

At **Admin → Web archive** (`/admin/web-archive`) you can:

- **Capture a record page.** Enter a **published record ID** and select *Capture
  snapshot*. Heratio resolves that record's own canonical public page URL
  (`/{slug}`), checks the record is published, and fetches that one page.
- **Browse captures.** Every capture is listed with the date, the record, the
  exact target URI, the count of same-host subresources captured ("Assets", e.g.
  "+7" or "page only"), the WARC file size, the outcome status (Captured / Failed),
  the HTTP status of the page, and the file's SHA-256 fixity digest.
- **Download the WARC.** Each successful capture has a download link that streams
  the stored `.warc` file (`Content-Type: application/warc`).

The page is admin-gated and always degrades gracefully: an unreachable page, an
oversize page, or a record that is not published produces a clear *Failed* row
with a short reason, never an error page.

## What is in the WARC file

Each capture writes a single WARC 1.1 file (version line `WARC/1.1`) containing,
in order:

1. **`warcinfo`** - capture-software and format metadata (`application/warc-fields`).
2. **`request`** - the exact HTTP request line and headers Heratio sent for the page.
3. **`response`** - the HTTP status line, response headers and body of the page
   (`application/http; msgtype=response`).
4. **A `request` + `response` pair for each captured same-host subresource** -
   every CSS, JavaScript, image or icon Heratio fetched from the same host, with
   the same record structure as the page records.

Every record carries correct WARC headers: `WARC-Type`, a unique
`WARC-Record-ID` (a `urn:uuid`), `WARC-Date`, `WARC-Target-URI`, `Content-Type`,
a `Content-Length` for the block, and a `WARC-Block-Digest` of `sha256:...`. The
file validates as WARC 1.1 and can be opened by standard WARC tooling.

## Where the files are stored

WARC files are written under the configured storage path, in a `web-archive`
subdirectory (`{HERATIO_STORAGE_PATH}/web-archive`). The path is never hardcoded;
it follows the same storage configuration as the rest of Heratio. The
`warc_capture` table records the metadata for each capture (record, target URI,
file path, byte size, SHA-256, HTTP status and outcome status).

## Safety and bounds

- **Same-host only (SSRF-safe).** The capture takes a record *ID*, not a URL.
  Heratio derives the record's own canonical page URL on this host, and every
  subresource it then fetches must be on the SAME host: no off-host address, no
  different host, no embedded credentials, no alternate port, no loopback /
  metadata address, no non-HTTP scheme. Off-host assets (third-party CDNs, web
  fonts) are dropped, not fetched.
- **Bounded.** One page plus its direct (depth-1) same-host subresources per
  capture, with connection and total timeouts, a redirect cap, per-asset and
  total-capture size limits, a cap on the number of subresources, and URL
  de-duplication. An oversize page or a failing asset is handled cleanly: the page
  fails cleanly if it is oversize, and an individual asset that 404s, times out, is
  oversize, or redirects off-host is simply skipped while the rest of the capture
  succeeds.

## What this does not do yet (honest scope)

This slice archives the record's own HTML page **plus its direct same-host
subresources** (CSS, JavaScript, images, icons). It deliberately does **not** fetch
**off-host** assets (third-party CDNs, web fonts, analytics), so a page that pulls
a stylesheet or a font from another domain will replay without it. Subresource
discovery is depth-1 only: a nested `@import` inside a fetched CSS file is not
followed. There is also **no in-app replay** (Wayback / pywb) viewer yet - the
stored WARC is browsed with external WARC tooling. Off-host capture and replay
remain on the digital-preservation roadmap.
