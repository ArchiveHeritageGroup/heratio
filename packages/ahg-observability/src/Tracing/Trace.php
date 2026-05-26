<?php

/**
 * Trace - static helper for opening OpenTelemetry spans from app code.
 *
 * Phase 5 of issue #677.
 *
 * Usage:
 *
 *   use AhgObservability\Tracing\Trace;
 *
 *   // Wrap a callable - return value is propagated.
 *   $result = Trace::span('htr.run', function () {
 *       return $htrService->process($pageId);
 *   }, ['page_id' => $pageId]);
 *
 *   // Imperative form - caller is responsible for ending the span.
 *   $span = Trace::start('export.zip', ['record_count' => $n]);
 *   try {
 *       doWork();
 *   } finally {
 *       Trace::end($span);
 *   }
 *
 * Spans created here automatically nest under the active request-level
 * span (opened by TraceMiddleware on every HTTP request). When tracing
 * is disabled (`observability.otel_exporter=null`) every method on the
 * returned span is a no-op - zero runtime cost.
 *
 * Defensive: a tracer/exporter failure must not propagate into business
 * logic. Every public method catches \Throwable and falls back to direct
 * execution of the wrapped callable.
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

namespace AhgObservability\Tracing;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;

class Trace
{
    /**
     * Run $callable inside a new child span named $name. Returns whatever
     * the callable returns. Exceptions are recorded on the span and then
     * re-thrown unchanged.
     */
    public static function span(string $name, callable $callable, array $attributes = []): mixed
    {
        $span = self::start($name, $attributes);
        $scope = self::activate($span);

        try {
            $result = $callable();
            self::ok($span);

            return $result;
        } catch (\Throwable $e) {
            self::recordException($span, $e);
            throw $e;
        } finally {
            $scope?->detach();
            self::end($span);
        }
    }

    /**
     * Start a span without entering its scope. Caller MUST eventually
     * call Trace::end($span). Useful for spans whose lifetime crosses
     * function boundaries (e.g. job picked up, job finished).
     */
    public static function start(string $name, array $attributes = []): SpanInterface
    {
        try {
            $tracer = self::tracer();
            $builder = $tracer->spanBuilder($name);

            foreach ($attributes as $key => $value) {
                $builder->setAttribute($key, $value);
            }

            return $builder->startSpan();
        } catch (\Throwable) {
            // Tracer unavailable - return a noop span so callers can still
            // call ->end(), ->setAttribute(), ->recordException() etc.
            return self::noopSpan();
        }
    }

    /**
     * Make $span the current span for the duration of the returned scope.
     * Returns null if scope activation is not possible.
     */
    public static function activate(SpanInterface $span)
    {
        try {
            return Context::getCurrent()->withContextValue($span)->activate();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function end(?SpanInterface $span): void
    {
        if ($span === null) {
            return;
        }

        try {
            $span->end();
        } catch (\Throwable) {
            // never let span teardown bubble
        }
    }

    public static function ok(SpanInterface $span): void
    {
        try {
            $span->setStatus(StatusCode::STATUS_OK);
        } catch (\Throwable) {
            // ignore
        }
    }

    public static function recordException(SpanInterface $span, \Throwable $e): void
    {
        try {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Set attributes on the currently-active span (if any). Useful for
     * threading request-level context like tenant_id once it's known.
     */
    public static function setCurrentAttributes(array $attributes): void
    {
        try {
            $span = \OpenTelemetry\API\Trace\Span::getCurrent();
            foreach ($attributes as $key => $value) {
                $span->setAttribute($key, $value);
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Resolve the configured tracer. Pulled from the container so tests
     * can rebind it.
     */
    protected static function tracer(): TracerInterface
    {
        $tracer = app()->bound('otel.tracer') ? app('otel.tracer') : null;

        if ($tracer instanceof TracerInterface) {
            return $tracer;
        }

        return new \OpenTelemetry\API\Trace\NoopTracer;
    }

    protected static function noopSpan(): SpanInterface
    {
        // The NoopTracer's startSpan() returns a NonRecordingSpan; we
        // synthesise one the same way to guarantee a SpanInterface back.
        return (new \OpenTelemetry\API\Trace\NoopTracer)
            ->spanBuilder('noop')
            ->startSpan();
    }
}
