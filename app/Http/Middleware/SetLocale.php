<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Resolution order (#675 Phase 1+2+3):
        //   1. URL param        ?sf_culture=xx  (explicit override, also seeds cookie)
        //   2. Session           session('locale')
        //   3. Cookie            request->cookie('locale')  (365-day persistence)
        //   4. User preference   Auth::user()->preferred_locale  (#675 Phase 3)
        //   5. Accept-Language   HTTP header                (#675 Phase 1)
        //   6. App default       config('app.locale')       (Laravel's existing fallback)
        //
        // User preference (step 4) deliberately sits BELOW URL/session/cookie:
        // those three express in-session intent ("I just clicked the language
        // switcher / I have a sticky cookie from a previous visit"). The user
        // preference is the durable per-account default - it kicks in when
        // none of the in-session signals are set, which is typically the very
        // first request after a fresh login on a new device.
        $culture = $request->query('sf_culture');

        if ($culture && $this->isValidCulture($culture)) {
            App::setLocale($culture);
            session(['locale' => $culture]);
            // Year-long cookie so URL-driven switches persist across sessions
            // (the POST /set-locale route does the same; without this, language
            // switches via the nav-bar menu only last as long as the session).
            Cookie::queue('locale', $culture, 60 * 24 * 365, '/', null, true, false, false, 'lax');
        } elseif ($sessionLocale = session('locale')) {
            App::setLocale($sessionLocale);
        } elseif ($cookieLocale = $request->cookie('locale')) {
            if ($this->isValidCulture($cookieLocale)) {
                App::setLocale($cookieLocale);
                session(['locale' => $cookieLocale]);
            }
        } elseif ($userLocale = $this->resolveFromAuthenticatedUser()) {
            App::setLocale($userLocale);
            session(['locale' => $userLocale]);
        } elseif ($acceptLocale = $this->resolveFromAcceptLanguage($request)) {
            // #675 Phase 1: only triggers when URL/session/cookie ALL missing —
            // i.e. first-time anonymous visitor. We DON'T persist via cookie
            // here; cookies are reserved for explicit user choices so a Chrome
            // sending Accept-Language: de doesn't lock the device into German
            // forever. The user's explicit menu switch (which goes through the
            // ?sf_culture path) still wins on the next request.
            App::setLocale($acceptLocale);
            session(['locale' => $acceptLocale]);
        }

        // Hydrate ui_label overrides AFTER the locale is set so config('app.ui_label_*')
        // and __('Archival description') etc. flip per-culture. This runs every
        // request because App::setLocale changes the locale dynamically — the
        // booted-once hydrator in AhgCoreServiceProvider can't see the request culture.
        $this->hydrateUiLabels(App::getLocale());

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // #675 Phase 2: advertise the active locale to clients/proxies/CDNs
        // (RFC 7231 §3.1.3.2). Set on every response so caches key correctly
        // and screen readers announce content with the right pronunciation.
        $response->headers->set('Content-Language', App::getLocale());

        return $response;
    }

    /**
     * Resolve the authenticated user's saved `preferred_locale`, falling
     * through silently when:
     *   - no user is logged in,
     *   - the `user` table has no `preferred_locale` column (fresh install
     *     before the #675 Phase 3 migration runs), or
     *   - the saved value isn't in the supported / enabled set (operator may
     *     have disabled a locale since the user picked it).
     *
     * The lookup uses Auth::id() + a single column query against the `user`
     * table rather than reading $authUser->preferred_locale directly because
     * AhgCore\Models\User extends Actor and does not auto-include the
     * Phase-3 column in $appends. A 1-column DB::table hit is cheap and
     * avoids forcing the model layer to know about this attribute.
     *
     * @internal #675 Phase 3
     */
    private function resolveFromAuthenticatedUser(): ?string
    {
        try {
            if (! Auth::check()) {
                return null;
            }
            $id = Auth::id();
            if (! $id) {
                return null;
            }
            if (! Schema::hasTable('user') || ! Schema::hasColumn('user', 'preferred_locale')) {
                return null;
            }
            $value = DB::table('user')->where('id', $id)->value('preferred_locale');
            if (! is_string($value) || $value === '') {
                return null;
            }
            if (! $this->isValidCulture($value)) {
                return null;
            }

            return $value;
        } catch (\Throwable $e) {
            // Auth facade not bound (CLI), DB unavailable (boot-time middleware
            // dry-run), etc - silently fall through to the next resolution step.
            return null;
        }
    }

    /**
     * Parse the Accept-Language header and return the highest-Q supported
     * locale, or null when no supported locale matches.
     *
     * Handles RFC 7231 Q-values:
     *   Accept-Language: fr-CA,fr;q=0.9,en;q=0.8
     * Picks fr-CA (implicit q=1.0); if fr-CA isn't supported, falls back to
     * fr (q=0.9), then en (q=0.8). Region-tagged codes (fr-CA → fr_CA) and
     * 2-letter prefixes (fr-CA → fr) are both probed against the supported
     * set. Matching is case-insensitive on the locale code.
     *
     * @internal #675 Phase 1
     */
    private function resolveFromAcceptLanguage(Request $request): ?string
    {
        $header = (string) $request->header('Accept-Language', '');
        if ($header === '') {
            return null;
        }

        $supported = $this->getSupportedLocales();
        if (empty($supported)) {
            return null;
        }

        // Build a lower-cased lookup keyed on bare locale codes so we can
        // match "fr-CA" against "fr_CA" or "fr".
        $supportedLower = [];
        foreach ($supported as $code) {
            $supportedLower[strtolower((string) $code)] = $code;
        }

        $parsed = [];
        foreach (explode(',', $header) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            $parts = array_map('trim', explode(';', $token));
            $tag = strtolower(array_shift($parts));
            if ($tag === '' || $tag === '*') {
                continue;
            }
            $q = 1.0;
            foreach ($parts as $param) {
                if (stripos($param, 'q=') === 0) {
                    $candidate = (float) substr($param, 2);
                    if ($candidate >= 0.0 && $candidate <= 1.0) {
                        $q = $candidate;
                    }
                }
            }
            if ($q <= 0.0) {
                continue;
            }
            // Preserve original order for stable sort on tied Q-values.
            $parsed[] = ['tag' => $tag, 'q' => $q, 'order' => count($parsed)];
        }

        if (empty($parsed)) {
            return null;
        }

        usort($parsed, function ($a, $b) {
            if ($a['q'] === $b['q']) {
                return $a['order'] <=> $b['order'];
            }

            return $b['q'] <=> $a['q'];
        });

        foreach ($parsed as $entry) {
            $tag = $entry['tag'];

            // Direct hit ("fr" → fr, "ar" → ar).
            if (isset($supportedLower[$tag])) {
                $code = $supportedLower[$tag];
                if ($this->isValidCulture($code)) {
                    return $code;
                }
            }

            // RFC tag uses "-"; Laravel/AtoM tags use "_" for region.
            $underscored = str_replace('-', '_', $tag);
            if ($underscored !== $tag && isset($supportedLower[$underscored])) {
                $code = $supportedLower[$underscored];
                if ($this->isValidCulture($code)) {
                    return $code;
                }
            }

            // Region-tag fallback: "fr-CA" → "fr".
            $dash = strpos($tag, '-');
            if ($dash !== false) {
                $primary = substr($tag, 0, $dash);
                if (isset($supportedLower[$primary])) {
                    $code = $supportedLower[$primary];
                    if ($this->isValidCulture($code)) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Discover the set of locales this Heratio instance speaks. Cached on
     * the middleware instance so a single request only pays the lookup cost
     * once even if handle() is invoked multiple times in tests.
     *
     * Source priority:
     *   1. config('app.supported_locales') if the operator has populated it
     *   2. enabled `setting` rows with scope=i18n_languages (matches isValidCulture)
     *   3. lang/*.json directory listing (final fallback)
     *
     * @internal #675 Phase 1
     *
     * @return array<int,string>
     */
    private function getSupportedLocales(): array
    {
        if ($this->cachedSupportedLocales !== null) {
            return $this->cachedSupportedLocales;
        }

        // 1. Explicit config-supplied list (if any operator added it).
        $configured = config('app.supported_locales');
        if (is_array($configured) && ! empty($configured)) {
            $configured = array_values(array_filter(array_map('strval', $configured)));
            if (! empty($configured)) {
                return $this->cachedSupportedLocales = $configured;
            }
        }

        // 2. DB-managed enabled languages (same source used by isValidCulture()).
        try {
            if (Schema::hasTable('setting')) {
                $enabled = DB::table('setting')
                    ->where('scope', 'i18n_languages')
                    ->where('editable', 1)
                    ->pluck('name')
                    ->all();
                if (! empty($enabled)) {
                    return $this->cachedSupportedLocales = array_values(array_map('strval', $enabled));
                }
            }
        } catch (\Throwable $e) {
            // Boot-time, missing tables, etc — fall through to lang/ scan.
        }

        // 3. lang/*.json fallback (single source-of-truth for a fresh install
        //    where no DB or config has been seeded yet).
        $locales = [];
        $langDir = base_path('lang');
        if (is_dir($langDir)) {
            foreach (glob($langDir.'/*.json') ?: [] as $path) {
                $name = basename($path, '.json');
                if ($name === '' || $name[0] === '_' || $name[0] === '.') {
                    continue; // skip _meta.json, .lock siblings, etc.
                }
                $locales[] = $name;
            }
        }

        return $this->cachedSupportedLocales = $locales;
    }

    /** @var array<int,string>|null cached per-request supported-locale list */
    private ?array $cachedSupportedLocales = null;

    /** Mirrors the boot-time hydrator in AhgCoreServiceProvider but is keyed
     *  on the just-resolved request culture. Cheap (one query per request). */
    protected function hydrateUiLabels(string $culture): void
    {
        try {
            if (! Schema::hasTable('setting') || ! Schema::hasTable('setting_i18n')) {
                return;
            }
            $fallback = config('app.fallback_locale', 'en');
            $rows = DB::table('setting as s')
                ->leftJoin('setting_i18n as si', function ($j) use ($culture) {
                    $j->on('s.id', '=', 'si.id')->where('si.culture', '=', $culture);
                })
                ->leftJoin('setting_i18n as si_fb', function ($j) use ($fallback) {
                    $j->on('s.id', '=', 'si_fb.id')->where('si_fb.culture', '=', $fallback);
                })
                ->where('s.scope', 'ui_label')
                ->select('s.name', 'si.value as cur', 'si_fb.value as fb')
                ->get();
            $translatorOverrides = [];
            foreach ($rows as $r) {
                $raw = ($r->cur !== null && $r->cur !== '') ? $r->cur : $r->fb;
                // Decode JSON culture-map values (AtoM-style) and pick this culture,
                // so the raw i18n JSON never leaks into titles/headings.
                $val = \AhgCore\Services\SettingHelper::pickI18nLabel($raw, $culture, $fallback);
                if ($val === '') {
                    continue;
                }
                config(["app.ui_label_{$r->name}" => $val]);
                $en = \AhgCore\Services\SettingHelper::pickI18nLabel($r->fb, $fallback, $fallback);
                if ($en !== '' && $val !== $en) {
                    $translatorOverrides[$en] = $val;
                }
            }
            if (! empty($translatorOverrides)) {
                app('translator')->addLines($translatorOverrides, $culture, '*');
            }
        } catch (\Throwable $e) {
            // Swallow — boot-time hydrator already covered the default locale.
        }
    }

    protected function isValidCulture(string $culture): bool
    {
        // Validate against enabled languages in the DB
        if (Schema::hasTable('setting')) {
            $enabled = DB::table('setting')
                ->where('scope', 'i18n_languages')
                ->where('editable', 1)
                ->pluck('name')
                ->toArray();

            if (! empty($enabled)) {
                return in_array($culture, $enabled);
            }
        }

        // Fallback: accept any 2-5 char code
        return (bool) preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $culture);
    }
}
