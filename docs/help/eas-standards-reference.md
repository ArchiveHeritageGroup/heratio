> Heratio Help Center article. Category: Reference / Standards.

# Encoded Archival Standards (EAS) — Reference Guide

## Overview

Heratio implements the full TS-EAS (Technical Subcommittee on Encoded Archival Standards) family of XML standards for interoperability with other archival management systems. This guide documents the current implementation status and how these standards relate to the RiC (Records in Contexts) data model.

## Current Implementation Status

| Standard | Version | Import | Export | Status |
|----------|---------|--------|--------|--------|
| EAD | 2002 | Yes | Yes | Full |
| EAD3 | 1.1.2 | Yes | Yes | Full |
| EAD 4 | Draft 2 (May 2025) | Yes | Yes | Full |
| EAC-CPF | 1.0 | Yes | Yes | Full |
| EAC-CPF | 2.0.1 (Aug 2022) | Yes | Yes | Full |
| EAC-F | Draft (2025) | No | Yes | Export only |
| EAG | 2012 / 3.0 draft | No | Yes | Export only |
| RiC-O | Current | Via sync | JSON-LD | Full |

## The TS-EAS Family

The TS-EAS standards are converging with RiC rather than competing:

### Traditional XML (Hierarchical)

- **EAD 4** — Describes archival records (fonds, series, files, items). Maps to `rico:Record` and `rico:RecordSet`.
- **EAC-CPF 2.0** — Describes agents (persons, corporate bodies, families). Maps to `rico:Agent`.
- **EAC-F** — Describes functions and activities (ISDF data). Maps to `rico:Activity` and the `ric_activity` table.
- **EAG 3.0** — Describes repositories and institutions. Maps to `rico:CorporateBody` with repository role.

### Graph-Based (Linked Data)

- **RiC-O** — The ICA Records in Contexts Ontology, expressed as JSON-LD and stored in a Fuseki triplestore.

Official crosswalks exist between the XML standards and RiC-O, meaning the same data can be expressed in both formats.

## EAD 4.0

EAD 4.0 is the latest revision of Encoded Archival Description, aligned with EAC-CPF 2.0 and designed to work alongside RiC-O. Key changes from EAD3:

- Shared `<control>` element pattern with EAC-CPF 2.0
- Better relationship modelling
- Alignment with RiC entity types
- Support for linked data identifiers

In Heratio, EAD 4 export maps ISAD(G) fields from the `information_object` and `information_object_i18n` tables directly. The hierarchy is serialized using recursive `<c>` elements following the nested set model.

### Export

Navigate to **Admin > Export > Metadata Export** and select the **EAD 4** format. You can export individual records or entire collections.

## EAC-CPF 2.0

EAC-CPF 2.0 was released in August 2022 and introduces significant improvements over version 1.0:

- Shared `<control>` element pattern aligned with EAD 4
- Improved relationship modelling with typed relations
- Better support for parallel names and multilingual descriptions
- Maintenance history tracking

In Heratio, EAC-CPF 2.0 serializes data from the `actor` and `actor_i18n` tables, including authorized forms of name, dates of existence, history, mandates, legal status, and functions.

### Namespace Change

EAC-CPF 2.0 uses the namespace `https://archivists.org/ns/eac/v2` (replacing the 1.0 namespace `urn:isbn:1-931666-33-4`).

## EAC-F (Functions)

EAC-F is a new standard for encoding ISDF (International Standard for Describing Functions) data. It directly maps to the `ric_activity` entities in Heratio.

Data sources:
- `function` / `function_i18n` tables (ISDF data)
- `ric_activity` table (RiC-O activity entities)
- `relation` table for function-to-record and function-to-agent relationships

## EAG 3.0

EAG (Encoded Archival Guide) describes archival institutions and repositories, aligned with ISDIAH. It serializes from:

- `repository` table with actor inheritance
- `actor_i18n` for institution names and descriptions
- `contact_information` for addresses, phones, emails, websites

## Mapping to RiC Entities

| EAS Standard | ISAD Standard | RiC-O Type | Heratio Table |
|-------------|---------------|-----------|---------------|
| EAD 4 | ISAD(G) | rico:Record / rico:RecordSet | information_object |
| EAC-CPF 2 | ISAAR(CPF) | rico:Agent | actor |
| EAC-F | ISDF | rico:Activity | function / ric_activity |
| EAG 3 | ISDIAH | rico:CorporateBody | repository |

## Further Reading

- [TS-EAS on GitHub](https://github.com/SAA-SDT/TS-EAS-subteam-notes)
- [EAD at Library of Congress](https://www.loc.gov/ead/)
- [EAC-CPF 2.0 Schema](https://eac.staatsbibliothek-berlin.de/schema/v2/)
- [ICA Records in Contexts](https://www.ica.org/standards/RiC/RiC-O_v0-2.html)
