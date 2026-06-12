> Heratio Help Center article. Category: Technical / Integration.

# Controlled Vocabularies as SKOS Concept Schemes

## Overview

Heratio publishes its controlled vocabularies (the authorities that records are described under) as standard SKOS concept schemes. SKOS (Simple Knowledge Organization System) is the W3C standard for sharing thesauri, taxonomies and subject-heading lists as linked data, so any SKOS-aware tool can consume your authorities directly.

Three schemes are published out of the box: **subjects**, **places** and **genres / forms**. Each scheme, and each individual concept within it, has a stable web address that returns linked data (JSON-LD, Turtle or RDF/XML).

These surfaces are open data: no API key, read-only, published records only, and cross-origin (CORS) open so a browser app on any site can fetch them.

---

## The index

**GET /vocabularies**

A human page listing the published concept schemes with their live term counts and links to each scheme and serialisation. Add `?format=json` (or send a JSON `Accept` header) to get a machine list of the schemes instead.

---

## One vocabulary as a concept scheme

**GET /vocabulary/{scheme}**

`{scheme}` is one of `subjects`, `places` or `genres`. The response is content-negotiated:

| Request | You get |
|---|---|
| bare path or `Accept: application/ld+json` | JSON-LD (the default for machines) |
| `.ttl` suffix or `Accept: text/turtle` | Turtle |
| `.rdf` suffix or `Accept: application/rdf+xml` | RDF/XML |

You can also force a format with `?format=jsonld|turtle|rdf`.

The scheme is a `skos:ConceptScheme`. Every term becomes a `skos:Concept` with:

- `skos:prefLabel` - the preferred label, language-tagged for your active locale
- `skos:notation` - the term's notation (its in-house code, or its stable id)
- `skos:inScheme` - a link to the scheme it belongs to
- `skos:topConceptOf` or `skos:broader` - its place in the hierarchy
- `skos:narrower` - its child concepts, where the vocabulary nests
- `skos:scopeNote` - a usage note, where the term has one

### Example

```bash
# The subjects vocabulary as Turtle
curl -H "Accept: text/turtle" https://your-site/vocabulary/subjects
# or by suffix
curl https://your-site/vocabulary/subjects.ttl

# The places vocabulary as JSON-LD
curl https://your-site/vocabulary/places.jsonld
```

---

## One concept

**GET /vocabulary/{scheme}/{termId}**

Returns a single concept nested under its scheme, with its labels, notation, hierarchy and scope note, plus a bounded handful of example published records that use the concept (as `dcterms:relation` links to those records' linked-data URIs). The same content negotiation and `.ttl` / `.jsonld` / `.rdf` suffixes apply.

The canonical per-term address remains **/id/term/{slug}**; each concept here links to it with `skos:exactMatch`, so the two views of the same concept reference each other.

```bash
curl https://your-site/vocabulary/places/901113.jsonld
```

---

## Notes

- A large vocabulary is paginated / capped; when a scheme has more concepts than one document lists, the scheme carries an honest note pointing you at the per-concept addresses.
- An unknown scheme name or concept id returns a clean 404 in the format you asked for, never an error page.
- The vocabularies are also listed in the open-data protocol index at **/open-data/protocol**, so a machine can discover them by fetching one URL.
- The vocabulary content is entirely data-driven - labels, notations, hierarchy and notes all come from your own term records, in whatever languages you have captured.
