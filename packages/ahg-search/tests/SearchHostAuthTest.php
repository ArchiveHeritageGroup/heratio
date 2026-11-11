<?php

/**
 * SearchHostAuthTest - the search server's credentials go to the search host and
 * nowhere else (OpenSearch with the security plugin on; GCIS RFB 008 work).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgSearch\Tests;

use AhgSearch\Support\SearchHostAuth;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchHostAuthTest extends TestCase
{
    private array $cfg = ['host' => 'https://search.example.test:9200', 'username' => 'heratio', 'password' => 's3cret', 'verify' => true];

    public function test_credentials_go_only_to_the_search_host(): void
    {
        Http::fake(['*' => Http::response('{}', 200)]);
        Http::globalMiddleware(SearchHostAuth::middleware($this->cfg));

        Http::get('https://search.example.test:9200/heratio_qubitterm/_search');
        Http::get('https://ai.example.test/ai/v1/health');
        Http::get('http://search.example.test:9200/');      // same host, wrong scheme
        Http::get('https://search.example.test:9201/');     // same host, other port

        $expected = 'Basic '.base64_encode('heratio:s3cret');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), ':9200/heratio_qubitterm') && $r->header('Authorization') === [$expected]);
        Http::assertNotSent(fn (Request $r) => ! str_contains($r->url(), 'https://search.example.test:9200/') && $r->hasHeader('Authorization'));
    }

    public function test_endpoint_fills_default_ports(): void
    {
        $this->assertSame('https://search.example.test:443', SearchHostAuth::endpoint('https://Search.example.test/x'));
        $this->assertSame('http://localhost:80', SearchHostAuth::endpoint('http://localhost'));
        $this->assertNull(SearchHostAuth::endpoint('not a url'));
    }

    public function test_not_needed_for_an_open_server(): void
    {
        $this->assertFalse(SearchHostAuth::needed(['host' => 'http://localhost:9200']));
        $this->assertTrue(SearchHostAuth::needed(['host' => 'http://localhost:9200', 'verify' => 'false']));
        $this->assertTrue(SearchHostAuth::needed($this->cfg));
    }
}
