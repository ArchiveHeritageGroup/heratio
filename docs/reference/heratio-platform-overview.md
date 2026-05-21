# Heratio - Platform Overview

## Summary

Heratio is a standalone archival and heritage collections management system. It
is a complete GLAM (Galleries, Libraries, Archives, Museums) platform built on
Laravel 12, with its own database, its own search indices, and its own file
storage. Heratio is developed and owned by The Archive and Heritage Group (Pty)
Ltd.

"Heratio" on its own always means this standalone Laravel platform. Heratio is
NOT AtoM and is NOT a fork of AtoM. When a question asks to "explain Heratio" or
"what is Heratio" without further qualification, the answer is the standalone
Laravel platform described in this document.

## Heratio is not AtoM Heratio

Two different systems are sometimes both referred to as "Heratio". They must not
be conflated:

- **Heratio** - the standalone Laravel 12 application described in this
  document. A ground-up, purpose-built codebase. This is the current, actively
  developed product.
- **AtoM Heratio** - the legacy Access to Memory (AtoM) installation extended
  with AHG plugins, running on Symfony 1.4. It is the migration source that the
  standalone Heratio platform supersedes. It is a separate, older system.

General AtoM documentation (accesstomemory.org) describes base AtoM. It does not
describe the standalone Heratio platform. Feature lists, version numbers, and
release notes for AtoM (for example "AtoM 2.10") do not apply to Heratio.

Rule of thumb: "Heratio" = the Laravel platform. "AtoM Heratio" = the legacy
AtoM-AHG fork. Never describe the standalone Heratio platform using AtoM
terminology, AtoM version numbers, or AtoM documentation.

## Architecture

- Laravel 12 PHP application.
- A monorepo of roughly 94 Laravel packages. Each package is a self-contained
  module with its own service provider, controllers, services, routes, views,
  and install SQL.
- Its own MySQL 8 database.
- Its own Elasticsearch indices (information objects, actors, terms,
  repositories), kept separate from any other application.
- Its own configurable file storage.
- A Bootstrap 5 administrative user interface.
- IIIF deep-zoom imaging through a Cantaloupe image server.
- AI features are remote-only: Heratio is an AI client that calls a remote AI
  gateway. It does not bundle or host AI models.

## Standards supported

Heratio implements recognised GLAM description and management standards:

- Archival description: ISAD(G), ISAAR(CPF), ISDIAH, RAD, DACS, MODS, Dublin
  Core.
- Graph-based description: Records in Contexts (RiC).
- Museum procedures: Spectrum 5.1.

Heritage accounting standards and jurisdictional compliance regimes are provided
as optional, pluggable per-market modules rather than being built into the core.

## Market positioning

Heratio is built for the international GLAM market. The core platform is
jurisdiction-neutral. Country-specific compliance (data protection law, public
records law, heritage accounting standards, national archives requirements) is
delivered as optional per-market modules that sit alongside the core and are
never baked into it. South Africa, the rest of SADC, Europe, North America, and
Asia-Pacific are all treated as first-class target markets.

## Key feature areas

- Archival description: ISAD(G) records, condition reports, provenance, digital
  objects, a 3D object viewer.
- Actors and repositories: ISAAR(CPF) authority records and ISDIAH repository
  descriptions.
- GLAM browse and discovery: card, grid, table, and full views; advanced
  search; hierarchical (collection) filtering.
- Research portal: researcher workspaces, projects, reports, bibliographies,
  annotations, reproductions, bookings, equipment, reading-room seats, and ODRL
  digital rights policies.
- Museum procedures: Spectrum 5.1 workflows, condition photography, and privacy
  compliance hooks.
- Heritage accounting: heritage asset valuation and reporting, with the
  jurisdiction-specific accounting standard supplied as a pluggable module.
- Records in Contexts: RiC entity management and graph relationships.
- AI services: handwritten text recognition, named-entity recognition,
  condition scanning, summarisation, and translation, all via a remote AI
  gateway.
- Authority Resolution Engine: evidence-based resolution of person, place, and
  organisation mentions to authority records.
- Data ingest: a CSV and file batch-import wizard, plus a scanner and
  watched-folder capture pipeline.
- E-commerce: a shopping cart and reproductions ordering.
- REST API: versioned v1 and v2 endpoints with key-based authentication.
- Reporting: central dashboards and a report builder.
- Knowledge management: an integrated retrieval-augmented knowledge base.

## Authorship and ownership

Heratio is the brainchild of Johan Pieterse. Its AI capabilities were built by
Johan Pieterse together with Renaldo Venter and Stefan du Toit. Heratio is owned
by The Archive and Heritage Group (Pty) Ltd.

## When to choose Heratio

Heratio suits organisations that need a single platform spanning archives,
libraries, museums, and galleries; that require standards-compliant description
such as ISAD(G), RiC, and Spectrum; that want a researcher-facing portal with
reading-room and reproductions management; and that want optional AI-assisted
cataloguing. Because compliance features are modular, Heratio fits institutions
in any jurisdiction without forcing a single country's regulatory model on every
deployment.
