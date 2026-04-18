<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Derive + cache thumbnails from uploaded images. Shells out to ImageMagick
 * (`convert`) so we don't need a PHP image extension. Thumbnails are
 * written under storage/app/thumbnails/{YYYY}/{MM}/{uuid}_{w}x{h}.jpg and
 * nginx can serve them directly from /thumbnails/ with long cache headers.
 */

namespace AhgRic\Support;

use Illuminate\Support\Facades\Log;

class Thumbnailer
{
    /** Accepted width/height — other values are clamped. Keeps the cache bounded. */
    public const ALLOWED_SIZES = [150, 300, 600, 1200];

    public const DEFAULT_WIDTH  = 300;
    public const DEFAULT_HEIGHT = 300;

    public const BASE_DIR = 'thumbnails';

    /**
     * Given an absolute source path and a width×height, return the absolute
     * path to the cached thumbnail. Generates on first request; returns the
     * cached file on subsequent ones. Returns null if the source isn't a
     * supported image type or ImageMagick isn't installed.
     */
    public static function ensure(string $sourceAbs, int $w = self::DEFAULT_WIDTH, int $h = self::DEFAULT_HEIGHT): ?string
    {
        $w = self::clamp($w);
        $h = self::clamp($h);

        if (!file_exists($sourceAbs)) return null;
        if (!self::isImage($sourceAbs)) return null;
        if (!self::canConvert()) return null;

        $relative = self::relativePathFor($sourceAbs, $w, $h);
        $root = storage_path('app/' . self::BASE_DIR);
        $abs  = $root . '/' . $relative;

        if (file_exists($abs) && filemtime($abs) >= filemtime($sourceAbs)) {
            return $abs;
        }

        if (!is_dir(dirname($abs))) mkdir(dirname($abs), 0775, true);

        // resize with aspect preserved (>), crop to fit if caller wants square.
        // Using `convert`'s standard thumbnail pipeline; flatten strips alpha
        // so a transparent source becomes a white-bg JPEG rather than black.
        $cmd = sprintf(
            'convert %s -auto-orient -thumbnail %dx%d^ -gravity center -extent %dx%d -background white -flatten -strip -quality 85 %s 2>&1',
            escapeshellarg($sourceAbs),
            $w, $h, $w, $h,
            escapeshellarg($abs)
        );
        $out = [];
        $rc  = 0;
        exec($cmd, $out, $rc);
        if ($rc !== 0 || !file_exists($abs)) {
            Log::warning('[Thumbnailer] convert failed', ['source' => $sourceAbs, 'cmd' => $cmd, 'rc' => $rc, 'out' => implode("\n", $out)]);
            return null;
        }
        return $abs;
    }

    /** Public URL for the cached thumbnail — resolves to /thumbnails/… served by nginx. */
    public static function publicUrlFor(string $absSourcePath, int $w = self::DEFAULT_WIDTH, int $h = self::DEFAULT_HEIGHT): string
    {
        $w = self::clamp($w); $h = self::clamp($h);
        $rel = self::relativePathFor($absSourcePath, $w, $h);
        return rtrim(config('app.url'), '/') . '/thumbnails/' . $rel;
    }

    private static function relativePathFor(string $sourceAbs, int $w, int $h): string
    {
        // Keep the source's YYYY/MM/ prefix if present, otherwise hash to
        // keep the cache directory balanced.
        if (preg_match('#/(\d{4})/(\d{2})/([^/]+)$#', $sourceAbs, $m)) {
            $base = pathinfo($m[3], PATHINFO_FILENAME);
            return sprintf('%s/%s/%s_%dx%d.jpg', $m[1], $m[2], $base, $w, $h);
        }
        $hash = substr(md5($sourceAbs), 0, 2);
        $base = pathinfo($sourceAbs, PATHINFO_FILENAME);
        return sprintf('misc/%s/%s_%dx%d.jpg', $hash, $base, $w, $h);
    }

    private static function clamp(int $v): int
    {
        // Snap to the nearest allowed size.
        $best = self::ALLOWED_SIZES[0];
        $diff = PHP_INT_MAX;
        foreach (self::ALLOWED_SIZES as $s) {
            if (abs($s - $v) < $diff) { $diff = abs($s - $v); $best = $s; }
        }
        return $best;
    }

    private static function isImage(string $path): bool
    {
        $mime = @mime_content_type($path) ?: '';
        return str_starts_with($mime, 'image/')
            && !in_array($mime, ['image/svg+xml']);  // SVG is not ImageMagick's strong suit
    }

    private static function canConvert(): bool
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        $out = []; $rc = 0;
        exec('command -v convert 2>/dev/null', $out, $rc);
        return $cached = ($rc === 0 && !empty($out));
    }
}
