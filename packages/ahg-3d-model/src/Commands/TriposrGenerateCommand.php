<?php

/**
 * TriposrGenerateCommand — call the TripoSR API to convert a 2D image into a
 * 3D GLB mesh. By default, the result is "imported" — copied into the IO's
 * upload dir and registered as a digital_object + object_3d_model row.
 *
 * Pass --no-import to keep the result in a temp location (used by the
 * web preview flow which lets the user inspect the model before committing).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace Ahg3dModel\Commands;

use CURLFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TriposrGenerateCommand extends Command
{
    protected $signature = 'ahg:triposr-generate
        {--image= : Path to the source image on disk}
        {--object-id= : Information object id to import the result into}
        {--import : Persist the result to the IO storage dir + DB (default)}
        {--no-import : Save to temp dir only — return the path}
        {--remove-bg= : Override remove_bg (defaults to admin setting)}
        {--resolution= : Override marching cubes resolution (defaults to admin setting)}
        {--texture : Force bake_texture on (defaults to admin setting)}
        {--health}
        {--preload}
        {--stats}
        {--jobs}';

    protected $description = 'Generate 3D models from 2D images via TripoSR';

    public function handle(): int
    {
        // Health/preload/stats sub-modes — defer to dedicated commands when present
        if ($this->option('health')) {
            return $this->call('ahg:triposr-health');
        }
        if ($this->option('preload')) {
            return $this->call('ahg:triposr-preload');
        }

        $image = (string) $this->option('image');
        if ($image === '' || !is_file($image)) {
            $this->error('--image is required and must point to an existing file.');
            return 1;
        }

        // Pull TripoSR config
        $cfg = DB::table('viewer_3d_settings')
            ->whereIn('setting_key', [
                'triposr_enabled', 'triposr_api_url', 'triposr_mode', 'triposr_remote_url',
                'triposr_remote_api_key', 'triposr_timeout', 'triposr_remove_bg',
                'triposr_foreground_ratio', 'triposr_mc_resolution', 'triposr_bake_texture',
            ])
            ->pluck('setting_value', 'setting_key')->all();

        if (((string) ($cfg['triposr_enabled'] ?? '0')) !== '1') {
            $this->error('TripoSR is disabled in settings (/admin/3d-models/settings).');
            return 1;
        }

        $apiBase = (string) ($cfg['triposr_mode'] ?? 'local') === 'remote'
            ? (string) ($cfg['triposr_remote_url'] ?? '')
            : (string) ($cfg['triposr_api_url'] ?? 'http://127.0.0.1:5050');
        if ($apiBase === '') {
            $this->error('TripoSR API URL is not configured.');
            return 1;
        }

        $timeout = (int) ($cfg['triposr_timeout'] ?? 300);
        $resOption = $this->option('resolution');
        $resolution = (int) (($resOption !== null && $resOption !== '') ? $resOption : ($cfg['triposr_mc_resolution'] ?? 256));
        $bgOption = $this->option('remove-bg');
        $removeBg = filter_var(($bgOption !== null && $bgOption !== '') ? $bgOption : ($cfg['triposr_remove_bg'] ?? '1'), FILTER_VALIDATE_BOOLEAN);
        $bakeTexture = $this->option('texture') || ((string) ($cfg['triposr_bake_texture'] ?? '0')) === '1';

        // Output path — staging in temp until the caller commits via --import
        $stagingDir = sys_get_temp_dir() . '/heratio-triposr';
        if (!is_dir($stagingDir)) {
            @mkdir($stagingDir, 0775, true);
        }
        $stem = 'triposr_' . bin2hex(random_bytes(6));
        $outFile = $stagingDir . '/' . $stem . '.glb';

        $this->info(sprintf('Calling TripoSR @ %s (resolution=%d, remove_bg=%s, texture=%s)...',
            $apiBase, $resolution, $removeBg ? 'yes' : 'no', $bakeTexture ? 'yes' : 'no'));

        // Detect mime so the server's `image must be image/*` check passes;
        // CURLFile's default content-type is application/octet-stream which is
        // rejected by our FastAPI endpoint.
        $imageMime = function_exists('mime_content_type') ? (mime_content_type($image) ?: 'image/png') : 'image/png';

        $ch = curl_init($apiBase . '/generate');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'image' => new CURLFile($image, $imageMime, basename($image)),
                'mc_resolution' => $resolution,
                'remove_bg' => $removeBg ? '1' : '0',
                'bake_texture' => $bakeTexture ? '1' : '0',
                'foreground_ratio' => (string) ($cfg['triposr_foreground_ratio'] ?? '0.85'),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => array_filter([
                'Accept: model/gltf-binary',
                !empty($cfg['triposr_remote_api_key']) ? 'Authorization: Bearer ' . $cfg['triposr_remote_api_key'] : null,
            ]),
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200 || !$body) {
            $msg = $err ?: ('HTTP ' . $httpCode);

            // Demo-mode fallback — if admin opted in, serve a bundled placeholder
            // GLB cube so the preview/save UX still works while the real backend
            // is unavailable (Ollama-vs-TripoSR GPU contention, server down, etc.).
            $demoOn = ((string) ($cfg['triposr_demo_mode'] ?? '0')) === '1';
            $sample = __DIR__ . '/../../resources/sample-cube.glb';
            if ($demoOn && is_file($sample)) {
                if (@copy($sample, $outFile)) {
                    $this->warn('TripoSR backend unreachable — using bundled demo placeholder ('
                        . basename($sample) . '). Real GPU/AI generation is on its way.');
                    if ($this->option('no-import')) {
                        $this->line('TRIPOSR_OUTPUT=' . $outFile);
                        $this->line('TRIPOSR_DEMO=1');
                    }
                    if (!$this->option('no-import')) {
                        $objectId = (int) $this->option('object-id');
                        if ($objectId) {
                            $importer = new \Ahg3dModel\Services\TriposrImportService();
                            $importer->importGlb($outFile, $objectId, $image);
                        }
                    }
                    return 0;
                }
            }

            $detail = '';
            if ($httpCode === 404) {
                $detail = ' — endpoint /generate not found at ' . $apiBase
                    . '. Set the correct TripoSR API URL on /admin/3d-models/settings (or switch to "Remote GPU Server" mode if your TripoSR runs elsewhere).';
            } elseif ($httpCode === 0 || stripos($msg, 'timed out') !== false || stripos($msg, 'could not connect') !== false) {
                $detail = ' — could not reach ' . $apiBase
                    . '. Verify the host is up and the port is reachable from this server (firewall, nginx, etc.).';
            }
            $this->error('TripoSR call failed: ' . $msg . $detail);
            return 1;
        }
        if (file_put_contents($outFile, $body) === false) {
            $this->error('Could not write GLB to ' . $outFile);
            return 1;
        }

        $this->info('Generated: ' . $outFile);

        // --no-import — leave the file in staging for the caller (web preview flow)
        if ($this->option('no-import')) {
            $this->line('TRIPOSR_OUTPUT=' . $outFile); // parseable marker for callers
            return 0;
        }

        // Default: import (persist + DB row)
        $objectId = (int) $this->option('object-id');
        if (!$objectId) {
            $this->error('--object-id is required when importing.');
            return 1;
        }

        $importer = new \Ahg3dModel\Services\TriposrImportService();
        $result = $importer->importGlb($outFile, $objectId, $image);
        if (!$result['success']) {
            $this->error('Import failed: ' . ($result['error'] ?? 'unknown'));
            return 1;
        }
        $this->info('Imported as model #' . $result['model_id']);
        return 0;
    }
}
