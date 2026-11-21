<?php

/**
 * GatewayFaceDetector - face detection and face embeddings through the AHG
 * AI gateway (heratio#1764).
 *
 * POSTs the image (base64 JSON) to the gateway face route, default
 * /ai/v1/vision/faces on the host of the configured gateway URL, with the
 * same gateway key every other ahg-ai-services client uses
 * (AiServicesSettings::gatewayKey()). Never a GPU node port.
 *
 * Contract: docs/technical/face-recognition-gateway-contract.md. Response
 * {faces: [{box: [x, y, w, h], embedding: [...], score}], model?}.
 *
 * Failure never throws: no key, quota exhausted, timeout, a non-2xx answer
 * or a 404 (the route not deployed yet - logged once per day) all return
 * no faces, so ingest carries on.
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

declare(strict_types=1);

namespace AhgAiServices\Services;

use AhgAiServices\Contracts\FaceDetectorInterface;
use AhgAiServices\Support\AiServicesSettings;
use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GatewayFaceDetector implements FaceDetectorInterface
{
    public const DEFAULT_GATEWAY = 'https://ai.theahg.co.za/ai/v1';

    public const DEFAULT_PATH = '/ai/v1/vision/faces';

    public function __construct(private QuotaService $quotaService, private CostService $costService) {}

    /** Full URL of the face route: the configured gateway's host plus the face_gateway_path setting. */
    public function url(): string
    {
        $gw = (string) (AiServicesSettings::apiUrl() ?: self::DEFAULT_GATEWAY);
        $p = parse_url($gw) ?: [];
        $origin = ($p['scheme'] ?? 'https').'://'.($p['host'] ?? 'ai.theahg.co.za').(isset($p['port']) ? ':'.$p['port'] : '');
        $path = trim((string) AhgSettingsService::get('face_gateway_path', self::DEFAULT_PATH)) ?: self::DEFAULT_PATH;

        return $origin.'/'.ltrim($path, '/');
    }

    public function detect(string $imagePath): array
    {
        $empty = ['faces' => [], 'model' => 'gateway', 'duration_ms' => 0];
        $key = (string) AiServicesSettings::gatewayKey();
        if ($key === '' || ! is_readable($imagePath)) {
            return $empty;
        }

        try {
            $this->quotaService->consume('face_detect');
        } catch (\Throwable $e) {
            Log::info('[face-gateway] quota refused the call: '.$e->getMessage());

            return $empty;
        }

        $started = microtime(true);
        try {
            $resp = Http::withToken($key)
                ->acceptJson()
                ->timeout(max(5, AiServicesSettings::apiTimeout()))
                ->post($this->url(), [
                    'image' => base64_encode((string) file_get_contents($imagePath)),
                    'filename' => basename($imagePath),
                    'return_embedding' => true,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[face-gateway] call failed: '.$e->getMessage());

            return $empty;
        }
        $ms = (int) round((microtime(true) - $started) * 1000);

        if ($resp->status() === 404) {
            // The gateway route is not deployed yet: say so once a day, not per image.
            if (Cache::add('face_gateway_404_logged', 1, 86400)) {
                Log::warning('[face-gateway] '.$this->url().' answered 404 - the gateway face route is not deployed; no faces stored. See docs/technical/face-recognition-gateway-contract.md');
            }

            return $empty;
        }
        if (! $resp->successful()) {
            Log::warning('[face-gateway] non-2xx answer', ['status' => $resp->status()]);

            return $empty;
        }

        $model = (string) ($resp->json('model') ?: 'gateway-face');
        $faces = [];
        foreach ((array) $resp->json('faces', []) as $f) {
            $box = $f['box'] ?? null;
            if (! is_array($box) || count($box) < 4) {
                continue;
            }
            $face = [
                'x' => (int) $box[0], 'y' => (int) $box[1], 'w' => (int) $box[2], 'h' => (int) $box[3],
                'score' => (float) ($f['score'] ?? 0),
            ];
            if (isset($f['embedding']) && is_array($f['embedding']) && $f['embedding'] !== []) {
                $face['embedding'] = array_map('floatval', array_values($f['embedding']));
            }
            $faces[] = $face;
        }

        try {
            $this->costService->record('face_detect', $model, ['tokens_in' => 0, 'tokens_out' => 0, 'duration_ms' => $ms]);
        } catch (\Throwable) {
            // metering is best effort
        }

        return ['faces' => $faces, 'model' => $model, 'duration_ms' => $ms];
    }

    /** A gateway key is configured. Makes no call (no live probe of the route). */
    public function health(): bool
    {
        return (string) AiServicesSettings::gatewayKey() !== '';
    }
}
