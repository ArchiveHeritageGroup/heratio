<?php

/**
 * SlugCatchAllShadowTest - the /{slug} record catch-all must not swallow a
 * one-segment page another package registers later (heratio#1554: /opac,
 * /audit, /embargo and others returned 404 because the catch-all matched
 * first). A new top-level route needs its prefix in the exclusion list in
 * routes/web.php; this test names the ones that are missing.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class SlugCatchAllShadowTest extends TestCase
{
    /** Routes deliberately left behind the catch-all, with the reason. */
    private const ALLOWED = [
        'mva', // RAF claims package from a separate project; exposure is an owner decision
    ];

    public function test_no_one_segment_page_is_shadowed_by_the_slug_catch_all(): void
    {
        $routes = app('router')->getRoutes();
        $shadowed = [];
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || ! preg_match('/^[a-z0-9][a-z0-9-]*$/', $uri) || in_array($uri, self::ALLOWED, true)) {
                continue;
            }
            $matched = $routes->match(Request::create('/'.$uri));
            if ($matched->uri() !== $uri) {
                $shadowed[] = "/{$uri} goes to {$matched->getActionName()}";
            }
        }

        $this->assertSame([], $shadowed, "Add these to the /{slug} exclusion list in ahg-information-object-manage/routes/web.php:\n".implode("\n", $shadowed));
    }

    public function test_the_opac_is_reachable_when_enabled(): void
    {
        $this->assertNotSame(404, $this->get('/opac')->getStatusCode());
    }
}
