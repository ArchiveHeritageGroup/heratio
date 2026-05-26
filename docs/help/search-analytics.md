---
slug: search-analytics
title: Search analytics
section: Administration
---

# Search analytics

The **Search analytics** dashboard (Admin -> Search -> Analytics, or
`/admin/search/analytics`) shows what people are searching for in your
Heratio instance and which queries are coming up empty.

## What you see

- **Totals strip** - total searches, unique queries, zero-result
  searches and overall click-through rate (CTR) for the selected
  window (default 30 days).
- **Top queries** - the 20 most-run queries by execution count,
  with per-query clicks, CTR, average result count and last-seen
  timestamp.
- **Zero-result queries** - the 20 most-run queries that returned
  no hits. This is your content-gap and synonym-dictionary backlog.

A small re-run icon next to each row jumps straight to the search
page with that query pre-filled.

## Window

The single control on the page is **Window (days)** - between 1 and
365. Refresh recomputes all four panels from `ahg_search_query_log`.

## Click-through rate

CTR is computed as

```
clicks(query) / executions(query)
```

A click counts when a user opens a result from a search-result list.
Multiple clicks per execution are de-duplicated to the latest
position the user selected.

## Privacy

- **Logged-in searchers** are recorded against their user ID.
- **Anonymous searchers** are recorded against a SHA-256 hash of
  their IP. The raw IP is never stored.
- The query string itself is stored verbatim (truncated to 512
  characters). Treat the table as personally-identifiable data if
  your jurisdiction requires it.

## Retention

There is no automatic purge of `ahg_search_query_log`. Pick a
retention window that fits your local rules and schedule the
appropriate `DELETE` via your DBA's normal data-lifecycle path.

## Where the data comes from

Every call to `/search` and `/search/advanced` writes a row. The
front-end calls `/search/track-click` when a user opens a result so
the click-position column can be filled in.

If the table is empty, either the search controller has not yet been
hit since the table was created, or the database insert is silently
failing - check `storage/logs/laravel-<date>.log` for any
`SearchAnalyticsService::recordQuery failed` debug lines.
