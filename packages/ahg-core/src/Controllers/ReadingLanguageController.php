<?php

/**
 * ReadingLanguageController - Heratio ahg-core
 *
 * heratio#1211 - universal multilingual access, preference layer. Lets a visitor
 * set a PREFERRED reading language ONCE and have it remembered, then applied to
 * the record-translation experience (the public /record/{idOrSlug}/translate
 * page) without re-choosing every time.
 *
 * What this surface does (and deliberately does NOT do):
 *   - Stores the chosen language code in a long-lived cookie (1 year) AND the
 *     session, so it survives across requests and is available even before the
 *     cookie round-trips. No database writes; this is an anonymous, per-browser
 *     affordance, not account state.
 *   - Validates the chosen code against MultilingualRecordService's SUPPORTED set
 *     (the same source the picker is built from). An unsupported / hand-edited /
 *     garbage value is IGNORED and the stored preference is CLEARED, so a stale
 *     cookie can never drive a dead gateway call.
 *   - Never changes HOW translation works and never touches the catalogue. It is
 *     purely the "remember my reading language" wrapper around the existing
 *     translate page.
 *
 * Progressive enhancement: the endpoint accepts a normal form POST (no JS) and
 * redirects back to the referring translate page with the new ?lang= applied, and
 * is equally callable as a nonce'd fetch from the picker (returns JSON when the
 * request asks for it). Either way it never throws and never 500s.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\MultilingualRecordService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cookie;

class ReadingLanguageController extends Controller
{
    /** Cookie + session key for the persisted reading-language preference. */
    public const PREF_KEY = 'ahg_reading_language';

    /** Cookie lifetime: 1 year, in minutes (Laravel cookie() takes minutes). */
    private const COOKIE_MINUTES = 60 * 24 * 365;

    public function __construct(private MultilingualRecordService $service) {}

    /**
     * Read the current reading-language preference for this request, or '' when
     * none is set / the stored value is no longer supported.
     *
     * Resolution order: session first (set earlier this request, before the
     * cookie round-trips), then the cookie. The value is validated against the
     * service's supported set; an unsupported value resolves to '' (treated as
     * "no preference") so the caller falls back to current behaviour. Never
     * throws.
     */
    public static function current(Request $request, MultilingualRecordService $service, ?string $sourceCulture = null): string
    {
        try {
            $raw = $request->session()->get(self::PREF_KEY);
            if (! is_string($raw) || trim($raw) === '') {
                $raw = $request->cookie(self::PREF_KEY);
            }
            if (! is_string($raw) || trim($raw) === '') {
                return '';
            }

            $code = $service->canonicaliseLang($raw);

            return $service->isSupportedCode($code, $sourceCulture) ? $code : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * POST /reading-language (name reading-language.set). CSRF-protected.
     *
     * Stores a chosen language code in the cookie + session when it is in the
     * supported set; CLEARS the preference when the submitted value is empty or
     * unsupported (so the visitor can switch back to "no preference" / Original
     * only). Validated against MultilingualRecordService so a garbage value is
     * silently dropped rather than persisted.
     *
     * Progressive enhancement:
     *   - JSON request (fetch from the picker) -> JSON {ok, lang, cleared}.
     *   - Normal form POST (no JS) -> 303 redirect back to the referring page,
     *     carrying the new ?lang= so the translate page re-renders translated.
     *
     * Never throws, never 500s.
     */
    public function set(Request $request)
    {
        $submitted = (string) $request->input('lang', '');
        $code = $this->service->canonicaliseLang($submitted);

        $supported = $code !== '' && $this->service->isSupportedCode($code);
        $store = $supported ? $code : '';
        $cleared = ($store === '');

        // Persist to the session immediately so it is readable THIS request.
        try {
            if ($store !== '') {
                $request->session()->put(self::PREF_KEY, $store);
            } else {
                $request->session()->forget(self::PREF_KEY);
            }
        } catch (\Throwable) {
            // Session unavailable (e.g. stateless context) - the cookie below
            // still carries the preference; degrade quietly.
        }

        // Queue the cookie (1 year) or forget it when clearing.
        $cookie = $cleared
            ? Cookie::forget(self::PREF_KEY)
            : Cookie::make(self::PREF_KEY, $store, self::COOKIE_MINUTES, '/', null, $request->isSecure(), true, false, 'Lax');

        // JSON path (picker fetch). The client persists, then re-translates.
        if ($request->expectsJson() || $request->ajax()) {
            return response()
                ->json(['ok' => true, 'lang' => $store, 'cleared' => $cleared])
                ->withCookie($cookie);
        }

        // No-JS path: redirect back to where the visitor came from, applying the
        // new choice. Prefer an explicit redirect target, else the referer, else
        // the public explore hub. We only ever append ?lang= to our own translate
        // route; an off-site / unknown referer falls back safely.
        $target = $this->resolveRedirectTarget($request, $store);

        return redirect()->to($target, 303)->withCookie($cookie);
    }

    /**
     * Work out where to send a no-JS form post back to. Honours an explicit
     * `redirect` field (must be a relative same-app path), else the Referer when
     * it points at our translate page, else the explore hub / home. Appends the
     * chosen ?lang= (or strips it when cleared) so the destination re-renders in
     * the preferred language. Never returns an absolute off-site URL.
     */
    private function resolveRedirectTarget(Request $request, string $lang): string
    {
        $candidate = (string) $request->input('redirect', '');
        if ($candidate === '' || ! str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            $referer = (string) $request->headers->get('referer', '');
            $candidate = $this->sameAppPath($request, $referer);
        }

        if ($candidate === '') {
            // Nothing usable - send them to a safe public landing.
            $candidate = \Illuminate\Support\Facades\Route::has('explore.index')
                ? route('explore.index', [], false)
                : '/';
        }

        return $this->withLangParam($candidate, $lang);
    }

    /**
     * Reduce a (possibly absolute) URL to its path+query IF it belongs to this
     * application's host; otherwise return ''. Keeps the no-JS redirect strictly
     * on-site (no open-redirect).
     */
    private function sameAppPath(Request $request, string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return '';
        }

        // Relative URL (no host) is fine as-is.
        if (empty($parts['host'])) {
            $path = $parts['path'] ?? '/';

            return $path . (isset($parts['query']) ? ('?' . $parts['query']) : '');
        }

        if (strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return '';
        }

        $path = $parts['path'] ?? '/';

        return $path . (isset($parts['query']) ? ('?' . $parts['query']) : '');
    }

    /**
     * Set/replace/strip the `lang` query parameter on a same-app path. An empty
     * $lang strips it (back to "original only"); a value sets it.
     */
    private function withLangParam(string $pathAndQuery, string $lang): string
    {
        $path = $pathAndQuery;
        $query = '';
        $hash = strpos($pathAndQuery, '?');
        if ($hash !== false) {
            $path = substr($pathAndQuery, 0, $hash);
            $query = substr($pathAndQuery, $hash + 1);
        }

        parse_str($query, $params);
        if ($lang === '') {
            unset($params['lang']);
        } else {
            $params['lang'] = $lang;
        }

        $qs = http_build_query($params);

        return $path . ($qs !== '' ? ('?' . $qs) : '');
    }
}
