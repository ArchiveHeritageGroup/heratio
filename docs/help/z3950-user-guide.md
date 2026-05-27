> Heratio Help Center article. Category: User Guide.

# Heratio - Z39.50 Bibliographic Search: User Manual

**Version:** 1.0.0
**Date:** May 2026
**Author:** The Archive and Heritage Group (Pty) Ltd
**Issue:** heratio#759

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Searching a Remote Target](#3-searching-a-remote-target)
4. [Reviewing Results](#4-reviewing-results)
5. [Importing a Record](#5-importing-a-record)
6. [Managing Targets](#6-managing-targets)
7. [Configuration](#7-configuration)
8. [Limitations](#8-limitations)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Introduction

Z39.50 (ISO 23950 / ANSI/NISO Z39.50-2003) is the international standard for federated bibliographic search. Heratio acts as a Z39.50 client, allowing cataloguers to search remote library catalogues (Library of Congress, COPAC, WorldCat, national libraries) and import MARC21 records directly into the local catalogue.

NLSA LMS Tender §7.1 requires this capability for bibliographic control.

This release ships the **client** half of the protocol. The Z39.50 **server** that exposes the Heratio catalogue to external clients (and the SRU HTTP wrapper) is tracked as remaining work in heratio#759 and will follow.

---

## 2. Getting Started

Navigate to **/z3950** from any authenticated session. The dashboard lists:

- Configured remote targets (host, port, database name)
- Recent search history
- Result-set cache (active for 30 minutes)

You must be authenticated to search. Target management requires admin role.

---

## 3. Searching a Remote Target

1. Click **New search** from /z3950.
2. Pick a target from the dropdown. Targets ship pre-seeded (LoC, BL, OCLC sample) and can be extended by admins.
3. Enter your query. Bib-1 attribute set supported:
   - `@attr 1=4 "title"` (title)
   - `@attr 1=1003 "author"` (personal name)
   - `@attr 1=7 "isbn"` (ISBN)
   - `@attr 1=8 "issn"` (ISSN)
   - `@attr 1=21 "subject"` (subject)
4. Submit. The server returns the result set ID and total hit count.

Free-text queries are auto-wrapped with `@attr 1=1016` (any).

---

## 4. Reviewing Results

The results page renders the first 20 records (default page size) as a MARC21-summary card:

- 100/700 (creator)
- 245 (title)
- 260/264 (publication)
- 020 (ISBN)
- 650 (subjects)

Click a record to see the full MARC21 dump. The raw record is preserved verbatim for fidelity.

---

## 5. Importing a Record

From the result detail page, click **Import to catalogue**. The MARC21 record is parsed and a `library_item` row is created with:

- Title, author, publication, edition, physical description (300)
- ISBN, ISSN (020/022) added to `library_item_identifier`
- Subjects (650) deduplicated against the local `term` table
- Creator (100/700) mapped to `actor` with personal-name normalisation
- Cataloguing source (040) recorded as provenance

Batch import is supported: tick multiple result rows, then **Import selected**.

Locally-edited fields are preserved on re-import. The remote source is logged in `library_item.provenance_source` for traceability.

---

## 6. Managing Targets

Admins can manage Z39.50 targets via /z3950/admin:

- Add target (host, port, database, syntax, charset)
- Edit target (test connection before save)
- Delete target

Default port is 210. Common databases: `Voyager`, `BIB`, `INNOPAC`. Auth (username + password) is supported per-target.

---

## 7. Configuration

`config/ahg-z3950.php` controls runtime behaviour:

| Key | Default | Purpose |
|---|---|---|
| `client.timeout` | 30 | Connection timeout (seconds) |
| `client.max_records` | 100 | Per-search record cap |
| `client.preferred_record_syntax` | `marc21` | Wire-format syntax |
| `server.enabled` | false | (Reserved) Z39.50 server daemon switch |
| `server.port` | 2100 | (Reserved) Server listen port |
| `cache.result_set_ttl` | 1800 | Result-set cache lifetime (seconds) |

`.env` overrides:

```env
AHG_Z3950_CLIENT_TIMEOUT=60
AHG_Z3950_MAX_RECORDS=200
```

---

## 8. Limitations

- **Server side not yet shipped.** The Heratio catalogue cannot yet be searched via Z39.50 by external clients. SRU (Search/Retrieve via URL) HTTP wrapper is also pending.
- **YAZ extension recommended.** If the `php-yaz` PECL extension is installed, native Z39.50 is used. Without it, the client falls back to an HTTP-to-Z39.50 gateway (slower, less reliable). Install: `pecl install yaz`.
- **No SRW (SOAP) variant.**
- **Search syntax** is limited to bib-1; CCL parsing is on the roadmap.

---

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| "Cannot connect to target" | Firewall / target down | Test with `yaz-client tcp:<host>:<port>/<db>` |
| Empty result set | Bib-1 attribute mismatch | Try simpler `1=4` title search |
| Charset garble | Target uses MARC-8 not UTF-8 | Force `charset=utf-8` in target config |
| Import duplicates | Same ISBN already in catalogue | Use **Merge** instead of **Import** |
| Slow response | Remote target rate-limiting | Lower `max_records`, batch imports |

---

For technical operators, see `docs/reference/z3950-implementation.md`.
