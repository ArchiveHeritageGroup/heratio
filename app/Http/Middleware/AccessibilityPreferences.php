<?php

/**
 * AccessibilityPreferences - Heratio app middleware
 *
 * heratio#1211 - universal multilingual access ("every museum for everyone").
 * The accessibility-preference layer that sits alongside the existing locale
 * persistence (SetLocale middleware). It lets ANY anonymous visitor turn on a
 * small set of reading-comfort preferences once and have them remembered and
 * applied on every subsequent page, without an account and without JavaScript
 * being required to make the choice.
 *
 * Three preferences, each a simple on/off:
 *   - high-contrast  (a11y-high-contrast)
 *   - larger-text    (a11y-larger-text)
 *   - reduced-motion (a11y-reduced-motion)
 *
 * Persistence (mirrors the reading-language + locale pattern in this codebase):
 *   - Session       ahg_a11y_prefs   (readable immediately, this request)
 *   - Cookie        ahg_a11y_prefs   (1-year, so it survives across sessions)
 * No database writes - this is a per-browser affordance, not account state.
 *
 * Application (two complementary surfaces, so it works with OR without JS):
 *   - Server-side: the resolved body-class string is shared to all views as
 *     $ahgA11yBodyClass, so a view that emits @yield('body-class') (e.g. the
 *     explore hub) carries the classes on first paint, no JS needed.
 *   - Site-wide: a tiny inline <script> is injected just before </body> on HTML
 *     responses. It adds the same classes to <body> on every page (including the
 *     locked sector/show pages whose layout we never edit), and listens for an
 *     'ahg:a11y' event so the on-page toggle applies instantly without a reload.
 *     Following the established response-injector pattern in this app
 *     (ShareLinkInjector / VersionLinkInjector / SchemaJsonLdInjector).
 *
 * Fail-soft throughout: a garbage / hand-edited cookie resolves to "no prefs";
 * a non-HTML response is passed through untouched; any error degrades to the
 * unmodified response. Never throws, never 500s.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class AccessibilityPreferences
{
    /** Cookie + session key holding the comma-joined active preference keys. */
    public const PREF_KEY = 'ahg_a11y_prefs';

    /**
     * The supported preferences, keyed by their stable storage token, mapped to
     * the CSS body class the theme can style. The set is fixed and validated
     * against - an unknown token in a stored value is ignored.
     *
     * @var array<string,string>
     */
    public const PREFS = [
        'high-contrast' => 'a11y-high-contrast',
        'larger-text' => 'a11y-larger-text',
        'reduced-motion' => 'a11y-reduced-motion',
    ];

    public function handle(Request $request, Closure $next)
    {
        $active = self::resolve($request);

        // Share the body-class string + the active map to every view so a view
        // emitting @yield('body-class') (the explore hub) gets it server-side.
        try {
            $bodyClass = implode(' ', array_map(
                static fn (string $token): string => self::PREFS[$token],
                $active
            ));
            View::share('ahgA11yBodyClass', $bodyClass);
            View::share('ahgA11yActive', $active);
        } catch (\Throwable) {
            // View facade unbound (CLI / early boot) - the injected script below
            // still applies prefs client-side; degrade quietly.
        }

        $response = $next($request);

        // Inject the always-on applier script so prefs apply on EVERY page,
        // including locked layouts we never edit. HTML responses only.
        try {
            $this->injectApplierScript($request, $response, $active);
        } catch (\Throwable) {
            // Never let presentation polish break the response.
        }

        return $response;
    }

    /**
     * Resolve the active preference tokens for this request from session first
     * (set earlier this request, before the cookie round-trips), then the
     * cookie. Unknown tokens are dropped. Returns a clean, ordered subset of
     * the PREFS keys; never throws.
     *
     * @return array<int,string>
     */
    public static function resolve(Request $request): array
    {
        // Session first (set earlier this request, before the cookie round-trips).
        // Guarded on its own so a request with NO session bound (cookie-only,
        // CLI, stateless) still falls through to the cookie rather than bailing.
        $raw = null;
        try {
            if ($request->hasSession()) {
                $raw = $request->session()->get(self::PREF_KEY);
            }
        } catch (\Throwable) {
            $raw = null;
        }

        if (! is_string($raw) || trim($raw) === '') {
            try {
                $raw = $request->cookie(self::PREF_KEY);
            } catch (\Throwable) {
                $raw = null;
            }
        }

        return self::parse(is_string($raw) ? $raw : '');
    }

    /**
     * Parse a stored comma-joined token string into a validated, de-duplicated,
     * canonically ordered list of supported preference tokens.
     *
     * @return array<int,string>
     */
    public static function parse(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $wanted = array_filter(array_map('trim', explode(',', $raw)), static fn ($t) => $t !== '');
        $wanted = array_map('strtolower', $wanted);

        // Canonical order = PREFS declaration order; only known tokens survive.
        $out = [];
        foreach (array_keys(self::PREFS) as $token) {
            if (in_array($token, $wanted, true)) {
                $out[] = $token;
            }
        }

        return $out;
    }

    /**
     * Inject a small inline script that applies the a11y body classes on every
     * HTML page and reacts to the in-page toggle's 'ahg:a11y' event. No-op for
     * non-HTML responses, downloads, or responses with no </body>.
     */
    private function injectApplierScript(Request $request, $response, array $active): void
    {
        if (! method_exists($response, 'getContent') || ! method_exists($response, 'setContent')) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && stripos($contentType, 'text/html') === false) {
            return;
        }

        // Don't touch streamed/binary responses.
        if ($response->headers->has('Content-Disposition')) {
            return;
        }

        $html = $response->getContent();
        if (! is_string($html) || stripos($html, '</body>') === false) {
            return;
        }

        $nonce = '';
        try {
            $nonce = (string) (app()->bound('csp-nonce') ? app('csp-nonce') : '');
        } catch (\Throwable) {
            $nonce = '';
        }

        // The full class map + the currently-active classes, as JSON the script
        // can apply immediately (covers the cookie-only, no-server-share case).
        $allClasses = array_values(self::PREFS);
        $activeClasses = array_map(static fn (string $t): string => self::PREFS[$t], $active);

        $allJson = json_encode($allClasses, JSON_UNESCAPED_SLASHES);
        $activeJson = json_encode(array_values($activeClasses), JSON_UNESCAPED_SLASHES);

        $nonceAttr = $nonce !== '' ? ' nonce="'.e($nonce).'"' : '';

        $script = <<<HTML
<script{$nonceAttr}>
(function(){
  try{
    var ALL = {$allJson};
    var ACTIVE = {$activeJson};
    var b = document.body;
    if(!b){return;}
    function apply(list){
      ALL.forEach(function(c){ b.classList.remove(c); });
      (list||[]).forEach(function(c){ if(c){ b.classList.add(c); } });
    }
    apply(ACTIVE);
    // The on-page toggle dispatches this so changes apply without a reload.
    document.addEventListener('ahg:a11y', function(e){
      try{ apply((e && e.detail && e.detail.classes) || []); }catch(_){}
    });
  }catch(_){}
})();
</script>
HTML;

        $response->setContent(str_ireplace('</body>', $script.'</body>', $html));
    }
}
