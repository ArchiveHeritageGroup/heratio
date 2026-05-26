# Issue #675 Phase 3 - locale-aware formatting + per-user locale

Shipped in v1.98.0. Adds the date/number/currency surface and the per-user default-locale storage that Phase 1+2 deliberately deferred. RTL layout polish and docs/email translation stay open for Phase 4.

## What changed

### 1. Per-user preferred locale

- Schema: `user.preferred_locale CHAR(8) DEFAULT NULL` (migration `2026_05_26_000000_add_preferred_locale_to_user_table.php`). Idempotent - skips on `user.preferred_locale` already present from the email Phase 2 migration (`2026_05_25_020000_create_email_phase2_tables.php`), which introduces the same column as VARCHAR(10) for `LocaleAwareMailable`.
- Profile edit page (`packages/ahg-user-manage/resources/views/edit.blade.php`): new "Preferred language" accordion section with a dropdown sourced from the existing `availableLanguages` whitelist (DB `setting.i18n_languages`). Empty option = "Use site default / browser preference".
- Controller (`UserController::store/update`): validation `regex:/^[A-Za-z]{2}(_[A-Za-z]{2})?$/`, blank-string coerced to null.
- Service (`UserService::create/update/getById`): writes the column when the schema has it; passes through `null` to clear.
- Resolution order in `SetLocale` middleware: `URL ?sf_culture` -> session -> cookie -> **`Auth::user()->preferred_locale` (NEW)** -> Accept-Language -> `config('app.locale')`. User preference sits below the in-session signals so a deliberate click on the language switcher still wins for that visit, but the saved preference becomes the per-account default once the in-session signals clear.

### 2. Locale-aware formatting helpers

`app/Helpers/i18n.php` exposes six globals, autoloaded via `composer.json` `autoload.files`:

| Helper | Purpose | en + sample input | af + sample input |
|---|---|---|---|
| `ahg_date($v, 'medium')` | Date only | `Aug 2, 2026` | `02 Aug. 2026` |
| `ahg_datetime($v, 'medium')` | Date + short time | `Aug 2, 2026, 12:34 PM` | `02 Aug. 2026 12:34` |
| `ahg_time($v, 'short')` | Time only | `12:34 PM` | `12:34` |
| `ahg_number($v, ?$decimals)` | Locale decimal grouping | `1,234,567.89` | `1 234 567,89` |
| `ahg_currency($v, $iso=null)` | Currency with ISO/symbol | `ZAR 1,234.50` | `R 1 234,50` |
| `ahg_percent($v, $decimals=1)` | Fraction -> "%" | `12.5%` | `12,5%` |

All helpers accept:

- Dates: `\DateTimeInterface`, ISO/parseable string, Unix timestamp (int or string of digits), `null`/`''` (returns `''`).
- Numbers: `int`, `float`, numeric string, `null`/`''` (returns `''`).
- Format keyword (dates only): `short` | `medium` (default) | `long` | `full`. `ahg_datetime` always pairs the date level with `SHORT` time so user-facing surfaces stay compact - reach for `ahg_time($v, 'medium')` if you need seconds precision (audit views).

Currency code defaults to `config('app.currency', 'ZAR')`. Set `APP_CURRENCY` in `.env` for non-ZA tenants.

### 3. Blade directives

`app/Providers/I18nFormattingServiceProvider.php` registers `@ahgDate`, `@ahgDateTime`, `@ahgTime`, `@ahgNumber`, `@ahgCurrency`, `@ahgPercent`. Each compiles to `echo e(ahg_<name>(...))`. Registered in `bootstrap/providers.php`.

The directives would normally live next to the existing `AhgTranslationServiceProvider`, but `packages/ahg-translation/` is in `.locked-paths`; hosting them in `app/Providers/` keeps Phase 3 inside the unlocked surface and means the formatting layer still loads in a stripped-down API-only deployment that omits the translation package.

### 4. ICU notes (ext-intl)

- ICU 73+ inserts U+202F NARROW NO-BREAK SPACE between digits and AM/PM in `en` time output. Renders identically to a regular space but behaves like a non-breaking separator. Tests assert against the literal codepoint.
- `af` CLDR uses U+00A0 NO-BREAK SPACE for thousands grouping and a comma for the decimal mark. Same surface as `nl`, `fr`, most of continental Europe.
- `en + ZAR` renders the ISO code (`ZAR 1,234.50`) because `en` CLDR has no localised symbol for the Rand. `af + ZAR` renders `R 1 234,50` (localised symbol).

### 5. Graceful fallback when ext-intl is missing

Every helper checks `extension_loaded('intl')` once (statically cached) and falls back to `Y-m-d` / `Y-m-d H:i` / `H:i` / `number_format()` / `"$code ".number_format($v, 2)` / `(value*100)."%"`. Pages still render; they just lose locale-specific number formatting and date display. Production hosts ship with ext-intl as standard - this is a CI-scaffolding / minimal-Docker safety net.

## Test plan

- `php artisan test --filter=I18nHelpersTest` (12 tests, 13 assertions, all ICU output assertions exact).
- Profile edit: pick "Afrikaans", save, log out + in, confirm UI renders in Afrikaans on the next request.
- Override: with `preferred_locale=af`, append `?sf_culture=en` to any URL - middleware should switch to English for that session.
- Anonymous request with `Accept-Language: ar;q=1.0` still picks `ar` (Phase 1 path unchanged).
- Helpers in Blade: drop `@ahgCurrency($total, 'ZAR')` into a view, flip the active locale via the nav switcher, confirm the output flips between `ZAR 1,234.50` and `R 1 234,50`.

## Out of scope (Phase 4)

- RTL layout polish (Bootstrap RTL CSS bundle for `ar`/`he`/`fa`/`ur`)
- Docs / email translation pipeline (the `lang/*.json` UI strings are already wired; Phase 4 covers the docs/help content + outbound mail bodies)
- Per-tenant default locale (different from per-user; would live on `ahg_tenant`)

## Migration / rollback

```bash
php artisan migrate --path=database/migrations/2026_05_26_000000_add_preferred_locale_to_user_table.php
# rollback is a deliberate no-op - the email Phase 2 migration owns the column drop
```
