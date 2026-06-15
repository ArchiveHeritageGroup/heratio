# Rights

> Heratio's rights management records copyright status, licensing, embargoes, territorial limits, traditional knowledge labels and orphan-work due diligence against archival descriptions and digital objects, so every item carries an auditable statement of how it may be used.

## Overview

The Rights module (`ahg-rights`) holds the data model that captures the legal and cultural conditions attached to a record. It centres on a primary rights record per archival description, plus supporting tables for standard rights statements, Creative Commons licences, embargoes, grants, territories, Traditional Knowledge / Biocultural labels, and orphan-work searches. The vocabularies it ships are international: rightsstatements.org statements, Creative Commons 4.0 licences, and Local Contexts TK/BC labels, with copyright jurisdiction stored as an ISO 3166 country code rather than any single country's law.

Rights data is consumed across Heratio: digital-object delivery can apply watermark, resize, redaction and metadata-strip rules per role, clearance level and purpose; the ODRL policy engine enforces use and reproduction on the archival-record show page; and embargo records gate visibility until a release date is reached.

## Key features

- **Rights records** - one record per archival description capturing basis (copyright, licence, statute, donor, policy or other), copyright status, holder, jurisdiction (ISO country code), determination date and free-text notes, with translatable rights and restriction notes.
- **Standard rights statements** - the twelve rightsstatements.org vocabulary terms (In Copyright, No Copyright, and Other categories) with URIs, codes and icons, ready to assign to a record.
- **Creative Commons licences** - the full CC 4.0 suite plus CC0 and the Public Domain Mark, each flagged for commercial use, derivatives, share-alike and attribution requirements, with badge images.
- **Embargoes** - time-boxed access restrictions (full, metadata-only, digital-only or partial) with reason codes, review dates, auto-release on the end date, advance-notification windows and a full action log.
- **Rights grants** - PREMIS-style act/restriction pairs (render, disseminate, replicate, migrate, modify, print, publish and more) set to allow, disallow or conditional, with optional date ranges and conditions.
- **Territories** - per-record include/exclude lists of country or region codes, with optional GDPR-territory and legal-basis flags for jurisdiction-aware delivery.
- **Traditional Knowledge and Biocultural labels** - the Local Contexts TK and BC label sets (attribution, non-commercial, community voice, culturally sensitive, secret/sacred, gender- and clan-restricted, research, consent, provenance and more) attachable to records with community contact details and verification status.
- **Orphan-work due diligence** - records a diligent-search process per work, with individual search steps (source type, name, URL, date, terms, results and evidence) to document good-faith efforts to locate a rights holder.
- **Derivative rules and log** - rules that drive watermarking, redaction, resizing, format conversion and metadata stripping on digital-object derivatives, scoped per object, collection or globally and conditioned on role, clearance level and purpose, with a generation log.

## How to use

Rights data is created and edited from the archival-record management screens and the related rights, embargo and digital-object delivery features rather than from a single standalone page. In practice:

1. Open an archival description in the management interface.
2. Use the rights section to record the basis, copyright status, holder, jurisdiction and any notes for the item.
3. Assign a standard rights statement and, where applicable, a Creative Commons licence so the record displays a recognised badge and machine-readable status.
4. To restrict access for a period, add an embargo with its type, reason, start and end dates and review interval; the record is gated until the embargo lifts or is released.
5. For culturally sensitive material, attach the appropriate TK or BC labels and capture the community contact and any usage protocol.
6. For works whose rights holder is unknown, open an orphan-work record and log each search step as evidence of diligent search.
7. Define derivative rules where delivered copies should be watermarked, redacted or downsized for particular roles, clearance levels or purposes.

Enforcement happens automatically once the data exists: the ODRL policy engine checks `odrl:use` on viewing and `odrl:reproduce` on printing, and the digital-object pipeline applies any matching derivative rules. Administrators bypass policy checks.

## Configuration

- The standard rights statements, Creative Commons licences and TK/BC labels are seeded on install from `packages/ahg-rights/database/install.sql` and can be managed as reference vocabularies.
- Copyright and statute jurisdiction are stored as ISO 3166 country or region codes, keeping the module jurisdiction-neutral; populate them per record to suit the market.
- Derivative rules, embargo reasons and similar enumerated values follow the Dropdown Manager pattern - manage them under Admin and never hardcode option lists.
- Rights enforcement on records is driven by the ODRL policy engine; with no policy in place, access is allowed.

## Known issues

- `ahg-rights` currently ships the data schema and seed vocabularies. Day-to-day editing of rights, embargoes, labels and orphan-work records is surfaced through the archival-record and digital-object management screens and the ODRL policy tooling rather than a dedicated rights dashboard. Track outstanding controller, service and view work in the linked GitHub issue.

## References

- Source: packages/ahg-rights/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/620
