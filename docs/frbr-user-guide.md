> Heratio Help Center article. Category: User Guide.

# Heratio - FRBR Work-Set Clustering: User Manual

**Version:** 1.0.0
**Date:** May 2026
**Author:** The Archive and Heritage Group (Pty) Ltd
**Issue:** heratio#763

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [The FRBR Model](#2-the-frbr-model)
3. [Importing FRBR](#3-importing-frbr)
4. [Exporting FRBR](#4-exporting-frbr)
5. [Editing as an Agent](#5-editing-as-an-agent)
6. [Validation](#6-validation)
7. [Configuration](#7-configuration)
8. [Limitations](#8-limitations)
9. [Standards & References](#9-standards--references)

---

## 1. Introduction

FRBR (Functional Requirements for Bibliographic Records) is the IFLA conceptual model that groups bibliographic records into four levels of abstraction: **Work** (the intellectual or artistic creation), **Expression** (a particular realisation of the Work), **Manifestation** (the physical embodiment of the Expression), and **Item** (a single exemplar of the Manifestation).

NLSA LMS Tender §2.2 lists FRBR clustering and deduplication as a unified-search requirement: a single search for "Cry, the Beloved Country" should return one hit with a "View all 47 editions" affordance, not 47 separate result rows.

This release ships the **FRBR serialisation surface** (import, export, validation, agent-form editing). Search-result clustering, the work-key generator, the manual force-group / force-split override, and the 132k-record performance benchmark remain on the roadmap.

---

## 2. The FRBR Model

Heratio maps `library_item` to FRBR as follows:

| Heratio column | FRBR level | Predicate |
|---|---|---|
| Normalised uniform-title + first-creator | Work | `frbr:Work` |
| Language + form (text, audio, score) | Expression | `frbr:Expression` |
| Edition + publisher + date | Manifestation | `frbr:Manifestation` |
| `library_item` row (one barcode/accession) | Item | `frbr:Item` |

The work-key recipe (in progress):

1. NFD-normalise the uniform-title (245 + 240/130) to lowercase, strip diacritics and punctuation.
2. Normalise the first creator (100/700, no $e role) the same way.
3. Concatenate. The resulting string hashes to the `library_work_key`.
4. Manual overrides take precedence: `library_work_override` table can force two items together (`force_group`) or apart (`force_split`).

---

## 3. Importing FRBR

Visit **/frbr/import**. Upload an RDF/XML, Turtle or JSON-LD file expressing FRBR triples.

The importer:

1. Parses the graph.
2. Walks Work → Expression → Manifestation → Item chains.
3. Matches existing Heratio `library_item` rows where possible, by ISBN/ISSN, normalised title, or explicit `frbr:exemplarOf` triples.
4. Creates new rows for unmatched levels.
5. Records the FRBR provenance source for downstream traceability.

---

## 4. Exporting FRBR

Visit **/frbr/export**. Choose a record (or a search-result set) and a format:

| Format | MIME type |
|---|---|
| RDF/XML | `application/rdf+xml` |
| Turtle | `text/turtle` |
| JSON-LD | `application/ld+json` |

Each export emits one Work node and the cascade of Expressions / Manifestations / Items beneath it.

---

## 5. Editing as an Agent

The agent view (`/frbr/agent`) shows the Work-Expression-Manifestation-Item stack as a collapsible tree. Editing the Work updates the inherited Expression metadata; explicit Expression overrides shadow the Work.

---

## 6. Validation

The validator (**/frbr/validate**) checks:

- RDF parse validity
- IFLA FRBR vocabulary conformance
- Required cardinalities (every Manifestation must `frbr:embodimentOf` an Expression; every Expression must `frbr:realisationOf` a Work)
- Cycle detection (Work cannot be a Manifestation of itself)

---

## 7. Configuration

`config/ahg-biblio-frbr.php`:

| Key | Default | Purpose |
|---|---|---|
| `namespaces.frbr` | `http://iflastandards.info/ns/fr/frbr/frbrer/` | FRBR-ER namespace |
| `work_key.algorithm` | `nfd-lower-strip` | Title-creator normaliser variant |
| `work_key.hash` | `xxh64` | Fast non-cryptographic hash |
| `clustering.max_per_work` | 200 | Result-list cap per cluster |

---

## 8. Limitations

- **Work-key column live on `library_item` (v1.112+).** Backfilled via `php artisan ahg:frbr-backfill-work-keys`. Re-run after large imports or normalisation rule changes.
- **Force-group / force-split admin live (v1.112+).** Cataloguer UI at `/admin/frbr/overrides` (`library_work_override` table). Overrides take precedence over the algorithmic key and trigger an immediate work-key recompute on the affected item.
- **Clustering helper service live (v1.112+).** `WorkKeyService::clusterItems()` accepts a result-set of `library_item` IDs and returns clusters grouped by work-key. Search-result UI still renders each row independently; wiring the cluster helper into the GLAM browse hit list is the remaining integration step.
- **No performance benchmark published yet.** Target is 132k records cluster in <500ms. Initial local timing on the 14-row test set was sub-millisecond; full-scale benchmark on a populated catalogue is the remaining acceptance item.

---

## 9. Standards & References

- IFLA FRBR final report (1998): https://www.ifla.org/publications/functional-requirements-for-bibliographic-records
- IFLA FRBR namespace: http://iflastandards.info/ns/fr/frbr/frbrer/
- IFLA Library Reference Model (LRM, the FRBR successor): https://www.ifla.org/publications/ifla-library-reference-model

---

For technical operators, see `docs/reference/frbr-implementation.md`.
