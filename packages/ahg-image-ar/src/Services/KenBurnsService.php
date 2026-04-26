<?php

/**
 * KenBurnsService — renders an MP4 overlay clip from a still image using
 * ffmpeg's zoompan filter. Used by ahg-image-ar to produce the video that
 * MindAR overlays on top of the recognised artwork.
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

namespace AhgImageAr\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class KenBurnsService
{
    public const MOTIONS = ['zoom_in', 'zoom_out', 'pan_lr', 'pan_rl', 'ken_burns_diagonal'];

    /**
     * @param  array<string,mixed>  $opts
     * @return array{duration:float,size:int,motion:string}
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

    public function defaults(): array
    {
        $rows = [];
        try {
            $rows = DB::table('image_ar_settings')->pluck('setting_value', 'setting_key')->all();
        } catch (\Throwable $e) {
            // table not yet installed
        }
        return [
            'motion' => (string) ($rows['ar_default_motion'] ?? 'zoom_in'),
            'duration_secs' => (float) ($rows['ar_duration_secs'] ?? 5),
            'fps' => (int) ($rows['ar_fps'] ?? 25),
            'width' => (int) ($rows['ar_width'] ?? 1280),
            'height' => (int) ($rows['ar_height'] ?? 720),
            'zoom_strength' => (float) ($rows['ar_zoom_strength'] ?? 1.30),
        ];
    }

    public function isEnabled(): bool
    {
        try {
            $rows = DB::table('image_ar_settings')
                ->whereIn('setting_key', ['ar_enabled', 'ar_user_button'])
                ->pluck('setting_value', 'setting_key')->all();
        } catch (\Throwable $e) {
            return false;
        }
        return ((string) ($rows['ar_enabled'] ?? '0')) === '1'
            && ((string) ($rows['ar_user_button'] ?? '1')) === '1';
    }

    protected function buildFilter(string $motion, int $totalFrames, int $fps, int $w, int $h, float $zoom): string
    {
        $zStep = number_format(max(0.0001, ($zoom - 1.0) / max(1, $totalFrames - 1)), 5, '.', '');
        $zoomMax = number_format($zoom, 3, '.', '');
        $size = "{$w}x{$h}";
        $base = "scale=4000:-2:flags=lanczos,";
        $tail = ":d={$totalFrames}:s={$size}:fps={$fps}";

        $expr = match ($motion) {
            'zoom_in' => "zoompan=z='min(zoom+{$zStep},{$zoomMax})'"
                . ":x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'{$tail}",
            'zoom_out' => "zoompan=z='if(lte(on,1),{$zoomMax},max(1.0,zoom-{$zStep}))'"
                . ":x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'{$tail}",
            'pan_lr' => "zoompan=z='1.2'"
                . ":x='(iw-iw/zoom)*on/{$totalFrames}':y='ih/2-(ih/zoom/2)'{$tail}",
            'pan_rl' => "zoompan=z='1.2'"
                . ":x='(iw-iw/zoom)*(1-on/{$totalFrames})':y='ih/2-(ih/zoom/2)'{$tail}",
            'ken_burns_diagonal' => "zoompan=z='min(zoom+{$zStep},{$zoomMax})'"
                . ":x='(iw-iw/zoom)*on/{$totalFrames}':y='(ih-ih/zoom)*on/{$totalFrames}'{$tail}",
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
