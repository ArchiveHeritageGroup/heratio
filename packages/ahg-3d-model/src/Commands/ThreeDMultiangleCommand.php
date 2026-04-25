<?php

/**
 * ThreeDMultiangleCommand — render a turntable MP4 from an existing
 * object_3d_model row by sending its GLB to the TripoSR server's
 * /render-turntable endpoint, then storing the resulting MP4 alongside
 * the GLB and updating the row.
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

class ThreeDMultiangleCommand extends Command
{
    protected $signature = 'ahg:3d-multiangle
        {--id= : object_3d_model.id (a single 3D model)}
        {--object-id= : information_object.id (the IO with a 3D model)}
        {--frames=36 : number of orbit frames}
        {--fps=24 : MP4 frame rate}
        {--width=640} {--height=480}
        {--force : Re-render even if a turntable already exists}
        {--dry-run : Print would-do without writing}';

    protected $description = 'Render a turntable MP4 around a 3D model';

    public function handle(): int
    {
        $modelId  = (int) $this->option('id');
        $ioId     = (int) $this->option('object-id');

        $row = null;
        if ($modelId) {
            $row = DB::table('object_3d_model')->where('id', $modelId)->first();
        } elseif ($ioId) {
            $row = DB::table('object_3d_model')->where('object_id', $ioId)->orderByDesc('id')->first();
        } else {
            $this->error('Provide --id or --object-id');
            return 1;
        }
        if (!$row) {
            $this->error('No 3D model found for the given id.');
            return 1;
        }

        if ($row->turntable_mp4_path && !$this->option('force')) {
            $this->info("Already has turntable: {$row->turntable_mp4_path} (use --force to re-render).");
            return 0;
        }

        // Resolve GLB on disk
        $base = rtrim((string) config('heratio.uploads_path', ''), '/');
        $rel = preg_replace('#^/uploads/r?/?#', '', (string) $row->file_path);
        $glbPath = $base . '/' . trim($rel, '/');
        if (!is_file($glbPath)) {
            $glbPath = $base . '/' . $row->object_id . '/' . $row->filename;
        }
        if (!is_file($glbPath)) {
            $this->error("GLB file not found on disk for model #{$row->id} (tried {$glbPath}).");
            return 1;
        }

        if ($this->option('dry-run')) {
            $this->info("DRY RUN — would render turntable from {$glbPath}");
            return 0;
        }

        $cfg = DB::table('viewer_3d_settings')
            ->whereIn('setting_key', ['triposr_api_url', 'triposr_mode', 'triposr_remote_url'])
            ->pluck('setting_value', 'setting_key')->all();
        $apiBase = ((string) ($cfg['triposr_mode'] ?? 'local')) === 'remote'
            ? (string) ($cfg['triposr_remote_url'] ?? '')
            : (string) ($cfg['triposr_api_url'] ?? 'http://127.0.0.1:5050');
        if ($apiBase === '') {
            $this->error('TripoSR API URL is not configured.');
            return 1;
        }

        $this->info("POST {$apiBase}/render-turntable (frames={$this->option('frames')}, fps={$this->option('fps')})");

        $ch = curl_init($apiBase . '/render-turntable');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'glb' => new CURLFile($glbPath, 'model/gltf-binary', basename($glbPath)),
                'frames' => (int) $this->option('frames'),
                'fps' => (int) $this->option('fps'),
                'width' => (int) $this->option('width'),
                'height' => (int) $this->option('height'),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200 || !$body) {
            $this->error('Turntable render failed: ' . ($err ?: 'HTTP ' . $httpCode));
            return 1;
        }

        $stem = pathinfo($row->filename, PATHINFO_FILENAME);
        $mp4Name = $stem . '_turntable.mp4';
        $mp4DiskPath = dirname($glbPath) . '/' . $mp4Name;
        if (file_put_contents($mp4DiskPath, $body) === false) {
            $this->error('Could not write MP4 to ' . $mp4DiskPath);
            return 1;
        }
        @chmod($mp4DiskPath, 0644);

        $webPath = rtrim(dirname((string) $row->file_path), '/') . '/' . $mp4Name;

        DB::table('object_3d_model')->where('id', $row->id)->update([
            'turntable_mp4_path' => $webPath,
            'turntable_generated_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info(sprintf('Turntable MP4 saved (%d KB) at %s', (int) (strlen($body) / 1024), $webPath));
        return 0;
    }
}
