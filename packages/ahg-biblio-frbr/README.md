# AHG BIBLIO-FRBR

FRBR (Functional Requirements for Bibliographic Records) implementation for Heratio.

Converts Heratio bibliographic catalogue records to/from the IFLA FRBR conceptual model
(Work, Expression, Item, Manifestation) via the OpenRiC RiC-O service layer.

## Overview

FRBR is the conceptual model from IFLA that structures bibliographic records as:

- **Work** — a distinct intellectual or artistic creation
- **Expression** — a specific realisation of a Work (text, translation, edition)
- **Manifestation** — the physical/digital form of an Expression (format, carrier)
- **Item** — a concrete copy of a Manifestation

Heratio mapping:

FRBR entities are projected from the live library catalogue via
`AhgBiblioBf\Services\BiblioWorkRepository` (there is no separate FRBR store):

| FRBR entity       | Heratio source                              |
|-------------------|---------------------------------------------|
| Work              | `library_item` clustered by `work_key`      |
| Expression        | `library_item` (instance rows in a cluster) |
| Manifestation     | `library_item` (instance rows in a cluster) |
| Item              | `library_copy`                              |
| Person/Corporate Body | `library_item_creator`                  |

## Routes

```
GET  /frbr                        — dashboard
GET  /frbr/{workId}               — single Work as FRBR entity
GET  /frbr/export                 — export UI
POST /frbr/export                 — run export
GET  /frbr/import                 — import UI
POST /frbr/import                 — run import
GET  /frbr/validate               — validate UI
POST /frbr/validate               — run validation
GET  /frbr/agent                  — agent management
```

## Dependencies

- Heratio library catalogue (`library_item` / `library_copy` / `library_item_creator`) via `AhgBiblioBf\Services\BiblioWorkRepository`
- OpenRiC at `services.openric.url` (optional; degrades gracefully)

## License

AGPL-3.0 — The Archive Heritage Group (Pty) Ltd
