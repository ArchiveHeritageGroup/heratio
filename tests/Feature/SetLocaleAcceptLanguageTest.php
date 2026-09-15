<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * #1513 - LOCALE_IGNORE_ACCEPT_LANGUAGE makes APP_LOCALE authoritative for an
 * anonymous visitor with no URL, session or cookie choice, while an explicit
 * ?sf_culture choice still wins.
 */
class SetLocaleAcceptLanguageTest extends TestCase
{
    private function run_(Request $request): string
    {
        $request->setLaravelSession($this->app['session.store']);
        $this->app['session.store']->flush();
        App::setLocale(config('app.locale'));
        (new SetLocale())->handle($request, fn () => new Response('ok'));

        return App::getLocale();
    }

    private function anonymous(string $query = ''): Request
    {
        $r = Request::create('/'.$query, 'GET');
        $r->headers->set('Accept-Language', 'en-GB,en;q=0.9');

        return $r;
    }

    public function test_browser_language_wins_by_default(): void
    {
        config(['app.locale' => 'ar', 'app.locale_ignore_accept_language' => false]);

        $this->assertSame('en', $this->run_($this->anonymous()));
    }

    public function test_app_locale_wins_when_accept_language_is_ignored(): void
    {
        config(['app.locale' => 'ar', 'app.locale_ignore_accept_language' => true]);

        $this->assertSame('ar', $this->run_($this->anonymous()));
    }

    public function test_explicit_choice_still_wins_when_ignoring_accept_language(): void
    {
        config(['app.locale' => 'ar', 'app.locale_ignore_accept_language' => true]);

        $this->assertSame('fr', $this->run_($this->anonymous('?sf_culture=fr')));
    }
}
