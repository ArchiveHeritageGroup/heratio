<?php

/**
 * AnnotationsW3cTest - Phase 1 of #648 (W3C Web Annotation compliance).
 *
 * Round-trips for:
 *   * SpecificResource body shape persistence (body_selector_json column)
 *   * TextQuoteSelector / TimeSelector / GeoSelector / MediaFragmentSelector
 *     on target.selector
 *   * Web Annotation Protocol (WAP) headers on GET + POST + container
 *   * ETag + If-Match / If-None-Match optimistic concurrency
 *   * Prefer: contained-iris container shape
 *
 * Skipped automatically when ahg_iiif_annotation is unreachable (CI without
 * MySQL) or when no users row exists for authenticated writes.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace Tests\Feature\Annotations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnnotationsW3cTest extends TestCase
{
    /**
     * Target IRI used for all test annotations. Distinctive enough that
     * the same row is reliably picked up by search() and torn down on
     * teardown without colliding with real fixtures.
     */
    private const TEST_TARGET = 'https://test.heratio.local/iiif/3/test-w3c-phase1/canvas/1';

    /**
     * Best-effort tear-down: any annotation pinned to TEST_TARGET created
     * during the test run gets removed. We can't rely on
     * DatabaseTransactions because the package writes directly with
     * DB::table()->insert() and the controller does its own DB calls
     * outside the test's transaction.
     */
    protected function tearDown(): void
    {
        try {
            if (Schema::hasTable('ahg_iiif_annotation')) {
                DB::table('ahg_iiif_annotation')
                    ->where('target_iri', self::TEST_TARGET)
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Best-effort; if the schema isn't there, nothing to clean.
        }
        parent::tearDown();
    }

    private function ensureRouteAvailable(): void
    {
        try {
            if (! Schema::hasTable('ahg_iiif_annotation')) {
                $this->markTestSkipped('ahg_iiif_annotation table not available');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('database not reachable: '.$e->getMessage());
        }

        // Skip when the OpenTelemetry stack (ahg-observability) isn't
        // loadable in the test classloader. Production has the package
        // installed via composer; running the suite from a fresh worktree
        // without `composer install` blows up on RequestIdMiddleware's
        // Trace::start() before our controller even gets called. The
        // ahg-annotations behaviour itself is independent of tracing.
        if (! class_exists(\OpenTelemetry\API\Trace\NoopTracer::class)) {
            $this->markTestSkipped('OpenTelemetry NoopTracer not loadable in this test env');
        }
    }

    private function actingAsAnyUser(): void
    {
        if (! Schema::hasTable('users')) {
            $this->markTestSkipped('users table not available');
        }
        $user = DB::table('users')->limit(1)->first();
        if (! $user) {
            $this->markTestSkipped('no user available for authenticated writes');
        }
        $userClass = '\\App\\Models\\User';
        if (! class_exists($userClass)) {
            $this->markTestSkipped('User model not available');
        }
        $model = $userClass::find($user->id);
        if (! $model) {
            $this->markTestSkipped('user row could not be hydrated');
        }
        $this->actingAs($model);
    }

    /**
     * The Web Annotation Protocol Content-Type and Link headers are
     * REQUIRED on every annotation response. Without them, conformance
     * tools (e.g. https://annotations.io/conformance) fail the endpoint.
     */
    public function test_search_response_carries_wap_headers(): void
    {
        $this->ensureRouteAvailable();

        $response = $this->getJson('/api/annotations/search?targetId='.urlencode(self::TEST_TARGET));
        $response->assertStatus(200);
        $this->assertStringContainsString('application/ld+json', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('profile="http://www.w3.org/ns/anno.jsonld"', $response->headers->get('Content-Type'));

        $link = $response->headers->get('Link');
        $this->assertNotNull($link);
        $this->assertStringContainsString('http://www.w3.org/ns/ldp#Resource', $link);
        $this->assertStringContainsString('http://www.w3.org/TR/annotation-protocol/', $link);
        $this->assertStringContainsString('BasicContainer', $link);

        // Container responses must advertise Accept-Post.
        $this->assertNotNull($response->headers->get('Accept-Post'));
        $this->assertStringContainsString('Prefer', $response->headers->get('Vary'));
    }

    public function test_specific_resource_body_round_trips(): void
    {
        $this->ensureRouteAvailable();
        $this->actingAsAnyUser();

        $payload = [
            '@context' => 'http://www.w3.org/ns/anno.jsonld',
            'type' => 'Annotation',
            'motivation' => 'commenting',
            'target' => self::TEST_TARGET,
            'body' => [
                'type' => 'SpecificResource',
                'source' => 'https://example.org/source-doc',
                'selector' => [
                    'type' => 'TextQuoteSelector',
                    'exact' => 'the quick brown fox',
                    'prefix' => 'lazy dog ',
                    'suffix' => ' jumped over',
                ],
            ],
        ];

        $response = $this->postJson('/api/annotations', $payload);
        $response->assertStatus(201);

        $created = $response->json();
        $this->assertEquals('SpecificResource', $created['body']['type']);
        $this->assertEquals('the quick brown fox', $created['body']['selector']['exact']);
        $this->assertNotNull($response->headers->get('ETag'));
        $this->assertNotNull($response->headers->get('Location'));

        // Verify body_selector_json was denormalised into its own column.
        $row = DB::table('ahg_iiif_annotation')
            ->where('target_iri', self::TEST_TARGET)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->body_selector_json);
        $decoded = json_decode($row->body_selector_json, true);
        $this->assertEquals('https://example.org/source-doc', $decoded['source']);
        $this->assertEquals('TextQuoteSelector', $decoded['selector']['type']);
    }

    public function test_text_quote_selector_on_target_round_trips(): void
    {
        $this->ensureRouteAvailable();
        $this->actingAsAnyUser();

        $payload = [
            '@context' => 'http://www.w3.org/ns/anno.jsonld',
            'type' => 'Annotation',
            'motivation' => 'highlighting',
            'target' => [
                'source' => self::TEST_TARGET,
                'selector' => [
                    'type' => 'TextQuoteSelector',
                    'exact' => 'lorem ipsum',
                    'prefix' => 'dolor ',
                    'suffix' => ' sit amet',
                ],
            ],
            'body' => ['type' => 'TextualBody', 'value' => 'a highlight'],
        ];

        $created = $this->postJson('/api/annotations', $payload)->assertStatus(201)->json();
        $this->assertEquals('TextQuoteSelector', $created['target']['selector']['type']);
        $this->assertEquals('lorem ipsum', $created['target']['selector']['exact']);

        // Round-trip via GET.
        $uuid = basename($created['id']);
        $fetched = $this->getJson('/api/annotations/'.$uuid)->assertStatus(200)->json();
        $this->assertEquals('TextQuoteSelector', $fetched['target']['selector']['type']);
        $this->assertEquals('lorem ipsum', $fetched['target']['selector']['exact']);
    }

    public function test_time_selector_round_trips(): void
    {
        $this->ensureRouteAvailable();
        $this->actingAsAnyUser();

        $payload = [
            '@context' => 'http://www.w3.org/ns/anno.jsonld',
            'type' => 'Annotation',
            'motivation' => 'commenting',
            'target' => [
                'source' => self::TEST_TARGET,
                'selector' => [
                    'type' => 'TimeSelector',
                    't' => 'npt=12.5,30.0',
                ],
            ],
            'body' => ['type' => 'TextualBody', 'value' => 'audio cue'],
        ];

        $created = $this->postJson('/api/annotations', $payload)->assertStatus(201)->json();
        $this->assertEquals('TimeSelector', $created['target']['selector']['type']);
        $this->assertEquals('npt=12.5,30.0', $created['target']['selector']['t']);
        // conformsTo is auto-tagged on known selector types.
        $this->assertArrayHasKey('conformsTo', $created['target']['selector']);
    }

    public function test_geo_selector_round_trips(): void
    {
        $this->ensureRouteAvailable();
        $this->actingAsAnyUser();

        $payload = [
            '@context' => 'http://www.w3.org/ns/anno.jsonld',
            'type' => 'Annotation',
            'motivation' => 'identifying',
            'target' => [
                'source' => self::TEST_TARGET,
                'selector' => [
                    'type' => 'GeoSelector',
                    'value' => 'POINT(28.0473 -26.2041)',
                ],
            ],
        ];

        $created = $this->postJson('/api/annotations', $payload)->assertStatus(201)->json();
        $this->assertEquals('GeoSelector', $created['target']['selector']['type']);
        $this->assertEquals('POINT(28.0473 -26.2041)', $created['target']['selector']['value']);
        $this->assertArrayHasKey('conformsTo', $created['target']['selector']);
    }

    public function test_media_fragment_selector_round_trips(): void
    {
        $this->ensureRouteAvailable();
        $this->actingAsAnyUser();

        $payload = [
            'type' => 'Annotation',
            'target' => [
                'source' => self::TEST_TARGET,
                'selector' => [
                    'type' => 'MediaFragmentSelector',
                    'value' => 't=10,20',
                ],
            ],
        ];

        $created = $this->postJson('/api/annotations', $payload)->assertStatus(201)->json();
        $this->assertEquals('MediaFragmentSelector', $created['target']['selector']['type']);
        $this->assertStringContainsString('media-frags', $created['target']['selector']['conformsTo']);
    }

    public function test_etag_and_if_match_optimistic_concurrency(): void
    {
        $this->ensureRouteAvailable();
        $this->actingAsAnyUser();

        $payload = [
            'type' => 'Annotation',
            'target' => self::TEST_TARGET,
            'body' => ['type' => 'TextualBody', 'value' => 'first'],
        ];
        $createResp = $this->postJson('/api/annotations', $payload)->assertStatus(201);
        $uuid = basename($createResp->json('id'));
        $etag = $createResp->headers->get('ETag');
        $this->assertNotNull($etag);

        // PUT with the right If-Match: succeeds.
        $update = [
            'type' => 'Annotation',
            'target' => self::TEST_TARGET,
            'body' => ['type' => 'TextualBody', 'value' => 'second'],
        ];
        $okResp = $this->withHeaders(['If-Match' => $etag])
            ->putJson('/api/annotations/'.$uuid, $update)
            ->assertStatus(200);
        $newEtag = $okResp->headers->get('ETag');
        $this->assertNotNull($newEtag);
        $this->assertNotEquals($etag, $newEtag);

        // PUT with the STALE etag: 412 Precondition Failed.
        $this->withHeaders(['If-Match' => $etag])
            ->putJson('/api/annotations/'.$uuid, $update)
            ->assertStatus(412);

        // GET with If-None-Match matching current etag: 304 Not Modified.
        $this->withHeaders(['If-None-Match' => $newEtag])
            ->getJson('/api/annotations/'.$uuid)
            ->assertStatus(304);
    }

    public function test_prefer_contained_iris_returns_iris_only(): void
    {
        $this->ensureRouteAvailable();
        $this->actingAsAnyUser();

        // Seed two annotations on the test target.
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/annotations', [
                'type' => 'Annotation',
                'target' => self::TEST_TARGET,
                'body' => ['type' => 'TextualBody', 'value' => 'a-'.$i],
            ])->assertStatus(201);
        }

        $resp = $this->withHeaders(['Prefer' => 'return=representation; include="http://www.w3.org/ns/oa#PreferContainedIRIs"'])
            ->getJson('/api/annotations/search?targetId='.urlencode(self::TEST_TARGET))
            ->assertStatus(200);

        $items = $resp->json('first.items');
        $this->assertIsArray($items);
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertIsString($item, 'contained-iris mode must return bare IRI strings');
            $this->assertStringContainsString('/api/annotations/', $item);
        }
        $this->assertNotNull($resp->headers->get('Preference-Applied'));
    }

    public function test_post_response_carries_location_and_etag(): void
    {
        $this->ensureRouteAvailable();
        $this->actingAsAnyUser();

        $resp = $this->postJson('/api/annotations', [
            'type' => 'Annotation',
            'target' => self::TEST_TARGET,
            'body' => ['type' => 'TextualBody', 'value' => 'hi'],
        ])->assertStatus(201);

        $this->assertNotNull($resp->headers->get('Location'));
        $this->assertNotNull($resp->headers->get('ETag'));
        $this->assertStringContainsString('/api/annotations/', $resp->headers->get('Location'));
        $this->assertStringContainsString('application/ld+json', $resp->headers->get('Content-Type'));
    }

    public function test_unauthenticated_post_returns_json_401(): void
    {
        $this->ensureRouteAvailable();
        // No actingAs() - anonymous request.
        $resp = $this->postJson('/api/annotations', [
            'type' => 'Annotation',
            'target' => self::TEST_TARGET,
        ]);
        $resp->assertStatus(401);
        $this->assertStringContainsString('application/ld+json', $resp->headers->get('Content-Type'));
    }
}
