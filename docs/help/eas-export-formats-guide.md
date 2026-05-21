> Heratio Help Center article. Category: Import/Export / Standards.

# EAS Export Formats

## Overview

Heratio supports exporting archival metadata in multiple EAS (Encoded Archival Standards) XML formats. Each format targets a different type of archival entity.

## Available Export Formats

### EAD 4 — Archival Descriptions
Export finding aids and archival descriptions in the latest EAD 4 format.

**What it exports:** Information objects (fonds, series, files, items) with full ISAD(G) metadata including scope and content, arrangement, access conditions, dates, creators, and hierarchical structure.

**How to use:**
1. Go to Admin > Export > Metadata Export
2. Select EAD 4 format
3. Choose a repository or specific record
4. Click Export

### EAC-CPF 2.0 — Authority Records
Export authority records (persons, corporate bodies, families) in EAC-CPF 2.0 format.

**What it exports:** Actor records with authorized names, dates of existence, history, mandates, legal status, functions, relationships to other actors.

**How to use:**
1. Go to Admin > Export > Metadata Export
2. Select EAC-CPF 2.0 format
3. Choose actors to export
4. Click Export

### EAC-F — Functions
Export function descriptions in the new EAC-F format (ISDF encoding).

**What it exports:** Function records from the function table and RiC activity entities, including classification, dates, description, history, legislation, and relationships.

### EAG 3.0 — Repository Guides
Export repository/institution descriptions in EAG 3.0 format.

**What it exports:** Repository records with institution name, contact information, opening hours, history, holdings description, and services.

## Format Comparison

| Format | Entity | Standard | Schema |
|--------|--------|----------|--------|
| EAD 4 | Records | ISAD(G) | archivists.org/ns/ead/v4 |
| EAC-CPF 2 | Agents | ISAAR(CPF) | archivists.org/ns/eac/v2 |
| EAC-F | Functions | ISDF | archivists.org/ns/eac-f/v1 |
| EAG 3 | Repositories | ISDIAH | archivists.org/ns/eag/v3 |

## Tips

- EAD 4 exports include child records recursively using the nested set hierarchy
- EAC-CPF 2.0 uses a new namespace different from version 1.0
- EAC-F maps directly from RiC activity entities if available, falling back to function table data
- EAG 3.0 includes contact information from the contact_information table
- All formats produce well-formed XML with proper namespace declarations
