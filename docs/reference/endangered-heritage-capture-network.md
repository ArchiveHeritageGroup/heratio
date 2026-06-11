# Endangered-heritage register and capture-priority list (heratio#1205)

A slice of the North Star "race against loss: endangered-heritage capture network".
It adds an endangered-heritage register and a capture-priority worklist on top of
the existing catalogue, built additively in the non-locked `ahg-semantic-search`
package (the same package that hosts displaced-heritage and discoveries). Epic
heratio#1205 stays open; this is one slice toward it.

## What it does

- Curators FLAG catalogue records as at-risk, with a risk category, an urgency, a
  documented reason, and a capture status.
- A capture-PRIORITY worklist (admin) orders flagged items most-urgent first by a
  simple, legible priority score.
- A PUBLIC, read-only "at risk" register (`/at-risk`) shows published items still
  awaiting capture, most-urgent first, framing why heritage is endangered and the
  race to capture it.

## Data model

One additive table, `endangered_heritage_item`, auto-created on first boot
(`Schema::hasTable` probe wrapped in a single try/catch, preferring
`database/install_endangered_heritage.sql`, with a Schema-builder fallback):

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `item_ref` | BIGINT | soft reference to `information_object.id`, NO foreign key |
| `risk_category` | VARCHAR(64) | dropdown value, never ENUM: conflict / climate / decay / funding / displacement / digitisation_gap / other |
| `urgency` | VARCHAR(32) | dropdown value: low / medium / high / critical |
| `reason` | TEXT | documented reason for the flag |
| `capture_status` | VARCHAR(32) | dropdown value: unflagged / flagged / in_progress / captured |
| `flagged_by` | BIGINT | user id, nullable |
| `created_at` / `updated_at` | TIMESTAMP | |

No `ALTER` of any existing table. The only writes are flag insert/update on this
new table. Everything else is read-only over `information_object`,
`information_object_i18n`, `slug`, and `status`, all behind `Schema::hasTable`
guards.

## Priority / urgency model

`EndangeredHeritageService::priorityScore()` is deliberately legible:

- urgency base weight: critical = 1000, high = 100, medium = 10, low = 1; plus
- a +5 bonus when the risk is `digitisation_gap` (no durable surrogate yet); and
- captured / unflagged rows score 0 (they leave the worklist).

The worklist (`priorityList()`) keeps only flagged / in_progress rows, sorts by
score descending, then by id ascending (longest-waiting first within a band).

## Published-records gate (public register)

`publicRegister()` filters the worklist to published, real records only:
`information_object` row exists, id is not the catalogue root (1), and the latest
`status` row with `type_id = 158` (publication status) has `status_id = 160`
(published). Any uncertainty resolves to "not published", so unpublished items are
never surfaced publicly.

## Routes (catch-all-safe)

Admin routes (`auth` + `admin`, in `routes/web.php`, all 2-segment+ so they never
collide with the single-segment `/{slug}` archival-record catch-all):

- `GET  /endangered/priority` - worklist (`endangered.priority`)
- `GET  /endangered/flag` - flag form (`endangered.flag.form`)
- `POST /endangered/flag` - record / update a flag (`endangered.flag`)
- `POST /endangered/{id}/capture-status` - advance capture status (`endangered.capture-status`)

Public route (single-segment, so it is registered in the provider's `register()`
via `callAfterResolving('router')` to bind BEFORE the `/{slug}` catch-all - the same
pattern as `/discoveries` and `/displaced-heritage`):

- `GET /at-risk` - public at-risk register (`endangered.register`)

## Resilience

Every query is `Schema::hasTable`-guarded and wrapped in try/catch. A missing table
or any failure renders the empty-state, never a 500. Each view has a dignified
empty-state. Framing is factual and non-alarmist: a flag is a prioritisation
judgement and the reason for it, never a prediction of certain loss or a claim about
any institution's stewardship. The product is jurisdiction-neutral / international.

## Files

- `packages/ahg-semantic-search/database/install_endangered_heritage.sql`
- `packages/ahg-semantic-search/src/Services/EndangeredHeritageService.php`
- `packages/ahg-semantic-search/src/Controllers/EndangeredHeritageController.php`
- `packages/ahg-semantic-search/resources/views/endangered/{worklist,flag,register}.blade.php`
- `packages/ahg-semantic-search/routes/web.php` (admin routes)
- `packages/ahg-semantic-search/src/Providers/AhgSemanticSearchServiceProvider.php` (public route + boot installer)
- `docs/help/endangered-heritage-capture-priority.md`
