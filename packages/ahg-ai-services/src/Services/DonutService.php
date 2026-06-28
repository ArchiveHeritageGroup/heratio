<?php

/**
 * DonutService - Service for Heratio
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



namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DonutService
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct()
    {
        // #1368 — route Donut document-extraction through the AHG AI gateway
        // (/ai/v1/donut/*), never a direct :5008 node. A raw-node
        // DONUT_SERVICE_URL override is ignored so a stale env value cannot
        // bypass the gateway (metering/quota/failover/logging).
        $override = (string) env('DONUT_SERVICE_URL', '');
        $this->baseUrl = ($override !== '' && ! $this->looksLikeNode($override))
            ? rtrim($override, '/')
            : 'https://ai.theahg.co.za/ai/v1/donut';
        $this->apiKey = $this->resolveGatewayKey();
    }

    /** True when a URL points at a raw GPU node rather than the gateway (#1368). */
    private function looksLikeNode(string $url): bool
    {
        return (bool) preg_match('~:11434|://(?:127\.0\.0\.1|localhost|192\.168\.|10\.|172\.(?:1[6-9]|2\d|3[01])\.)~i', $url);
    }

    /**
     * Resolve the gateway Bearer key, same order NER/HTR use (#1368):
     * ahg_ner_settings.api_key, then ahg_ai_settings feature='general' api_key.
     */
    private function resolveGatewayKey(): string
    {
        try {
            $key = (string) (DB::table('ahg_ner_settings')
                ->where('setting_key', 'api_key')
                ->value('setting_value') ?? '');
            if ($key !== '') {
                return $key;
            }
            $key = (string) (DB::table('ahg_ai_settings')
                ->where('feature', 'general')
                ->where('setting_key', 'api_key')
                ->value('setting_value') ?? '');
            if ($key !== '') {
                return $key;
            }
        } catch (\Throwable) {
            // settings tables absent during boot — no key.
        }

        return '';
    }

    /** HTTP client carrying the gateway Bearer token (#1368). */
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->apiKey !== ''
            ? Http::withToken($this->apiKey)
            : Http::withHeaders([]);
    }

    public function health(): ?array
    {
        try {
            $response = $this->http()->timeout(10)->get("{$this->baseUrl}/health");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut health check failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract ILM fields from a document image.
     *
     * Returns: FS_RECORD_TYPE, FS_RECORD_TYPE_ID, EVENT_YEAR_ORIG,
     *          EVENT_PLACE_ORIG, non_genealogical, confidence, needs_review
     */
    public function extract(string $filePath, ?int $digitalObjectId = null): ?array
    {
        // #667 Phase 1 - per-tenant quota gate.
        try {
            app(\AhgAiServices\Services\QuotaService::class)->consume('donut');
        } catch (\AhgAiServices\Exceptions\QuotaExceededException $e) {
            Log::info('[ahg-ai] Donut blocked by quota', $e->toArray());
            throw $e;
        } catch (\Throwable) {
            // soft-fail
        }

        // #750 - resolve embedded-metadata hints for prompt + audit injection.
        $contextHints = $this->resolveContextHints($digitalObjectId);

        try {
            $t0 = microtime(true);
            $payload = [];
            if (!$contextHints->isEmpty()) {
                $payload['context_hints'] = $contextHints->toPromptPrefix();
            }
            $response = $this->http()->timeout(60)
                ->attach('file', fopen($filePath, 'r'), basename($filePath))
                ->post("{$this->baseUrl}/extract", $payload);
            if (!$response->successful()) {
                return null;
            }
            $body = $response->json();
            $modelId = (string) ($body['model'] ?? 'donut-gateway');
            $durationMs = (int) round((microtime(true) - $t0) * 1000);
            $this->logInferenceReceipt(
                'donut',
                $modelId,
                $body['model_version'] ?? null,
                'file:' . basename($filePath) . ':' . (is_readable($filePath) ? (string) filesize($filePath) : '?'),
                is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : (string) $body,
                ['latency_ms' => $durationMs],
            );
            try {
                app(\AhgAiServices\Services\CostService::class)->record('donut', $modelId, [
                    'duration_ms' => $durationMs,
                ]);
            } catch (\Throwable) {
                // never block inference
            }
            $this->logContextEventIfAny('donut', $digitalObjectId, $contextHints);
            return $body;
        } catch (\Exception $e) {
            Log::error('Donut extract failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * #750 - resolve embedded-metadata hints. Safe no-op when no DO id.
     */
    private function resolveContextHints(?int $digitalObjectId): \AhgAiServices\DTO\AiContextHints
    {
        if ($digitalObjectId === null || $digitalObjectId <= 0) {
            return \AhgAiServices\DTO\AiContextHints::empty();
        }
        try {
            return app(\AhgAiServices\Services\EmbeddedMetadataContextService::class)
                ->forDigitalObject($digitalObjectId);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] DonutService resolveContextHints failed: ' . $e->getMessage());
            return \AhgAiServices\DTO\AiContextHints::empty();
        }
    }

    /**
     * #750 - emit inference_context_used audit event.
     */
    private function logContextEventIfAny(string $service, ?int $digitalObjectId, \AhgAiServices\DTO\AiContextHints $hints): void
    {
        if ($digitalObjectId === null || $digitalObjectId <= 0 || $hints->isEmpty()) {
            return;
        }
        try {
            app(\AhgAiServices\Services\EmbeddedMetadataContextService::class)
                ->logContextEvent($service, $digitalObjectId, $hints);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] DonutService logContextEventIfAny failed: ' . $e->getMessage());
        }
    }

    private function logInferenceReceipt(
        string $service,
        string $modelId,
        ?string $modelVersion,
        string $input,
        string $output,
        array $extra = [],
    ): void {
        if (!class_exists(\AhgAiCompliance\Services\InferenceLogger::class)) {
            return;
        }
        try {
            app(\AhgAiCompliance\Services\InferenceLogger::class)
                ->log($service, $modelId, $modelVersion, $input, $output, $extra);
        } catch (\Throwable) {
            // chain failure must not abort inference
        }
    }

    /**
     * Extract ILM fields from an image by path (no upload needed if on same server).
     */
    public function extractByPath(string $imagePath): ?array
    {
        try {
            $response = $this->http()->timeout(60)
                ->post("{$this->baseUrl}/extract", [
                    'image_path' => $imagePath,
                ]);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut extract-by-path failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Batch extract ILM fields from multiple images.
     */
    public function batch(array $filePaths): ?array
    {
        try {
            $request = $this->http()->timeout(120);
            foreach ($filePaths as $path) {
                $request = $request->attach('files', fopen($path, 'r'), basename($path));
            }
            $response = $request->post("{$this->baseUrl}/batch");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut batch failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Classify document type only (lighter than full extraction).
     */
    public function classify(string $filePath): ?array
    {
        try {
            $response = $this->http()->timeout(30)
                ->attach('file', fopen($filePath, 'r'), basename($filePath))
                ->post("{$this->baseUrl}/classify");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut classify failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get median field positions from training annotations.
     */
    public function positions(string $docType = 'type_a'): ?array
    {
        try {
            $response = $this->http()->timeout(15)->get("{$this->baseUrl}/positions", [
                'doc_type' => $docType,
            ]);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut positions failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download results for a completed extraction job.
     */
    public function downloadResult(string $jobId): ?array
    {
        try {
            $response = $this->http()->timeout(30)->get("{$this->baseUrl}/download/{$jobId}");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut download failed: ' . $e->getMessage());
            return null;
        }
    }

    public function trainingStatus(): ?array
    {
        try {
            $response = $this->http()->timeout(10)->get("{$this->baseUrl}/training/status");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut training status failed: ' . $e->getMessage());
            return null;
        }
    }

    public function triggerTraining(int $epochs = 15, int $batchSize = 2): ?array
    {
        try {
            $response = $this->http()->timeout(30)->post("{$this->baseUrl}/train", [
                'epochs' => $epochs,
                'batch_size' => $batchSize,
            ]);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut trigger training failed: ' . $e->getMessage());
            return null;
        }
    }
}
