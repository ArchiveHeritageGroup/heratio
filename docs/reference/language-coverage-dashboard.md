# Language Coverage dashboard + on-demand metadata translation (heratio#1211)

**Summary.** A public, read-only LANGUAGE-COVERAGE dashboard at `GET /language-coverage` plus an
on-demand metadata-translation endpoint at `POST /language-coverage/translate`, both in the
non-locked `ahg-core` package. The dashboard shows, per language, how much of the PUBLISHED
catalogue can be read (descriptions, authority records, vocabulary terms) as counts + simple CSS
bars, framed as a "help us reach more readers" invitation. The translate endpoint machine-translates
one published record's key metadata into a target language via the SANCTIONED AHG gateway, always
labelled "machine translation via the AHG gateway - not an official translation", with the original
kept authoritative. This is the NEXT slice of the #1211 north-star ("every museum for everyone -
universal multilingual access"); epic #1211 stays OPEN. It builds in front of the earlier per-record
translate surface (`/record/{idOrSlug}/translate`, `MultilingualController` / `MultilingualRecordService`).

## Package and files

Chosen package: **`packages/ahg-core`** (NOT locked - only specific `ahg-core` files are in
`.locked-paths`: VoiceController, TtsController, SectorIdentifierService, IiifController, and two
clipboard/action blades. None of the new or edited files touch those). `ahg-display` (the obvious
home) is fully locked, so it was avoided.

- `src/Services/LanguageCoverageService.php` - read-only aggregator (new).
- `src/Controllers/LanguageCoverageController.php` - dashboard `index()` + `translate()` (new).
- `resources/views/language-coverage/index.blade.php` - dashboard view (new). Reuses the existing
  `collection-overview/_breakdown.blade.php` partial for the bar cards.
- `routes/web.php` - two routes added (edited).
- `src/Controllers/ExploreController.php` - added a "Languages of this collection" card, Route::has-gated (edited).

## Coverage aggregates (all cheap grouped COUNTs, no per-record loops)

Published = `status` row with `type_id=158` AND `status_id=160`, `object_id > 1` (root excluded).

- **Descriptions by language**: `information_object_i18n` joined to the published-id subquery,
  `GROUP BY culture`, `SUM(title<>'')` as `titled` and `SUM(scope_and_content<>'')` as `described`.
  pct is of total published. Richest (most titled) first.
- **Actors by language**: `actor_i18n GROUP BY culture` where `authorized_form_of_name` is non-blank;
  pct relative to the best-covered language.
- **Terms by language**: `term_i18n GROUP BY culture` where `name` is non-blank; pct relative to the
  best-covered language.
- **Headline**: total published, distinct language count, primary language (top description culture)
  and its pct.

Culture codes are normalised to the lower-cased base subtag (`pt_BR` -> `pt`). Per the project rule
(feedback_af_before_nl) the `af` row is ordered immediately before `nl` in every breakdown when both
are present (presentation-only reorder; counts unchanged).

Live snapshot at build time (this instance): published descriptions with titles - **en=377, af=7,
fr=2, ne=1, nl=1, pt=1**. Actor names - en dominant (389). Terms - en=738 plus several full ~322-row
translation sets (bs, pt_BR, cy, mk, hr, fr, es).

## Gateway translation path (mandatory)

`LanguageCoverageController::translate()` delegates to `MultilingualRecordService::translate()`, which
calls `AhgAiServices\Services\LlmService::translate()`. That client routes through the AHG AI gateway
at `https://ai.theahg.co.za/ai/v1` (cloud-mode / mt_endpoint dispatch). **No direct GPU node port is
ever used** (never `:11434`, `:5004`, `:5006`, `:8011`). Result is cached on (object, lang,
source-text-hash) for 30 days. The controller stamps a mandatory disclaimer on every response:

> Machine translation via the AHG gateway - not an official translation. The original text remains authoritative.

`is_translated=false` (with `provider='original'`) when a field could not be translated, so the UI
falls back to the original. The JSON contract: `{lang, language, source, provider, authoritative,
notice, fields[], disclaimer, ai_available}`.

## Routing and catch-all safety

- `GET /language-coverage` is single-segment, like `/explore` and `/collection-overview`. `ahg-core`
  boots early, so it is registered BEFORE the single-segment `/{slug}` archival-record catch-all in
  `ahg-information-object-manage` and wins (first-registered route wins).
- `POST /language-coverage/translate` is multi-segment, so it can never be captured as a `/{slug}`
  value either way.
- Route names: `language-coverage.index`, `language-coverage.translate`.

## Safety properties

- **Read-only**: no INSERT/UPDATE/DELETE/ALTER anywhere. SELECT-only aggregates.
- **Resilient**: every query is `Schema::hasTable`-guarded and wrapped in try/catch; failures log a
  warning and return empty, never a 500. Empty/zero collection renders a calm "still being catalogued"
  empty-state.
- **Publication gate**: the translate endpoint 404s a draft for anonymous visitors (no leak).
- **No locked path touched**: `./bin/check-locked` exits 0.
- **International**: jurisdiction-neutral copy, Afrikaans first-class (leads Dutch).
