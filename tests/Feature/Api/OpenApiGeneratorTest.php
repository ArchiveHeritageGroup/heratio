<?php

/**
 * OpenApiGeneratorTest
 *
 * Issue #652 Phase 1 - verifies the reflective OpenAPI 3.1 generator emits
 * a spec-correct document covering the known /api/* routes.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright 2026 Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace Tests\Feature\Api;

use AhgApi\Services\OpenApiGenerator;
use Tests\TestCase;

class OpenApiGeneratorTest extends TestCase
{
    public function test_generator_returns_openapi_3_1_envelope(): void
    {
        if (! class_exists(OpenApiGenerator::class)) {
            $this->markTestSkipped('OpenApiGenerator not yet wired in.');
        }

        $spec = (new OpenApiGenerator())->generate();

        $this->assertSame('3.1.0', $spec['openapi'] ?? null);
        $this->assertIsArray($spec['info'] ?? null);
        $this->assertNotEmpty($spec['info']['title']);
        $this->assertNotEmpty($spec['info']['version']);
        $this->assertIsArray($spec['paths'] ?? null);
        $this->assertIsArray($spec['components']['securitySchemes'] ?? null);
        $this->assertArrayHasKey('ApiKeyAuth', $spec['components']['securitySchemes']);
    }

    public function test_generator_includes_sample_known_routes(): void
    {
        if (! class_exists(OpenApiGenerator::class)) {
            $this->markTestSkipped('OpenApiGenerator not yet wired in.');
        }

        $spec = (new OpenApiGenerator())->generate();
        $paths = array_keys($spec['paths'] ?? []);

        // At least 3 known endpoints should appear (any version)
        $hasInfoObjects = $this->containsPath($paths, '/api/v1/informationobjects')
                       || $this->containsPath($paths, '/api/v2/descriptions');
        $hasActors = $this->containsPath($paths, '/api/v1/actors')
                  || $this->containsPath($paths, '/api/v2/authorities');
        $hasTaxonomies = $this->containsPath($paths, '/api/v1/taxonomies')
                      || $this->containsPath($paths, '/api/v2/taxonomies');

        $this->assertTrue($hasInfoObjects, 'OpenAPI spec missing information-object endpoints.');
        $this->assertTrue($hasActors, 'OpenAPI spec missing actor/authority endpoints.');
        $this->assertTrue($hasTaxonomies, 'OpenAPI spec missing taxonomy endpoints.');
    }

    public function test_generator_marks_post_endpoints_with_idempotency_header(): void
    {
        if (! class_exists(OpenApiGenerator::class)) {
            $this->markTestSkipped('OpenApiGenerator not yet wired in.');
        }

        $spec = (new OpenApiGenerator())->generate();

        $foundIdempotencyHeader = false;
        foreach ($spec['paths'] ?? [] as $path => $methods) {
            if (! isset($methods['post'])) {
                continue;
            }
            $params = $methods['post']['parameters'] ?? [];
            foreach ($params as $p) {
                if (($p['name'] ?? null) === 'Idempotency-Key' && ($p['in'] ?? null) === 'header') {
                    $foundIdempotencyHeader = true;
                    break 2;
                }
            }
        }

        $this->assertTrue($foundIdempotencyHeader, 'No POST operation advertises Idempotency-Key header.');
    }

    protected function containsPath(array $paths, string $needle): bool
    {
        foreach ($paths as $p) {
            if (str_starts_with($p, $needle)) {
                return true;
            }
        }

        return false;
    }
}
