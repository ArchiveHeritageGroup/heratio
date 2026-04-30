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

        return $next($request);
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
