# Locale Resolution & Content-Language (Issue #675)

How Heratio decides which language to render for each incoming request, and how that choice is advertised back to the client.

## Resolution chain (in order)

1. **URL parameter `?sf_culture=xx`** — admin-set explicit override. Matches AtoM's legacy switch. Also persists a 365-day `locale` cookie so the choice survives logout / new sessions.
2. **Session `locale`** — the locale carried by the current PHP session.
3. **Cookie `locale`** — 365-day persistence cookie set by either the `?sf_culture` URL flow or the `POST /set-locale` route.
4. **`Accept-Language` HTTP header** *(new — Phase 1)* — only consulted when **no** URL param, **no** session locale, and **no** cookie are present (i.e. a first-time anonymous visitor). The header is parsed for RFC 7231 Q-values (`fr-CA,fr;q=0.9,en;q=0.8`) and the highest-priority **supported** locale wins. Region tags are tolerated: `fr-CA` maps to `fr_CA` if available, otherwise falls back to `fr`. The chosen locale lives in the session for that visit but is **not** written to a cookie — cookies remain reserved for explicit user choices.
5. **App default** — `config('app.locale')` (Laravel's existing fallback, `APP_LOCALE` env, defaults to `en`).

The supported-locale set is discovered (in order): `config('app.supported_locales')` if set, then DB `setting` rows with `scope=i18n_languages AND editable=1`, then the `lang/*.json` directory listing.

## Response advertisements

Every HTML response now carries:

- **`Content-Language: {locale}`** HTTP header — RFC 7231 §3.1.3.2. Lets CDNs/proxies key caches correctly and helps assistive tech.
- **`<html lang="{locale}" dir="{ltr|rtl}">`** — RTL is auto-selected for `ar`, `he`, `fa`, `ur`.

## How to test

```bash
# 1. First-time visitor with French preference — should get fr
curl -sk -I -H "Accept-Language: fr;q=1.0, en;q=0.5" https://heratio.theahg.co.za/ \
  | grep -i 'content-language'

# 2. Arabic first-time visitor — html lang + dir flip
curl -sk -H "Accept-Language: ar;q=1.0" https://heratio.theahg.co.za/ \
  | grep -i '<html'

# 3. Cookie should still win over Accept-Language
curl -sk -I -b 'locale=de' -H "Accept-Language: fr;q=1.0" \
  https://heratio.theahg.co.za/ | grep -i 'content-language'
```

## Out of scope (tracked separately)

Per-element `xml:lang` on multilingual fields (info-object / actor show pages), locale-aware date/number/currency formatting, URL-path-based locale routing (`/fr/...`), per-user default, TMX/XLIFF round-trip, Crowdin sync.
