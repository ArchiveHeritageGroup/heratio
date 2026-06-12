# Public endangered-heritage dashboard (heratio#1205)

The next slice of the North Star "race against loss". It adds a PUBLIC, read-only
aggregate dashboard over the existing endangered-heritage register, built
additively in the non-locked `ahg-semantic-search` package (the same package that
hosts the at-risk register, displaced-heritage, repatriation and discoveries).
Epic heratio#1205 stays open; this is one slice toward it. It mirrors the
repatriation dashboard slice (`/repatriation` + `.json`) exactly in shape and
routing.

## Routes

- `GET /endangered-heritage` (name `endangered.dashboard`) - HTML dashboard.
- `GET /endangered-heritage.json` (name `endangered.dashboard.json`) - CORS-open
  machine-readable twin.

Both are single-segment public paths bound in
`AhgSemanticSearchServiceProvider::register()` via
`callAfterResolving('router')`, so they bind BEFORE the single-segment `/{slug}`
archival-record catch-all in `ahg-information-object-manage` and win the match.
The `.json` suffix keeps the machine route a distinct path that can never shadow a
slug. See `reference_slug_catchall_route_precedence.md`.

## What it shows

A read-only aggregate VIEW over `endangered_heritage_item` (no new table, no
writes, no ALTER):

- Big numbers: total flagged, still-outstanding, in-progress, captured.
- Capture-progress bar: `captured / (captured + outstanding)` as a percentage;
  unflagged rows are excluded from the denominator (no longer in the race).
- Breakdown by `risk_category` (CSS bars, no charting library).
- Breakdown by `urgency` band.
- A short, bounded tail (default 8) of the highest-priority OUTSTANDING items,
  PUBLISHED only, each linking to its catalogue record, with a link onward to the
  full `/at-risk` register.

## Implementation

- `EndangeredHeritageService::dashboard(int $topPriority = 8)` - new additive
  method. Cheap GROUP BY COUNTs only (`countsBy('risk_category')`,
  `urgencyCounts()`, `statusCounts()`); the priority tail reuses
  `publicRegister()`, which is already publication-gated and urgency-ordered, then
  slices the head. Everything is `Schema::hasTable`-guarded via `available()` and
  degrades to a zeroed (unavailable) shape rather than throwing.
- `EndangeredHeritageDashboardController` - `index()` (HTML) and `json()`
  (CORS-open), both routed through `safeDashboard()` which catches everything and
  returns the zeroed shape, so neither surface ever 500s.
- View `ahg-semantic-search::endangered.dashboard` - Bootstrap 5 + central theme,
  big numbers, CSS progress bars, dignified empty-state ("No items flagged as at
  risk yet"). Factual, non-alarmist, jurisdiction-neutral framing; the standing
  `EndangeredHeritageService::DISCLAIMER` is surfaced prominently.

## Guarantees

- Read-only over every existing table; writes nothing. No new table; no ALTER.
- The highest-priority tail and the JSON only surface PUBLISHED records (the
  publication-status gate in `isPublished()`: status table `type_id = 158`,
  `status_id = 160`). The aggregate counts cover every flag for an accurate
  operational picture, but no unpublished record is ever named publicly.
- Never 500s; renders a calm empty-state when nothing is flagged.
- Catch-all-safe single-segment routing as above.
- The existing register (`/at-risk`), admin worklist (`/endangered/*`) and the
  `endangered_heritage_item` table are untouched.
