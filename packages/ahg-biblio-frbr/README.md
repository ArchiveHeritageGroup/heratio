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

| FRBR entity       | Heratio table                  |
|-------------------|-------------------------------|
| Work              | `library_biblio_work`         |
| Expression        | `library_biblio_instance`     |
| Manifestation     | `library_biblio_instance`    |
| Item              | `library_biblio_item`         |
| Person/Corporate Body | `library_biblio_agent`    |

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

- Heratio `library_biblio_*` tables
- OpenRiC at `services.openric.url` (optional; degrades gracefully)

## License

AGPL-3.0 — The Archive Heritage Group (Pty) Ltd
