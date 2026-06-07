<?php

/**
 * ModelCompressionService - Service for Heratio
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
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Optimise 3D models for the web: convert OBJ to glTF where needed and apply
 * Draco mesh compression, producing a small .glb that loads in the walkthrough
 * without freezing the browser (a 66MB OBJ becomes ~1-2MB).
 *
 * Requires the Node tools installed on the host (see docs/model-optimisation-setup.md):
 *   /opt/ahg-model-tools/node_modules/.bin/{obj2gltf,gltf-transform}
 */
class ModelCompressionService
{
    /** Formats we can turn into a Draco glb. STL/PLY are not handled yet. */
    public const SUPPORTED = ['obj', 'glb', 'gltf'];

    /** Directory holding the Node tool binaries (override via heratio.model_tools_bin). */
    private function binDir(): string
    {
        return rtrim((string) config('heratio.model_tools_bin', '/opt/ahg-model-tools/node_modules/.bin'), '/');
    }

    /** Are the required CLI tools installed and executable on this host? */
    public function toolsAvailable(): bool
    {
        return is_executable($this->binDir().'/obj2gltf') && is_executable($this->binDir().'/gltf-transform');
    }

    public function supports(string $ext): bool
    {
        return in_array(strtolower($ext), self::SUPPORTED, true);
    }

    /**
     * Convert (if needed) and Draco-compress a model to a .glb.
     * Returns the absolute path of the compressed glb (in a temp dir the caller
     * must move/clean), or null on failure / unsupported input.
     */
    public function compressToGlb(string $srcAbs, string $ext): ?string
    {
        $ext = strtolower($ext);
        if (! is_file($srcAbs) || ! $this->supports($ext) || ! $this->toolsAvailable()) {
            return null;
        }

        $bin = $this->binDir();
        $tmp = sys_get_temp_dir().'/ahgmc_'.bin2hex(random_bytes(6));
        @mkdir($tmp, 0775, true);
        $glb = $tmp.'/in.glb';
        $out = $tmp.'/out.glb';

        try {
            // 1) Get a glb to compress. OBJ -> glb via obj2gltf; glb/gltf go straight in.
            if ($ext === 'obj') {
                $r = Process::timeout(600)->run([$bin.'/obj2gltf', '-i', $srcAbs, '-o', $glb]);
                if (! $r->successful() || ! is_file($glb)) {
                    Log::warning('ModelCompression: obj2gltf failed', ['src' => $srcAbs, 'err' => $r->errorOutput()]);

                    return null;
                }
            } else {
                $glb = $srcAbs;   // gltf-transform reads .glb / .gltf directly
            }

            // 2) Downscale oversized textures (cap 2048) so texture-heavy models shrink too
            // (Draco only compresses geometry). Falls back to the un-resized glb if unsupported.
            $resized = $tmp.'/resized.glb';
            $rr = Process::timeout(600)->run([$bin.'/gltf-transform', 'resize', $glb, $resized, '--width', '2048', '--height', '2048']);
            $toDraco = ($rr->successful() && is_file($resized) && filesize($resized) > 64) ? $resized : $glb;

            // 3) Draco-compress.
            $r2 = Process::timeout(600)->run([$bin.'/gltf-transform', 'draco', $toDraco, $out]);
            if (! $r2->successful() || ! is_file($out) || filesize($out) < 64) {
                Log::warning('ModelCompression: draco failed', ['src' => $srcAbs, 'err' => $r2->errorOutput()]);

                return null;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('ModelCompression: exception', ['src' => $srcAbs, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
