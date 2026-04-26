<?php

/**
 * ImageArCommand — Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * AGPL-3.0-or-later. See LICENSE.
 */

namespace AhgImageAr\Commands;

use AhgImageAr\Services\AnimationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImageArCommand extends Command
{
    protected $signature = 'ahg:image-ar
        {--object-id= : information_object.id}
        {--prompt= : Text prompt (ignored by SVD; used by CogVideoX/WAN)}
        {--model= : Override default model (svd, svd-xt, cogvideox-2b, wan-2.1)}
        {--frames= : Override default num_frames}
        {--fps= : Override default fps}
        {--motion= : Override default motion_bucket_id (1..255, SVD-only)}
        {--seed=0 : Random by default; >0 = deterministic}
        {--force : Re-render even if an animation already exists}
        {--health : Just print the AI server health and exit}';

    protected $description = 'Generate an AI image-to-video clip via the video-server (SVD on 8 GB; CogVideoX / WAN on 24 GB)';

    public function handle(AnimationService $ai): int
    {
        if ($this->option('health')) {
            $h = $ai->health();
            if (!$h) {
                $this->error('AI server unreachable.');
                return 1;
            }
            $this->line(json_encode($h, JSON_PRETTY_PRINT));
            return 0;
        }

        $ioId = (int) $this->option('object-id');
        if (!$ioId) {
            $this->error('Provide --object-id (or use --health).');
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

        $opts = $ai->defaults();
        foreach (['prompt' => 'prompt', 'model' => 'model',
                  'num_frames' => 'frames', 'fps' => 'fps',
                  'motion_bucket_id' => 'motion', 'seed' => 'seed'] as $optKey => $cliKey) {
            $val = $this->option($cliKey);
            if ($val !== null && $val !== '') {
                $opts[$optKey] = $val;
            }
        }

        $storage = rtrim((string) config('heratio.storage_path', ''), '/');
        $stem = pathinfo($do->name, PATHINFO_FILENAME);
        $stamp = substr((string) time(), -6);
        $modelTag = preg_replace('/[^a-z0-9]+/i', '', (string) $opts['model']) ?: 'ai';
        $mp4Filename = $modelTag . '_' . $stem . '_' . $stamp . '.mp4';
        $mp4Abs = $storage . '/uploads/ar/' . $ioId . '/' . $mp4Filename;
        $mp4Web = '/uploads/ar/' . $ioId . '/' . $mp4Filename;

        $this->info(sprintf(
            "Sending to AI server (%s, model=%s, %d frames @ %d fps, motion=%d)…\nThis can take 3–8 minutes on the 8 GB card.",
            $opts['server_url'], $opts['model'], $opts['num_frames'], $opts['fps'], $opts['motion_bucket_id']
        ));

        try {
            $stats = $ai->generate($sourcePath, $mp4Abs, $opts);
        } catch (\Throwable $e) {
            $this->error('Generation failed: ' . $e->getMessage());
            return 1;
        }

        $duration = $stats['fps'] > 0 ? round($stats['frames'] / $stats['fps'], 2) : null;
        DB::table('object_image_ar')->where('object_id', $ioId)->delete();
        DB::table('object_image_ar')->insert([
            'object_id' => $ioId,
            'digital_object_id' => $do->id,
            'mp4_filename' => $mp4Filename,
            'mp4_path' => $mp4Web,
            'mp4_size' => $stats['size'],
            'mp4_duration_secs' => $duration,
            'mp4_fps' => $stats['fps'],
            'mp4_motion' => 'ai-' . $stats['model'],
            'ai_model' => $stats['model'],
            'ai_prompt' => $stats['prompt'],
            'ai_seed' => $stats['seed'],
            'ai_motion_bucket_id' => $stats['motion_bucket_id'],
            'generation_secs' => $stats['generation_secs'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info(sprintf('Done — %d KB in %.0f s at %s', (int) ($stats['size'] / 1024), $stats['generation_secs'], $mp4Web));
        return 0;
    }
}
