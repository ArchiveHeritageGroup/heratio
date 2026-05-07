<?php

/**
 * FaceDetectionService - Heratio
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

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Face detection / recognition gate. Closes #75 (face_enabled +
 * face_backend settings now have a real consumer).
 *
 * The Heratio schema already has the destination tables
 * (digital_object_faces, actor_face_index) but no PHP service was
 * dispatching to a face-detection backend. This class is the gate +
 * dispatcher: when face_enabled is on AND a backend is configured,
 * detectAndStore(int $digitalObjectId) POSTs the file to the chosen
 * backend (face_recognition Python service / dlib / azure-face) and
 * persists the returned face boxes + embeddings into
 * digital_object_faces.
 *
 * Today the actual backend service runs on the GPU host - operator
 * configures face_backend to one of:
 *   - 'face_recognition' (default, Python face_recognition lib at the
 *     pool's translate-adapter-style endpoint - usually the same Ollama
 *     host with a separate port)
 *   - 'dlib'             (raw dlib HOG / CNN - faster, lower accuracy)
 *   - 'azure'            (cloud fallback - reads azure_face_endpoint
 *     setting; useful when local GPU is saturated)
 *   - 'noop'             (skip detection but log the call - testing)
 *
 * AhgGpuPoolService picks the local endpoint when backend != azure.
 * The 24GB GPU coming next week will host face-recognition + reid
 * jointly; until then the 8GB .78 host can run face_recognition with
 * the small (HOG) model.
 */
class FaceDetectionService
{
    public const BACKEND_FACE_RECOGNITION = 'face_recognition';
    public const BACKEND_DLIB             = 'dlib';
    public const BACKEND_AZURE            = 'azure';
    public const BACKEND_NOOP             = 'noop';

    public function isEnabled(): bool
    {
        return AhgSettingsService::getBool('face_enabled', false);
    }

    public function backend(): string
    {
        $b = (string) AhgSettingsService::get('face_backend', self::BACKEND_FACE_RECOGNITION);
        $b = strtolower(trim($b));
        $allowed = [
            self::BACKEND_FACE_RECOGNITION,
            self::BACKEND_DLIB,
            self::BACKEND_AZURE,
            self::BACKEND_NOOP,
        ];
        return in_array($b, $allowed, true) ? $b : self::BACKEND_FACE_RECOGNITION;
    }

    /**
     * Run face detection + persist results. Returns the number of faces
     * detected; 0 when disabled / no faces / backend error (logged).
     * Caller (ingest pipeline, manual rescan) should treat this as
     * non-fatal - face detection failures must not break the surrounding
     * upload / commit flow.
     */
    public function detectAndStore(int $digitalObjectId): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        if (!$do || empty($do->path) || empty($do->name)) {
            return 0;
        }

        $localPath = $this->resolveOnDiskPath($do->path, $do->name);
        if (!is_file($localPath) || !is_readable($localPath)) {
            return 0;
        }

        $backend = $this->backend();

        if ($backend === self::BACKEND_NOOP) {
            Log::info('[face-detect] noop backend - skipping', ['do_id' => $digitalObjectId]);
            return 0;
        }

        try {
            $faces = match ($backend) {
                self::BACKEND_AZURE => $this->detectAzure($localPath),
                self::BACKEND_FACE_RECOGNITION, self::BACKEND_DLIB => $this->detectLocalGpu($backend, $localPath),
                default => [],
            };
        } catch (\Throwable $e) {
            Log::warning('[face-detect] backend call failed', [
                'do_id' => $digitalObjectId,
                'backend' => $backend,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }

        if (empty($faces)) return 0;

        // Persist: one row per face into digital_object_faces. Schema is
        // operator-managed so tolerate column drift (use Schema::hasColumn
        // would slow the hot path; trust + catch instead).
        $count = 0;
        foreach ($faces as $face) {
            try {
                DB::table('digital_object_faces')->insert([
                    'digital_object_id' => $digitalObjectId,
                    'bbox_x'      => (int)   ($face['bbox']['x'] ?? 0),
                    'bbox_y'      => (int)   ($face['bbox']['y'] ?? 0),
                    'bbox_w'      => (int)   ($face['bbox']['w'] ?? 0),
                    'bbox_h'      => (int)   ($face['bbox']['h'] ?? 0),
                    'embedding'   => isset($face['embedding']) ? json_encode($face['embedding']) : null,
                    'confidence'  => isset($face['confidence']) ? (float) $face['confidence'] : null,
                    'backend'     => $backend,
                    'created_at'  => now(),
                ]);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('[face-detect] persist row failed', ['err' => $e->getMessage()]);
            }
        }
        return $count;
    }

    /**
     * Local GPU backend: POST the image to the GPU pool's face endpoint.
     * Uses AhgGpuPoolService::pickEndpoint to honour the operator's
     * priority/round-robin choice; falls back to the legacy translate-
     * adapter endpoint when the pool returns null.
     */
    private function detectLocalGpu(string $backend, string $imagePath): array
    {
        // Prefer a pool endpoint that supports the backend (operator tags
        // models_supported='face_recognition' on the relevant rows). Falls
        // back to whatever's available + the conservative 4GB vRAM floor
        // (HOG model is tiny; CNN model wants 6GB+).
        $minVram = $backend === self::BACKEND_DLIB ? 4 : 6;
        $url = AhgGpuPoolService::pickEndpoint($backend, $minVram);
        if ($url === null) {
            // Legacy fallback - operator's pre-pool config.
            $url = (string) AhgSettingsService::get('ai_face_endpoint', '');
        }
        if ($url === '') {
            Log::info('[face-detect] no endpoint available', ['backend' => $backend]);
            return [];
        }

        $resp = Http::timeout(60)->attach('image', file_get_contents($imagePath), basename($imagePath))
            ->post(rtrim($url, '/') . '/detect', ['backend' => $backend]);

        if (!$resp->successful()) {
            Log::info('[face-detect] backend non-2xx', ['http' => $resp->status(), 'url' => $url]);
            return [];
        }
        $body = $resp->json();
        return $body['faces'] ?? [];
    }

    /**
     * Azure Face API v1.0 (cloud fallback). Reads azure_face_endpoint +
     * azure_face_key settings - both must be set for this branch to fire.
     */
    private function detectAzure(string $imagePath): array
    {
        $endpoint = (string) AhgSettingsService::get('azure_face_endpoint', '');
        $key      = (string) AhgSettingsService::get('azure_face_key', '');
        if ($endpoint === '' || $key === '') {
            Log::info('[face-detect] azure backend selected but endpoint/key not configured');
            return [];
        }

        $resp = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
                'Content-Type' => 'application/octet-stream',
            ])
            ->withBody(file_get_contents($imagePath), 'application/octet-stream')
            ->timeout(30)
            ->post(rtrim($endpoint, '/') . '/face/v1.0/detect?returnFaceLandmarks=false&recognitionModel=recognition_04');

        if (!$resp->successful()) return [];

        // Normalise Azure's response shape to the same structure the local
        // backends return: each face {bbox: {x,y,w,h}, embedding?, confidence}.
        $out = [];
        foreach ($resp->json() ?: [] as $f) {
            $r = $f['faceRectangle'] ?? null;
            if (!$r) continue;
            $out[] = [
                'bbox' => ['x' => $r['left'], 'y' => $r['top'], 'w' => $r['width'], 'h' => $r['height']],
                'confidence' => 1.0, // Azure doesn't return per-detection score on detect
                'backend' => 'azure',
            ];
        }
        return $out;
    }

    /**
     * Map digital_object's URL-style path (/uploads/r/{ioId}/) + name
     * to the on-disk path under heratio.uploads_path. Mirrors the
     * resolver in EncryptionDerivativesBulkApplyCommand.
     */
    private function resolveOnDiskPath(string $path, string $name): string
    {
        $base = rtrim((string) config('heratio.uploads_path'), '/');
        $rel = preg_replace('#^/uploads/#', '', $path);
        $rel = ltrim((string) $rel, '/');
        return $base . '/' . $rel . $name;
    }
}
