# Web archiving and WARC in Heratio

**Summary.** Web archiving is the practice of capturing websites and web-delivered
content and preserving them so they remain accessible after the live site
changes or disappears. The standard container for archived web content is WARC
(Web ARChive format, ISO 28500): a single WARC file concatenates the HTTP
request / response records, headers, and payloads captured during a crawl, so a
replay tool (such as the Wayback Machine / pywb) can reconstruct the page exactly
as it was served. Capture tools include Heritrix, Browsertrix / Webrecorder, and
wget's WARC mode. **Heratio now implements a bounded first slice of WARC web
archiving (heratio#1244):** the catalogue can snapshot a PUBLISHED record's OWN
public page - plus that page's direct SAME-HOST subresources (CSS / JS / images /
icons) - into a single valid WARC 1.1 file at **Admin -> Web archive**
(`/admin/web-archive`). This article describes what that slice does, exactly, and
is honest about what is NOT yet done (off-host subresource capture, replay). If a
user asks how Heratio captures or replays websites, the correct answer is: it can
web-archive its own record pages AND their same-host subresources into a valid WARC
1.1 file today, but it does not fetch off-host (third-party CDN / font) assets and
has no in-app replay surface.

## The concept

- **WARC (ISO 28500).** The ISO container for web crawls. Each record captures one
  HTTP transaction (URL, headers, status, payload) plus capture metadata, so the
  archived resource is self-describing and replayable.
- **Capture.** A crawler (Heritrix, Browsertrix, wget --warc) fetches pages and
  their dependencies (CSS, JS, images, embedded media) and writes them to WARC.
  Modern dynamic / JavaScript-heavy sites need a browser-based crawler to capture
  faithfully.
- **Replay.** A WARC-aware replay tool reconstructs and serves the archived pages
  from the WARC, rewriting links so navigation stays inside the archive.
- **Why it is a distinct discipline.** Web content is not a static file: it is a
  graph of resources with time-sensitive, often dynamic behaviour, which is why it
  has its own capture tools and container rather than being treated as ordinary
  file ingest.

## How Heratio addresses this

Heratio implements a bounded, verifiable first slice of WARC web archiving: the
catalogue can web-archive **its own published record pages**. Precisely:

- **Capture surface (`/admin/web-archive`, admin-gated).** An operator enters a
  PUBLISHED record's ID and captures a snapshot. The page is in
  `packages/ahg-core` (`WebArchiveController` + `WarcCaptureService`); the routes
  are multi-segment (`/admin/web-archive`, `/admin/web-archive/capture`,
  `/admin/web-archive/{id}/download`) so they never collide with the
  single-segment `/{slug}` archival-record catch-all.
- **What is fetched (SSRF-safe).** The capture takes a record ID, not a URL.
  `WarcCaptureService` resolves the record's OWN canonical public URL with
  `url('/'.slug)` on THIS host (published gate: `status.type_id=158`,
  `status_id=160`, root id 1 excluded) and `assertOwnRecordUrl()` re-derives that
  canonical URL and refuses anything whose scheme / host / path differs, anything
  with an embedded port or credentials, or any non-HTTP scheme. No off-host,
  cross-record, or arbitrary URL is ever fetched.
- **The bounded fetch.** A server-side cURL GET of that one page, with a connect
  timeout, a total timeout, a redirect cap, and a hard response-size cap (8 MiB).
  An unreachable or oversize page records a `failed` row with a clean reason.
- **Same-host subresources (depth-1).** After the page is fetched, the HTML is
  parsed for its DIRECT subresource references: `<link rel=stylesheet|icon|preload
  href>`, `<script src>`, `<img src>` + `srcset`, `<source srcset>`, and `url(...)`
  inside inline `<style>` blocks. Each reference is resolved against the page URL
  and passed through `assertSameHostUrl()`, which applies the SAME SSRF guards as
  the page fetch: http/https only, the same host as the record page, no embedded
  credentials, no alternate port, and a literal-host block-list (loopback /
  link-local / cloud-metadata / private-range). ONLY same-host assets are kept;
  off-host references (third-party CDNs, fonts, analytics) are dropped and never
  fetched. Each kept asset is fetched with the same cURL client (pinned to
  http/https) and appended to the SAME WARC as a `request` + `response` pair,
  identical in structure to the page records. Bounds: at most 50 subresources, a
  per-asset cap (4 MiB), a total-capture budget (24 MiB), a per-asset timeout, URL
  de-duplication, and depth-1 only (nested `@import` inside fetched CSS is NOT
  recursed). A subresource that 404s / times out / is oversize / redirects
  off-host is skipped cleanly and never aborts the capture; a page with no
  same-host subresources still produces a valid page-only WARC.
- **The WARC 1.1 produced.** A `warcinfo` record (`application/warc-fields`), then
  the page `request` (`application/http; msgtype=request`) and `response`
  (`application/http; msgtype=response`), then a `request` + `response` pair for
  each captured same-host subresource - all in one file. Each record carries a
  `WARC/1.1` version line and correct headers: `WARC-Type`, a `urn:uuid`
  `WARC-Record-ID`, `WARC-Date`, `WARC-Target-URI`, `Content-Type`,
  `Content-Length` for the block, and a `WARC-Block-Digest: sha256:<base32>`.
  Records are CRLF-CRLF terminated.
- **Storage + register.** The `.warc` bytes are written under
  `config('heratio.storage_path').'/web-archive'` (never a hardcoded path; the
  storage root is www-data-writable). A row is recorded in the NEW `warc_capture`
  table (information_object_id, slug, target_uri, file path + name, byte size,
  file SHA-256, http status, outcome status, captured_by, captured_at). The
  outcome status comes from the Dropdown Manager group `warc_capture_status` (no
  ENUM). The subresource COUNT is recorded WITHOUT any schema change (no ALTER, no
  new column): for a successful capture a short note ("N same-host subresources
  captured." / "Page only; no same-host subresources.") is written into the
  existing `error_message` free-text column and parsed back for the admin list +
  result message. Download streams the file with `Content-Type: application/warc`.
- **Adjacent file pipelines.** Heratio's ingest and scanner pipelines
  (`ahg-ingest`, `ahg-scan`) still treat any externally-produced WARC deposited as
  a plain file the way they treat any file (store, checksum, PUID-identify). See
  `dp-02-sip-aip-dip-lifecycle` and `dp-06-pronom-format-identification`.

## Gaps / not yet (the honest answer)

- **Same-host subresources only - off-host assets not fetched.** The capture now
  archives the record's own HTML page PLUS its direct same-host subresources (CSS,
  JS, images, icons). It deliberately does NOT fetch OFF-HOST assets (third-party
  CDNs, web fonts, analytics) - those are dropped honestly by the SSRF guard - so a
  page that loads, say, a CDN stylesheet or a Google font will replay without it.
  Subresource discovery is also depth-1: nested `@import` inside a fetched CSS file
  is not followed.
- **No replay surface.** There is no in-app Wayback / pywb-style replay viewer;
  the stored WARC is browsed with external WARC tooling.
- **Own pages only.** The capture is deliberately scoped to the catalogue's OWN
  published record pages on this host (SSRF-safe). It is not a general crawler and
  does not capture external sites.
- **No WARC ingest/unpack profile.** There is no ingest step that unpacks a
  deposited WARC into its constituent captured resources or indexes them.
- **Recommendation if asked.** For full-site or subresource-faithful archiving,
  use a dedicated tool (Browsertrix, Conifer / Webrecorder, the Internet Archive's
  Archive-It, or Heritrix + pywb) and deposit the resulting WARC into Heratio as a
  preserved file (with fixity and PREMIS). Off-host subresource capture and replay
  inside Heratio remain open under the digital-preservation roadmap (heratio#1244
  / epic heratio#1243).
