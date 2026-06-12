# Collection data-quality report - technical reference

Summary: an admin, read-only ISAD(G) descriptive-completeness dashboard at `/admin/data-quality` in `packages/ahg-reports`, mirroring the Trust & Transparency console pattern (card builder, Schema::hasTable-guarded aggregate COUNTs, Route::has-gated links, `theme::layouts.2col`). Shipped Heratio v1.142.52.

## What it counts

Over the published catalogue (status type_id=158 status_id=160, root id=1 excluded), one element-completeness row per core ISAD(G) element, each a "missing" count via a LEFT JOIN ... IS NULL / NOT EXISTS existence check (no per-row PHP scan):

| Element | Source |
|---|---|
| Title | `information_object_i18n.title` empty/absent |
| Reference code / identifier | `information_object.identifier` empty |
| Date(s) | no linked `event` carrying a date |
| Creator | no creator `event` linking an actor |
| Scope and content | `information_object_i18n.scope_and_content` empty |
| Extent and medium | `information_object_i18n.extent_and_medium` empty |
| Level of description | `information_object.level_of_description_id` null |
| Repository | `information_object.repository_id` null |

## Completeness score

`score = pct(records carrying ALL core elements / total published)`, rendered as a CSS gauge (no charting library). A "top gaps" list sorts the elements by missing count so the largest gap is surfaced first.

## Properties

- Read-only: SELECT/aggregate only; no writes, no ALTER, no new table.
- Resilient: every metric Schema::hasTable-guarded + try/catch; empty catalogue degrades to a calm empty state, never a 500.
- Admin-gated route; two-segment `/admin/data-quality` (catch-all-safe). Browse-filter links are Route::has-gated so no dead links render.
- International: ISAD(G) framing, no jurisdiction-specific rules.
