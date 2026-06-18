<?php

/**
 * AccessibilityPreferenceController - Heratio ahg-core
 *
 * heratio#1211 - universal multilingual access ("every museum for everyone").
 * The persistence endpoint for the public accessibility preferences (the a11y
 * cousin of ReadingLanguageController). Lets any anonymous visitor toggle a
 * small set of reading-comfort preferences and have them remembered.
 *
 * What it stores (and deliberately does NOT do):
 *   - The active preference tokens (subset of AccessibilityPreferences::PREFS:
 *     high-contrast / larger-text / reduced-motion) in a long-lived cookie
 *     (1 year) AND the session, so they survive across requests and apply even
 *     before the cookie round-trips. No database writes; per-browser only.
 *   - Validates every submitted token against the fixed supported set. Unknown
 *     / hand-edited tokens are dropped, so a stale cookie can never inject a
 *     rogue class.
 *
 * Progressive enhancement (mirrors ReadingLanguageController):
 *   - JSON request (fetch from the toggle) -> JSON {ok, active, classes}.
 *   - Normal form POST (no JS) -> 303 redirect back to the referring page with
 *     the new preferences already applied server-side.
 * Never throws, never 500s.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use App\Http\Middleware\AccessibilityPreferences;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;

class AccessibilityPreferenceController extends Controller
{
    /** Cookie lifetime: 1 year, in minutes (Laravel cookie() takes minutes). */
    private const COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * POST /accessibility-preferences (name accessibility.preferences.set).
     * CSRF-protected.
     *
     * Accepts either a `prefs[]` array of tokens (a no-JS multi-checkbox form
     * post) or a `prefs` comma-joined string (a JS fetch). Persists the
     * validated subset to the session + a 1-year cookie; an empty / unknown
     * submission clears the preference. Never throws, never 500s.
     */
    public function set(Request $request)
    {
        $tokens = $this->extractSubmitted($request);

        // Validate against the fixed supported set + canonical order.
        $active = AccessibilityPreferences::parse(implode(',', $tokens));
        $stored = implode(',', $active);
        $cleared = ($stored === '');

        // Persist to the session immediately so it is readable THIS request.
        try {
            if (! $cleared) {
                $request->session()->put(AccessibilityPreferences::PREF_KEY, $stored);
            } else {
                $request->session()->forget(AccessibilityPreferences::PREF_KEY);
            }
        } catch (\Throwable) {
            // Session unavailable - the cookie below still carries the choice.
        }

        $cookie = $cleared
            ? Cookie::forget(AccessibilityPreferences::PREF_KEY)
            : Cookie::make(
                AccessibilityPreferences::PREF_KEY,
                $stored,
                self::COOKIE_MINUTES,
                '/',
                null,
                $request->isSecure(),
                true,    // httpOnly: the injected applier reads from the server-rendered value, not JS cookie reads
                false,
                'Lax'
            );

        $classes = array_values(array_map(
            static fn (string $t): string => AccessibilityPreferences::PREFS[$t],
            $active
        ));

        // JSON path (toggle fetch).
        if ($request->expectsJson() || $request->ajax()) {
            return response()
                ->json(['ok' => true, 'active' => $active, 'classes' => $classes, 'cleared' => $cleared])
                ->withCookie($cookie);
        }

        // No-JS path: redirect back to where the visitor came from.
        $target = $this->resolveRedirectTarget($request);

        return redirect()->to($target, 303)->withCookie($cookie);
    }

    /**
     * Read the submitted preference tokens from either an array field (no-JS
     * multi-checkbox) or a comma-joined string (JS fetch). Returns the raw
     * tokens; validation happens in the caller.
     *
     * @return array<int,string>
     */
    private function extractSubmitted(Request $request): array
    {
        $raw = $request->input('prefs', []);

        if (is_string($raw)) {
            return array_filter(array_map('trim', explode(',', $raw)), static fn ($t) => $t !== '');
        }

        if (is_array($raw)) {
            return array_values(array_filter(array_map(
                static fn ($v) => is_string($v) ? trim($v) : '',
                $raw
            ), static fn ($t) => $t !== ''));
        }

        return [];
    }

    /**
     * Work out a safe, same-app destination for a no-JS form post: an explicit
     * relative `redirect` field, else the Referer (same host only), else the
     * explore hub / home. Never returns an off-site URL (no open redirect).
     */
    private function resolveRedirectTarget(Request $request): string
    {
        $candidate = (string) $request->input('redirect', '');
        if ($candidate === '' || ! str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            $referer = (string) $request->headers->get('referer', '');
            $candidate = $this->sameAppPath($request, $referer);
        }

        if ($candidate === '') {
            $candidate = Route::has('explore.index') ? route('explore.index', [], false) : '/';
        }

        return $candidate;
    }

    /**
     * Reduce a (possibly absolute) URL to its path+query IF it belongs to this
     * application's host; otherwise return ''. Keeps the redirect on-site.
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
        if (empty($parts['host'])) {
            $path = $parts['path'] ?? '/';

            return $path.(isset($parts['query']) ? ('?'.$parts['query']) : '');
        }
        if (strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return '';
        }
        $path = $parts['path'] ?? '/';

        return $path.(isset($parts['query']) ? ('?'.$parts['query']) : '');
    }
}
