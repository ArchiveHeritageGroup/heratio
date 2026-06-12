> Heratio Help Center article. Category: Technical / Integration.

# IIIF Presentation 3.0 manifest per record

## Overview

Every published record has a IIIF Presentation API 3.0 **manifest**. A manifest is a small JSON document that any IIIF-compatible image viewer (Mirador, Universal Viewer) can open to display the record's images with deep zoom, and that IIIF aggregators and harvesters can ingest to surface your holdings alongside collections worldwide.

This is open data: no API key, read-only, published records only, and CORS-open, so a viewer hosted on any site can load it.

---

## The manifest URL

**GET /iiif-presentation/{idOrSlug}/manifest.json**

`{idOrSlug}` is the record's slug (the same identifier used in its public page address) or its numeric record id.

Example:

```
https://<your-heratio>/iiif-presentation/a-photograph-collection/manifest.json
```

Paste that URL into Mirador's "Add resource" box or append it to a Universal Viewer embed (`?manifest=...`) and the record's images open with pan and zoom.

The response is `application/ld+json` (IIIF Presentation 3.0).

---

## What is in the manifest

| Manifest field | Source |
|---|---|
| `label` | The record title (language-mapped) |
| `summary` | Scope and content, when present |
| `metadata` | A few safe descriptive fields: reference code, dates, level of description, repository |
| `requiredStatement` | Attribution to the holding repository |
| `rights` | The reproduction-conditions URI, when it is a recognised rights URI (Creative Commons / RightsStatements.org) |
| `homepage` | A deep link back to the human record page |
| `provider` | The publishing institution |
| `items` | One **Canvas** per image, each pointing at a IIIF Image API 3.0 service for deep zoom, plus a thumbnail |

Each Canvas nests an AnnotationPage, a painting Annotation, and an Image body whose `service` block references the deployment's IIIF Image API (the same image service the record's own viewer uses).

An honest mapping: a field that is absent is simply omitted, never invented.

---

## Records with no images

A published record that has no image files still returns a **valid** manifest, with an empty `items` list. Harvesters always receive a well-formed document; the endpoint never errors over a record that happens to have no pictures.

---

## Behaviour and edge cases

- An **unknown**, **unpublished**, or the synthetic **root** record returns a clean `404` JSON, never an error page and never a draft leak.
- Only **published** records are exposed (the same publication gate as the rest of the public API).
- The URLs inside the manifest (its own id, the homepage, and the image service) follow the host the request arrived on, so a fresh install on its own domain emits its own URLs with nothing hardcoded.

---

## Related surfaces

- **Cite this record** - `/cite/{idOrSlug}` for bibliographic citation formats.
- **Open data protocol** - `/open-data/protocol` lists every open-data surface, including this manifest, so an agent can discover them all from one document.
