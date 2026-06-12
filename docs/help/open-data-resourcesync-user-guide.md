> Heratio Help Center article. Category: Technical / Integration.

# Open Data: ResourceSync (NISO Z39.99) User Guide

## Overview

ResourceSync is the modern web synchronisation framework standardised as **NISO Z39.99-2017**. It lets an aggregator efficiently discover and keep in sync with the full set of published records, and complements the platform's existing OAI-PMH endpoint and XML sitemaps. ResourceSync is built on the sitemaps.org XML format, extended with the ResourceSync `xmlns:rs="http://www.openarchives.org/rs/terms/"` terms. Start at **/.well-known/resourcesync**.

---

## What it does

ResourceSync publishes four standards-based XML documents over the published catalogue:

- **/.well-known/resourcesync** is the *Source Description* - the zero-knowledge entry point. It links to the Capability List.
- **/resourcesync/capabilitylist.xml** is the *Capability List* - it advertises the sync capabilities this source offers: a Resource List and a Change List.
- **/resourcesync/resourcelist.xml** is the *Resource List* - every published record as a `<url>` with its canonical record-page `<loc>`, a `<lastmod>`, and an `<rs:md>` carrying the same modified time. This is the full baseline for an initial sync.
- **/resourcesync/changelist.xml** is the *Change List* - the records created, updated, or deleted within a trailing recency window, each tagged `change="created"`, `change="updated"`, or `change="deleted"`. This is the incremental "what changed recently" surface for keeping an existing sync up to date.

Only **published** records are ever listed (drafts are never exposed). Each `<loc>` is the canonical public record page, the same resource a search engine or OAI-PMH harvester resolves.

---

## How to use it

1. **Discover:** fetch **/.well-known/resourcesync** (for example `https://your-site.example/.well-known/resourcesync`). It returns the Source Description pointing at the Capability List.
2. **Read capabilities:** follow the link to **/resourcesync/capabilitylist.xml** to see that this source offers a Resource List and a Change List.
3. **Baseline sync:** fetch **/resourcesync/resourcelist.xml** to enumerate every published record with its last-modified time. Mirror each `<loc>`.
4. **Page through a large catalogue:** the Resource List is paged (the default page size is 1000 records, well under the ResourceSync 50000-line ceiling). When more remain, the document carries an explicit `<rs:ln rel="next" href="...?page=2"/>` (and `rel="prev"` going back) - follow the chain to the end; nothing is silently truncated.
5. **Incremental sync:** later, fetch **/resourcesync/changelist.xml** to pick up just the records that changed - including deletions. The `from` and `until` attributes on the change list declare the window it covers (default the last 30 days); the list pages the same way with `?page=N`.

---

## The Change List, its timestamp, and deletions

The Change List is genuine, not fabricated: it is driven by the real per-record modified timestamp the platform stores for every record (`object.updated_at`). A record whose creation time equals its modified time, inside the window, is reported as `change="created"`; otherwise it is `change="updated"`. Deletions are reported as `change="deleted"` and are sourced from the same tombstone store the OAI-PMH endpoint uses, so ResourceSync and OAI-PMH report an identical deletion set. Because a real modified timestamp exists and is populated for every published record, the Change List is advertised in the Capability List. If a future deployment lacked that timestamp, the Change List would be omitted rather than invented.

---

## Notes

- **Read-only and public.** All four documents are open data, require no authentication, and never modify anything.
- **Content type.** Every document is served as `application/xml`.
- **Portable.** Every URL is built from the deployment's own host, so a fresh install on its own domain self-describes - there are no hardcoded hosts.
- **Tuning.** Operators can adjust the change-list window (`RESOURCESYNC_CHANGELIST_DAYS`, default 30) and the page size (`RESOURCESYNC_PAGE_SIZE`, default 1000); the page size also follows the OAI-PMH resumption-token limit when that is set, so one knob tunes both surfaces.
- **Where it fits.** ResourceSync (sync), OAI-PMH at `/api/oai` (metadata harvest), `/sitemap.xml` and `/sitemap-data.xml` (crawl discovery), and `/feed.atom` / `/feed.rss` (syndication) are complementary surfaces over the same published corpus. ResourceSync is also listed in the Open Memory Protocol index at `/open-data/protocol`.
