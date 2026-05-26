<?php

/**
 * TenantContextTest - exercises the Phase 1 resolver behaviour without
 * standing up the full Heratio container. We unit-test the in-memory
 * scope() stack, scope nesting, and exception safety. The DB-backed
 * resolution paths (host, session, auth) are covered by integration
 * tests in a follow-up.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests\Unit;

use AhgMultiTenant\Services\TenantContext;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    /**
     * scope() exposes the override id via scopeDepth(). Tests the stack
     * mechanics in isolation - no DB hits because the stack frame is
     * consulted before the resolve() chain.
     */
    public function test_scope_pushes_and_pops_stack_depth(): void
    {
        $ctx = new TenantContext();
        $this->assertSame(0, $ctx->scopeDepth());

        $ret = $ctx->scope(7, function () use ($ctx) {
            $this->assertSame(1, $ctx->scopeDepth());
            return 'inside';
        });

        $this->assertSame('inside', $ret);
        $this->assertSame(0, $ctx->scopeDepth());
    }

    /**
     * Nested scope() calls compose - each pushes a new frame, the TOP
     * frame wins, popping restores the previous one.
     */
    public function test_scope_nests_lifo(): void
    {
        $ctx = new TenantContext();

        $ctx->scope(1, function () use ($ctx) {
            $this->assertSame(1, $ctx->scopeDepth());

            $ctx->scope(2, function () use ($ctx) {
                $this->assertSame(2, $ctx->scopeDepth());

                $ctx->scope(3, function () use ($ctx) {
                    $this->assertSame(3, $ctx->scopeDepth());
                });

                $this->assertSame(2, $ctx->scopeDepth());
            });

            $this->assertSame(1, $ctx->scopeDepth());
        });

        $this->assertSame(0, $ctx->scopeDepth());
    }

    /**
     * scope() must restore the previous tenant on exception, otherwise
     * an error inside a job's tenant scope poisons every subsequent call
     * on the same worker process.
     */
    public function test_scope_restores_on_throw(): void
    {
        $ctx = new TenantContext();

        $this->assertSame(0, $ctx->scopeDepth());

        try {
            $ctx->scope(42, function () use ($ctx) {
                $this->assertSame(1, $ctx->scopeDepth());
                throw new \RuntimeException('boom');
            });
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(0, $ctx->scopeDepth(), 'scope() must pop the frame even on throw');
    }

    /**
     * scope() returns whatever the closure returns - including null.
     */
    public function test_scope_returns_closure_value(): void
    {
        $ctx = new TenantContext();

        $this->assertSame(99, $ctx->scope(1, fn () => 99));
        $this->assertNull($ctx->scope(1, fn () => null));
        $this->assertSame(['a' => 1], $ctx->scope(1, fn () => ['a' => 1]));
    }

    /**
     * forget() must clear the memo without affecting the override stack
     * (so tests that swap auth between calls can force a re-resolve).
     */
    public function test_forget_does_not_pop_scope_stack(): void
    {
        $ctx = new TenantContext();

        $ctx->scope(5, function () use ($ctx) {
            $this->assertSame(1, $ctx->scopeDepth());
            $ctx->forget();
            $this->assertSame(1, $ctx->scopeDepth(), 'forget() must not touch the stack');
        });
    }
}
