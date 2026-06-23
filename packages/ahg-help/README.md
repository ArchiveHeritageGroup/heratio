# ahg-help

In-app Help Center for Heratio: ingests the `docs/help/*.md` corpus into the
database and serves a searchable, categorised help center inside the application
UI, reachable from every page via the navbar **?** (Help Center) icon.

## Package purpose

Surface Heratio's help corpus (540+ markdown articles under `docs/help/`) as a
first-class in-app experience — browse by category, full-text search, per-article
pages with adjacent-article navigation, plus a system map / system breakdown.

## Status: implemented and live

- **Content:** `help_article` + `help_section` tables, populated from
  `docs/help/*.md` (556 articles ingested as of 2026-06-23).
- **Ingest:** `php artisan ahg:help-ingest` (`IngestAllHelpCommand`) bulk-loads /
  refreshes all articles; `IngestHelpArticleCommand` handles a single file. Run on
  deploy and whenever `docs/help/` changes (per CLAUDE.md `feedback_update_help_and_docs`).
- **Services:** `HelpArticleService` (categories, recently-updated, by-category,
  by-slug, adjacent), `SystemMapService`, `SystemBreakdownService`.
- **Controller/routes** (`HelpController`):
  - `/help` — index (categories + recently updated)
  - `/help/search?q=` — full-text search
  - `/help/category/{category}` — category listing
  - `/help/article/{slug}` — article page (+ prev/next)
  - `/help/system-map`, `/help/system-breakdown`
- **Global surfacing:** the theme navbar (`ahg-theme-b5` `header.blade.php`) links
  the **?** Help Center icon to `/help` on every page.

## Optional future enhancement

- **Page-contextual deep-links:** link individual admin/feature pages directly to
  their most relevant article (vs the global Help Center entry). Tracked under the
  contextual-help portion of #1332. Note: pages are under `.locked-paths`, so this
  is best done via a route-aware theme widget (one injection point) rather than
  editing each page.

## References

- `docs/help/` — the article corpus (markdown source of truth)
- `packages/ahg-help/` — source (Commands / Controllers / Services / Providers)
