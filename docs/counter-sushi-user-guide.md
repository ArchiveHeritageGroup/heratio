> Heratio Help Center article. Category: User Guide.

# Heratio - COUNTER 5 + SUSHI Usage Statistics: User Manual

**Version:** 1.0.0
**Date:** May 2026
**Author:** The Archive and Heritage Group (Pty) Ltd
**Issue:** heratio#766

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Usage Capture](#2-usage-capture)
3. [COUNTER Reports](#3-counter-reports)
4. [SUSHI Harvest](#4-sushi-harvest)
5. [Subscriptions](#5-subscriptions)
6. [Configuration](#6-configuration)
7. [Limitations](#7-limitations)
8. [Standards & References](#8-standards--references)

---

## 1. Introduction

COUNTER Code of Practice Release 5 (COUNTER R5) is the international standard for vendor-neutral e-resource usage statistics. SUSHI (NISO Z39.93) is the harvesting protocol that lets subscribing institutions pull COUNTER reports programmatically.

NLSA LMS Tender §2.12 requires both for usage reporting.

This release ships the **SUSHI client + report-generation backbone**. Per-event capture instrumentation (page view, link click, download), the Item Report (IR), TR_J1/TR_J3 standard views, the SUSHI **server** endpoint, scheduled-email delivery, and the COUNTER conformance certification path remain on the roadmap.

---

## 2. Usage Capture

Heratio captures usage at three levels:

1. **Catalogue access events** via the existing `audit_trail` pipeline (every `library_item` view).
2. **Per-event JS instrumentation** (heratio#766, heratio#1096). The `usage-tracker.js` beacon, auto-injected by the `InjectUsageTracker` middleware, posts to `/api/library/usage-event`:
   - `view` (page view) -> `Total_Item_Investigations`
   - `request` / `link_click` (download / link-out) -> `Total_Item_Requests`
   - `search` (OPAC search submission with a non-empty query) -> `Searches_Platform` (COUNTER PR1)
   Each event is written to **two** surfaces: the aggregate counter `library_usage_stats` and the per-event log `library_counter_log`. The per-event log carries an anonymised `session_id` (sha256 of an http-only `lib_usage_sid` cookie - never a raw identifier), `resource_type`, `access_type` (`Controlled` / `OA_Gold`) and `status`. The session log is what makes the **unique-item** metrics (`Unique_Item_Requests`, `Unique_Item_Investigations` = distinct item per session per day) and the PR1 search count computable - they cannot be derived from a pre-aggregated counter.
3. **SUSHI harvest events** when Heratio pulls reports from upstream vendors.

---

## 3. COUNTER Reports

Visit **/library-manage/usage** for the dashboard. The shipped report types:

| Report | View | Notes |
|---|---|---|
| TR (Title Report) | `/library-manage/usage/tr` | Per-title use breakdown |
| DR (Database Report) | `/library-manage/usage/dr` | Per-database use breakdown |
| PR (Platform Report) | available via export | Per-platform aggregates incl. unique-item + search counts |
| IR (Item Report) | available via export | Per-item granularity |

Each report supports:

- Date-range filter (begin / end month)
- Platform filter (multi-select)
- Format: JSON (canonical), TSV, CSV, and **XLSX** (Excel)

The exported JSON conforms to the COUNTER R5 spec's `Report_Header` / `Report_Items` structure.

**Export URL:** `GET /library-manage/usage/export/{type}?from=YYYY-MM-DD&to=YYYY-MM-DD&format=tsv|csv|xlsx`. `type` is one of `PR`, `TR`, `TR_J1`, `TR_J3`, `DR`, `IR`. `format` defaults to `tsv`; `xlsx` is generated with PhpSpreadsheet and falls back to CSV if the workbook write fails.

---

## 4. SUSHI Harvest

Visit **/library-manage/usage/harvest** to pull COUNTER reports from vendors that publish a SUSHI endpoint.

1. Add a subscription (see next section).
2. Click **Test** to confirm the credential and `Report_ID` are valid.
3. Click **Harvest now** for a one-off pull, or wait for the scheduled job (default monthly).

Harvested reports are written to `library_usage_report` and indexed for the TR/DR/PR pages above.

---

## 5. Subscriptions

Visit **/library-manage/usage/subscriptions** to manage SUSHI subscriptions.

For each subscription, capture:

| Field | Purpose |
|---|---|
| Vendor name | Display label |
| SUSHI base URL | e.g. `https://api.springernature.com/sushi/r5` |
| Customer ID | Vendor-assigned ID |
| Requestor ID | Often a UUID issued by the vendor |
| API key | Bearer token if required |
| Report IDs | Comma-separated, e.g. `TR_J1,TR_J3,DR_D1` |

Click **Test connection** to issue a `/status` SUSHI probe before saving.

---

## 6. Configuration

Settings live in the `library_settings` table (no per-deploy `.env` keys today):

| Key | Default | Purpose |
|---|---|---|
| `counter.organization_name` | (empty) | Required on every COUNTER export header |
| `counter.organization_id` | (empty) | Library identifier (e.g. ISIL) |
| `sushi.timeout` | 60 | Per-request timeout (seconds) |
| `sushi.retry` | 3 | Retries on 429/5xx |

---

## 7. SUSHI 5.0 server endpoint (live in v1.112+)

Heratio now publishes its COUNTER R5 reports via a SUSHI 5.0 REST endpoint at `/api/sushi/r5/*`:

| Path | Purpose |
|---|---|
| `GET /api/sushi/r5/status` | Service health + alerts |
| `GET /api/sushi/r5/members` | Institutions served by this endpoint |
| `GET /api/sushi/r5/reports` | List of supported report IDs |
| `GET /api/sushi/r5/reports/{report_id}` | Specific report, with `begin_date` and `end_date` query params |

Supported report IDs: `PR`, `TR`, `TR_J1`, `TR_J3`, `DR`, `IR`. The COUNTER R4-style standard-view aliases named in the NLSA ToR are also accepted and mapped onto the R5 master reports: `BR1`/`BR2` -> `TR`, `PR1` -> `PR`, `DR1` -> `DR`. So a partner can call `GET /api/sushi/r5/reports/BR1?begin_date=2026-01-01&end_date=2026-06-30` and receive a valid R5 envelope.

**Authentication.** Optional by default (anonymous-allow). Set `library.sushi.require_auth = true` to enforce per-consumer credentials stored in the `library_sushi_consumer` registry. Each consumer row carries `customer_id`, `requestor_id`, an `api_key_hash` (sha256 of the issued key), and an `active` flag; a request authenticates when all three of `customer_id` + `requestor_id` + `api_key` match an active row.

**Audit log.** Every `status` / `members` / `reports` / report request is written to `library_sushi_audit_log` (customer/requestor id, report id, date range, IP, user-agent, authorised flag, timestamp) - the "who requested which report" trail required by the ToR.

## 8. Limitations

- **All COUNTER R5 standard views live.** PR / TR / TR_J1 / TR_J3 / DR / IR plus the R4-style BR1/BR2/PR1/DR1 aliases are produced server-side; the `LibraryUsageController` and the SUSHI server endpoint both honour them.
- **Per-event JS instrumentation live (heratio#1096).** Page view, download / link-out click, and OPAC search are captured at the JS layer and de-duplicated per session for the unique-item + search metrics. OPAC browse is no longer understated. Note the `lib_usage_sid` cookie is a same-site session grouping token, not a cross-site tracker.
- **No COUNTER conformance certification.** Exports are R5-shaped but the audit dossier for the COUNTER Code of Practice is not yet submitted to Project Counter.
- **Implementation lives inside `ahg-library`,** not a dedicated `ahg-counter` package. This is intentional for the first cut to avoid premature package proliferation; a future extraction is possible if downstream consumers need it independently.

---

## 8. Standards & References

- COUNTER R5 Code of Practice: https://www.projectcounter.org/code-of-practice-five-sections/
- SUSHI 5.0 protocol (NISO Z39.93): https://www.niso.org/standards-committees/sushi
- COUNTER R5 JSON schema: https://github.com/Project-Counter/counter-schema

---

For technical operators, see `docs/reference/counter-sushi-implementation.md`.
