<?php

namespace AhgAiServices\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HtrService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('HTR_SERVICE_URL', 'http://192.168.0.115:5006'), '/');
    }

    public function health(): ?array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/health");
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
        try {
            $response = Http::timeout(60)
                ->attach('file', fopen($filePath, 'r'), basename($filePath))
                ->post("{$this->baseUrl}/extract", [
                    'doc_type' => $docType,
                    'format' => $format,
                ]);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('HTR extract failed: ' . $e->getMessage());
            return null;
        }
    }

    public function batch(array $filePaths, string $format = 'csv'): ?array
    {
        try {
            $request = Http::timeout(60);
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
            return Http::timeout(30)->get("{$this->baseUrl}/download/{$jobId}/{$format}");
        } catch (\Exception $e) {
            Log::error('HTR download failed: ' . $e->getMessage());
            return null;
        }
    }

    public function saveAnnotation(string $imagePath, string $type, array $annotations): ?array
    {
        try {
            $response = Http::timeout(30)
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
            $response = Http::timeout(10)->get("{$this->baseUrl}/training/status");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('HTR training status failed: ' . $e->getMessage());
            return null;
        }
    }

    public function triggerTraining(): ?array
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/train");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('HTR trigger training failed: ' . $e->getMessage());
            return null;
        }
    }

    public function sources(): array
    {
        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/sources");
            return $response->successful() ? $response->json() : ['sources' => [], 'training_stats' => [], 'familysearch_configured' => false];
        } catch (\Exception $e) {
            Log::error('HTR sources failed: ' . $e->getMessage());
            return ['sources' => [], 'training_stats' => ['type_a' => 0, 'type_b' => 0, 'type_c' => 0], 'familysearch_configured' => false];
        }
    }

    public function downloadBatch(string $collectionId, int $count, string $docType = ''): ?array
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/download-batch", [
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
            $response = Http::timeout(10)->get("{$this->baseUrl}/download-status/{$jobId}");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('HTR download-status failed: ' . $e->getMessage());
            return null;
        }
    }

    public function downloadJobs(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/download-jobs");
            return $response->successful() ? $response->json() : ['jobs' => []];
        } catch (\Exception $e) {
            Log::error('HTR download-jobs failed: ' . $e->getMessage());
            return ['jobs' => []];
        }
    }
}
