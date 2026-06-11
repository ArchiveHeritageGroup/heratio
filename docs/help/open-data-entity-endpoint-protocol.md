> Heratio Help Center article. Category: Technical / Integration.

# Linked-Data Entity Endpoint and Open Memory Protocol

## Overview

Every published record has a single, stable, dereferenceable web address that returns a linked-data description of that record. Point a data client at the record's identity URL and ask for the format you want (JSON-LD, Turtle, or RDF/XML) with an HTTP `Accept` header; point a browser at the same URL and you are sent on to the normal human record page. A separate "protocol" document lists every open-data surface the platform offers, so a machine can discover the whole offering by fetching one URL.

These surfaces are open data: no API key, read-only, published records only, and cross-origin (CORS) open so a browser app on any site can fetch them.

---

## The entity endpoint

**GET /id/{slug}** (with the alias **GET /data/{slug}**)

`{slug}` is the record's slug - the same identifier used in its public page address. The response is content-negotiated by the `Accept` header:

| Accept header | You get |
|---|---|
| `application/ld+json` | JSON-LD (the default for machines) |
| `text/turtle` | Turtle |
| `application/rdf+xml` | RDF/XML |
| `text/html` (a browser) | a 303 redirect to the human record page |

You can also force a format with `?format=jsonld|turtle|rdf|html`.

The description includes the title, type (schema.org plus a Records-in-Contexts type), identifier, dates, creators, subjects, places, the holding repository, the parent record (`dcterms:isPartOf`), and `rdfs:seeAlso` links back to the record page, the graph-neighbourhood endpoint, and the dataset surfaces. The URL itself is the record's `@id`, so the record is its own stable name on the web.

An unknown or unpublished slug returns a clean 404 in the format you asked for - never an error page.

### Example

```bash
# JSON-LD (default)
curl -s "https://your-site.example/id/my-record-slug"

# Turtle
curl -s -H "Accept: text/turtle" "https://your-site.example/id/my-record-slug"

# RDF/XML
curl -s -H "Accept: application/rdf+xml" "https://your-site.example/id/my-record-slug"
```

---

## The protocol capabilities document

**GET /open-data/protocol** (machine view at **GET /open-data/protocol.json**)

This is the machine-discoverable index of every open-data surface: the VoID/DCAT discovery document, the linked-data graph (dataset front door and per-record), this new entity endpoint, the JSON-LD context, the crawl seed, the bulk CSV and JSON-LD dataset dumps, OAI-PMH harvesting, the sitemaps, the Atom/RSS feeds, and the OpenAPI specification and API docs. Each entry lists its URL (or URL template) and the media types it serves.

A browser visiting `/open-data/protocol` sees a readable HTML table; a data client (or `/open-data/protocol.json`) gets JSON. A surface only appears when its feature is installed, so the document never points at something that is not there.

### Example

```bash
curl -s "https://your-site.example/open-data/protocol.json"
```

---

## Notes for integrators

- All of these endpoints are read-only and require no authentication.
- They expose **published** records only; drafts are never disclosed.
- Responses set `Access-Control-Allow-Origin: *`, so browser-based apps can fetch them directly.
- URLs are always relative to the platform's own host, so a description fetched from one install resolves entirely within that install.
