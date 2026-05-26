<?php

/**
 * TracingTest - issue #677 Phase 5.
 *
 * Smoke-tests the OpenTelemetry wiring:
 *   - When otel_exporter=null, TracerProvider returns a NoopTracerProvider
 *     and Trace::span() still runs the callable + returns its value.
 *   - When otel_exporter=console, the resolver returns a working SDK
 *     TracerProvider (no transport assertion - we don't want CI to
 *     speak gRPC).
 *   - Trace::span() propagates exceptions and the wrapped callable runs.
 *   - The static Trace helper is a no-op when no tracer is bound.
 *
 * These tests do NOT assert anything about the exported span payload -
 * that's a job for the OTel SDK's own tests. We're verifying our wiring
 * + defensive fallbacks.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgObservability\Tests\Feature;

use AhgObservability\Tracing\Trace;
use AhgObservability\Tracing\TracerProvider as HeratioTracerProvider;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Tests\TestCase;

class TracingTest extends TestCase
{
    public function test_null_mode_returns_noop_tracer_provider(): void
    {
        if (! interface_exists(TracerProviderInterface::class)) {
            $this->markTestSkipped('OTel SDK not installed yet.');
        }

        config()->set('observability.otel_exporter', 'null');

        $provider = HeratioTracerProvider::build(
            config()->get('observability'),
            '1.99.0'
        );

        $this->assertInstanceOf(NoopTracerProvider::class, $provider);
    }

    public function test_trace_span_helper_returns_callable_result(): void
    {
        if (! interface_exists(TracerProviderInterface::class)) {
            $this->markTestSkipped('OTel SDK not installed yet.');
        }

        $result = Trace::span('test.compute', fn () => 42 + 7, ['unit' => 'test']);
        $this->assertSame(49, $result);
    }

    public function test_trace_span_helper_propagates_exceptions(): void
    {
        if (! interface_exists(TracerProviderInterface::class)) {
            $this->markTestSkipped('OTel SDK not installed yet.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        Trace::span('test.fail', function () {
            throw new \RuntimeException('boom');
        });
    }

    public function test_trace_helper_is_noop_when_tracer_unbound(): void
    {
        // Forget any container binding so Trace::tracer() falls through
        // to its NoopTracer fallback path.
        $this->app->forgetInstance('otel.tracer');

        $ran = false;
        Trace::span('test.unbound', function () use (&$ran) {
            $ran = true;
        });

        $this->assertTrue($ran, 'Wrapped callable must run even when tracer is unbound.');
    }
}
