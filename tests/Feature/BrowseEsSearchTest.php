<?php

/**
 * BrowseEsSearchTest - with ahg_display_search_es on, the browse search box
 * takes its title/description matches from Elasticsearch, best match first
 * (heratio#1575), never searches the text of digital objects, and falls back
 * to the substring scans when ES cannot answer.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgDisplay\Controllers\DisplayController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrowseEsSearchTest extends TestCase
{
    use DatabaseTransactions;

    private int $byTitle;

    private int $byIdentifier;

    private string $term;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'ahg_display_search_es'], ['setting_value' => '1']);
        $this->term = 'zzes'.uniqid();
        $this->byTitle = $this->record(['title' => "A {$this->term} title"]);
        $this->byIdentifier = $this->record([], "ID-{$this->term}");
    }

    private function record(array $i18n, ?string $identifier = null): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en', 'identifier' => $identifier]);
        if ($i18n) {
            DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en'] + $i18n);
        }

        return $id;
    }

    private function resolve(): ?array
    {
        $c = app(DisplayController::class);
        $r = new \ReflectionClass($c);
        $r->getProperty('isAuthenticated')->setValue($c, true); // staff: no cross-request cache
        $r->setStaticPropertyValue('textMatchIdCache', []);
        $r->setStaticPropertyValue('searchEs', null);

        return $r->getMethod('resolveTextMatchIds')->invoke($c, $this->term);
    }

    public function test_es_ids_lead_and_identifier_scan_still_runs(): void
    {
        Http::fake(['*/_search' => Http::response(['hits' => ['total' => ['value' => 2], 'hits' => [['_id' => '999000001'], ['_id' => (string) $this->byTitle]]]])]);

        $ids = $this->resolve();

        $this->assertSame([999000001, $this->byTitle, $this->byIdentifier], $ids);
        Http::assertSent(function (Request $req) {
            $fields = $req['query']['simple_query_string']['fields'] ?? [];

            return $fields !== [] && ! array_filter($fields, fn ($f) => preg_match('/^(all|transcript)\b/', $f));
        });
    }

    public function test_es_down_falls_back_to_the_substring_scans(): void
    {
        Http::fake(['*/_search' => Http::response('unavailable', 503)]);

        $ids = $this->resolve();

        $this->assertEqualsCanonicalizing([$this->byTitle, $this->byIdentifier], $ids);
    }
}
