<?php

/**
 * TracerProvider - bootstraps the OpenTelemetry SDK for Heratio.
 *
 * Phase 5 of issue #677.
 *
 * Builds a process-wide OTel TracerProvider configured from
 * `config('observability')` + `version.json` + the host env:
 *
 *   - service.name         <- observability.otel_service_name (default heratio)
 *   - service.version      <- version.json -> version field
 *   - service.instance.id  <- gethostname()
 *   - deployment.environment <- observability.otel_environment / APP_ENV
 *
 * Exporters:
 *   - "otlp"    -> OTLP gRPC (or http/protobuf, http/json) to
 *                  observability.otel_endpoint (default http://localhost:4317).
 *   - "console" -> stderr dump (dev only).
 *   - "null"    -> NoopTracerProvider; spans are accepted and dropped.
 *                  This is the default. Without a reachable collector the
 *                  app keeps running and producing zero overhead.
 *
 * The factory is wrapped in a try/catch: a misconfigured exporter / missing
 * SDK class / unreachable transport must not crash boot. We log + return
 * the NoopTracerProvider so callers can still call
 * `app('otel.tracer')->spanBuilder(...)->startSpan()` and get a no-op span.
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

use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter as OtlpSpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\Stream\StreamTransportFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider as SdkTracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class TracerProvider
{
    /**
     * Build the configured tracer provider (or a Noop fallback). Calls
     * site responsibility: cache the result; don't call this per-request.
     */
    public static function build(array $config, string $appVersion): TracerProviderInterface
    {
        // If the OTel SDK isn't installed yet (composer hasn't run) bail
        // out with a Noop so the rest of the app keeps working.
        if (! class_exists(SdkTracerProvider::class) || ! class_exists(NoopTracerProvider::class)) {
            return self::fallbackNoop();
        }

        $mode = strtolower((string) ($config['otel_exporter'] ?? 'null'));

        if ($mode === 'null' || $mode === 'noop' || $mode === 'off' || $mode === '') {
            return new NoopTracerProvider;
        }

        try {
            $resource = self::buildResource($config, $appVersion);
            $sampler  = self::buildSampler($config);
            $exporter = self::buildExporter($mode, $config);

            if ($exporter === null) {
                return new NoopTracerProvider;
            }

            // BatchSpanProcessor is the right default for production - it
            // buffers and ships spans on a worker. Console mode is dev so
            // we use SimpleSpanProcessor for immediate flush.
            $processor = ($mode === 'console')
                ? new SimpleSpanProcessor($exporter)
                : new BatchSpanProcessor($exporter, null);

            return SdkTracerProvider::builder()
                ->addSpanProcessor($processor)
                ->setResource($resource)
                ->setSampler($sampler)
                ->build();
        } catch (\Throwable $e) {
            // Misconfigured collector / unreachable transport / etc. must
            // not crash the app. Fall back to noop.
            return self::fallbackNoop();
        }
    }

    /**
     * Compose the OTel Resource (service.name + version + instance + env).
     */
    protected static function buildResource(array $config, string $appVersion): ResourceInfo
    {
        $serviceName = (string) ($config['otel_service_name'] ?? 'heratio');
        $env         = (string) ($config['otel_environment'] ?? 'production');
        $hostname    = gethostname() ?: 'unknown-host';

        $attributes = Attributes::create([
            ResourceAttributes::SERVICE_NAME        => $serviceName,
            ResourceAttributes::SERVICE_VERSION     => $appVersion,
            ResourceAttributes::SERVICE_INSTANCE_ID => $hostname,
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT => $env,
        ]);

        // ResourceInfoFactory::defaultResource() picks up OTEL_* env-vars
        // and host telemetry SDK metadata. Merge our service attrs on top.
        return ResourceInfoFactory::defaultResource()
            ->merge(ResourceInfo::create($attributes));
    }

    /**
     * Parent-based + ratio-based sampler. Inbound traceparent decisions
     * win; root spans get the configured ratio.
     */
    protected static function buildSampler(array $config)
    {
        $ratio = (float) ($config['otel_sample_ratio'] ?? 1.0);
        $ratio = max(0.0, min(1.0, $ratio));

        return new ParentBased(new TraceIdRatioBasedSampler($ratio));
    }

    /**
     * Build the exporter for the configured mode. Returns null if the
     * caller asked for a mode we don't recognise (treated as off).
     */
    protected static function buildExporter(string $mode, array $config)
    {
        if ($mode === 'console') {
            if (! class_exists(ConsoleSpanExporter::class)) {
                return null;
            }

            return new ConsoleSpanExporter(
                (new StreamTransportFactory)->create('php://stderr', 'application/x-ndjson')
            );
        }

        if ($mode === 'otlp') {
            if (! class_exists(OtlpSpanExporter::class) || ! class_exists(OtlpHttpTransportFactory::class)) {
                return null;
            }

            $endpoint = (string) ($config['otel_endpoint'] ?? 'http://localhost:4317');
            $protocol = strtolower((string) ($config['otel_protocol'] ?? 'grpc'));

            // gRPC requires open-telemetry/transport-grpc + grpc PHP ext.
            // If not available, fall back to HTTP/protobuf which is in the
            // base exporter-otlp package.
            if ($protocol === 'grpc' && class_exists('\OpenTelemetry\Contrib\Grpc\GrpcTransportFactory')) {
                $transport = (new \OpenTelemetry\Contrib\Grpc\GrpcTransportFactory)
                    ->create($endpoint.'/opentelemetry.proto.collector.trace.v1.TraceService/Export');

                return new OtlpSpanExporter($transport);
            }

            $contentType = $protocol === 'http/json'
                ? 'application/json'
                : 'application/x-protobuf';

            // OTLP HTTP wants the full /v1/traces path; tolerate either
            // input (bare host or already-qualified).
            $url = rtrim($endpoint, '/');
            if (! str_contains($url, '/v1/traces')) {
                $url .= '/v1/traces';
            }

            $transport = (new OtlpHttpTransportFactory)->create($url, $contentType);

            return new OtlpSpanExporter($transport);
        }

        return null;
    }

    /**
     * Concrete NoopTracerProvider if the SDK is loaded, or a tiny inline
     * stub otherwise so callers can still invoke ->spanBuilder()->startSpan().
     */
    protected static function fallbackNoop(): TracerProviderInterface
    {
        if (class_exists(NoopTracerProvider::class)) {
            return new NoopTracerProvider;
        }

        // Defensive: the API package was supposed to ship NoopTracerProvider.
        // Build an anonymous shim if for some reason it didn't load.
        return new class implements TracerProviderInterface
        {
            public function getTracer(string $name, ?string $version = null, ?string $schemaUrl = null, iterable $attributes = []): \OpenTelemetry\API\Trace\TracerInterface
            {
                return new \OpenTelemetry\API\Trace\NoopTracer;
            }
        };
    }
}
