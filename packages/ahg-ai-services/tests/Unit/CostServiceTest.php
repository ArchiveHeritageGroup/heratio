<?php

/**
 * CostServiceTest - smoke tests for the per-call cost ledger.
 *
 * Issue #667 Phase 1. Pure unit tests covering the parts of CostService
 * that do not need a live DB - the soft-fail behaviour of record() /
 * totals() / lookupCost() when the schema or facades are unreachable.
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

use AhgAiServices\Services\CostService;
use AhgAiServices\Services\QuotaService;
use Illuminate\Container\Container;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Facade;
use Monolog\Handler\NullHandler;
use Monolog\Logger as Monolog;
use PHPUnit\Framework\TestCase;

class CostServiceTest extends TestCase
{
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

    private function svc(): CostService
    {
        return new CostService(new QuotaService());
    }

    public function test_record_fails_soft_when_facades_unavailable(): void
    {
        // No Laravel app bound; Schema::hasTable() throws and the inner
        // Throwable catch must swallow it so inference is never blocked
        // by cost logging.
        $this->svc()->record('llm', 'gpt-4o-mini', [
            'tokens_in' => 100, 'tokens_out' => 50, 'tenant_id' => 0,
        ]);
        $this->expectNotToPerformAssertions();
    }

    public function test_totals_returns_zeroes_when_facades_unavailable(): void
    {
        $t = $this->svc()->totals();
        $this->assertSame(0.0, $t['total_usd']);
        $this->assertSame(0,   $t['calls']);
        $this->assertSame(0,   $t['tokens_in']);
        $this->assertSame(0,   $t['tokens_out']);
    }

    public function test_totals_with_filters_still_returns_zeroes_when_unavailable(): void
    {
        $t = $this->svc()->totals(7, 'llm', '2026-01-01 00:00:00');
        $this->assertSame(0.0, $t['total_usd']);
        $this->assertSame(0,   $t['calls']);
    }

    public function test_lookup_cost_returns_null_when_pricing_unreachable(): void
    {
        // DB::table('ahg_ai_pricing')... throws; lookupCost must return
        // null rather than blow up.
        $this->assertNull($this->svc()->lookupCost('gpt-4o-mini', 100, 50));
    }

    public function test_lookup_cost_handles_zero_tokens(): void
    {
        // Even when pricing IS reachable, zero tokens means zero cost.
        // Here we just confirm the method returns null (pricing
        // unreachable) without throwing on zero input.
        $this->assertNull($this->svc()->lookupCost('any-model', 0, 0));
    }
}
