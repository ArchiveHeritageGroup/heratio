<?php

/**
 * HtrGatewayRoutingTest - HTR goes through the AI gateway, and a stale env var
 * cannot drag it back onto a GPU node.
 *
 * heratio#131 moved HTR onto the gateway and left a comment saying
 * HTR_SERVICE_URL was "a developer-only override, no longer the production
 * source of truth". Nothing enforced it. Both heratio and heratio-dev still
 * carried HTR_SERVICE_URL=http://192.168.0.115:5006 in .env, so on every
 * instance that mattered the override WAS the production source of truth,
 * months after the fix. That is how the health check came to time out against
 * a standby-only node with a broken driver, twice a day, direct to a GPU node
 * port the gateway rule forbids.
 *
 * DonutService already refused raw-node overrides (#1368). This is the same
 * guard, and the reason it lives in code rather than in each instance's .env is
 * that a protection needing per-instance reapplication is one that eventually
 * is not applied.
 *
 * Note what this does NOT claim: that HTR works. Nothing is listening on 5006
 * on any host, and the gateway's own legacy proxy answers 502 "upstream
 * unreachable" for 127.0.0.1:5006. The service is not deployed anywhere. This
 * only ensures the failure is fast, accurate and gateway-shaped.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

namespace AhgAiServices\Tests\Unit;

use AhgAiServices\Services\HtrService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtrGatewayRoutingTest extends TestCase
{
    public static function rawNodeUrls(): array
    {
        return [
            'the stale .env value' => ['http://192.168.0.115:5006'],
            'the healthy node is still a node' => ['http://192.168.0.76:5006'],
            'ollama by port alone' => ['https://gpu.example.org:11434'],
            'gateway-local upstream' => ['http://127.0.0.1:5006'],
            'localhost by name' => ['http://localhost:5006'],
            'rfc1918 ten' => ['http://10.0.0.5:5004'],
            'rfc1918 172.16' => ['http://172.16.4.9:8011'],
            'rfc1918 172.31' => ['http://172.31.255.1:5008'],
        ];
    }

    #[DataProvider('rawNodeUrls')]
    public function test_a_raw_node_override_is_refused(string $url): void
    {
        $this->assertTrue(HtrService::looksLikeNode($url), "{$url} must never be used as the HTR endpoint");
    }

    public static function legitimateUrls(): array
    {
        return [
            'the gateway' => ['https://ai.theahg.co.za/ai/v1/htr'],
            'the gateway legacy proxy' => ['https://ai.theahg.co.za/ai/v1/htr/legacy'],
            'a genuine external supplier' => ['https://htr.example.org/api'],
        ];
    }

    #[DataProvider('legitimateUrls')]
    public function test_a_gateway_or_external_url_is_allowed(string $url): void
    {
        $this->assertFalse(HtrService::looksLikeNode($url), "{$url} is not a raw node and should be usable");
    }

    /**
     * 172.16-172.31 is private; 172.15 and 172.32 are not. Worth pinning
     * because an over-broad "172." match would refuse legitimate public hosts,
     * and this codebase has already been bitten once by a substring test that
     * matched more than it meant to.
     */
    public function test_the_private_range_boundaries_are_respected(): void
    {
        $this->assertFalse(HtrService::looksLikeNode('http://172.15.0.1:5006'), '172.15 is public');
        $this->assertFalse(HtrService::looksLikeNode('http://172.32.0.1:5006'), '172.32 is public');
        $this->assertTrue(HtrService::looksLikeNode('http://172.16.0.1:5006'), '172.16 is private');
        $this->assertTrue(HtrService::looksLikeNode('http://172.31.0.1:5006'), '172.31 is private');
    }
}
