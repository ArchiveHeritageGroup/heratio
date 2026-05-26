<?php

/**
 * TenantFileServiceTest - Phase 1 path-helper smoke tests. We exercise
 * segmentFor() (which does not touch config()) plus a minimal stub of
 * TenantContext to drive the explicit-id and null-context branches.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests\Unit;

use AhgMultiTenant\Services\TenantContext;
use AhgMultiTenant\Services\TenantFileService;
use Tests\TestCase;

class TenantFileServiceTest extends TestCase
{
    public function test_segment_for_explicit_id(): void
    {
        $svc = new TenantFileService(new TenantContext());

        $this->assertSame('tenant-7', $svc->segmentFor(7));
    }

    public function test_segment_for_zero_returns_empty(): void
    {
        $svc = new TenantFileService(new TenantContext());

        $this->assertSame('', $svc->segmentFor(0), 'tenantId=0 forces unscoped path');
    }

    public function test_segment_for_negative_returns_empty_defensively(): void
    {
        $svc = new TenantFileService(new TenantContext());

        $this->assertSame('', $svc->segmentFor(-1));
    }

    public function test_segment_for_null_with_no_context_returns_empty(): void
    {
        // Stub: currentId() returns null - mirrors a single-tenant install
        // with no tenant row resolved for the current request.
        $stub = new class extends TenantContext {
            public function currentId(): ?int { return null; }
        };
        $svc = new TenantFileService($stub);

        $this->assertSame('', $svc->segmentFor());
    }

    public function test_segment_for_null_uses_context_when_available(): void
    {
        // Stub: currentId() returns 42 - the path helper must pick that up.
        $stub = new class extends TenantContext {
            public function currentId(): ?int { return 42; }
        };
        $svc = new TenantFileService($stub);

        $this->assertSame('tenant-42', $svc->segmentFor());
    }
}
