<?php

/**
 * KenBurnsService — Heratio
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

namespace AhgImageAnimate\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Renders a Ken Burns / 2.5D-style MP4 from a still image using ffmpeg's
 * zoompan filter. Pure-CPU, no model required. Runs locally on the
 * Heratio server.
 */
class KenBurnsService
{
    /** @var string[] Valid motion presets */
    public const MOTIONS = ['zoom_in', 'zoom_out', 'pan_lr', 'pan_rl', 'ken_burns_diagonal'];

    /**
     * @param  string  $sourceImage  absolute path to a JPG/PNG on disk
     * @param  string  $destMp4      absolute path to write the MP4 to
     * @param  array<string,mixed>  $opts  motion / duration_secs / fps / width / height / zoom_strength
     * @return array{duration:float,size:int,motion:string} stats
     */
    public function render(string $sourceImage, string $destMp4, array $opts = []): array
    {
        if (!is_file($sourceImage)) {
            throw new RuntimeException("Source image not found: {$sourceImage}");
        }

        $opts = array_merge($this->defaults(), $opts);

        $motion = in_array($opts['motion'], self::MOTIONS, true) ? $opts['motion'] : 'zoom_in';
        $duration = max(1.0, (float) $opts['duration_secs']);
        $fps = max(10, (int) $opts['fps']);
        $w = max(320, (int) $opts['width']);
        $h = max(240, (int) $opts['height']);
        $zoom = max(1.05, min(2.0, (float) $opts['zoom_strength']));
        $totalFrames = (int) round($duration * $fps);

        $filter = $this->buildFilter($motion, $totalFrames, $fps, $w, $h, $zoom);

        @mkdir(dirname($destMp4), 0775, true);

        $cmd = sprintf(
            '%s -y -loop 1 -i %s -vf %s -t %.2f -c:v libx264 -preset medium -crf 23 -pix_fmt yuv420p -movflags +faststart %s 2>&1',
            escapeshellcmd($this->ffmpegBin()),
            escapeshellarg($sourceImage),
            escapeshellarg($filter),
            $duration,
            escapeshellarg($destMp4)
        );

        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($destMp4)) {
            throw new RuntimeException("ffmpeg failed (exit {$exitCode}): " . implode("\n", array_slice($output, -10)));
        }

        return [
            'duration' => $duration,
            'size' => filesize($destMp4),
            'motion' => $motion,
        ];
    }

    /**
     * Pull current defaults from image_animate_settings, falling back to
     * sensible hard-coded values when the table is empty / missing.
     */
    public function defaults(): array
    {
        $rows = [];
        try {
            $rows = DB::table('image_animate_settings')
                ->pluck('setting_value', 'setting_key')->all();
        } catch (\Throwable $e) {
            // table may not exist yet on first boot
        }

        return [
            'motion' => (string) ($rows['animate_default_motion'] ?? 'zoom_in'),
            'duration_secs' => (float) ($rows['animate_duration_secs'] ?? 5),
            'fps' => (int) ($rows['animate_fps'] ?? 25),
            'width' => (int) ($rows['animate_width'] ?? 1280),
            'height' => (int) ($rows['animate_height'] ?? 720),
            'zoom_strength' => (float) ($rows['animate_zoom_strength'] ?? 1.30),
        ];
    }

    public function isEnabled(): bool
    {
        try {
            $rows = DB::table('image_animate_settings')
                ->whereIn('setting_key', ['animate_enabled', 'animate_user_button'])
                ->pluck('setting_value', 'setting_key')->all();
        } catch (\Throwable $e) {
            return false;
        }

        return ((string) ($rows['animate_enabled'] ?? '0')) === '1'
            && ((string) ($rows['animate_user_button'] ?? '1')) === '1';
    }

    /**
     * Build the ffmpeg -vf filter chain for the requested motion preset.
     *
     * Strategy: scale the input up to a large intermediate (4000px wide),
     * then crop a moving 1280x720 window using zoompan. The upscale keeps
     * the pixels crisp during the zoom. The 'd' parameter is the total
     * output-frame count for a single input frame (we use -loop 1 so
     * there's effectively one input frame).
     */
    protected function buildFilter(string $motion, int $totalFrames, int $fps, int $w, int $h, float $zoom): string
    {
        // Per-frame zoom delta so we reach `zoom` by the last frame.
        $zStep = number_format(max(0.0001, ($zoom - 1.0) / max(1, $totalFrames - 1)), 5, '.', '');
        $zoomMax = number_format($zoom, 3, '.', '');

        $size = "{$w}x{$h}";
        $base = "scale=4000:-2:flags=lanczos,";
        $tail = ":d={$totalFrames}:s={$size}:fps={$fps}";

        // x/y expressions in the zoompan filter are evaluated per output frame.
        // 'on' is the current output frame index, 'd' is the total frame count.
        $expr = match ($motion) {
            'zoom_in' => "zoompan=z='min(zoom+{$zStep},{$zoomMax})'"
                . ":x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'{$tail}",

            'zoom_out' => "zoompan=z='if(lte(on,1),{$zoomMax},max(1.0,zoom-{$zStep}))'"
                . ":x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'{$tail}",

            'pan_lr' => "zoompan=z='1.2'"
                . ":x='(iw-iw/zoom)*on/{$totalFrames}'"
                . ":y='ih/2-(ih/zoom/2)'{$tail}",

            'pan_rl' => "zoompan=z='1.2'"
                . ":x='(iw-iw/zoom)*(1-on/{$totalFrames})'"
                . ":y='ih/2-(ih/zoom/2)'{$tail}",

            'ken_burns_diagonal' => "zoompan=z='min(zoom+{$zStep},{$zoomMax})'"
                . ":x='(iw-iw/zoom)*on/{$totalFrames}'"
                . ":y='(ih-ih/zoom)*on/{$totalFrames}'{$tail}",

            default => "zoompan=z='min(zoom+{$zStep},{$zoomMax})'"
                . ":x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'{$tail}",
        };

        return $base . $expr . ',format=yuv420p';
    }

    protected function ffmpegBin(): string
    {
        return trim((string) (config('heratio.ffmpeg_bin') ?: 'ffmpeg'));
    }
}
