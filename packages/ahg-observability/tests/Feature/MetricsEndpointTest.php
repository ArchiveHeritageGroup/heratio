<?php

/**
 * MetricsEndpointTest - issue #677 Phase 3.
 *
 * Verifies:
 *   - GET /metrics with no token returns 401
 *   - GET /metrics with the configured bearer token returns 200
 *   - The response body contains Prometheus HELP/TYPE preamble for our metrics
 *   - Hitting any GET route then re-scraping increments http_requests_total
 *
 * Uses an InMemory adapter so the assertion against the counter delta is
 * deterministic - no Redis/APCu dependency for CI.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgObservability\Tests\Feature;

use AhgObservability\Services\MetricsRegistry;
use Prometheus\Storage\InMemory;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Force InMemory storage + a known token so the assertions don't
        // rely on host config (which may or may not have Redis configured).
        config()->set('observability.storage_driver', 'inmemory');
        config()->set('observability.token', 'test-token-abc123');
        config()->set('observability.allowed_ips', []);

        // Rebind the registry singleton so it picks up the in-memory driver
        // we just configured (the previous instance, if any, was cached).
        $this->app->forgetInstance(MetricsRegistry::class);
        $this->app->singleton(MetricsRegistry::class, function () {
            return new MetricsRegistry(new InMemory);
        });
    }

    public function test_metrics_endpoint_rejects_unauthenticated_scrape(): void
    {
        $response = $this->get('/metrics');
        $response->assertStatus(401);
    }

    public function test_metrics_endpoint_accepts_bearer_token(): void
    {
        $response = $this->get('/metrics', [
            'Authorization' => 'Bearer test-token-abc123',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type', ''));
    }

    public function test_metrics_endpoint_emits_help_lines_for_request_counter(): void
    {
        // Make at least one request through the middleware so the
        // http_requests_total counter is registered.
        $this->get('/');

        $response = $this->get('/metrics', [
            'Authorization' => 'Bearer test-token-abc123',
        ]);

        $response->assertStatus(200);
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('# HELP heratio_http_requests_total', $body);
        $this->assertStringContainsString('# TYPE heratio_http_requests_total counter', $body);
    }

    public function test_request_counter_increments_between_scrapes(): void
    {
        $this->get('/');

        $first = $this->get('/metrics', ['Authorization' => 'Bearer test-token-abc123']);
        $firstBody = (string) $first->getContent();
        $firstCount = $this->extractTotal($firstBody);

        // Hit a route again; counter should grow by at least 1.
        $this->get('/');

        $second = $this->get('/metrics', ['Authorization' => 'Bearer test-token-abc123']);
        $secondBody = (string) $second->getContent();
        $secondCount = $this->extractTotal($secondBody);

        $this->assertGreaterThan(
            $firstCount,
            $secondCount,
            'http_requests_total should grow after another GET. Body was: '.substr($secondBody, 0, 500)
        );
    }

    /**
     * Sum every heratio_http_requests_total sample line in the Prometheus
     * text body. Format per sample line:
     *   heratio_http_requests_total{method="GET",route="...",status="200"} 7
     */
    private function extractTotal(string $body): float
    {
        $total = 0.0;
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            if (! str_starts_with($line, 'heratio_http_requests_total')) {
                continue;
            }
            if (str_starts_with($line, 'heratio_http_requests_total_')) {
                // Skip _bucket / _sum / _count variants if any creep in.
                continue;
            }
            $parts = preg_split('/\s+/', trim($line));
            if (! $parts || count($parts) < 2) {
                continue;
            }
            $total += (float) end($parts);
        }

        return $total;
    }
}
