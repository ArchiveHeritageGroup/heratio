<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Resolution order (matches AtoM, extended with #675 Accept-Language fallback):
        //   1. URL param      ?sf_culture=xx  (explicit override, also seeds cookie)
        //   2. Session         session('locale')
        //   3. Cookie          request->cookie('locale')  (365-day persistence)
        //   4. Accept-Language HTTP header     (first-time visitor heuristic, #675 Phase 1)
        //   5. App default     config('app.locale')       (Laravel's existing fallback)
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
     * @return array<int,string>
     */
    private function getSupportedLocales(): array
    {
        if ($this->cachedSupportedLocales !== null) {
            return $this->cachedSupportedLocales;
        }

        // 1. Explicit config-supplied list (if any operator added it).
        $configured = config('app.supported_locales');
        if (is_array($configured) && !empty($configured)) {
            $configured = array_values(array_filter(array_map('strval', $configured)));
            if (!empty($configured)) {
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
                if (!empty($enabled)) {
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
            foreach (glob($langDir . '/*.json') ?: [] as $path) {
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
            if (!Schema::hasTable('setting') || !Schema::hasTable('setting_i18n')) {
                return;
            }
            $fallback = config('app.fallback_locale', 'en');
            $rows = DB::table('setting as s')
                ->leftJoin('setting_i18n as si',    function ($j) use ($culture)  { $j->on('s.id', '=', 'si.id')->where('si.culture', '=', $culture); })
                ->leftJoin('setting_i18n as si_fb', function ($j) use ($fallback) { $j->on('s.id', '=', 'si_fb.id')->where('si_fb.culture', '=', $fallback); })
                ->where('s.scope', 'ui_label')
                ->select('s.name', 'si.value as cur', 'si_fb.value as fb')
                ->get();
            $translatorOverrides = [];
            foreach ($rows as $r) {
                $val = ($r->cur !== null && $r->cur !== '') ? $r->cur : $r->fb;
                $val = $val !== null ? strtr((string) $val, ['&nbsp;' => ' ']) : '';
                if ($val === '') continue;
                config(["app.ui_label_{$r->name}" => $val]);
                $en = $r->fb !== null ? strtr((string) $r->fb, ['&nbsp;' => ' ']) : '';
                if ($en !== '' && $val !== $en) {
                    $translatorOverrides[$en] = $val;
                }
            }
            if (!empty($translatorOverrides)) {
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

            if (!empty($enabled)) {
                return in_array($culture, $enabled);
            }
        }

        // Fallback: accept any 2-5 char code
        return (bool) preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $culture);
    }
}
