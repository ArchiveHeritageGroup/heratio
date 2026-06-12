# Public accessibility statement page (/accessibility-statement)

Heratio ships a public, outward, human-readable **accessibility statement** at
`GET /accessibility-statement`, in the `ahg-core` package. It is the standard
conformance statement every public digital service publishes, following the
W3C model accessibility statement structure, and it is international /
jurisdiction-neutral.

## What it is vs the two internal tools

- `/accessibility-statement` (PUBLIC) - the outward commitment + conformance
  claim + how to report a barrier. This page.
- `/admin/accessibility` (admin) - heuristic metadata *coverage report*. Measures
  how much published content carries each accessibility signal. NOT this page.
- `/admin/alt-text` (admin) - *curation worklist* where cataloguers author image
  alt text into the `image_alt_text` side table. NOT this page.

The public statement is the outward face of that internal work.

## Files (all additive, under packages/ahg-core)

- `src/Controllers/AccessibilityStatementController.php` - `index()` renders the
  page; falls back to an all-defaults statement on any failure (never 500s).
- `src/Services/AccessibilityStatementService.php` - assembles the payload;
  reads `ahg_settings` with neutral defaults; feature list gated on routes;
  honest limitations; guarded throughout (never throws). Read-only.
- `resources/views/accessibility-statement/index.blade.php` - Bootstrap 5 + the
  central `theme::layouts.1col` layout; six W3C-model sections.
- `routes/web.php` - one new line registering the route.

NOTE: this is a NEW directory `accessibility-statement/`, separate from the
just-shipped `accessibility/` (coverage report) and `alt-text/` (curation) view
directories - those slices' files are not touched.

## Route registration (catch-all safe)

```php
Route::get('/accessibility-statement', [AccessibilityStatementController::class, 'index'])
    ->name('accessibility.statement');
```

It is a SINGLE-segment public path. Catch-all safety relies on the **same proven
mechanism** as the existing `/explore`, `/open-data`, and `/collection-overview`
public hubs: `ahg-core` boots before `ahg-information-object-manage`, so this
route is registered before the single-segment `/{slug}` archival-record catch-all
(`InformationObjectController@show`) and **first-registered route wins**. The IO
catch-all's negative-lookahead exclusion list is belt-and-braces only and is NOT
relied on here (neither `explore` nor `open-data` appear in that list, yet both
work). A normal record slug still resolves because the catch-all only matches a
single-segment path no earlier route has claimed.

The IO catch-all lives at `packages/ahg-information-object-manage/routes/web.php`
(`Route::get('/{slug}', ... ->where('slug', '^(?!...)[a-z0-9][a-z0-9-]*$')`). That
package is LOCKED and was deliberately NOT modified - the boot-order mechanism does
not need it.

## Configurable via ahg_settings (no new table)

Read by `AccessibilityStatementService` via `AhgSettingsService::get(key, default)`:

| Key | Default |
|---|---|
| `accessibility_institution_name` | "This institution" |
| `accessibility_contact_email` | `accessibility@your-site.example` |
| `accessibility_contact_url` | "" (link hidden when blank) |
| `accessibility_conformance_level` | "Partially conformant, level AA targeted" |
| `accessibility_wcag_version` | "2.2" |
| `accessibility_statement_date` | deploy date (file mtime; never a fabricated legal date) |
| `accessibility_response_days` | 10 (clamped 1..30) |

## Conformance framing

WCAG 2.2 is the primary recognised baseline. EN 301 549 is named as ONE
recognised harmonised standard that references WCAG, explicitly as an example,
never as the sole or governing legal regime. No single country's law is framed
as the rule. Default conformance label: "Partially conformant, level AA targeted".

## Honest known limitations (shown, never hidden)

1. Legacy scanned material without full searchable text / complete text
   alternative.
2. Third-party deep-zoom image viewers and 3D / point-cloud viewers may not be
   fully keyboard / screen-reader operable; a non-visual alternative is provided
   where possible.
3. User-contributed content (comments, tags, uploaded descriptions) may not meet
   the same standard.
4. Older embedded / downloadable documents (e.g. PDFs) may be untagged; an
   accessible version is offered on request.

## Constraints honoured

Read-only (no INSERT/UPDATE/DELETE/ALTER); no new table (ahg_settings reused);
no locked file touched; AHG/Plain Sailing/AGPL headers with @copyright "Plain
Sailing Information Systems"; no em-dashes; `url()`/`route()` (no hardcoded host);
Bootstrap 5 + central theme; guards so the page never 500s.
