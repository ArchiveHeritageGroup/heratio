<?php

/**
 * IiifContentStateServiceTest - Unit tests for the Content State 1.0 encoder.
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
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgIiifCollection\Tests\Unit;

use AhgIiifCollection\Services\IiifContentStateService;
use PHPUnit\Framework\TestCase;

/**
 * Pure-PHP unit tests for the Content State 1.0 encoder. The service has
 * no DB / framework deps once instantiated, so we use the bare PHPUnit
 * TestCase rather than the Laravel one - faster + isolated from db.
 *
 * Covers:
 *   - URL-safe base64 round-trip (encode -> decode returns the same shape)
 *   - Annotation envelope is spec-conformant (@context, motivation, target)
 *   - FragmentSelector defaults (type / conformsTo) populate when missing
 *   - Decode rejects malformed / non-JSON tokens with null (no exceptions)
 */
class IiifContentStateServiceTest extends TestCase
{
    private IiifContentStateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IiifContentStateService();
    }

    public function test_round_trip_preserves_manifest_canvas_and_selector(): void
    {
        $manifest = 'https://heratio.example/iiif-manifest/test-doc';
        $canvas = 'https://heratio.example/iiif-manifest/test-doc/canvas/3';
        $selector = ['xywh' => '100,200,400,300'];

        $token = $this->service->encode($manifest, $canvas, $selector);

        // URL-safe alphabet only: A-Z a-z 0-9 - _ (no +/= per RFC 4648 §5).
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_\-]+$/',
            $token,
            'Content State token contains characters outside the URL-safe alphabet'
        );

        $decoded = $this->service->decode($token);
        $this->assertIsArray($decoded);
        $this->assertSame('Annotation', $decoded['type']);
        $this->assertSame('contentState', $decoded['motivation']);
        $this->assertSame('http://iiif.io/api/presentation/3/context.json', $decoded['@context']);

        // Canvas IRI takes precedence over manifest in target.source
        $this->assertSame($canvas, $decoded['target']['source']['id']);
        $this->assertSame('Canvas', $decoded['target']['source']['type']);

        // partOf must point back to the manifest
        $this->assertSame($manifest, $decoded['target']['source']['partOf'][0]['id']);
        $this->assertSame('Manifest', $decoded['target']['source']['partOf'][0]['type']);

        // FragmentSelector defaults populated.
        $this->assertArrayHasKey('selector', $decoded['target']);
        $this->assertSame('FragmentSelector', $decoded['target']['selector']['type']);
        $this->assertSame('http://www.w3.org/TR/media-frags/', $decoded['target']['selector']['conformsTo']);
        $this->assertSame('100,200,400,300', $decoded['target']['selector']['xywh']);
    }

    public function test_round_trip_without_canvas_or_selector_still_valid(): void
    {
        $manifest = 'https://heratio.example/iiif-manifest/plain';
        $token = $this->service->encode($manifest);

        $decoded = $this->service->decode($token);
        $this->assertIsArray($decoded);
        $this->assertSame('Annotation', $decoded['type']);
        // Without canvas, target.source.id == manifest
        $this->assertSame($manifest, $decoded['target']['source']['id']);
        $this->assertSame('Manifest', $decoded['target']['source']['type']);
        $this->assertArrayNotHasKey('selector', $decoded['target']);
    }

    public function test_decode_returns_null_for_empty_or_garbage_tokens(): void
    {
        $this->assertNull($this->service->decode(''));
        // Valid base64 but not JSON - decode should fail gracefully.
        $notJson = rtrim(strtr(base64_encode('hello not json'), '+/', '-_'), '=');
        $this->assertNull($this->service->decode($notJson));
        // Completely invalid base64 (illegal padding count after URL-safe
        // conversion - PHP's strict mode rejects this).
        $this->assertNull($this->service->decode('%%%not-base64%%%'));
    }

    public function test_build_annotation_emits_spec_conformant_envelope(): void
    {
        $annotation = $this->service->buildAnnotation(
            'https://heratio.example/iiif-manifest/x',
            null,
            null
        );
        $this->assertSame('Annotation', $annotation['type']);
        $this->assertSame('contentState', $annotation['motivation']);
        $this->assertArrayHasKey('@context', $annotation);
        $this->assertArrayHasKey('id', $annotation);
        $this->assertArrayHasKey('target', $annotation);
        $this->assertSame('SpecificResource', $annotation['target']['type']);
    }
}
