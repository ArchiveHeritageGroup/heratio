# Heratio — Custom Fields Framework

## Feature Overview

**Plugin:** ahgCustomFieldsPlugin v1.0.0
**Category:** Metadata Management
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## Overview

The Custom Fields Framework enables institutions to define unlimited custom metadata fields on any entity type — without writing code. Administrators configure fields through an intuitive web interface, and the system automatically renders them on entity edit and view pages.

This eliminates the need for developer involvement when institutions require additional metadata fields beyond the standard archival description fields (ISAD(G), DACS, Dublin Core, RAD, MODS).

---

## Key Features

### Admin-Configurable Field Definitions
- Define custom fields via a web-based admin interface at **Admin > Custom Fields**
- No code changes, database migrations, or server restarts required
- Fields can be created, edited, reordered, activated, or deactivated at any time

### Multiple Field Types
| Type | Description |
|------|-------------|
| Text | Single-line text input |
| Textarea | Multi-line text input |
| Date | Date picker (YYYY-MM-DD) |
| Number | Numeric input with decimal support |
| Boolean | Checkbox (Yes/No) |
| Dropdown | Controlled vocabulary from Dropdown Manager |
| URL | URL input with clickable link |

### Entity Type Support
Custom fields can be attached to any of the following entity types:
- **Information Object** — Archival descriptions
- **Actor** — Authority records (persons, organizations, families)
- **Accession** — Accession records
- **Repository** — Archival institutions
- **Donor** — Donor records
- **Function** — ISDF function descriptions

### Repeatable Fields
A single field definition can accept multiple values. For example, a "Barcode" field can store multiple barcode values for a single record.

### Field Grouping
Related fields can be organized under section headings (e.g., "Legacy Data", "Tracking", "Ontario Fields"). Groups are displayed as labelled sections on edit and view pages.

### Validation
- Required field enforcement
- Custom validation rules (maximum length, regular expression patterns)
- Type-specific validation (valid dates, valid URLs, numeric values)

### Visibility Control
Each field offers independent visibility settings:
- **Public View** — Show or hide on the public-facing record page
- **Edit Form** — Show or hide on the staff edit form

### Searchable Fields
Fields can be flagged as searchable for future integration with the search index.

### Dropdown Integration
Dropdown-type fields are linked to existing controlled vocabularies managed via the Dropdown Manager (ahg_dropdown). This ensures consistency with institution-wide terminology.

### Import / Export
Field definitions can be exported as JSON and imported into another Heratio instance, enabling easy migration and standardization across deployments.

### Display Integration
Custom field values automatically appear on entity view pages via the display panel system. No template modifications required.

---

## Use Cases

| Institution Need | Custom Fields Solution |
|-----------------|----------------------|
| Track barcodes on archival boxes | Repeatable text field "Barcode" on Information Object |
| Record legacy system IDs | Text field "Legacy ID" grouped under "Migration" |
| Flag records as open data | Boolean field "Open Data" on Information Object |
| Classify by filing code | Dropdown field linked to institution's filing taxonomy |
| Store donor submission dates | Date field "Submission Date" on Actor |
| Link to external catalogue | URL field "External Catalogue Link" on Repository |

---

## Reporting Views

The framework includes three denormalized SQL views for integration with external business intelligence tools (Power BI, Tableau, Metabase, or any SQL-compatible BI platform):

| View | Description |
|------|-------------|
| `v_report_descriptions` | Flattened archival descriptions with repository, level of description, event dates, publication status |
| `v_report_authorities` | Flattened authority records with entity type and description status |
| `v_report_accessions` | Flattened accession records with acquisition type, processing status, and priority |

---

## Access Restriction Vocabularies

A base set of 9 access restriction codes is included as seed data, available via the Dropdown Manager:

| Code | Label |
|------|-------|
| open | Open / Unrestricted |
| closed | Closed |
| restricted_time | Time-based Restriction |
| restricted_permission | Permission Required |
| restricted_privacy | Privacy Restriction |
| restricted_legal | Legal Hold |
| restricted_cultural | Cultural Protocol |
| restricted_security | Security Classification |
| restricted_donor | Donor Restriction |

Institutions can extend this vocabulary with their own codes via **Admin > Dropdown Manager**.

---

## Technical Requirements

| Requirement | Version |
|-------------|---------|
| Heratio Framework | >= 2.8.0 |
| AtoM Base | >= 2.8 |
| PHP | >= 8.1 |
| MySQL | 8.0+ |
| Required Plugin | ahgCorePlugin |

---

## Installation

1. Enable the plugin via CLI: `php bin/atom extension:enable ahgCustomFieldsPlugin`
2. Run the database install: `mysql -u root archive < plugins/ahgCustomFieldsPlugin/database/install.sql`
3. Clear cache: `rm -rf cache/* && php symfony cc`
4. Navigate to **Admin > Custom Fields** to begin defining fields

---

*The Archive and Heritage Group (Pty) Ltd*
*https://www.theahg.co.za*
