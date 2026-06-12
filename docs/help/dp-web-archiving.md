> Heratio Help Center article. Category: Digital Preservation.

# Web archiving and WARC (what Heratio does and does not do)

## What web archiving is

Web archiving captures websites - their pages, images, stylesheets, scripts, and
embedded media - and preserves them so they can still be viewed after the live
site changes or disappears. The standard container is **WARC** (Web ARChive
format, ISO 28500), which records each captured HTTP request and response so a
replay tool can rebuild the page exactly as it was served. Common capture tools
are Heritrix, Browsertrix / Webrecorder, and wget's WARC mode.

## What Heratio does today

Heratio implements a bounded, verifiable slice of WARC web archiving at **Admin →
Web archive** (`/admin/web-archive`). One admin surface offers two capture modes,
and every capture can be replayed back from its stored WARC inside Heratio:

- **Archive a URL.** Snapshot any public http/https page into a WARC 1.1 file
  (single-page capture).
- **Capture a record page.** Snapshot a published record's OWN public page on this
  host, plus that page's direct same-host subresources (CSS, JavaScript, images,
  icons), into one WARC 1.1 file.
- **Replay.** Each successful capture replays back entirely from the stored WARC,
  never from the live site, with an "Archived snapshot" banner and a restrictive
  content-security policy. Captured same-host subresources are rewritten to load
  from the archive; nothing live is fetched.

It is SSRF-safe (record mode fetches only the record's own page + same-host assets;
URL mode refuses internal / loopback / metadata / credentialed URLs), bounded
(timeouts, redirect cap, size caps, subresource cap), and writes only the
`warc_capture` register table plus the `.warc` files under the configured storage
path. For the full user guide see the "Web archive (WARC)" help article.

Heratio's ingest and capture pipelines additionally treat any externally-produced
WARC deposited as a plain file (store, checksum, PUID-identify), the way they treat
any file.

## Honest gap

The capture archives the record's own page plus its direct same-host subresources
(record mode) or a single page (URL mode). It does **not** fetch off-host assets
(third-party CDNs, web fonts), so those do not replay, and subresource discovery is
depth-1. Neither mode is a general site crawler.

## If you need full-site web archiving now

For full-site or subresource-faithful archiving, use a dedicated tool to capture the
site to WARC - for example Browsertrix / Webrecorder, the Internet Archive's
Archive-It, or Heritrix with pywb for replay. You may then deposit the resulting WARC
file into Heratio as a preserved object (so it gets fixity and a PREMIS history).

## See also

- Digital preservation overview (help)
- OAIS packages: SIP, AIP, DIP (help)
