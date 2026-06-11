> Heratio Help Center article. Category: Technical / Integration.

# Open-Data Catalogue (DCAT)

## Overview

The platform publishes a single machine-readable catalogue of everything it offers as open data, using DCAT (the W3C Data Catalog Vocabulary, aligned with DCAT-AP). A data portal, harvester, or AI agent can fetch this one document and learn every dataset on offer, where to download each one, and in which formats - with no prior knowledge of the platform.

This catalogue is open data: no API key, read-only, published records only, and cross-origin (CORS) open so a browser app on any site can fetch it.

---

## The catalogue endpoint

**GET /data/catalog**

The response is content-negotiated by the HTTP `Accept` header:

| Accept header | You get |
|---|---|
| `application/ld+json` | JSON-LD (the machine default) |
| `text/turtle` | Turtle |
| `application/rdf+xml` | RDF/XML |
| `text/html` (a browser) | a human-readable catalogue page |

You can also force a format with an explicit address:

- `GET /data/catalog.jsonld` - JSON-LD
- `GET /data/catalog.ttl` - Turtle
- `GET /data/catalog.rdf` - RDF/XML

Or with a query string: `?format=jsonld | turtle | rdf | html`. A plain command-line `curl` (which sends a catch-all `Accept`) receives JSON-LD.

---

## What the catalogue describes

- A **Catalog** (`dcat:Catalog`): the offering itself, with a title, description, licence (CC-BY-4.0), publisher, last-modified date, and a landing page.
- One **Dataset** (`dcat:Dataset`) per open-data surface: the bulk linked-data and CSV dumps, the per-record linked-data identity endpoints, the VoID dataset description, the OAI-PMH harvesting endpoint, the XML sitemaps, the syndication feeds, and the OpenAPI specification. Each dataset has a title, description, licence, publisher, and landing page.
- One or more **Distributions** (`dcat:Distribution`) per dataset: each concrete way to access the data, with the access URL (`dcat:accessURL`), a download URL where the data is a true file download (`dcat:downloadURL`), and the media type (`dcat:mediaType`). A dataset that serves several formats lists several distributions.

For surfaces that use a URL template (for example the per-record identity endpoint `/id/{slug}`), the distribution's access URL points at the protocol document and the template form is given in the distribution's description, so you still learn how to build a request.

---

## How it relates to the protocol document

The catalogue and the protocol "capabilities" document (`/open-data/protocol`) describe the SAME set of open surfaces - one in DCAT, the other in a bespoke shape. They are generated from a single shared list, so they can never disagree. Use the DCAT catalogue when your tooling already speaks DCAT (data portals, CKAN, the European Data Portal); use the protocol document when you want one compact, self-explaining index. The protocol document links to the catalogue as its DCAT entry point.

---

## Notes

- Every URL in the catalogue is built from the platform's own base address, so the document is correct on any deployment without configuration.
- The catalogue performs no database lookups and never fails over data: even an empty platform returns a valid, well-formed catalogue.
- The licence for the whole offering is Creative Commons Attribution 4.0 (CC-BY-4.0).
