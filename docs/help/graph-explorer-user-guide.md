> Heratio Help Center article. Category: Open Data.

# Graph Explorer

**Version:** 1.0
**Date:** 2026-06-11
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## 1. Overview

The Graph Explorer is a public, no-login way to navigate your collection
as a connected graph. Instead of reading one record in isolation, a
visitor can follow the links between records, the people and
organisations they involve, the places they describe, and the subjects
they are about - one hop at a time.

It is the human-friendly companion to Heratio's linked-data identity
endpoints (`/id/...`). Those endpoints publish every record, agent and
concept as machine-readable linked data; the Graph Explorer lets a
person walk exactly the same graph in a browser.

Everything in the explorer is public, read-only, and covers published
records only. No API key is required.

## 2. Starting out

Open `/graph-explorer`. The landing page offers two ways in:

- **Search** - type a few letters of a record title, a person or
  organisation name, or a subject / place / genre. Matching entities are
  listed with a small badge showing whether each is a record, an agent,
  or a concept.
- **Starting points** - a short list of the most richly-connected
  records, so you always have somewhere to begin even if you do not know
  what to search for.

Select any result to open its entity page.

## 3. Walking the graph

Each entity page shows:

- The entity's **label** and a few **key facts** (for a record: dates,
  level of description, reference code; for an agent: dates of existence
  and type; for a concept: its kind).
- Its **connections**, grouped into plain-language categories:
  - For a **record**: People and organisations, Subjects, Places,
    Repository, and Related records (the parent it is part of and the
    children it contains).
  - For an **agent** (person, family, or corporate body): the Records it
    is linked to.
  - For a **concept** (subject, place, or genre / form): its Broader
    term, its Narrower terms, and the Records tagged with it.

Every connection that resolves to another published entity is a link.
Select it to move to that entity's page and keep exploring. A connection
that has no public page of its own is shown as plain text, so you always
see the full picture without ever hitting a broken link.

If an entity has no recorded connections yet, the page says so calmly -
as the collection grows and is linked, related entities will appear.

## 4. Linking out

Every entity page also links outward, so the explorer is a hub rather
than a silo:

- **View the full record / authority record / browse with this term** -
  takes you to the canonical human page for the entity (the record page,
  the authority record, or a filtered browse for a concept).
- **Linked data (`/id/...`)** - the machine-readable description of the
  same entity, available as JSON-LD, Turtle, or RDF/XML through content
  negotiation. This is what aggregators, researchers and linked-data
  clients consume.

## 5. Notes

- The explorer never exposes unpublished or draft records. An address
  that does not match a published entity returns a friendly "not found"
  page.
- All links are built relative to your own site address, so the explorer
  works correctly on any domain without configuration.
- The Graph Explorer is part of Heratio's Open Memory Protocol. See also
  the **Open data and APIs** hub (`/open-data`) for the full set of
  machine surfaces (linked-data graph, bulk dataset dumps, OAI-PMH, the
  VoID discovery document, and more).
