<?php

/**
 * HtrService - Service for Heratio
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

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HtrService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        // heratio#131 - resolve the HTR endpoint from settings, routed through
        // the gateway's legacy HTR proxy. HTR_SERVICE_URL stays a developer-
        // only override, no longer the production source of truth.
        $htrUrl = rtrim($this->setting('htr_url', 'https://ai.theahg.co.za/ai/v1/htr'), '/');
        $this->baseUrl = rtrim(env('HTR_SERVICE_URL', $htrUrl . '/legacy'), '/');
        $this->apiKey  = $this->setting('api_key', '');
    }

    /** heratio#131 - resolve an AI setting (ahg_ner_settings, then ahg_ai_settings general). */
    private function setting(string $key, string $default): string
    {
        try {
            $v = \Illuminate\Support\Facades\DB::table('ahg_ner_settings')
                ->where('setting_key', $key)->value('setting_value');
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
            $v = \Illuminate\Support\Facades\DB::table('ahg_ai_settings')
                ->where('feature', 'general')->where('setting_key', $key)->value('setting_value');
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
        } catch (\Throwable $e) {
            // settings tables absent during boot - fall through to the default
        }
        return $default;
    }

    /** heratio#131 - HTTP client carrying the gateway Bearer token. */
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
            Log::error('HTR health check failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract handwriting from a document.
     * @param string $format  One of: all, json, csv, ilm, gedcom
     */
    public function extract(string $filePath, string $docType = 'auto', string $format = 'all'): ?array
    {
        // #667 Phase 1 - per-tenant quota gate. Bubble the quota
        // exception so the caller can surface a real "rate limited"
        // signal instead of just an empty result.
        try {
            app(\AhgAiServices\Services\QuotaService::class)->consume('htr');
        } catch (\AhgAiServices\Exceptions\QuotaExceededException $e) {
            Log::info('[ahg-ai] HTR blocked by quota', $e->toArray());
            throw $e;
        } catch (\Throwable) {
            // soft-fail
        }

        try {
            $t0 = microtime(true);
            $response = $this->http()->timeout(60)
                ->attach('file', fopen($filePath, 'r'), basename($filePath))
                ->post("{$this->baseUrl}/extract", [
                    'doc_type' => $docType,
                    'format' => $format,
                ]);
            if (!$response->successful()) {
                return null;
            }
            $body = $response->json();
            $modelId = (string) ($body['model'] ?? 'htr-gateway');
            $durationMs = (int) round((microtime(true) - $t0) * 1000);
            $this->logInferenceReceipt(
                'htr',
                $modelId,
                $body['model_version'] ?? null,
                'file:' . basename($filePath) . ':' . (is_readable($filePath) ? (string) filesize($filePath) : '?'),
                is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : (string) $body,
                ['latency_ms' => $durationMs],
            );
            try {
                app(\AhgAiServices\Services\CostService::class)->record('htr', $modelId, [
                    'duration_ms' => $durationMs,
                ]);
            } catch (\Throwable) {
                // never block inference
            }
            return $body;
        } catch (\Exception $e) {
            Log::error('HTR extract failed: ' . $e->getMessage());
            return null;
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
     * Extract handwriting AND record the inference.
     *
     * Issue #61 / ADR-0002 Phase 2e: canonical entry point for HTR callers
     * that have a target IO id (currently ahg-scan/Jobs/ProcessScanFile).
     * Logs one inference per page transcribed: input_hash = sha256 of the
     * image bytes, output_hash = sha256 of the response json, confidence =
     * 1 - CER when exposed (CER is error rate; flip to "higher is better"
     * to match the contract).
     */
    public function extractAndRecord(string $filePath, int $informationObjectId, string $docType = 'auto', string $format = 'all', ?int $userId = null): ?array
    {
        $t0 = microtime(true);
        $imageBytes = @file_get_contents($filePath);
        $result = $this->extract($filePath, $docType, $format);
        $elapsedMs = (int) round((microtime(true) - $t0) * 1000);

        try {
            $svc = app(\AhgProvenanceAi\Services\InferenceService::class);
            $inputHash  = is_string($imageBytes) ? hash('sha256', $imageBytes) : str_repeat('0', 64);
            $outputJson = is_array($result) ? (string) json_encode($result, JSON_UNESCAPED_UNICODE) : '';
            [$outHash, $outExc] = \AhgProvenanceAi\DTO\InferenceRecord::hashAndExcerpt($outputJson);

            // CER (character error rate) is sometimes exposed as 'cer' in the
            // result; convert to "higher is better" confidence by 1 - CER.
            $confidence = null;
            if (is_array($result) && isset($result['cer']) && is_numeric($result['cer'])) {
                $confidence = max(0.0, min(1.0, 1.0 - (float) $result['cer']));
            } elseif (is_array($result) && isset($result['confidence']) && is_numeric($result['confidence'])) {
                $confidence = max(0.0, min(1.0, (float) $result['confidence']));
            }

            $svc->record(new \AhgProvenanceAi\DTO\InferenceRecord(
                serviceName:      'HTR',
                modelName:        (string) ($result['model'] ?? 'unknown'),
                modelVersion:     (string) ($result['model_version'] ?? 'unknown'),
                inputHash:        $inputHash,
                outputHash:       $outHash,
                targetEntityType: 'information_object',
                targetEntityId:   $informationObjectId,
                targetField:      'physical_characteristics',
                confidence:       $confidence,
                standard:         'ISAD(G)-physical_characteristics',
                endpoint:         $this->baseUrl . '/extract',
                inputExcerpt:     'image:' . basename($filePath),
                outputExcerpt:    $outExc,
                elapsedMs:        $elapsedMs,
                userId:           $userId,
            ));
        } catch (\Throwable $e) {
            Log::warning('HtrService::extractAndRecord: provenance write failed: ' . $e->getMessage());
        }

        return $result;
    }

    public function batch(array $filePaths, string $format = 'csv'): ?array
    {
        try {
            $request = $this->http()->timeout(60);
            foreach ($filePaths as $path) {
                $request = $request->attach('files', fopen($path, 'r'), basename($path));
            }
            $response = $request->post("{$this->baseUrl}/batch", ['format' => $format]);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('HTR batch failed: ' . $e->getMessage());
            return null;
        }
    }

    public function downloadOutput(string $jobId, string $format)
    {
        try {
            return $this->http()->timeout(30)->get("{$this->baseUrl}/download/{$jobId}/{$format}");
        } catch (\Exception $e) {
            Log::error('HTR download failed: ' . $e->getMessage());
            return null;
        }
    }

    public function saveAnnotation(string $imagePath, string $type, array $annotations): ?array
    {
        try {
            $response = $this->http()->timeout(30)
                ->attach('image', fopen($imagePath, 'r'), basename($imagePath))
                ->post("{$this->baseUrl}/annotate", [
                    'type' => $type,
                    'annotations' => json_encode($annotations),
                ]);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('HTR annotate failed: ' . $e->getMessage());
            return null;
        }
    }

    public function trainingStatus(): ?array
    {
        try {
            $response = $this->http()->timeout(10)->get("{$this->baseUrl}/training/status");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('HTR training status failed: ' . $e->getMessage());
            return null;
        }
    }

    public function triggerTraining(): ?array
    {
        try {
            $response = $this->http()->timeout(30)->post("{$this->baseUrl}/train");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('HTR trigger training failed: ' . $e->getMessage());
            return null;
        }
    }

    public function sources(): array
    {
        try {
            $response = $this->http()->timeout(15)->get("{$this->baseUrl}/sources");
            return $response->successful() ? $response->json() : ['sources' => [], 'training_stats' => [], 'familysearch_configured' => false];
        } catch (\Exception $e) {
            Log::error('HTR sources failed: ' . $e->getMessage());
            return ['sources' => [], 'training_stats' => ['type_a' => 0, 'type_b' => 0, 'type_c' => 0], 'familysearch_configured' => false];
        }
    }

    public function downloadBatch(string $collectionId, int $count, string $docType = ''): ?array
    {
        try {
            $response = $this->http()->timeout(30)->post("{$this->baseUrl}/download-batch", [
                'collection_id' => $collectionId,
                'count' => $count,
                'doc_type' => $docType,
            ]);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('HTR download-batch failed: ' . $e->getMessage());
            return null;
        }
    }

    public function downloadStatus(string $jobId): ?array
    {
        try {
            $response = $this->http()->timeout(10)->get("{$this->baseUrl}/download-status/{$jobId}");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('HTR download-status failed: ' . $e->getMessage());
            return null;
        }
    }

    public function downloadJobs(): array
    {
        try {
            $response = $this->http()->timeout(10)->get("{$this->baseUrl}/download-jobs");
            return $response->successful() ? $response->json() : ['jobs' => []];
        } catch (\Exception $e) {
            Log::error('HTR download-jobs failed: ' . $e->getMessage());
            return ['jobs' => []];
        }
    }
}
