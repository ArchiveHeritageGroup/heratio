<?php

/**
 * ImageAnimateCommand — Heratio
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

namespace AhgImageAnimate\Commands;

use AhgImageAnimate\Services\KenBurnsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImageAnimateCommand extends Command
{
    protected $signature = 'ahg:image-animate
        {--object-id= : information_object.id (uses its master image)}
        {--source= : absolute path to an image (overrides --object-id lookup)}
        {--motion=zoom_in : zoom_in, zoom_out, pan_lr, pan_rl, ken_burns_diagonal}
        {--duration=5}
        {--fps=25}
        {--width=1280} {--height=720}
        {--zoom=1.30}
        {--force : Re-render even if an animation already exists}
        {--dry-run}';

    protected $description = 'Render a Ken Burns / 2.5D MP4 from a still image (local ffmpeg)';

    public function handle(KenBurnsService $kb): int
    {
        $ioId = (int) $this->option('object-id');
        $source = (string) $this->option('source');

        if (!$ioId && !$source) {
            $this->error('Provide --object-id or --source.');
            return 1;
        }

        $digitalObject = null;
        if ($ioId && !$source) {
            [$source, $digitalObject] = $this->resolveMasterImage($ioId);
            if (!$source) {
                $this->error("No master image found for IO #{$ioId}.");
                return 1;
            }
        }

        if (!is_file($source)) {
            $this->error("Source image not found: {$source}");
            return 1;
        }

        if ($ioId && !$this->option('force')) {
            $existing = DB::table('object_image_animation')->where('object_id', $ioId)->first(['id', 'file_path']);
            if ($existing) {
                $this->info("IO #{$ioId} already has an animation: {$existing->file_path} (use --force to re-render).");
                return 0;
            }
        }

        $opts = [
            'motion' => $this->option('motion'),
            'duration_secs' => (float) $this->option('duration'),
            'fps' => (int) $this->option('fps'),
            'width' => (int) $this->option('width'),
            'height' => (int) $this->option('height'),
            'zoom_strength' => (float) $this->option('zoom'),
        ];

        $base = rtrim((string) config('heratio.uploads_path', ''), '/');
        $stem = pathinfo($source, PATHINFO_FILENAME);
        $filename = 'kenburns_' . $stem . '_' . $opts['motion'] . '.mp4';
        if ($ioId) {
            // Mirror the 3D MP4 layout — drop the file into the IO's media dir
            // so /uploads/r/<ioId>/ resolves it via the existing nginx alias.
            $absPath = $base . '/' . $ioId . '/' . $filename;
            $webPath = '/uploads/r/' . $ioId . '/' . $filename;
        } else {
            $absPath = $base . '/animations/cli/' . $filename;
            $webPath = '/uploads/animations/cli/' . $filename;
        }

        if ($this->option('dry-run')) {
            $this->info("DRY RUN — would render {$source} → {$absPath}");
            $this->table(['option', 'value'], collect($opts)->map(fn ($v, $k) => [$k, $v])->values()->all());
            return 0;
        }

        $this->info("Rendering {$opts['motion']} animation ({$opts['duration_secs']}s @ {$opts['fps']}fps)…");

        try {
            $stats = $kb->render($source, $absPath, $opts);
        } catch (\Throwable $e) {
            $this->error('Render failed: ' . $e->getMessage());
            return 1;
        }

        if ($ioId) {
            DB::table('object_image_animation')
                ->where('object_id', $ioId)->delete(); // simple replace policy
            DB::table('object_image_animation')->insert([
                'object_id' => $ioId,
                'digital_object_id' => $digitalObject?->id,
                'filename' => $filename,
                'file_path' => $webPath,
                'file_size' => $stats['size'],
                'mime_type' => 'video/mp4',
                'mode' => 'kenburns',
                'motion' => $stats['motion'],
                'duration_secs' => $opts['duration_secs'],
                'fps' => $opts['fps'],
                'width' => $opts['width'],
                'height' => $opts['height'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info(sprintf(
            'Done — %d KB at %s',
            (int) ($stats['size'] / 1024),
            $ioId ? $webPath : $absPath
        ));
        return 0;
    }

    /**
     * Resolve the master JPG/PNG digital_object for an IO.
     *
     * @return array{0:?string,1:?\stdClass}
     */
    protected function resolveMasterImage(int $ioId): array
    {
        $do = DB::table('digital_object')
            ->where('object_id', $ioId)
            ->where('mime_type', 'like', 'image/%')
            ->whereNull('parent_id')
            ->first(['id', 'name', 'path', 'mime_type']);
        if (!$do) {
            return [null, null];
        }

        $base = rtrim((string) config('heratio.uploads_path', ''), '/');
        $rel = preg_replace('#^/uploads/r?/?#', '', (string) $do->path);
        $abs = $base . '/' . trim($rel, '/') . '/' . $do->name;
        if (!is_file($abs)) {
            $abs = $base . '/' . $ioId . '/' . $do->name;
        }
        return [is_file($abs) ? $abs : null, $do];
    }
}
