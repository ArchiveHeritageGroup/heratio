> Heratio Help Center article. Category: GLAM Sectors.

# Heratio - Library Module: Complete Guide

**Version:** 2.0.0
**Date:** May 2026
**Author:** The Archive and Heritage Group (Pty) Ltd

This is the umbrella guide to Heratio's Library (Integrated Library System) module. Each major area links to its own detailed help article.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Cataloguing](#2-cataloguing)
3. [MARC Editor](#3-marc-editor)
4. [Serials](#4-serials)
5. [Circulation](#5-circulation)
6. [Patrons](#6-patrons)
7. [Inter-Library Loan (ILL)](#7-inter-library-loan-ill)
8. [Acquisitions](#8-acquisitions)
9. [KBART Vendor Feeds](#9-kbart-vendor-feeds)
10. [Z39.50 / SRU](#10-z3950--sru)
11. [BIBFRAME](#11-bibframe)
12. [FRBR Work-Set Clustering](#12-frbr-work-set-clustering)
13. [COUNTER / SUSHI Usage](#13-counter--sushi-usage)
14. [Reports](#14-reports)
15. [ISBN Providers](#15-isbn-providers)
16. [Standards Conformance](#16-standards-conformance)

---

## 1. Overview

The Library module turns Heratio into a full ILS for the Gallery/Library/Archive/Museum sector. It manages bibliographic records, serials, circulation, patrons, acquisitions, inter-library loans, e-resource usage statistics, and interoperability with the wider library ecosystem (MARC21, BIBFRAME, FRBR, KBART, Z39.50/SRU, COUNTER/SUSHI).

| Surface | URL |
|---|---|
| Public catalogue browse | `/library`, `/glam/browse?type=library` |
| Admin dashboard | `/library-manage/index` |
| Library settings | `/admin/settings/library`, `/admin/settings/ahg/library` |

Library records are `library_item` rows attached to `information_object`, so a library item is a first-class archival description with library-specific fields layered on.

---

## 2. Cataloguing

Create and edit bibliographic records with full MARC/RDA-aligned fields: title, statement of responsibility, edition, publication (264), physical description (300), series, ISBN/ISSN/LCCN/OCLC, classification (Dewey/LCC/Cutter), subjects, notes, and holdings.

- AI subject suggestions are available on the edit form (ranked by relevance + usage).
- Book covers are fetched + cached via the cover proxy (`/library/cover-image/{isbn}`) so the patron's IP is never exposed to the upstream cover service.
- Authority control: each creator is upserted to an `actor` (Authority Record) and linked.

---

## 3. MARC Editor

`/library-manage/marc` - import, view, edit and export MARC21 records.

- **Import**: `/library-manage/marc/import` (MARCXML or binary .mrc)
- **Edit**: `/library-manage/marc/{id}/edit` - field/subfield editor
- **Export**: `/library-manage/marc/{id}/download` (MARCXML) + `/download-binary` (.mrc)

See also: the MARC21 import help article.

---

## 4. Serials

`/library-manage/serials` - manage periodical subscriptions and predicted issues.

- Create / edit serials with frequency (weekly through irregular), ISSN, publisher, status (active / ceased / suspended).
- **Coverage** (`/serial/{id}/coverage`), **prediction** (`/serial/{id}/predict`) of expected issues, **subscription** tracking (`/serial/{id}/subscription`).
- **Overdue claims** (`/serial/overdue-claims`) - issues expected but not received.
- Expected-issue generation runs on a schedule (`ahg:library-serial-expected`).

---

## 5. Circulation

`/library-manage/circulation` - checkouts, returns, holds, renewals.

- **Loan rules** (`/circulation/loan-rules`) - per-material-type loan periods + fine rates.
- **Overdue** (`/circulation/overdue`) - overdue items + fine calculation.
- Scheduled housekeeping: hold expiry (02:30), patron expiry (02:45), fine calculation (03:15), all daily and individually toggleable in library settings.

---

## 6. Patrons

`/library-manage/patrons` - library membership records, distinct from research-portal researchers.

- Membership expiry with grace period; auto-expire runs daily.
- Patron view (`/patron/{id}`), reactivation flow.

---

## 7. Inter-Library Loan (ILL)

`/library-manage/ill` - ISO 10160/10161-aligned ILL request state machine.

- Patron self-service request (`/opac/ill/create`), staff queue, per-request transitions (`/ill/{id}/transition`).
- Tipasa configuration under `/ill/settings`.
- Overdue ILL reporting via `ahg:library-ill-overdue`.

---

## 8. Acquisitions

`/library-manage/acquisitions` - orders, budgets, batch capture.

- Purchase orders (`/acquisition/order/{id}`), budget tracking (`/acquisition/budgets`), batch capture (`/acquisition/batch-capture`).

---

## 9. KBART Vendor Feeds

`/library-manage/kbart` - import title lists from content providers (NISO RP-9-2014 KBART Phase II).

- Manual TSV upload (`/kbart/import`) with preview + commit.
- **Remote feeds** (`/kbart/remote`): register a vendor's hosted KBART URL; Heratio fetches, parses, dedupes, and imports it.
- **Automated refresh**: daily at 01:00, with per-feed `refresh_frequency`, change detection, librarian notifications, and a fingerprint short-circuit for unchanged feeds.
- **Refresh log** (`/kbart/remote/log`): per-fetch history with +added / -removed / changed deltas.

Detailed article: **KBART Remote Feeds & Automated Refresh**.

---

## 10. Z39.50 / SRU

Bibliographic search interoperability (NLSA LMS Tender §7.1).

- **Z39.50 client** (`/z3950`): search remote targets (LoC, BL, etc.), import MARC21 records.
- **SRU 2.0 server** (`/sru`): exposes the Heratio catalogue to federated discovery clients. Supports `explain` + `searchRetrieve`, CQL queries, MARC21 + Dublin Core record schemas.
- Native binary Z39.50 daemon: optional YAZ sidecar (see operator runbook).

Detailed article: **Z39.50 Bibliographic Search**.

---

## 11. BIBFRAME

`/bibframe` - the Library of Congress linked-data model (Work / Instance / Item).

- Bidirectional MARC21 <-> BIBFRAME conversion; RDF/XML, Turtle, JSON-LD export.
- Graph-aware editor (`/bibframe/editor/{id}`) for Work/Instance/Contribution/Topic.
- Open Discovery Initiative (NISO RP-19-2020) conformance statement.

Detailed article: **BIBFRAME 2.0 Integration**.

---

## 12. FRBR Work-Set Clustering

`/frbr` - groups multiple editions/translations/formats of one intellectual Work into a single search hit.

- Work-key generation + ES indexing; GLAM browse collapses clusters with a "View all N editions" expander linking to `/library/work-cluster/{key}`.
- Cataloguer force-group / force-split overrides (`/admin/frbr/overrides`).

Detailed article: **FRBR Work-Set Clustering**.

---

## 13. COUNTER / SUSHI Usage

`/library-manage/usage` - vendor-neutral e-resource usage statistics (COUNTER R5 + SUSHI 5.0).

- Report types: TR, DR, PR, IR, TR_J1, TR_J3 (`/usage/tr`, `/usage/dr`, ...).
- SUSHI **client**: harvest reports from vendors (`/usage/harvest`, `/usage/subscriptions`).
- SUSHI **server**: publish Heratio's reports at `/api/sushi/r5/*`.
- Per-event capture (page view, link click, download) via the usage-tracker beacon.
- Scheduled monthly email delivery (`ahg:library-email-usage-reports`).

Detailed article: **COUNTER 5 + SUSHI Usage Statistics**.

---

## 14. Reports

`/library-manage/reports` - catalogue analytics: by call number, creator, publisher, subject, and full catalogue export.

---

## 15. ISBN Providers

`/library-manage/isbn-providers` (+ `/admin/library/isbn-providers`) - configure the upstream metadata + cover providers used by ISBN lookup (`/library-manage/isbn-lookup`). Toggle, edit, prioritise providers.

---

## 16. Standards Conformance

The Library module implements or interoperates with:

- **MARC21 / MARCXML** + **RDA** - cataloguing
- **BIBFRAME 2.0** - linked-data interchange
- **FRBR / IFLA LRM** - work-set clustering
- **Dublin Core**, **MODS** - metadata crosswalks
- **KBART Phase II** (NISO RP-9-2014) - title-list exchange
- **Z39.50** (ISO 23950) + **SRU 2.0** - federated search
- **COUNTER R5** + **SUSHI 5.0** (NISO Z39.93) - usage statistics
- **Open Discovery Initiative** (NISO RP-19-2020) - discovery transparency
- **ISO 10160/10161** - inter-library loan

---

## Related help articles

- Z39.50 Bibliographic Search
- BIBFRAME 2.0 Integration
- FRBR Work-Set Clustering
- COUNTER 5 + SUSHI Usage Statistics
- KBART Remote Feeds & Automated Refresh
- AI Library Assistant
- Caption & Subtitle Track Management
- MARC21 Import
- ORCID Integration (for researcher authorship)
