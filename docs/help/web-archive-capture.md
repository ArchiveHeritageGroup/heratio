# Web archiving (single-page capture to WARC)

Heratio can capture a single web page to a **WARC 1.1** file (ISO 28500), the
international standard container format for web archives. This is the first
slice of a larger web-archiving capability.

## What it does today

- Captures **one page** at the URL you give it (the page's own HTTP response).
- Writes a standards-conformant WARC 1.1 file you can open in any WARC-aware
  tool (for example `warcio`, the Webrecorder tooling, or pywb).
- Records each capture in a list with its HTTP status, content type, byte size,
  and the path to the WARC file.
- **Replays the captured page back to you** from its stored WARC file, with a
  clear archived-snapshot banner (see "Replaying a snapshot" below).

## What it does NOT do yet

- **No crawl.** It does not follow links or fetch a whole site, and it does not
  download embedded resources (images, CSS, JavaScript). It captures the single
  HTTP response for the URL you submit.
- **Single-document replay only.** Replay serves the captured page document
  itself, not its embedded resources. Images, CSS, and JavaScript are not
  replayed from the archive, and nothing live is fetched, so a replayed page may
  look unstyled. Full multi-resource replay (rendering the page with its
  archived assets) is planned future work.

These are deliberate scope limits and are tracked for future work.

## Replaying a snapshot

Open a captured page from its detail view with **Replay snapshot**
(`/admin/web-archive/{id}/replay`). Heratio reads the archived HTTP response
straight from the stored WARC file and serves it back to you:

- For an **HTML page**, the archived document is shown with its original content
  type and a fixed red **ARCHIVED SNAPSHOT** banner at the top, stating the
  capture date and original URL, and making clear this is a stored copy and not
  the live site.
- For a **non-HTML resource** (PDF, image, JSON, and so on), Heratio shows a
  short metadata page with a download link rather than rendering it inline.
- Replay is **read-only and offline**: it serves only the bytes archived at
  capture time. A restrictive Content-Security-Policy blocks every live network
  request, so a replayed page cannot load trackers, scripts, or any live
  resource, and cannot be framed by another site.
- A missing or corrupt WARC file shows a clean "snapshot unavailable" notice
  rather than an error.

## How to capture a page

1. Go to **Admin → Web archive** (`/admin/web-archive`).
2. Paste an `http://` or `https://` URL into the capture box and press
   **Capture**.
3. The capture appears in the list. Open it to see the WARC record headers and
   to download the WARC file.

You can also capture from the command line:

```bash
php artisan ahg:web-capture https://example.org/page
```

## Limits and behaviour

- Only `http`/`https` URLs are accepted.
- Up to **5 redirects** are followed; the fetch times out after 30 seconds.
- Responses larger than **50 MB** are skipped and recorded with an explanatory
  note rather than stored.
- A page that cannot be fetched (host down, DNS failure, timeout) is recorded as
  a **failed** capture with the error message. The capture tool never crashes on
  a network problem.

## Where WARC files are stored

WARC files are written under the configured Heratio storage path, in a
`web-archive/<year>/<month>/` folder. The exact base is governed by
`config('heratio.storage_path')` and is never hard-coded, so it follows your
install's storage configuration (local disk, NAS, etc.).

## About the WARC format

A WARC file is a sequence of records. Each capture produces:

- A **warcinfo** record naming the capturing software and the WARC version.
- A **response** record holding the verbatim HTTP response: the status line,
  the response headers, and the body, wrapped as
  `application/http; msgtype=response`.

Every record carries a globally unique `WARC-Record-ID` (a `urn:uuid`), a
`WARC-Date`, and a `Content-Length`. The response record also carries the
`WARC-Target-URI` (the captured URL).

Reference: WARC 1.1 / ISO 28500 (IIPC WARC specification).
