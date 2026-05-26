<?php

/**
 * QuotaServiceTest - smoke tests for the per-tenant quota gate.
 *
 * Issue #667 Phase 1. These are pure unit tests that exercise the bits of
 * QuotaService that do not touch the DB or container - the public services
 * list, the QuotaExceededException shape, and the soft-fail behaviour of
 * consume() when the schema is missing.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

declare(strict_types=1);

namespace AhgAiServices\Tests\Unit;

use AhgAiServices\Exceptions\QuotaExceededException;
use AhgAiServices\Services\QuotaService;
use Illuminate\Container\Container;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Facade;
use Monolog\Handler\NullHandler;
use Monolog\Logger as Monolog;
use PHPUnit\Framework\TestCase;

class QuotaServiceTest extends TestCase
{
    /**
     * Bind a no-op Log facade so the Log::warning() calls inside the
     * service's Throwable catch blocks resolve cleanly. We deliberately
     * do NOT bind a DB connection - the service must fail-soft when the
     * schema is unreachable.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        Container::setInstance($container);
        $container->instance('log', new Logger(new Monolog('test', [new NullHandler()])));
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_services_constant_covers_the_seven_gated_services(): void
    {
        $services = QuotaService::SERVICES;
        $this->assertContains('llm', $services);
        $this->assertContains('ner', $services);
        $this->assertContains('htr', $services);
        $this->assertContains('donut', $services);
        $this->assertContains('translate', $services);
        $this->assertContains('spellcheck', $services);
        $this->assertContains('face_detect', $services);
        $this->assertCount(7, $services);
    }

    public function test_consume_on_unknown_service_is_a_no_op(): void
    {
        $svc = new QuotaService();
        // Unknown service short-circuits before touching Schema/DB at all -
        // safe to call with no Laravel bootstrap.
        $svc->consume('not-a-real-service', 0);
        $this->expectNotToPerformAssertions();
    }

    public function test_consume_fails_soft_when_facades_unavailable(): void
    {
        // No Laravel bootstrap in this test, so Schema::hasTable() will
        // throw RuntimeException("Facade root has not been set."); the
        // outer Throwable catch must absorb that and let the call through.
        $svc = new QuotaService();
        $svc->consume('llm', 0);
        $this->expectNotToPerformAssertions();
    }

    public function test_snapshot_returns_empty_array_when_facades_unavailable(): void
    {
        $svc = new QuotaService();
        $this->assertSame([], $svc->snapshot());
        $this->assertSame([], $svc->snapshot(42));
    }

    public function test_quota_exceeded_exception_carries_machine_readable_payload(): void
    {
        $e = new QuotaExceededException(7, 'llm', 'daily', 100, 50);
        $arr = $e->toArray();
        $this->assertSame(7, $arr['tenant_id']);
        $this->assertSame('llm', $arr['service']);
        $this->assertSame('daily', $arr['window']);
        $this->assertSame(100, $arr['used']);
        $this->assertSame(50, $arr['limit']);
        $this->assertStringContainsString('llm', $e->getMessage());
        $this->assertStringContainsString('tenant 7', $e->getMessage());
    }

    public function test_quota_exceeded_exception_default_message_is_formatted(): void
    {
        $e = new QuotaExceededException(0, 'translate', 'monthly', 5000, 1000);
        $this->assertSame(
            'AI quota exceeded for service "translate" (tenant 0, window=monthly, used=5000, limit=1000)',
            $e->getMessage(),
        );
    }
}
