> Heratio Help Center article. Category: Technical / Integration.

# Open Data: Linked-Data Crawl Sitemap User Guide

## Overview

The linked-data crawl sitemap helps search engines and Linked-Open-Data crawlers discover every entity in the collection's open-data graph. Where the public sitemap at **/sitemap.xml** lists the human record pages, this sitemap lists the stable, dereferenceable **/id/...** entity URIs - one per published record, per actor (person, corporate body, family), and per controlled-vocabulary term (place, subject, genre). A single index at **/sitemap-data.xml** links per-type sitemaps so a crawler can walk the whole graph. Start at **/sitemap-data.xml**.

---

## What it does

These endpoints publish the open-data graph's entity URIs as standards-based XML sitemaps for crawling:

- **/sitemap-data.xml** is a sitemap *index*: it links the per-type sitemaps below (and, when a type is large, each of its numbered pages). Fetch this one document to discover everything else.
- **/sitemap-data-records.xml** lists the **/id/{slug}** identity URI of every published record.
- **/sitemap-data-actors.xml** lists the **/id/actor/{slug}** URI of every actor (person, corporate body, family).
- **/sitemap-data-terms.xml** lists the **/id/term/{slug}** URI of every controlled-vocabulary term.

Each entry is a stable, dereferenceable entity URI - the same **/id/...** surface that serves JSON-LD, Turtle and RDF/XML by content negotiation - so a crawler that finds a URI here can fetch the entity's full linked-data description.

---

## How to use it

1. **Discover the graph:** fetch **/sitemap-data.xml** (for example `https://your-site.example/sitemap-data.xml`). It returns a sitemap index linking the per-type sitemaps.
2. **Enumerate a type:** follow the index entries, or fetch a per-type sitemap directly, such as **/sitemap-data-records.xml**, **/sitemap-data-actors.xml** or **/sitemap-data-terms.xml**.
3. **Page through large types:** when a type holds more than 50000 entities, the index lists numbered pages; request a page with `?page=N` (for example `https://your-site.example/sitemap-data-records.xml?page=2`).
4. **Dereference an entity:** take any **/id/...** URL from a sitemap and fetch it with an `Accept` header (`application/ld+json`, `text/turtle` or `application/rdf+xml`) to get the entity's linked-data description.
5. **Advertise it:** point your crawler configuration (or a robots `Sitemap:` line) at **/sitemap-data.xml** alongside the existing **/sitemap.xml**.

---

## Good to know

- Each per-type sitemap is capped at 50000 URLs (the sitemaps.org limit); larger types are split across numbered `?page=N` sitemaps, all listed in the index.
- Only **published** records are listed; drafts are never exposed. Actors and terms are reference (authority) entities and are listed as such.
- Every **/loc** URL is built from the request's own host, so the sitemap is correct on any deployment - no hardcoded address.
- These sitemaps are read-only, require no authentication, and are CORS-open, so any crawler may fetch them.
- An empty collection still returns a valid (empty) sitemap rather than an error.
- This crawl sitemap is the discovery counterpart to **/sitemap.xml** (human record pages) and **/api/v1/graph/sitemap.xml** (per-record graph neighbourhoods); together they cover pages, neighbourhoods and entity identities.
- The full open-data offering is indexed at **/open-data/protocol**, which now lists this sitemap among its surfaces.
- Examples here use `your-site.example` as a placeholder; substitute your own site's address.
