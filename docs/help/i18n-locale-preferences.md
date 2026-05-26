# Language and locale preferences

Heratio renders dates, numbers, currency, and the user interface in whatever language you've chosen for your account. This guide covers where to set the preference and how Heratio decides which language to show.

## Setting your preferred language

1. Open your profile: top-right user menu -> **Edit profile**.
2. Scroll to the **Preferred language** section.
3. Pick a language from the dropdown. The list shows every language an administrator has enabled for this Heratio instance (Admin -> Settings -> I18n).
4. Click **Save**.

The next request from your browser uses your saved language. To clear the preference and fall back to the default chain (URL parameter -> browser preference), pick the blank **Use site default / browser preference** option and save.

## How Heratio decides which language to render

For every request, Heratio walks this list in order and uses the first signal it finds:

1. **URL parameter** - any `?sf_culture=xx` in the URL wins. Useful for sharing a link in a specific language.
2. **Session** - the language you're currently using in this browser session.
3. **Cookie** - a 365-day cookie set when you explicitly switch language via the nav-bar menu or the URL parameter above.
4. **Your preferred language** - the dropdown on your profile (this article).
5. **Browser `Accept-Language` header** - the language your browser tells Heratio you prefer.
6. **Site default** - the operator's `APP_LOCALE` setting (usually English).

That order means clicking the language switcher in the nav bar still wins for the current session - your saved preference is the per-account default that kicks in when none of the in-session signals are set (typically your first request after a fresh login on a new device).

## What "locale" affects

- **Interface labels and menu text** - sourced from `lang/*.json` plus the operator's UI string overrides.
- **Dates** - `Aug 2, 2026` in English, `02 Aug. 2026` in Afrikaans, `02.08.2026` in German, etc.
- **Numbers and currency** - English: `1,234,567.89` and `ZAR 1,234.50`. Afrikaans: `1 234 567,89` and `R 1 234,50`.
- **Page direction** - Arabic, Hebrew, Persian, and Urdu render right-to-left when one of those locales is active. Bootstrap RTL styling polish is tracked separately.
- **Outgoing email** - if a Mailable knows your preferred language, it picks the matching per-locale view.

## What "locale" does NOT change (today)

- **Multilingual archival content** - descriptions, scope notes, biographical histories, and other ISAD/ISDIAH fields are stored per-culture. Heratio shows whichever cultures the operator has authored - your preferred language only affects the chrome.
- **Search index language** - Elasticsearch indexes per culture; your preferred language affects which culture's index is queried by default.

## Operator notes

- Enable additional languages: **Admin -> Settings -> I18n -> Languages enabled** (DB table `setting`, scope `i18n_languages`). Only enabled languages show up in the profile dropdown and in the nav-bar switcher.
- The site default is `APP_LOCALE` in `.env`. The default currency is `APP_CURRENCY` (defaults to ZAR).
- Per-user preferences live on `user.preferred_locale` (CHAR(8)). The column is populated by the **Preferred language** dropdown and read by both the `SetLocale` middleware (web requests) and the `LocaleAwareMailable` trait (outgoing email).
- ICU's CLDR data drives all date / number / currency rendering. Reach for the `ahg_date()`, `ahg_datetime()`, `ahg_time()`, `ahg_number()`, `ahg_currency()`, and `ahg_percent()` helpers (or the matching `@ahgDate`, `@ahgDateTime`, etc. Blade directives) when rendering any of these values from custom views.

## See also

- **Locale Resolution and Content-Language** (`docs/help/i18n-locale-resolution.md`) - the URL/session/cookie/Accept-Language fallback chain in detail.
- **Translation user guide** (`docs/help/translation-user-guide.md`) - translating archival content into additional cultures.
