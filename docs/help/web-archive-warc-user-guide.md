# Web archive (WARC)

Snapshot a published record's own public page into a valid WARC 1.1 (ISO 28500)
file, so the catalogue can web-archive its own record pages.

## What web archiving is

Web archiving captures a web page and preserves it in the standard **WARC**
container (Web ARChive format, ISO 28500) so it remains readable after the live
page changes or disappears. A WARC file concatenates the HTTP request and
response records, headers and payloads captured during a fetch, so the archived
page is self-describing.

Heratio's first slice of this lets the catalogue web-archive **its own** record
pages. Each capture performs a server-side request for one published record's own
public page on this host and writes a valid WARC 1.1 file you can store and
download.

## The admin surface: /admin/web-archive

At **Admin → Web archive** (`/admin/web-archive`) you can:

- **Capture a record page.** Enter a **published record ID** and select *Capture
  snapshot*. Heratio resolves that record's own canonical public page URL
  (`/{slug}`), checks the record is published, and fetches that one page.
- **Browse captures.** Every capture is listed with the date, the record, the
  exact target URI, the WARC file size, the outcome status (Captured / Failed),
  the HTTP status of the page, and the file's SHA-256 fixity digest.
- **Download the WARC.** Each successful capture has a download link that streams
  the stored `.warc` file (`Content-Type: application/warc`).

The page is admin-gated and always degrades gracefully: an unreachable page, an
oversize page, or a record that is not published produces a clear *Failed* row
with a short reason, never an error page.

## What is in the WARC file

Each capture writes a WARC 1.1 file (version line `WARC/1.1`) containing three
records, in order:

1. **`warcinfo`** — capture-software and format metadata (`application/warc-fields`).
2. **`request`** — the exact HTTP request line and headers Heratio sent.
3. **`response`** — the HTTP status line, response headers and body of the page
   (`application/http; msgtype=response`).

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

- **Same-host, own-record only (SSRF-safe).** The capture takes a record *ID*,
  not a URL. Heratio derives the record's own canonical page URL on this host and
  refuses to fetch anything else — no off-host address, no different record, no
  non-HTTP scheme.
- **Bounded.** One page per capture, with connection and total timeouts, a
  redirect cap, and a hard response-size limit. An oversize page fails cleanly.

## What this does not do yet (honest scope)

This first slice archives the record's **own HTML page only**. It does **not**
yet fetch and embed the page's subresources (CSS, JavaScript, images, fonts), and
there is **no in-app replay** (Wayback / pywb) viewer — the stored WARC is
browsed with external WARC tooling. Multi-resource capture and replay remain on
the digital-preservation roadmap.
