<?php

/**
 * BrowseTextMatchCacheTest - a visitor's text search keeps its resolved ids
 * across requests, so paging and facet clicks skip the substring scans (about
 * 1 s at ANC). Staff always scan fresh, so a record they just edited is found.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgDisplay\Controllers\DisplayController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrowseTextMatchCacheTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0: ?array, 1: int} the ids and how many scans of information_object_i18n ran */
    private function resolve(string $term, bool $staff): array
    {
        $c = app(DisplayController::class);
        $r = new \ReflectionClass($c);
        $r->getProperty('isAuthenticated')->setValue($c, $staff);
        $r->setStaticPropertyValue('textMatchIdCache', []); // a new request
        $scans = 0;
        DB::listen(function ($e) use (&$scans) {
            if (str_contains($e->sql, 'from `information_object_i18n`')) {
                $scans++;
            }
        });

        return [$r->getMethod('resolveTextMatchIds')->invoke($c, $term), $scans];
    }

    public function test_visitor_repeat_search_reuses_ids_and_staff_scan_fresh(): void
    {
        $term = 'zzcache'.uniqid();
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => "A $term title"]);

        [$first, $scans] = $this->resolve($term, false);
        $this->assertSame([$id], $first);
        $this->assertGreaterThan(0, $scans);

        [$again, $scans] = $this->resolve($term, false);
        $this->assertSame([$id], $again);
        $this->assertSame(0, $scans);

        [, $scans] = $this->resolve($term, true);
        $this->assertGreaterThan(0, $scans);
    }
}
