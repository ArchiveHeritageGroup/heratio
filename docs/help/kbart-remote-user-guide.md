> Heratio Help Center article. Category: User Guide.

# Heratio - KBART Remote Feeds & Automated Refresh: User Manual

**Version:** 1.0.0
**Date:** May 2026
**Author:** The Archive and Heritage Group (Pty) Ltd
**Issues:** heratio#767 (remote URL fetch), heratio#768 (scheduler)

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [What is KBART?](#2-what-is-kbart)
3. [Registering a Remote Feed](#3-registering-a-remote-feed)
4. [Preview & Commit](#4-preview--commit)
5. [Automated Refresh Schedule](#5-automated-refresh-schedule)
6. [Refresh Log](#6-refresh-log)
7. [Configuration](#7-configuration)
8. [Limitations](#8-limitations)
9. [Standards & References](#9-standards--references)

---

## 1. Introduction

KBART (Knowledge Base and Related Tools) Phase II is the NISO Recommended Practice (RP-9-2014) for exchanging title-list information between content providers (publishers, aggregators) and knowledge-base receivers (libraries, link resolvers, discovery services).

This release ships **remote URL fetch** (heratio#767, Option B: vendor registry) and the **daily automated refresh scheduler** (heratio#768, partial). Cataloguers no longer need to download and manually upload KBART TSV files; instead, register the vendor's hosted feed URL once and Heratio pulls it on schedule.

---

## 2. What is KBART?

KBART is a standardised TSV (tab-separated values) format with a fixed column set:

`publication_title`, `print_identifier` (ISSN/ISBN), `online_identifier`, `date_first_issue_online`, `num_first_vol_online`, `date_last_issue_online`, `num_last_vol_online`, `title_url`, `first_author`, `title_id`, `embargo_info`, `coverage_depth`, `notes`, `publisher_name`, `publication_type`, `date_monograph_published_print`, `date_monograph_published_online`, `monograph_volume`, `monograph_edition`, `first_editor`, `parent_publication_title_id`, `preceding_publication_title_id`, `access_type`.

Heratio's KBART module imports these rows into `library_item` (per-title) and the linked sidecar tables for coverage, identifiers, and provenance.

---

## 3. Registering a Remote Feed

Navigate to **/library-manage/kbart/remote**. Click **Add feed**.

| Field | Required | Notes |
|---|---|---|
| Name | yes | Vendor display label (e.g. "Springer Nature 2026") |
| URL | yes | Full URL to the KBART TSV file (HTTP/HTTPS only) |
| Vendor | no | Free-text vendor name for reporting |
| Active | yes | If unchecked, the scheduler skips this feed |
| Refresh frequency | (planned) | Per-feed cron; currently global daily |
| Auth header | no | If the vendor protects the URL, paste `Authorization: ...` |

Click **Test URL** to validate the feed before saving. Heratio fetches the first 1 KB, checks Content-Type, parses the header row, and reports any obvious problems (wrong column count, missing required columns).

---

## 4. Preview & Commit

After registering, click **Refresh now** to pull the latest feed.

Heratio:

1. Downloads via Guzzle with a 60-second timeout and follows up to 5 redirects.
2. Parses the TSV using the existing `KbartService::parseTsv()` pipeline.
3. Validates each row against the KBART column spec.
4. Stores the result in your session under `kbart_raw_tsv` (same key the manual file-upload path uses).
5. Redirects you to the preview page where you can spot-check before committing.

Click **Commit** to write the rows into `library_item`. The existing dedupe-by-identifier logic prevents duplicates.

---

## 5. Automated Refresh Schedule

The Artisan command `php artisan ahg:library-kbart-refresh` runs every day at **01:00** via Laravel's scheduler (configured in `AhgLibraryServiceProvider`). It:

1. Iterates `library_kbart_feed` rows where `active = 1`.
2. Fetches each URL.
3. Parses + validates.
4. Writes the resulting batch to staging (uncommitted, awaiting cataloguer review).
5. Records `last_fetch_at`, `last_row_count`, and `last_error` on the feed row.

A cataloguer reviews staged refreshes from the dashboard.

To override the global daily cadence, edit the schedule line in `AhgLibraryServiceProvider::boot()` or wait for the per-feed `refresh_frequency` field, which is in the heratio#768 acceptance backlog.

---

## 6. Refresh Log

The KBART admin page (**/library-manage/kbart/remote**) shows per-feed:

- Last fetch time
- Row count delta vs the previous fetch (calculated from `last_row_count`)
- Last error (HTTP status, parse error, timeout)
- A **Test URL** button for ad-hoc validation

A richer "KBART refresh log" page (with full per-fetch row counts, diff detection, and notification integration via `ahg_notification`) is on the heratio#768 roadmap.

---

## 7. Configuration

Settings on `library_kbart_feed` rows are per-feed; global defaults live in `KbartRemoteService`:

| Setting | Default | Purpose |
|---|---|---|
| Connect timeout | 10 s | Stop early if vendor's resolver is down |
| Read timeout | 60 s | Long enough for multi-MB feeds |
| Max redirects | 5 | Stops infinite redirect loops |
| Max body size | 100 MB | Prevents a misconfigured feed from filling disk |
| User-Agent | `Heratio-KBART/1.0` | Lets vendors track aggregate use |

---

## 8. Refresh log (live in v1.112+)

Visit `/library-manage/kbart/remote/log` for the per-fetch history. Each row shows status, row count, `+added` / `-removed` / `changed` deltas, error message (if any), elapsed time, and a sample of changed titles.

The fingerprint short-circuit (sha256 of the TSV body) skips writeImportBatch when the feed hasn't changed since the last fetch, so identical refreshes complete in milliseconds.

## 9. Notifications (live in v1.112+)

The scheduler now writes `ahg_notification` rows to the `librarian` recipient role on:

- `success` with added/removed titles (subject: `[KBART] <vendor> (changes)`)
- `failure` (subject: `[KBART] <vendor> (failure)`) with the error message in the body

Notifications appear in the standard Heratio bell.

## 10. Per-feed refresh frequency (live in v1.112+)

Every `library_kbart_feed` row now has a `refresh_frequency` column accepting `hourly`, `daily` (default), `weekly`, `monthly`, or a cron-style expression. The scheduler runs daily but only fetches feeds whose `last_fetch_at + frequency_interval` has elapsed.

## 11. Limitations

- **No COUNTER cross-link.** The refresh log doesn't yet correlate KBART feed changes with the COUNTER usage reports.

---

## 9. Standards & References

- NISO RP-9-2014 (KBART Phase II): https://groups.niso.org/higherlogic/ws/public/projects/82/details
- KBART file specification: https://www.niso.org/publications/rp-9-2014-kbart

---

For technical operators, see `docs/reference/kbart-remote-implementation.md`.
