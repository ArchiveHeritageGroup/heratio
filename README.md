# Heratio

**The operational GLAM, archival, DAM, and records management platform with RiC as a first-class capability.**

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![PHP](https://img.shields.io/badge/PHP-8.3-purple)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-red)](https://laravel.com)

---

## Overview

Heratio is a pure Laravel 12 application designed and developed by **[The Archive and Heritage Group (Pty) Ltd](https://www.theahg.co.za)**. It is the operational platform for AI-assisted archival records management, digital asset management, and GLAM workflows.

Heratio connects directly to an archival MySQL database using Laravel's Eloquent ORM and operates as an independent application stack. It addresses a critical challenge in public sector archives: transforming large volumes of unmanaged, unstructured content into governed, accessible, and legislatively-compliant archival holdings.

**RiC (Records in Contexts)** is a first-class capability within Heratio — not a hidden add-on. Every major entity page supports both a traditional archival view and a RiC contextual view over the same data, permissions, and identifiers.

The RiC-native semantic framework, documentation, and ecosystem is centralised under **[OpenRiC](https://openric.org)**.

A live demonstration instance is available at **[https://heratio.theahg.co.za](https://heratio.theahg.co.za)**.

---

## Key Features

### Operational Platform
- Full archival description, hierarchy, workflows, editing, and administration
- Plugin architecture with modular packages for every functional domain
- Role-based access controls and institutional deployment support
- Import/export flows and digital object management

### AI-Assisted Metadata Enrichment
- NLP-based Named Entity Recognition (NER) pipeline extracts persons, organisations, dates, locations, and subjects
- Extracted entities mapped to archival description fields and authority records
- Human-in-the-loop review — AI suggestions presented for archivist approval before committing
- Model-agnostic architecture supports locally hosted models via Ollama, ensuring data sovereignty under POPIA

### RiC — First-Class Contextual Mode
- Dual-view on every major entity page: traditional archival view and RiC contextual view
- RiC Explorer with interactive 2D/3D graph visualization (Cytoscape.js, Three.js)
- Automatic sync to Apache Jena Fuseki triplestore
- SPARQL endpoint queryable by external systems
- JSON-LD / RDF outputs and multi-standard export (EAD3, EAC-CPF, Turtle, RDF/XML)
- Semantic search across the archival graph (Qdrant vector search + Elasticsearch)
- EAD/ISAD to RiC-O mapping
- Integrity checking, orphan management, and provenance tracking

### Researcher Self-Description Portal
- Dedicated portal for field researchers, subject matter experts, and contributing institutions
- Simplified ISAD(G)-mapped description forms accessible to non-archivists
- Five-stage workflow: Draft, Submitted, Under Review, Published, Returned for Revision
- Archivist review gate maintains description quality before public accessibility

### Privacy-Preserving Digital Object Management
- Automated POPIA/GDPR sensitivity screening
- AES-256-GCM digital object encryption for restricted holdings
- On-the-fly decryption for authorised access with streaming delivery
- Full audit logging of access and encryption events

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│  Heratio (Laravel 12 / PHP 8.3) — Application Layer     │
├──────────────┬──────────────┬────────────┬──────────────┤
│  MySQL       │  Fuseki      │ Elastic    │  Qdrant      │
│  Archival DB │  RiC-O graph │ Full-text  │  Semantic    │
│  (AtoM-      │  17.9M+      │ search     │  search      │
│  compatible) │  triples     │            │              │
├──────────────┴──────────────┴────────────┴──────────────┤
│  RiC Explorer — Graph visualization (Flask/Cytoscape.js) │
│  Bootstrap 5 — WCAG 2.1 Level AA                        │
└─────────────────────────────────────────────────────────┘
```

Heratio operates alongside the existing AtoM MySQL database. Both application stacks coexist with no code dependency — metadata enriched by Heratio is immediately available through both platforms.

---

## Package Architecture

Heratio uses a monorepo plugin architecture:

```
packages/
├── ahg-core/                  — Models, base services, pagination, components
├── ahg-theme-b5/              — Layouts, nav, footer, static assets
├── ahg-information-object-manage/ — IO browse/show (ISAD)
├── ahg-actor-manage/          — Actor browse/show
├── ahg-repository-manage/     — Repository browse/show (ISDIAH)
├── ahg-accession-manage/      — Accession browse/show
├── ahg-donor-manage/          — Donor browse/show
├── ahg-rights-holder-manage/  — Rights holder browse/show
├── ahg-storage-manage/        — Physical object browse/show
├── ahg-term-taxonomy/         — Term + taxonomy browse/show
├── ahg-function-manage/       — Function browse/show (ISDF)
├── ahg-user-manage/           — User browse/show (admin)
├── ahg-settings/              — Settings dashboard (admin)
├── ahg-jobs-manage/           — Jobs browse/show (admin)
├── ahg-iiif-collection/       — IIIF Collection management
└── ...                        — Additional domain packages
```

---

## Heratio and OpenRiC

**Heratio** is the operational platform institutions use.

**[OpenRiC](https://openric.org)** is the public RiC-native initiative that centralises RiC implementation guidance, semantic architecture, graph capabilities, documentation, mappings, and ecosystem collaboration.

OpenRiC powers the RiC capabilities within Heratio and is available as a standalone framework for the broader archival community.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.3 or higher |
| Laravel | 12 |
| MySQL / MariaDB | 8.0+ / 10.3+ |
| Nginx | 1.18+ |
| Apache Jena Fuseki | 4.10+ (for RiC) |
| Ollama (optional) | Latest stable |

---

## Installation

Heratio supports two install scenarios.

| Scenario | When to use | Entry point |
| --- | --- | --- |
| **1. Overlay** onto an existing AtoM database | You already run AtoM and want to add Heratio without losing your catalogue, or you're cutting a customer over from AtoM | `./bin/install-overlay` |
| **2. Standalone** clean install | No AtoM. New deployment from scratch | `./bin/install` *(in development — see [`docs/standalone-install-plan.md`](docs/standalone-install-plan.md))* |

Both paths share the same Laravel-side bootstrap (composer install, `.env`, `key:generate`, ServiceProvider auto-seed). The two scenarios differ only in how the database is brought to life.

### Scenario 1 — Overlay onto existing AtoM

Heratio sits **on top of** your existing AtoM database without destroying any data. The overlay adds Heratio-only tables, syncs missing columns on shared tables, and seeds Heratio's settings + help — using `INSERT IGNORE` everywhere so customer customisations are preserved. Re-runnable / idempotent.

```bash
git clone https://github.com/ArchiveHeritageGroup/heratio.git /path/to/heratio
cd /path/to/heratio
composer install
cp .env.example .env
php artisan key:generate

# Point .env at your existing AtoM DB
# DB_DATABASE=your_atom_db
# DB_USERNAME=root
# DB_PASSWORD=...

# Dry-run first to see what would change (read-only)
./bin/install-overlay --target=your_atom_db --dry-run

# Apply
./bin/install-overlay --target=your_atom_db
```

The overlay runs eight idempotent stages: pre-flight → schema overlay → column-delta sync → settings replicate → help replicate → ServiceProvider boot (auto-seed dropdowns) → Elasticsearch reindex → smoke test.

Full guide: [`docs/overlay-install-howto.md`](docs/overlay-install-howto.md)

### Scenario 2 — Standalone clean install

For a fresh deployment with no AtoM. Status: planning document committed; entry point `bin/install` under development. The plan ports AtoM's core schema + 81 AHG plugin install.sqls + 6 YAML fixtures into Heratio's own `database/core/`, `packages/*/database/`, and `database/seeds/` so a fresh box becomes a working Heratio in one command.

See [`docs/standalone-install-plan.md`](docs/standalone-install-plan.md) for the full work plan.

### Web server

Point a subdomain (e.g. `heratio.yourdomain.com`) to Heratio's `public/` directory. Nginx vhost templates ship under `config/nginx/` for reference.

### Encryption key (optional, for restricted-holdings encryption)

```bash
php artisan heratio:generate-encryption-key
```

---

## Compliance

Heratio is designed to support compliance with:

- **POPIA** — Protection of Personal Information Act 4 of 2013 (South Africa)
- **PAIA** — Promotion of Access to Information Act 2 of 2000 (South Africa)
- **NARSSA** — National Archives and Records Service of South Africa Act 43 of 1996
- **GDPR** — General Data Protection Regulation (EU)
- **ISO 15489** — Records management principles
- **ISO 23081** — Metadata for records

Institutions are responsible for configuring the system appropriately for their legislative context.

---

## Roadmap

- [x] Full NER pipeline integration with human-in-the-loop review
- [x] RiC Explorer with 2D/3D graph visualization
- [x] Fuseki triplestore sync and SPARQL endpoint
- [x] IIIF manifest generation for digitised collections
- [x] RiC dual-view on all major entity pages
- [x] Relation-aware editing widgets
- [x] SHACL and semantic validation in admin workflows
- [ ] PostgreSQL Heratio-native domains (workflow, audit, enrichment)
- [ ] Multi-language NER support (including isiZulu, Sesotho, Afrikaans)
- [ ] Bulk shared drive ingestion agent with automated classification
- [ ] Document Management (DM) — versioned digital-document workflows, check-in/check-out, redaction, watermarking, derivative chains
- [ ] Records Management (RM) — file plans, retention schedules, disposal workflows (recommend / approve / execute / reject / legal hold), audit-grade event log; full ISO 15489 / ISO 16175 alignment

---

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting pull requests.

---

## License

Heratio is released under the [GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE).

---

## Authors and Credits

Heratio is the brainchild of **Johan Pieterse**, conceived and architected as the operational platform for AI-assisted archival, records, and digital asset management. The AI subsystems and platform were built by:

- **Johan Pieterse** — concept, architecture, platform lead
- **Renaldo Venter** — AI engineering
- **Stefan du Toit** — AI engineering

### Acknowledgement — AtoM (Access to Memory) and the Qubit framework

Heratio runs against the **Qubit schema** originally created for **AtoM (Access to Memory)** by **[Artefactual Systems Inc.](https://www.artefactual.com)** (Copyright © 2006–2014, Artefactual Systems Inc., licensed under the GNU Affero General Public License v3.0 — see [https://www.accesstomemory.org](https://www.accesstomemory.org)).

AtoM remains a foundational contribution to the open-source archival software ecosystem. Heratio's data model, descriptive standards (ISAD(G), ISAAR(CPF), ISDIAH, ISDF), class-table inheritance, and many cataloguing patterns derive directly from AtoM's design. Heratio is a Laravel-based platform built on top of that schema; it is not a fork of AtoM and contains no AtoM source code, but its existence depends on Artefactual's two decades of standards work, and that contribution is gratefully acknowledged.

The Heratio team thanks Artefactual Systems Inc., the AtoM contributor community, and the International Council on Archives (ICA) for the standards and tooling on which this work stands.

## Citation

If you use Heratio in your research, please cite:

```bibtex
@article{pieterse2026heratio,
  author  = {Pieterse, Johannes Jurie},
  title   = {AI-Driven Digital Transformation of Unstructured Records:
             A Socio-Technical Framework and Proof-of-Concept Implementation},
  journal = {Information Systems Frontiers},
  year    = {2026},
  note    = {Submitted}
}

@article{pieterse2026samab,
  author  = {Pieterse, Johannes Jurie},
  title   = {Artificial Intelligence as Collections Steward:
             Enabling Inclusive, Sustainable Museum Collections Management
             in the African Context},
  journal = {South African Museums Association Bulletin (SAMAB)},
  volume  = {48},
  year    = {2026},
  note    = {Abstract accepted; full paper due 30 May 2026;
             editorial decision by 26 June 2026}
}
```

---

## Contact

**The Archive and Heritage Group (Pty) Ltd**
Johan Pieterse — johan@theahg.co.za

---

*Heratio in front. RiC beside it. OpenRiC above and beneath it.*
