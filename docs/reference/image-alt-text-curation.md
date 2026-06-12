# Image alt-text curation (ahg-core)

Heratio's alt-text curation slice (heratio#1211, "every museum for everyone") adds
a human-authored ALTERNATIVE-TEXT store for image digital objects, plus an admin
curation surface on top of it. It directly acts on the gap the digital
accessibility report surfaced: published image surrogates carry essentially no
genuine alt text (the catalogue has no dedicated alt-text column, so the report
could only proxy from the embedded IPTC/XMP caption). It lives entirely in the
non-locked `ahg-core` package and is additive: one NEW side table, no ALTER on any
existing table, and writes confined to the new table.

## Why

WCAG 2.1 - 1.1.1 Non-text Content requires a text alternative for images so
screen-reader users can understand them. Heratio stored no genuine alt text. This
slice gives cataloguers and contributors a real place to author it, lang-aware
(international; Afrikaans is a first-class working language, not a fallback).

## The store

- **Table `image_alt_text`** (auto-installed on boot via the guarded
  `AhgCoreServiceProvider` pattern - `Schema::hasTable()` + `DB::unprepared()` of
  `database/install_image_alt_text.sql`, single outer try/catch). Columns: `id`,
  `digital_object_id` (soft reference, NO FK), `lang VARCHAR(16) default 'en'`,
  `alt_text TEXT`, `contributed_by`, `updated_by`, `created_at`, `updated_at`.
  `UNIQUE(digital_object_id, lang)` so one row per image per language. No ENUM
  column (`lang` is a VARCHAR). `CREATE TABLE IF NOT EXISTS` only; no ALTER.

## Components

- **`AhgCore\Services\AltTextService`** - the only write path is `save()`, which
  upserts one `(digital_object_id, lang)` row in `image_alt_text` (a blank value
  clears the row). It refuses to write for anything that is not a published image
  surrogate. Read methods: `coverage($lang)` (cheap aggregate - genuine curated
  alt text vs total published images), `worklist($lang, $page, $perPage)` (bounded,
  paginated list of published images MISSING a real alt-text entry, each with the
  parent record title + slug + filename + embedded caption to seed from), `one()`
  (single image context for the edit form), and `objectIdsWithAltText()` (the set
  of digital_object ids that now carry real alt text, exposed for the report). Every
  query is `Schema::hasTable()`/`hasColumn()`-guarded and wrapped in try/catch; a
  missing table yields an empty, honest result, never a 500. No AI calls.

- **`AhgCore\Controllers\AltTextController`** - `index()` renders the worklist +
  coverage; `store()` validates and calls `save()`, then redirects back to the
  worklist (preserving `lang` + `page`) with a one-line flash. Auth+admin gated via
  the route group's `auth` middleware.

- **View `ahg-core::alt-text.index`** - extends `theme::layouts.1col` (central
  Bootstrap 5 theme). Coverage card + CSS progress bar, a working-language switch,
  the worklist table with an inline per-row textarea + Save form (CSRF), and
  bounded pagination. Calm empty states for "feature unavailable", "nothing to
  curate", and "no published images". Never a 500.

## Routes

Registered in `packages/ahg-core/routes/web.php` under an `auth` group:

- `GET  /admin/alt-text`       -> `alt-text.index`
- `POST /admin/alt-text/save`  -> `alt-text.save`

Two-segment paths keep them clear of the single-segment `/{slug}` archival-record
catch-all (that route only ever matches ONE path segment).

## Report integration

`AccessibilityReportService::imageAltArea()` now counts a published image as having
a text alternative if EITHER it has a genuine curated row in `image_alt_text` OR -
as a fallback - an embedded IPTC/XMP caption in `digital_object_metadata.description`.
The OR is two `whereExists` legs in a single bounded aggregate, each guarded so an
absent source simply drops out. The evidence string also reports how many of the
"with" carry genuine curated alt text. The proxy logic is retained, not replaced;
the curated store is OR-ed in.

## Constraints honoured

Read-only over existing tables except the boot auto-create and alt-text writes to
the NEW `image_alt_text` table; no ALTER anywhere; bounded queries;
`Schema::hasTable`-guarded + try/catch; lang-aware / international; VARCHAR not
ENUM; no locked file touched (in `ahg-core`, only `VoiceController`, `TtsController`,
`SectorIdentifierService`, `IiifController`, `_action-icons.blade.php`, and
`clipboard/index.blade.php` are locked - none are touched). Alt text is
human-authored; no AI is used in this slice.
