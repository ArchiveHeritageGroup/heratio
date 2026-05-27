> Heratio Help Center article. Category: User Guide.

# Heratio - BIBFRAME 2.0 Integration: User Manual

**Version:** 1.0.0
**Date:** May 2026
**Author:** The Archive and Heritage Group (Pty) Ltd
**Issue:** heratio#760

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [The BIBFRAME Model](#2-the-bibframe-model)
3. [Importing BIBFRAME](#3-importing-bibframe)
4. [Exporting BIBFRAME](#4-exporting-bibframe)
5. [Editing as an Agent](#5-editing-as-an-agent)
6. [Validation](#6-validation)
7. [Configuration](#7-configuration)
8. [Limitations](#8-limitations)
9. [Standards & References](#9-standards--references)

---

## 1. Introduction

BIBFRAME 2.0 is the Library of Congress's linked-data replacement for MARC21. The model expresses bibliographic information as a graph of three core entity types: **Work** (the intellectual content), **Instance** (a specific publication), and **Item** (a physical or digital copy). Agents (Person / Organization), Subjects, Events, Topics and Notes attach to the Work or Instance via typed predicates.

NLSA LMS Tender §2.12 and §7.1 require BIBFRAME for interoperability.

This release ships **bidirectional MARC21 ↔ BIBFRAME conversion** plus RDF/XML, Turtle and JSON-LD export. The dedicated BIBFRAME Editor UI is partial: a structured view (`/bibframe/agent`) is available; the full graph-aware editor is on the roadmap.

---

## 2. The BIBFRAME Model

Heratio maps the local `library_item` model to BIBFRAME as follows:

| Heratio column | BIBFRAME predicate | Target class |
|---|---|---|
| `library_item.id` | `bf:identifiedBy` | `bf:Local` |
| `library_item.title` | `bf:title` | `bf:Title` |
| `library_item_creator.actor_id` | `bf:contribution` | `bf:Contribution` |
| `library_item_identifier.value` (ISBN) | `bf:identifiedBy` | `bf:Isbn` |
| `library_item_subject.term_id` | `bf:subject` | `bf:Topic` |
| `library_item.publication_place` | `bf:provisionActivity` | `bf:Publication` |
| `library_item.physical_description` | `bf:extent` | `bf:Extent` |

The same item produces both a `bf:Work` triple (intellectual content) and a `bf:Instance` triple (this specific publication). The default mapping favours fidelity over compression, so round-trip MARC ↔ BIBFRAME preserves all controlled-vocabulary annotations.

---

## 3. Importing BIBFRAME

1. Visit **/bibframe/import**.
2. Upload an RDF/XML, Turtle or JSON-LD file.
3. Heratio parses the graph, normalises namespaces, and shows a preview:
   - Detected Works (and how many duplicate matches against your catalogue)
   - Detected Instances per Work
   - Unresolved agents (cataloguer must pick / create the matching authority)
4. **Commit** writes the records into `library_item` and the related sidecars.

Multi-file batch is supported via ZIP upload.

---

## 4. Exporting BIBFRAME

Visit **/bibframe/export**. Choose:

- A single record (`/bibframe/{workId}/export?format=ttl`)
- A search-result selection
- A whole bibliographic set (KBART feed, subject heading, location)

Supported serialisations:

| Format | MIME type | Notes |
|---|---|---|
| RDF/XML | `application/rdf+xml` | Canonical wire format |
| Turtle | `text/turtle` | Human-readable |
| JSON-LD | `application/ld+json` | Best for web APIs |

The exported file uses LoC-canonical namespaces (`bf:`, `bflc:`, `rdf:`, `rdfs:`, `xsd:`). Custom Heratio extensions are emitted under a `heratio:` namespace and clearly separated.

---

## 5. Editing as an Agent

The agent view (`/bibframe/agent`) shows the graph in a property-sheet form: each predicate is one row; nested entities (e.g. Contribution → Agent → Role) collapse and expand. Edits round-trip cleanly to BIBFRAME on save.

This is a stepping stone toward the full graph-aware editor (heratio#760, remaining acceptance criterion).

---

## 6. Validation

Visit **/bibframe/validate**. Upload a file or paste a record. The validator checks:

- RDF parse validity
- BIBFRAME 2.4 vocabulary conformance (`bf:`, `bflc:`)
- Required cardinalities (`bf:Work` must have at least one `bf:title`)
- Datatype well-formedness (`xsd:date`, ISBN check digits)

Errors are listed with line/triple references for fast remediation.

---

## 7. Configuration

`config/ahg-biblio-bf.php`:

| Key | Default | Purpose |
|---|---|---|
| `namespaces.heratio` | `https://heratio.example/bf/` | Extension namespace base IRI |
| `export.default_format` | `application/rdf+xml` | Negotiated default |
| `export.compact` | true | Pretty-print or compact serialisation |
| `validation.strict` | false | Treat warnings as errors |

---

## 8. Limitations

- **No full graph editor yet.** Agent-form editing covers the common case; complex predicates may need manual export, external editing in the LoC BIBFRAME Editor, and re-import.
- **No Open Discovery Initiative conformance statement.** The export is ODI-shaped but the conformance dossier is pending.
- **MARC ↔ BIBFRAME mapping** follows the LoC `marc2bibframe2` reference where possible but is not byte-exact. Round-trip preserves semantics, not lexical form.

---

## 9. Standards & References

- BIBFRAME 2.0 vocabulary: https://www.loc.gov/bibframe/docs/index.html
- LoC marc2bibframe2 reference XSLT: https://github.com/lcnetdev/marc2bibframe2
- Open Discovery Initiative (NISO RP-19-2020): https://groups.niso.org/higherlogic/ws/public/projects/95/details
- NLSA LMS Tender §2.12, §7.1

---

For technical operators, see `docs/reference/bibframe-implementation.md`.
