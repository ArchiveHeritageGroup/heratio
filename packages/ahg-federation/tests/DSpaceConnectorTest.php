<?php

/*
 * DSpaceConnectorTest - Http::fake mapping assertions for the DSpace 7 REST
 * federated-search connector (#1329). No DB, no network: deterministic and
 * CI-safe.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgFederation\Tests;

use AhgFederation\Connectors\DSpaceConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DSpaceConnectorTest extends TestCase
{
    private function peer(string $baseUrl = 'https://repo.example.org'): object
    {
        return (object) [
            'base_url' => $baseUrl,
            'peer_name' => 'Example DSpace',
            'peer_id' => 7,
            'timeout_ms' => 5000,
        ];
    }

    private function fakeBody(): array
    {
        return [
            '_embedded' => ['searchResult' => [
                'page' => ['totalElements' => 2],
                '_embedded' => ['objects' => [
                    ['_embedded' => ['indexableObject' => [
                        'uuid' => 'u1', 'name' => 'Fallback Name', 'handle' => '123/1',
                        'metadata' => [
                            'dc.title' => [['value' => 'First Item']],
                            'dc.description.abstract' => [['value' => 'An abstract.']],
                            'dc.identifier.uri' => [['value' => 'https://hdl.handle.net/123/1']],
                            'dc.date.issued' => [['value' => '2019-05-01']],
                            'dc.contributor.author' => [['value' => 'Doe, Jane']],
                        ],
                    ]]],
                    ['_embedded' => ['indexableObject' => [
                        'uuid' => 'u2', 'name' => 'Only Name Title', 'handle' => '123/2',
                        'metadata' => [],
                    ]]],
                ]],
            ]],
        ];
    }

    public function test_maps_dspace_rest_items(): void
    {
        Http::fake(['*/discover/search/objects*' => Http::response($this->fakeBody(), 200)]);

        $c = new DSpaceConnector;
        $c->bind($this->peer());
        $rows = $c->search('africa', [], 10);

        $this->assertCount(2, $rows);

        $first = $rows[0];
        $this->assertSame('u1', $first->sourceId);
        $this->assertSame('First Item', $first->title);
        $this->assertSame('An abstract.', $first->snippet);
        $this->assertSame('https://hdl.handle.net/123/1', $first->url);
        $this->assertSame('dspace', $first->peerType);
        $this->assertSame('2019-05-01', $first->date);
        $this->assertSame('123/1', $first->extras['handle']);
        $this->assertSame('Doe, Jane', $first->extras['author']);
        $this->assertSame('hdl:123/1', $first->dedupeKey);
        $this->assertEqualsWithDelta(1.0, $first->score, 0.001);

        // Second item: no dc.title (falls back to name), no dc.identifier.uri
        // (falls back to the frontend handle URL), no date.
        $second = $rows[1];
        $this->assertSame('Only Name Title', $second->title);
        $this->assertSame('https://repo.example.org/handle/123/2', $second->url);
        $this->assertNull($second->date);
        $this->assertEqualsWithDelta(0.98, $second->score, 0.001);
    }

    public function test_builds_rest_endpoint_from_base_url(): void
    {
        Http::fake(['*/discover/search/objects*' => Http::response($this->fakeBody(), 200)]);

        $c = new DSpaceConnector;
        $c->bind($this->peer());
        $c->search('africa', [], 5);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://repo.example.org/server/api/discover/search/objects')
                && str_contains($request->url(), 'query=africa')
                && str_contains($request->url(), 'dsoType=item');
        });
    }

    public function test_ssrf_blocked_host_returns_empty_without_calling(): void
    {
        Http::fake(['*' => Http::response($this->fakeBody(), 200)]);

        $c = new DSpaceConnector;
        $c->bind($this->peer('http://169.254.169.254'));
        $rows = $c->search('anything', [], 5);

        $this->assertSame([], $rows);
        Http::assertNothingSent();
    }

    public function test_non_200_degrades_to_empty(): void
    {
        Http::fake(['*/discover/search/objects*' => Http::response('nope', 503)]);

        $c = new DSpaceConnector;
        $c->bind($this->peer());
        $this->assertSame([], $c->search('africa', [], 5));
    }
}
