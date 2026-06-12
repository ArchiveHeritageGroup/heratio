# Digital accessibility coverage report (ahg-core)

Heratio's accessibility slice (heratio#1211, "every museum for everyone") adds a
read-only DIGITAL ACCESSIBILITY coverage report: a heuristic measure of how much
PUBLISHED content carries the accessibility-relevant metadata Heratio stores. It
is explicitly NOT a WCAG conformance audit; it cites WCAG 2.1 AA success criteria
as an international reference grid. It lives entirely in the non-locked
`ahg-core` package and is additive (no table is created or altered; read-only).

## Components

- **`AhgCore\Services\AccessibilityReportService`** - read-only. `snapshot()`
  returns the framework label/note, `total_published`, the five scored `areas`,
  and an `overall_level` / `overall_level_name`. Every query is
  `Schema::hasTable()` / `Schema::hasColumn()`-guarded and wrapped in its own
  try/catch; on any failure the area degrades to "Not measured" rather than
  throwing. No writes, no ALTER, no AI.

- **`AhgCore\Controllers\AccessibilityReportController`** - `index()` renders
  `ahg-core::accessibility.index`. The service never throws; the controller still
  wraps it and renders an honest empty report on failure (never a 500).

- **View `ahg-core::accessibility.index`** - extends `theme::layouts.1col`
  (central Bootstrap 5 theme). Big numbers + CSS progress bars (no charting lib),
  per-area level badge, evidence, recommendation, an overall summary card, and an
  empty-state. An info banner makes the heuristic-not-conformance framing explicit.

- **Route** - `GET /admin/accessibility` inside the `auth` middleware group in
  `packages/ahg-core/routes/web.php`, named `accessibility.index`. The two-segment
  path keeps it clear of the single-segment `/{slug}` archival-record catch-all
  (same proven pattern as `/admin/data-quality`, `/admin/preservation-maturity`,
  `/admin/fixity`). Unauthenticated requests 302 to `/login`.

## The five areas and how each figure is computed

"Published" = a `status` row with `type_id=158` and `status_id=160`, `object_id>1`
(the synthetic root is excluded). All figures are grouped/aggregate COUNTs over
the published set; there are no per-record loops.

1. **Image alternative text** (WCAG 1.1.1). Heratio has NO dedicated alt-text
   column for image digital objects (`getDigitalObjectAltText()` is a blade
   accessor with no persisted backing column). The closest queryable signal is
   the embedded IPTC/XMP caption in `digital_object_metadata.description`. The
   report counts published image surrogates (`digital_object.mime_type LIKE
   'image/%'` or a known image extension) that join a `digital_object_metadata`
   row with a non-blank `description`, over all published image surrogates. The
   recommendation states that genuine alt text still needs a schema field. If the
   metadata table/column is absent the area reads "Not measured".

2. **Captions and subtitles** (WCAG 1.2.2). Published audio/video surrogates
   (`mime_type LIKE 'audio/%'|'video/%'` or a known AV extension) with `EXISTS`
   an active (`active=1`, guarded) `media_caption_track` row of `track_type IN
   ('caption','subtitle')`, over all published AV surrogates.

3. **Transcripts** (WCAG 1.2.3 / 1.2.5). Published AV surrogates with `EXISTS` a
   `media_transcription` row (non-blank `full_text` when that column exists), over
   all published AV surrogates.

4. **3D model alternative text** (WCAG 1.1.1). Direct measure: published
   `object_3d_model` rows with a non-blank `alt_text`, over all published 3D
   models. This is the one surrogate type with a dedicated alt-text column.

5. **Multilingual access** (WCAG 3.1.1 / 3.1.2). Published records whose
   `information_object_i18n` carries a real (non-blank) title in MORE THAN ONE
   distinct culture, over `total_published`. Computed as a grouped subquery
   (`COUNT(DISTINCT culture) > 1` per record) wrapped in an outer COUNT.

## Scoring and honest absence

- Coverage % maps to a level band: >=95 Strong, >=75 Good, >=40 Partial, >0 Low,
  0 None yet. Missing table/column/error -> level -1 "Not measured".
- **Overall level** = the lowest level across the MEASURED areas. Areas that are
  "Not measured" (missing schema) or have zero applicable content (e.g. no
  published AV) are excluded from the overall, not scored as a failure.
- The report never invents coverage. Where a signal cannot be evidenced, the area
  is "Not measured" with a specific gap recommendation.

## Schema findings (probed, not assumed)

- `digital_object` has `checksum`/`mime_type`/`name` but NO caption/alt/subtitle
  column - accessibility metadata lives in sidecar tables.
- `media_caption_track`: `digital_object_id`, `track_type`
  enum(caption|subtitle|description|chapters), `language_code`, `is_sdh`, `active`,
  `vtt_content`.
- `media_transcription`: `digital_object_id`, `object_id`, `language`, `full_text`,
  `vtt_path`/`srt_path`/`txt_path`.
- `digital_object_metadata.description` holds the embedded image caption (the
  image alt-text proxy); there is no dedicated alt-text field for images.
- `object_3d_model.alt_text` is the only dedicated alt-text column in the schema.
- `information_object_i18n.culture` + `.title` drive the multilingual measure.

## Status

Additive, read-only. New files only, all in the non-locked `ahg-core` package
(none of the locked carve-outs: VoiceController, TtsController,
SectorIdentifierService, IiifController, `_action-icons.blade.php`,
`clipboard/index.blade.php`). The epic (#1211) remains open.
