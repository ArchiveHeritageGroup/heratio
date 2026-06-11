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

Heratio does **not** currently crawl, store-as-WARC, or replay websites. It is a
GLAM archival and preservation platform for files (documents, images, audio,
video, 3D, and their metadata), not a web crawler.

To be precise about the adjacent capabilities:

- Heratio's ingest and capture pipelines handle file deposits with format
  identification, virus scanning, fixity, and OAIS packaging - but they treat
  inputs as files, not as crawled websites.
- A WARC file *could* be deposited into Heratio like any other file: it would be
  stored, checksummed, and identifiable by format. But Heratio has **no replay
  surface**, so you could not browse the archived site from within Heratio.
- Saving a page as a PDF/A snapshot is possible as ordinary file ingest, but that
  is a flat snapshot, not faithful web archiving - it loses interactivity and link
  structure.

## If you need web archiving now

Use a dedicated tool to capture the site to WARC - for example Browsertrix /
Webrecorder, the Internet Archive's Archive-It, or Heritrix with pywb for replay.
You may then deposit the resulting WARC file into Heratio as a preserved object
(so it gets fixity and a PREMIS history), while replay happens in the dedicated
tool.

First-class WARC support in Heratio would be a future enhancement, not a current
feature.

## See also

- Digital preservation overview (help)
- OAIS packages: SIP, AIP, DIP (help)
