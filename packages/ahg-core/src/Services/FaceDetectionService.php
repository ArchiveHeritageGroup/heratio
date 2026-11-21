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
 * heratio#1764: the default backend is 'gateway' - the AHG AI gateway
 * face route (GatewayFaceDetector), the only sanctioned path to a model.
 * The old 'face_recognition' and 'dlib' backends called a GPU node
 * directly, round the gateway, and were removed on 20 Nov 2026; a site
 * still configured with either now uses the gateway.
 *
 * Operator configures face_backend to one of:
 *   - 'gateway'  (default, AHG AI gateway /ai/v1/vision/faces)
 *   - 'azure'    (cloud fallback - reads azure_face_endpoint setting)
 *   - 'noop'     (skip detection but log the call - testing)
 */
class FaceDetectionService
{
    /** heratio#1764: through the AHG AI gateway (the default). */
    public const BACKEND_GATEWAY = 'gateway';

    public const BACKEND_AZURE = 'azure';

    public const BACKEND_NOOP = 'noop';

    /** ponytail: a fixed ceiling on frames per video (about 2.8 h at 10 s); make it a setting if longer masters need full coverage. */
    public const MAX_VIDEO_FRAMES = 1000;

    public function isEnabled(): bool
    {
        return AhgSettingsService::getBool('face_enabled', false);
    }

    public function backend(): string
    {
        $b = (string) AhgSettingsService::get('face_backend', self::BACKEND_GATEWAY);
        $b = strtolower(trim($b));
        $allowed = [
            self::BACKEND_GATEWAY,
            self::BACKEND_AZURE,
            self::BACKEND_NOOP,
        ];

        return in_array($b, $allowed, true) ? $b : self::BACKEND_GATEWAY;
    }

    /** Seconds between sampled video frames (setting face_video_interval, default 10, at least 1). */
    public function videoInterval(): float
    {
        $v = AhgSettingsService::get('face_video_interval');

        return max(1.0, is_numeric($v) ? (float) $v : 10.0);
    }

    /**
     * Run face detection + persist results. Returns the number of faces
     * stored; 0 when disabled / no faces / backend error (logged).
     * Caller (ingest pipeline, manual rescan) should treat this as
     * non-fatal - face detection failures must not break the surrounding
     * upload / commit flow.
     *
     * Images are sent as they are (non JPEG/PNG converted to JPEG first).
     * Video masters are sampled: one frame every face_video_interval
     * seconds, each frame sent to the detector, faces stored with the
     * frame time. A re-run replaces the object's unnamed faces and keeps
     * the ones staff have named.
     */
    public function detectAndStore(int $digitalObjectId): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        if (! $do || empty($do->path) || empty($do->name)) {
            return 0;
        }

        $localPath = $this->resolveOnDiskPath($do->path, $do->name);
        if (! is_file($localPath) || ! is_readable($localPath)) {
            return 0;
        }

        $backend = $this->backend();

        if ($backend === self::BACKEND_NOOP) {
            Log::info('[face-detect] noop backend - skipping', ['do_id' => $digitalObjectId]);

            return 0;
        }

        $isVideo = str_starts_with(strtolower((string) ($do->mime_type ?? '')), 'video/');
        $tmp = [];
        try {
            $samples = $isVideo ? $this->videoFrames($localPath, $tmp) : [[null, $this->imageForDetector($localPath, (string) ($do->mime_type ?? ''), $tmp)]];
            $found = [];
            foreach ($samples as [$time, $path]) {
                foreach ($this->detectFile($backend, $path) as $face) {
                    $found[] = [$time, $face];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[face-detect] backend call failed', [
                'do_id' => $digitalObjectId,
                'backend' => $backend,
                'error' => $e->getMessage(),
            ]);

            return 0;
        } finally {
            foreach ($tmp as $f) {
                @unlink($f);
            }
        }

        DB::table('digital_object_faces')->where('digital_object_id', $digitalObjectId)->whereNull('person_id')->delete();

        $count = 0;
        foreach ($found as $i => [$time, $face]) {
            try {
                DB::table('digital_object_faces')->insert([
                    'digital_object_id' => $digitalObjectId,
                    'face_index' => $i,
                    'frame_time_seconds' => $time,
                    'bounding_box' => json_encode(['x' => $face['x'], 'y' => $face['y'], 'w' => $face['w'], 'h' => $face['h']]),
                    'embedding' => $face['embedding'] ? FaceRecognitionService::pack($face['embedding']) : null,
                    'embedding_dim' => $face['embedding'] ? count($face['embedding']) : null,
                    'confidence' => $face['score'],
                    'backend' => $backend,
                    'created_at' => now(),
                ]);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('[face-detect] persist row failed', ['err' => $e->getMessage()]);
            }
        }

        return $count;
    }

    /**
     * Faces in one image file, whatever the backend, as
     * list<{x, y, w, h, score, embedding: ?list<float>}>.
     */
    public function detectFile(string $backend, string $imagePath): array
    {
        $raw = match ($backend) {
            self::BACKEND_GATEWAY => app(\AhgAiServices\Services\GatewayFaceDetector::class)->detect($imagePath)['faces'] ?? [],
            self::BACKEND_AZURE => $this->detectAzure($imagePath),
            default => [],
        };

        $out = [];
        foreach ($raw as $f) {
            $b = $f['bbox'] ?? $f; // legacy backends nest the box under bbox
            $emb = isset($f['embedding']) && is_array($f['embedding']) && $f['embedding'] !== [] ? array_map('floatval', array_values($f['embedding'])) : null;
            $out[] = [
                'x' => (int) ($b['x'] ?? 0), 'y' => (int) ($b['y'] ?? 0), 'w' => (int) ($b['w'] ?? 0), 'h' => (int) ($b['h'] ?? 0),
                'score' => (float) ($f['score'] ?? $f['confidence'] ?? 0),
                'embedding' => $emb,
            ];
        }

        return $out;
    }

    /**
     * Sample a video: one JPEG every videoInterval() seconds from 0 to the
     * duration ffprobe reports, at most MAX_VIDEO_FRAMES.
     *
     * @param  list<string>  $tmp  temp files made, for the caller to delete
     * @return list<array{0: float, 1: string}> [frame time in seconds, jpeg path]
     */
    public function videoFrames(string $videoPath, array &$tmp): array
    {
        $duration = (float) trim((string) @shell_exec('ffprobe -v error -show_entries format=duration -of default=nw=1:nk=1 '.escapeshellarg($videoPath).' 2>/dev/null'));
        if ($duration <= 0) {
            return [];
        }
        $step = $this->videoInterval();
        $out = [];
        for ($i = 0, $t = 0.0; $t < $duration && $i < self::MAX_VIDEO_FRAMES; $i++, $t = $i * $step) {
            $jpg = tempnam(sys_get_temp_dir(), 'face-frame-').'.jpg';
            $tmp[] = $jpg;
            @shell_exec(sprintf('ffmpeg -v error -y -ss %s -i %s -frames:v 1 -q:v 2 %s 2>/dev/null',
                escapeshellarg(number_format($t, 3, '.', '')), escapeshellarg($videoPath), escapeshellarg($jpg)));
            if (is_file($jpg) && filesize($jpg) > 0) {
                $out[] = [round($t, 3), $jpg];
            }
        }

        return $out;
    }

    /** JPEG and PNG go as they are; anything else (TIFF, JP2, ...) is converted to JPEG when Imagick is present. */
    private function imageForDetector(string $path, string $mime, array &$tmp): string
    {
        if (in_array(strtolower($mime), ['image/jpeg', 'image/png'], true) || ! class_exists(\Imagick::class)) {
            return $path;
        }
        try {
            $img = new \Imagick($path.'[0]');
            $img->setImageFormat('jpeg');
            $jpg = tempnam(sys_get_temp_dir(), 'face-img-').'.jpg';
            $img->writeImage($jpg);
            $tmp[] = $jpg;

            return $jpg;
        } catch (\Throwable) {
            return $path;
        }
    }

    /**
     * Azure Face API v1.0 (cloud fallback). Reads azure_face_endpoint +
     * azure_face_key settings - both must be set for this branch to fire.
     */
    private function detectAzure(string $imagePath): array
    {
        $endpoint = (string) AhgSettingsService::get('azure_face_endpoint', '');
        $key = (string) AhgSettingsService::get('azure_face_key', '');
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
            ->post(rtrim($endpoint, '/').'/face/v1.0/detect?returnFaceLandmarks=false&recognitionModel=recognition_04');

        if (! $resp->successful()) {
            return [];
        }

        // Normalise Azure's response shape to the same structure the local
        // backends return: each face {bbox: {x,y,w,h}, embedding?, confidence}.
        $out = [];
        foreach ($resp->json() ?: [] as $f) {
            $r = $f['faceRectangle'] ?? null;
            if (! $r) {
                continue;
            }
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

        return $base.'/'.$rel.$name;
    }
}
