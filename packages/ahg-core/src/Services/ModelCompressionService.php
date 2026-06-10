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
    public const SUPPORTED = ['obj', 'fbx', 'glb', 'gltf'];

    /** Directory holding the Node tool binaries (override via heratio.model_tools_bin). */
    private function binDir(): string
    {
        return rtrim((string) config('heratio.model_tools_bin', '/opt/ahg-model-tools/node_modules/.bin'), '/');
    }

    /** FBX2glTF binary path (override via heratio.fbx2gltf_bin). Standalone tool, not a Node bin. */
    private function fbx2gltfBin(): string
    {
        return (string) config('heratio.fbx2gltf_bin', '/opt/ahg-model-tools/FBX2glTF');
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
            // 1) Get a glb to compress. OBJ -> glb via obj2gltf; FBX -> glb via FBX2glTF;
            //    glb/gltf go straight in.
            if ($ext === 'obj') {
                $r = Process::timeout(600)->run([$bin.'/obj2gltf', '-i', $srcAbs, '-o', $glb]);
                if (! $r->successful() || ! is_file($glb)) {
                    Log::warning('ModelCompression: obj2gltf failed', ['src' => $srcAbs, 'err' => $r->errorOutput()]);

                    return null;
                }
            } elseif ($ext === 'fbx') {
                $fbxBin = $this->fbx2gltfBin();
                if (! is_executable($fbxBin)) {
                    Log::warning('ModelCompression: FBX2glTF binary not found/executable', ['bin' => $fbxBin, 'src' => $srcAbs]);

                    return null;
                }
                // --binary emits a single .glb. -o is a base path; FBX2glTF appends the
                // mesh name, so write to a scratch dir and pick up whatever .glb it produced.
                $fdir = $tmp.'/fbx';
                @mkdir($fdir, 0775, true);
                $r = Process::timeout(600)->run([$fbxBin, '--binary', '--input', $srcAbs, '--output', $fdir.'/m']);
                $produced = glob($fdir.'/*.glb') ?: [];
                if (! $r->successful() || empty($produced) || ! @copy($produced[0], $glb)) {
                    Log::warning('ModelCompression: FBX2glTF failed', ['src' => $srcAbs, 'err' => $r->errorOutput()]);

                    return null;
                }
            } else {
                $glb = $srcAbs;   // gltf-transform reads .glb / .gltf directly
            }

            // 2) Downscale oversized textures (cap configurable, default 1024) so
            // texture-heavy models shrink too (Draco only compresses geometry; a
            // 2048^2 PNG is ~4-5MB and Draco never touches it). Downscaling to a
            // 1024 cap takes that to ~1-2MB while staying universally loadable.
            // Falls back to the un-resized glb if the codec is unavailable.
            $cap = (string) max(64, (int) config('heratio.model_texture_cap', 1024));
            $resized = $tmp.'/resized.glb';
            $rr = Process::timeout(600)->run([$bin.'/gltf-transform', 'resize', $glb, $resized, '--width', $cap, '--height', $cap]);
            $stage = ($rr->successful() && is_file($resized) && filesize($resized) > 64) ? $resized : $glb;

            // 3) Optional WebP texture re-encode. OFF by default: the exhibition
            // walkthrough's three.js r128 GLTFLoader cannot decode the
            // EXT_texture_webp extension (added upstream in r131), so WebP models
            // load as "nothing" and hang there. Enable heratio.model_webp only
            // once the walkthrough loader is upgraded. model-viewer (the sector
            // show pages) decodes WebP fine - this guard is purely for the
            // walkthrough's older loader.
            if (config('heratio.model_webp', false)) {
                $webp = $tmp.'/webp.glb';
                $rw = Process::timeout(600)->run([$bin.'/gltf-transform', 'webp', $stage, $webp]);
                if ($rw->successful() && is_file($webp) && filesize($webp) > 64) {
                    $stage = $webp;
                }
            }
            $toDraco = $stage;

            // 4) Draco-compress geometry.
            $r2 = Process::timeout(600)->run([$bin.'/gltf-transform', 'draco', $toDraco, $out]);
            if (! $r2->successful() || ! is_file($out) || filesize($out) < 64) {
                Log::warning('ModelCompression: draco failed', ['src' => $srcAbs, 'err' => $r2->errorOutput()]);
                // Draco can fail on some meshes; fall back to the WebP/resized stage
                // if it is itself smaller than the source.
                $out = $toDraco;
            }

            // 5) No-inflation guard. Draco adds overhead that can make an
            // already-small / texture-light model LARGER. Never replace an
            // original with a bigger file - the caller treats null as "skip".
            $origExt = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));
            if (in_array($origExt, ['glb', 'gltf'], true)
                && is_file($out) && is_file($srcAbs)
                && filesize($out) >= filesize($srcAbs)) {
                Log::info('ModelCompression: output not smaller than source, skipping', [
                    'src' => $srcAbs, 'src_bytes' => filesize($srcAbs), 'out_bytes' => filesize($out),
                ]);

                return null;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('ModelCompression: exception', ['src' => $srcAbs, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
