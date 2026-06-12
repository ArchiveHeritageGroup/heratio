# Web archive (WARC)

Snapshot a web page into a valid WARC 1.1 (ISO 28500) file and replay it back from
the archive. One admin surface offers two capture modes: archive any public URL, or
snapshot a published record's own page on this host.

## What web archiving is

Web archiving captures a web page and preserves it in the standard **WARC**
container (Web ARChive format, ISO 28500) so it remains readable after the live
page changes or disappears. A WARC file concatenates the HTTP request and response
records, headers and payloads captured during a fetch, so the archived page is
self-describing.

## The admin surface: /admin/web-archive

At **Admin → Web archive** (`/admin/web-archive`) you can capture in two ways, then
browse, download and replay every capture in one list.

### Mode 1 - Archive a URL

Enter any public **http/https URL** and select *Capture URL*. Heratio fetches that
one page and writes it to a WARC file. This is a single-page capture (it does not
crawl the rest of the site or fetch the page's subresources). Only public URLs are
accepted - internal, loopback, and credentialed URLs are refused.

### Mode 2 - Capture a record page

Enter a **published record ID** and select *Capture snapshot*. Heratio resolves that
record's own canonical public page URL (`/{slug}`), checks the record is published,
and fetches that one page **plus its direct same-host subresources** (CSS,
JavaScript, images, icons), so the captured record page is more self-contained.

### Browse, download, replay

- **Browse captures.** Every capture is listed with the date, the mode (URL or
  Record), the target, the count of same-host subresources captured ("Assets", e.g.
  "+7" or "page only"), the WARC file size, the outcome status (Captured / Failed),
  the page's HTTP status, and the file's SHA-256 fixity digest.
- **Download the WARC.** Each successful capture streams the stored `.warc` file
  (`Content-Type: application/warc`), openable in any WARC-aware tool.
- **Replay the snapshot.** Each successful capture has a *Replay* action that serves
  the archived page back **entirely from the stored WARC**, never from the live site
  (see "Replaying a snapshot" below).

The page is admin-gated and always degrades gracefully: an unreachable page, an
oversize page, a blocked URL, or a record that is not published produces a clear
*Failed* row with a short reason, never an error page.

## What is in the WARC file

Each capture writes a single WARC 1.1 file (version line `WARC/1.1`) containing a
`warcinfo` record (capture-software + format metadata), then the page's `request`
and `response` records, and - for a record capture - a `request` + `response` pair
for each captured same-host subresource. Every record carries correct WARC headers:
`WARC-Type`, a unique `WARC-Record-ID` (a `urn:uuid`), `WARC-Date`,
`WARC-Target-URI`, `Content-Type`, a `Content-Length` for the block, and a
`WARC-Block-Digest` of `sha256:...`. The file validates as WARC 1.1.

## Replaying a snapshot

Replay reconstructs the captured page **from its stored WARC** and serves it back to
you with a clear **Archived snapshot** banner. For a record capture, the same-host
subresources that were captured into the WARC (CSS, JavaScript, images) are rewritten
to load from the archive, so the replayed page can render with its own styling and
images. A restrictive content-security policy is applied so the replayed page cannot
reach out to the live web, and **nothing live is ever fetched** - a subresource that
was not captured simply does not load. A missing or corrupt WARC shows a clean
"snapshot unavailable" message rather than an error page.

## Where the files are stored

WARC files are written under the configured storage path, in a `web-archive`
subdirectory (`{HERATIO_STORAGE_PATH}/web-archive`). The path is never hardcoded; it
follows the same storage configuration as the rest of Heratio. A `warc_capture` row
records the metadata for each capture (mode, target URI, file path, byte size,
SHA-256, HTTP status and outcome status).

## Safety and bounds

- **SSRF-safe.** Record mode takes a record *ID*, not a URL: Heratio derives the
  record's own canonical page URL on this host, and every subresource it fetches must
  be on the SAME host (no off-host address, no embedded credentials, no alternate
  port, no loopback / metadata address, no non-HTTP scheme). URL mode accepts only
  public http/https URLs and refuses internal / loopback / metadata / private-range
  and credentialed URLs.
- **Bounded.** One page (plus, in record mode, its direct depth-1 same-host
  subresources) per capture, with connection and total timeouts, a redirect cap,
  per-asset and total-capture size limits, a cap on the number of subresources, and
  URL de-duplication. An oversize page fails cleanly; an individual asset that 404s,
  times out, is oversize, or redirects off-host is skipped while the rest of the
  capture succeeds.

## What this does not do yet (honest scope)

Record mode archives the record's own HTML page plus its direct same-host
subresources; URL mode is a single-page capture. Neither fetches **off-host** assets
(third-party CDNs, web fonts, analytics), so a page that pulls a stylesheet or a font
from another domain will replay without it. Subresource discovery is depth-1 only.
Neither mode is a general site crawler. For full-site or subresource-faithful
archiving, use a dedicated tool (Browsertrix, Conifer / Webrecorder, Archive-It, or
Heritrix + pywb) and deposit the resulting WARC into Heratio as a preserved file.
Off-host capture remains on the digital-preservation roadmap.
