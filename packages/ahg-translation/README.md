# ahg-translation

UI-string translation + locale registry for Heratio. Combines the standard Laravel translator with a database-backed override layer so operators can localise strings without a code redeploy.

## Install

Path-loaded via the root `composer.json`'s `packages/*` repository. Auto-registered by Laravel package discovery.

## Key surfaces

- `AhgTranslation\Translation\DbAwareLoader` - drop-in replacement for Laravel's file loader; checks `ahg_ui_string` first, falls through to file translations
- `AhgTranslation\Services\UiStringService` - CRUD for the DB override table; used by the admin UI
- `AhgTranslation\Helpers\VocabularyOptions` - locale-aware helpers for rendering enumerated values in the active language
- `AhgTranslation\Helpers\TranslationProvenance` - tracks who translated what and when (operator vs MT vs LLM)
- `AhgTranslation\Console\Commands\ImportJsonToDbCommand` - `php artisan translation:import-json` (one-shot import of `lang/<locale>.json` into the DB override table)

## Locale resolution

Works hand-in-hand with the `SetLocale` middleware in `app/Http/Middleware/`. Resolution chain (highest priority first):

1. URL `?sf_culture=` query parameter
2. Session locale
3. Cookie locale
4. Authenticated `user.preferred_locale` (added in heratio#675 Phase 3)
5. Accept-Language header
6. Application default

## Routes

- `GET /admin/translations` - UI-string browse + filter
- `GET /admin/translations/{key}/edit` - per-string edit
- `POST /admin/translations/{key}` - persist override

## Database

Reads + writes `ahg_ui_string` (key, locale, value, provenance, updated_at). Auto-installed via the service-provider Schema::hasTable probe.

## Related packages

- `ahg-core` - the Laravel translator binding registers through here
- `ahg-mt` (if present) - machine-translation suggestions flow into UiStringService writes with provenance='mt'

## Locale-aware formatting

For dates / numbers / currency formatting in the active locale, use the helpers shipped in heratio#675 Phase 3 (`ahg_date()`, `ahg_currency()`, etc.) registered via `app/Helpers/i18n.php`.
