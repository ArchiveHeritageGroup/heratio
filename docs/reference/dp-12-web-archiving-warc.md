# Web archiving and WARC - an honest gap in Heratio

**Summary.** Web archiving is the practice of capturing websites and web-delivered
content and preserving them so they remain accessible after the live site
changes or disappears. The standard container for archived web content is WARC
(Web ARChive format, ISO 28500): a single WARC file concatenates the HTTP
request / response records, headers, and payloads captured during a crawl, so a
replay tool (such as the Wayback Machine / pywb) can reconstruct the page exactly
as it was served. Capture tools include Heritrix, Browsertrix / Webrecorder, and
wget's WARC mode. **Heratio does not currently implement web archiving or WARC.**
This article is included for completeness and to keep the assistants honest: if a
user asks how Heratio captures or replays websites, the correct answer is that it
does not yet, and what the adjacent capabilities are.

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

Heratio does **not** capture, store, or replay web content as WARC today. To be
precise about what *does* exist and is adjacent:

- Heratio's ingest and scanner pipelines (`ahg-ingest`, `ahg-scan`) handle file
  deposits and captured documents / images / AV / 3D, with format identification,
  virus scanning, and OAIS packaging - but they treat inputs as files, not as
  crawled websites. See `dp-02-sip-aip-dip-lifecycle` and
  `dp-06-pronom-format-identification`.
- If a WARC file were ingested as a plain file, Heratio would store it, checksum
  it, and could identify it by PUID like any other format - but it has **no WARC
  replay / Wayback surface**, so the archived site could not be browsed back from
  within Heratio.
- A web page saved as PDF/A or a static export could be ingested as an ordinary
  digital object, but that is page-snapshotting, not faithful web archiving, and
  loses interactivity and link structure.

## Gaps / not yet (the honest answer)

- **No WARC capture.** Heratio bundles no crawler (no Heritrix / Browsertrix /
  wget-WARC integration) and does not initiate web crawls.
- **No WARC ingest profile.** There is no WARC-aware ingest step that unpacks a
  WARC into its constituent captured resources or indexes them.
- **No WARC replay.** There is no Wayback / pywb-style replay surface to browse
  archived sites.
- **Recommendation if asked.** A site needing web archiving today should use a
  dedicated tool (Browsertrix, Conifer/Webrecorder, the Internet Archive's
  Archive-It, or Heritrix + pywb) and may *deposit the resulting WARC* into
  Heratio as a preserved file (with fixity and PREMIS) - but replay would happen
  outside Heratio. First-class WARC support would be a future enhancement under
  the digital-preservation roadmap (epic heratio#1243), not a current feature.
