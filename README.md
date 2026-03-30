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

### 1. Clone the repository

```bash
git clone https://github.com/ArchiveHeritageGroup/heratio.git
cd heratio
```

### 2. Install dependencies

```bash
composer install
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` to point to your archival MySQL database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_archival_db
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

### 4. Run migrations

```bash
php artisan migrate
```

### 5. Configure your web server

Point a subdomain (e.g. `heratio.yourdomain.com`) to Heratio's `public/` directory.

### 6. Set up encryption key (optional)

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
- [ ] RiC dual-view on all major entity pages
- [ ] Relation-aware editing widgets
- [ ] SHACL and semantic validation in admin workflows
- [ ] PostgreSQL Heratio-native domains (workflow, audit, enrichment)
- [ ] Multi-language NER support (including isiZulu, Sesotho, Afrikaans)
- [ ] Bulk shared drive ingestion agent with automated classification

---

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting pull requests.

---

## License

Heratio is released under the [GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE).

---

## Citation

If you use Heratio in your research, please cite:

```bibtex
@article{pieterse2026heratio,
  author  = {Pieterse, Johannes Jurie and Jacobs, Lorette},
  title   = {AI-Driven Digital Transformation of Unstructured Records in a
             South African State-Owned Company: A Socio-Technical Framework
             and Proof-of-Concept Implementation},
  journal = {Information Systems Frontiers},
  year    = {2026},
  note    = {Submitted}
}
```

---

## Contact

**The Archive and Heritage Group (Pty) Ltd**
Johan Pieterse — johan@theahg.co.za

---

*Heratio in front. RiC beside it. OpenRiC above and beneath it.*
