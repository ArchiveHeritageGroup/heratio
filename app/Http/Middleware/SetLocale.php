<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Resolution order (matches AtoM): URL param > session > cookie.
        // The cookie is written by the POST /set-locale route so the choice
        // survives logout and cookie-only visits.
        $culture = $request->query('sf_culture');

        if ($culture && $this->isValidCulture($culture)) {
            App::setLocale($culture);
            session(['locale' => $culture]);
        } elseif ($sessionLocale = session('locale')) {
            App::setLocale($sessionLocale);
        } elseif ($cookieLocale = $request->cookie('locale')) {
            if ($this->isValidCulture($cookieLocale)) {
                App::setLocale($cookieLocale);
                session(['locale' => $cookieLocale]);
            }
        }

        // Hydrate ui_label overrides AFTER the locale is set so config('app.ui_label_*')
        // and __('Archival description') etc. flip per-culture. This runs every
        // request because App::setLocale changes the locale dynamically — the
        // booted-once hydrator in AhgCoreServiceProvider can't see the request culture.
        $this->hydrateUiLabels(App::getLocale());

        return $next($request);
    }

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
