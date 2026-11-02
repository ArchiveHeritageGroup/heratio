<?php

/**
 * EsSearchAnalysisTest - ahg:es-search-analysis adds the stemming and country
 * synonym analysers, the stem sub-fields and the background fill in that
 * order (heratio#1575), and reopens the index even when the settings call fails.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EsSearchAnalysisTest extends TestCase
{
    private string $index;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.elasticsearch.host' => 'http://es.test:9200', 'services.elasticsearch.prefix' => 'x_']);
        $this->index = 'x_qubitinformationobject';
    }

    private function fake(int $settingsStatus = 200): void
    {
        $i = $this->index;
        Http::fake(function (Request $r) use ($i, $settingsStatus) {
            $path = parse_url($r->url(), PHP_URL_PATH);

            return match (true) {
                $r->method() === 'GET' && $path === "/$i/_settings" => Http::response([$i => ['settings' => ['index' => ['analysis' => []]]]]),
                $r->method() === 'GET' && $path === "/$i/_mapping" => Http::response([$i => ['mappings' => ['properties' => ['i18n' => ['properties' => ['en' => ['properties' => [
                    'title' => ['type' => 'text', 'analyzer' => 'english'], 'scopeAndContent' => ['type' => 'text']]]]]]]]]),
                $r->method() === 'PUT' && $path === "/$i/_settings" => Http::response([], $settingsStatus),
                $path === "/$i/_update_by_query" => Http::response(['task' => 'n:1']),
                default => Http::response(['acknowledged' => true]),
            };
        });
    }

    public function test_adds_analysers_then_sub_fields_then_fills(): void
    {
        $this->fake();

        $this->artisan('ahg:es-search-analysis')->assertSuccessful();

        $steps = collect(Http::recorded())->map(fn ($p) => $p[0]->method().' '.parse_url($p[0]->url(), PHP_URL_PATH))
            ->reject(fn ($s) => str_starts_with($s, 'GET'))->values()->all();
        $this->assertSame(["POST /{$this->index}/_close", "PUT /{$this->index}/_settings", "POST /{$this->index}/_open",
            "PUT /{$this->index}/_mapping", "POST /{$this->index}/_update_by_query"], $steps);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/_settings') && $r->method() === 'PUT'
            && in_array('belgium, belgian', $r['analysis']['filter']['heratio_demonyms']['synonyms'], true));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/_mapping') && $r->method() === 'PUT'
            && ($r['properties']['i18n']['properties']['en']['properties']['title']['analyzer'] ?? null) === 'english'
            && isset($r['properties']['i18n']['properties']['en']['properties']['title']['fields']['stem']));
    }

    public function test_index_is_reopened_when_the_settings_call_fails(): void
    {
        $this->fake(400);

        try {
            $this->artisan('ahg:es-search-analysis');
        } catch (\Throwable) {
        }

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/_open'));
    }
}
