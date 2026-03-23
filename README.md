# Heratio

**An open-source Laravel framework extending AtoM (Access to Memory) with AI-assisted records management capabilities.**

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![AtoM Compatible](https://img.shields.io/badge/AtoM-2.9%2B-green)](https://www.accesstomemory.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10%2B-red)](https://laravel.com)

---

## Overview

Heratio is developed by [The Archives and Heritage Group (The AHG)](https://www.theahg.co.za) as a research and production framework that addresses a critical challenge in public sector archives: the vast majority of organisational records reside outside formal records management control, making them inaccessible for compliance, audit, and institutional memory purposes.

By extending AtoM — the widely-deployed open-source archival description platform — Heratio introduces AI-assisted metadata enrichment, a researcher self-description portal, and privacy-preserving digital object management. Together these capabilities enable institutions to transform large volumes of previously unmanaged, unstructured content into governed, accessible, and legislatively-compliant archival holdings.

A live demonstration instance is available at **[https://heratio.theahg.co.za](https://heratio.theahg.co.za)**.

This work is associated with doctoral research at the University of South Africa (UNISA):
> Pieterse, J.J. & Jacobs, L. — *AI-Driven Digital Transformation of Unstructured Records in a South African State-Owned Company: A Socio-Technical Framework and Proof-of-Concept Implementation* (forthcoming, Information Systems Frontiers).

---

## Key Features

### 🤖 AI-Assisted Metadata Enrichment
- NLP-based Named Entity Recognition (NER) pipeline extracts persons, organisations, dates, geographic locations, and subjects from unstructured document text
- Extracted entities are mapped to AtoM description fields and authority record structures
- Human-in-the-loop review interface — AI suggestions are presented for archivist approval before being committed to the authoritative description
- Model-agnostic architecture supports locally hosted models via Ollama-compatible interfaces, ensuring data sovereignty compliance under POPIA

### 👤 Researcher Self-Description Portal
- Dedicated portal for field researchers, subject matter experts, and contributing institutions to upload digital objects and describe their collections
- Simplified ISAD(G)-mapped description forms accessible to non-archivists
- Five-stage workflow: **Draft → Submitted → Under Review → Published → Returned for Revision**
- Role-based access controls ensure researchers manage only their own submissions without access to broader archival holdings
- Archivist review gate maintains description quality before public accessibility in AtoM

### 🔒 Privacy-Preserving Digital Object Management
- Automated POPIA/GDPR sensitivity screening — NLP classifiers flag documents containing personal information categories prior to broader accessibility
- AES-256-GCM digital object encryption for restricted holdings
- Key management architecture with master keys stored outside the web root; per-collection key configuration available
- On-the-fly decryption for authorised access with streaming delivery — no intermediate plaintext storage
- Full audit logging of access and encryption events for compliance demonstration

### 🔗 AtoM Integration Layer
- Reads AtoM's MySQL database directly via Laravel's Illuminate Database (Capsule)
- Extends AtoM's data model without modifying AtoM core — compatible with standard AtoM upgrades
- Communicates with AtoM's Symfony layer through defined API interfaces
- Supports multiple AtoM instances from a single Heratio deployment

---

## Architecture

Heratio operates as a separate Laravel application alongside an existing AtoM installation, connected via shared database access and HTTP API interfaces. This separation maintains a clean process boundary that preserves AtoM's AGPL licensing integrity while allowing Heratio to be extended and integrated with proprietary or commercial components via its own API layer.

```
┌─────────────────────────────────────────┐
│              Heratio (Laravel)           │
│                                         │
│  ┌─────────────┐  ┌──────────────────┐  │
│  │  AI/NER     │  │  Researcher      │  │
│  │  Pipeline   │  │  Portal          │  │
│  └─────────────┘  └──────────────────┘  │
│  ┌─────────────┐  ┌──────────────────┐  │
│  │  Encryption │  │  Audit / Access  │  │
│  │  Service    │  │  Control         │  │
│  └─────────────┘  └──────────────────┘  │
│              │                          │
│     Illuminate Database (Capsule)        │
└──────────────┼──────────────────────────┘
               │
    ┌──────────▼──────────┐
    │   AtoM MySQL DB      │
    └──────────┬──────────┘
               │
    ┌──────────▼──────────┐
    │   AtoM (Symfony)     │
    │   Public Interface   │
    └─────────────────────┘
```

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.1 or higher |
| Laravel | 10 or higher |
| AtoM | 2.9 or higher |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Nginx | 1.18+ |
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

Edit `.env` to point to your existing AtoM database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=atom
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

ATOM_BASE_URL=https://your-atom-instance.example.com
```

### 4. Run migrations

```bash
php artisan migrate
```

### 5. Configure your web server

Point a subdomain (e.g. `heratio.yourdomain.com`) to Heratio's `public/` directory. A sample Nginx configuration is provided in `docs/nginx.conf.example`.

### 6. Set up the encryption key (optional)

If using digital object encryption, generate and store a master key outside the web root:

```bash
php artisan heratio:generate-encryption-key
```

Follow the prompts to store the key securely at `/etc/heratio/encryption.key` or equivalent.

---

## Configuration

Key configuration options are managed through `config/heratio.php` after installation:

| Option | Description |
|---|---|
| `atom.base_url` | URL of your AtoM instance |
| `atom.api_key` | AtoM REST API key (if using API features) |
| `ner.provider` | NER provider: `ollama`, `spacy`, or `api` |
| `ner.model` | Model name for NER processing |
| `encryption.enabled` | Enable/disable digital object encryption |
| `encryption.key_path` | Path to master encryption key |
| `popia.screening_enabled` | Enable/disable sensitivity screening |

---

## Compliance

Heratio is designed to support compliance with:

- **POPIA** — Protection of Personal Information Act 4 of 2013 (South Africa)
- **PAIA** — Promotion of Access to Information Act 2 of 2000 (South Africa)
- **NARSSA** — National Archives and Records Service of South Africa Act 43 of 1996
- **GDPR** — General Data Protection Regulation (EU) — via configurable privacy screening
- **ISO 15489** — Records management principles
- **ISO 23081** — Metadata for records

Heratio does not make compliance guarantees. Institutions are responsible for configuring the system appropriately for their legislative context and conducting their own compliance assessments.

---

## Roadmap

Planned features for upcoming releases:

- [ ] Full NER pipeline integration with continuous learning feedback loop
- [ ] Archivematica integration for preservation processing of ingested digital objects
- [ ] RiC-O (Records in Contexts Ontology) linked data export
- [ ] IIIF manifest generation for digitised collections
- [ ] Bulk shared drive ingestion agent with automated classification
- [ ] Multi-language NER support (including isiZulu, Sesotho, Afrikaans)

---

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting pull requests.

Heratio is governed in alignment with the [AtoM Foundation](https://www.accesstomemory.org/en/foundation/) community principles. We encourage contributions from archives, libraries, and memory institutions across the African continent and globally.

---

## License

Heratio is released under the [GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE), consistent with AtoM's licensing. This means:

- You may use, modify, and distribute Heratio freely
- Modifications must be released under the same licence
- If you run a modified version as a network service, you must make the source available to users

Proprietary extensions that communicate with Heratio via its HTTP API are considered independent works and are not subject to AGPL copyleft requirements, provided they do not incorporate AGPL-licensed code directly.

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

**The Archives and Heritage Group (The AHG)**
Johan Pieterse — johan@theahg.co.za
[https://www.theahg.co.za](https://www.theahg.co.za)

---

*Developed in South Africa. Built for archives everywhere.*
