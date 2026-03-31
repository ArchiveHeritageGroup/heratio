<?php

/**
 * DonutService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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

class DonutService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('DONUT_SERVICE_URL', 'http://192.168.0.115:5008'), '/');
    }

    public function health(): ?array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/health");
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
    public function extract(string $filePath): ?array
    {
        try {
            $response = Http::timeout(60)
                ->attach('file', fopen($filePath, 'r'), basename($filePath))
                ->post("{$this->baseUrl}/extract");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut extract failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract ILM fields from an image by path (no upload needed if on same server).
     */
    public function extractByPath(string $imagePath): ?array
    {
        try {
            $response = Http::timeout(60)
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
            $request = Http::timeout(120);
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
            $response = Http::timeout(30)
                ->attach('file', fopen($filePath, 'r'), basename($filePath))
                ->post("{$this->baseUrl}/classify");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut classify failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download results for a completed extraction job.
     */
    public function downloadResult(string $jobId): ?array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/download/{$jobId}");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut download failed: ' . $e->getMessage());
            return null;
        }
    }

    public function trainingStatus(): ?array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/training/status");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Donut training status failed: ' . $e->getMessage());
            return null;
        }
    }

    public function triggerTraining(int $epochs = 15, int $batchSize = 2): ?array
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/train", [
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
