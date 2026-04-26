<?php

/**
 * ImageArCommand — Heratio
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
 */

namespace AhgImageAr\Commands;

use AhgImageAr\Services\KenBurnsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImageArCommand extends Command
{
    protected $signature = 'ahg:image-ar
        {--object-id= : information_object.id}
        {--motion=zoom_in : Ken Burns motion preset}
        {--duration=5}
        {--fps=25}
        {--width=1280} {--height=720}
        {--zoom=1.30}
        {--force : Re-render even if an animation already exists}';

    protected $description = 'Render a Ken Burns MP4 animation for an information object';

    public function handle(KenBurnsService $kb): int
    {
        $ioId = (int) $this->option('object-id');
        if (!$ioId) {
            $this->error('Provide --object-id.');
            return 1;
        }

        if (!$this->option('force')) {
            if (DB::table('object_image_ar')->where('object_id', $ioId)->exists()) {
                $this->info("IO #{$ioId} already has an animation (use --force to rebuild).");
                return 0;
            }
        }

        $do = DB::table('digital_object')
            ->where('object_id', $ioId)
            ->where('mime_type', 'like', 'image/%')
            ->whereNull('parent_id')
            ->first(['id', 'name', 'path']);
        if (!$do) {
            $this->error("No master image for IO #{$ioId}.");
            return 1;
        }

        $base = rtrim((string) config('heratio.uploads_path', ''), '/');
        $rel = preg_replace('#^/uploads/r?/?#', '', (string) $do->path);
        $sourcePath = $base . '/' . trim($rel, '/') . '/' . $do->name;
        if (!is_file($sourcePath)) {
            $sourcePath = $base . '/' . $ioId . '/' . $do->name;
        }
        if (!is_file($sourcePath)) {
            $this->error("Source image not on disk: {$sourcePath}");
            return 1;
        }

        $opts = [
            'motion' => $this->option('motion'),
            'duration_secs' => (float) $this->option('duration'),
            'fps' => (int) $this->option('fps'),
            'width' => (int) $this->option('width'),
            'height' => (int) $this->option('height'),
            'zoom_strength' => (float) $this->option('zoom'),
        ];

        $storage = rtrim((string) config('heratio.storage_path', ''), '/');
        $stem = pathinfo($do->name, PATHINFO_FILENAME);
        $stamp = substr((string) time(), -6);
        $mp4Filename = 'kenburns_' . $stem . '_' . $opts['motion'] . '_' . $stamp . '.mp4';
        $mp4Abs = $storage . '/uploads/ar/' . $ioId . '/' . $mp4Filename;
        $mp4Web = '/uploads/ar/' . $ioId . '/' . $mp4Filename;

        $this->info("Rendering MP4 ({$opts['motion']}, {$opts['duration_secs']}s @ {$opts['fps']}fps)…");
        $kbStats = $kb->render($sourcePath, $mp4Abs, $opts);
        $this->info(sprintf('  → %d KB', (int) ($kbStats['size'] / 1024)));

        // Replace existing row + remove its old files.
        $existing = DB::table('object_image_ar')->where('object_id', $ioId)->first();
        if ($existing) {
            foreach ([$existing->mp4_path, $existing->mind_path] as $oldWeb) {
                if (!$oldWeb) {
                    continue;
                }
                $oldAbs = $storage . '/' . ltrim(preg_replace('#^/uploads/#', 'uploads/', $oldWeb), '/');
                if (is_file($oldAbs) && $oldAbs !== $mp4Abs) {
                    @unlink($oldAbs);
                }
            }
            DB::table('object_image_ar')->where('id', $existing->id)->delete();
        }

        DB::table('object_image_ar')->insert([
            'object_id' => $ioId,
            'digital_object_id' => $do->id,
            'mp4_filename' => $mp4Filename,
            'mp4_path' => $mp4Web,
            'mp4_size' => $kbStats['size'],
            'mp4_motion' => $kbStats['motion'],
            'mp4_duration_secs' => $opts['duration_secs'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("Animation ready — {$mp4Web}");
        return 0;
    }
}
