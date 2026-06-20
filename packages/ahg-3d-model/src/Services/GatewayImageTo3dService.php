<?php

/**
 * GatewayImageTo3dService - Heratio creates 3D structure/objects through the
 * AHG AI gateway's GPU backend (#1323).
 *
 * Posts a still image to the gateway's `/ai/v1/image-to-3d` endpoint (a TRELLIS
 * image-to-3D model) and returns the generated asset bytes (glTF-binary `glb`,
 * Gaussian-splat `splat`/.ply, or `usdz`). Per the standing AI-gateway rule this
 * goes through the configured gateway (ai.theahg.co.za / the internal proxy),
 * never a direct GPU node.
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

namespace Ahg3dModel\Services;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\Http;

class GatewayImageTo3dService
{
    private const FORMATS = ['glb', 'splat', 'usdz'];

    /** File extension per output format. */
    private const EXT = ['glb' => 'glb', 'splat' => 'ply', 'usdz' => 'usdz'];

    /**
     * Generate a 3D asset from one image, or from several angled views of the
     * same subject (multi-view reconstruction).
     *
     * Pass a single path for single-image, or an array of paths for multi-view.
     * Multi-view attaches several `image` parts; the gateway forwards them all to
     * TRELLIS `run_multi_image` for a sharper, more complete structure. Degrades
     * safely to single-image against a gateway/worker that only reads one part.
     *
     * @param  string|array<int,string>  $imagePaths
     * @return array{bytes:string,ext:string}|null  null on any failure (fail-soft)
     */
    public function generate(string|array $imagePaths, string $format = 'glb'): ?array
    {
        $paths = array_values(array_filter(
            is_array($imagePaths) ? $imagePaths : [$imagePaths],
            'is_file'
        ));
        if (! $paths) {
            return null;
        }
        $format = in_array($format, self::FORMATS, true) ? $format : 'glb';

        $base = rtrim((string) (AhgSettingsService::get('api_url') ?: 'https://ai.theahg.co.za/ai/v1'), '/');
        $key = $this->gatewayKey();
        if ($key === '') {
            return null;
        }

        try {
            $req = Http::withToken($key)
                ->timeout((int) (AhgSettingsService::get('image_to_3d.timeout') ?: 900));
            foreach ($paths as $p) {                       // 1 part = single-image; N parts = multi-view
                $req = $req->attach('image', file_get_contents($p), basename($p));
            }
            $resp = $req->post($base.'/image-to-3d', ['format' => $format]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $resp->successful() || strlen((string) $resp->body()) < 1024) {
            return null;
        }

        $outFmt = strtolower((string) ($resp->header('X-Output-Format') ?: $format));

        return ['bytes' => $resp->body(), 'ext' => self::EXT[$outFmt] ?? 'glb'];
    }

    /** Reachability + auth probe (cheap-ish; the gateway still spins a worker). */
    public function available(): bool
    {
        return $this->gatewayKey() !== '';
    }

    /**
     * The gateway API key. The translate key (`mt.api_key`) is the proven-valid
     * gateway credential today; prefer a dedicated key if one is configured.
     */
    private function gatewayKey(): string
    {
        foreach (['image_to_3d.api_key', 'mt.api_key', 'api_key'] as $k) {
            $v = trim((string) (AhgSettingsService::get($k) ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }
}
