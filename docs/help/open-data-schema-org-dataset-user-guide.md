> Heratio Help Center article. Category: Technical / Integration.

# schema.org Dataset descriptor (Google Dataset Search)

## Overview

The platform publishes a single **schema.org/Dataset** descriptor so that the general web search engines - Google Dataset Search and Bing in particular - can index your whole published collection AS A DATASET. With it, the collection can appear in dataset-search results, not only in the ordinary web index.

This is distinct from the DCAT catalogue (`/data/catalog`). The DCAT catalogue speaks to open-data portals (CKAN, the European Data Portal); the schema.org Dataset speaks the vocabulary the general web search engines crawl. Both describe the same offering; they target different audiences.

This descriptor is open data: no API key, read-only, published records only, and cross-origin (CORS) open.

---

## The endpoints

**GET /data/dataset.jsonld**

Always returns the schema.org/Dataset as JSON-LD (`application/ld+json`). This is the URL to give a search engine.

**GET /data/dataset**

Content-negotiated:

| Accept header | You get |
|---|---|
| `application/ld+json` (or a bare request) | the JSON-LD descriptor |
| `text/html` (a browser) | a 303 redirect to the **Open Data & APIs** landing page (`/open-data`) |

---

## What the descriptor contains

A single `schema.org/Dataset` node describing the published collection:

- **name**, **description**, **url** (the Open Data landing page) and a stable **identifier**.
- **license** - Creative Commons Attribution 4.0 (CC-BY-4.0).
- **creator** and **publisher** - your institution, taken from the platform name in settings.
- **keywords** - the subject area (archives, cultural heritage, GLAM, linked open data, finding aids).
- **temporalCoverage** - the date span of the collection (the earliest to the latest dated record), when dates are available.
- **spatialCoverage** - the most-referenced places in the collection.
- **includedInDataCatalog** - a link to the full DCAT catalogue (`/data/catalog`).
- **size** - the number of published records.
- **distribution** - one entry per downloadable form of the data (see below).

### Distributions (the downloads)

Each `distribution` is a `schema.org/DataDownload` with an `encodingFormat` and a `contentUrl`:

| Distribution | Format |
|---|---|
| Bulk catalogue dump (CSV) | `text/csv` |
| Bulk catalogue dump (JSON-LD) | `application/ld+json` |
| Combined CIDOC-CRM graph | `text/turtle` |
| Linked-data graph (front door) | `application/ld+json` |
| Per-record graph neighbourhood | `application/ld+json`, `text/turtle`, `application/rdf+xml` |
| OAI-PMH harvesting endpoint | `text/xml` |
| VoID / DCAT discovery | `text/turtle` |

The distribution list is built from the platform's canonical list of open-data surfaces, so it stays in step with everything else the platform offers - add a new open surface and it appears here automatically.

---

## How to use it

### Register the dataset with a search engine

Submit `https://YOUR-HOST/data/dataset.jsonld` (or the `/open-data` page that can embed the same markup) through Google Search Console. Google Dataset Search reads the schema.org/Dataset node and lists your collection in its dataset index.

### Validate the markup

Paste the JSON-LD into Google's Rich Results Test or the Schema Markup Validator to confirm the `Dataset` and its `DataDownload` distributions are recognised.

### Fetch it programmatically

```
curl -H "Accept: application/ld+json" https://YOUR-HOST/data/dataset.jsonld
```

---

## Notes

- **Read-only and resilient.** The descriptor only reads cheap aggregate figures (a record count, a date span, the top places). If a figure is unavailable it is simply omitted - the descriptor never fails.
- **Stable URLs.** Every address is built from the platform's own base URL, so the descriptor is correct on any host or behind any proxy.
- **Open licence.** Everything is published under CC-BY-4.0 - reuse it with attribution.

## Related

- **Open-Data Catalogue (DCAT)** - the data-portal view of the same offering (`/data/catalog`).
- **Open Memory Protocol** - the machine index of every open-data surface (`/open-data/protocol`).
- **Open graph statistics** - the size-and-shape figures the dataset size is drawn from (`/data/stats`).
- **Bulk open-data exports** - the CSV / JSON-LD dumps the distributions point at.
