<?php

/**
 * PdfWebOptimizationService - Console/service helper for Heratio
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

/**
 * Produces a web-optimized derivative of a large PDF so the document viewer
 * can show page 1 quickly instead of pulling the whole master over the wire.
 *
 * Two lossless-to-the-reader steps:
 *   1. Ghostscript downsamples embedded scan images to a screen-sensible DPI
 *      (a 200MB 8-page scan typically collapses to a few MB).
 *   2. qpdf --linearize rewrites the file for "fast web view" so the byte
 *      ranges needed for page 1 sit at the front - the native/iframe viewer
 *      renders the first page after the first chunk rather than the whole file.
 *
 * The MASTER is never touched; the output is a separate file the caller
 * registers as a reference (usage_id 141) digital object. Tooling (gs + qpdf)
 * is host-provided - see docs/pdf-web-optimisation-setup.md.
 */
class PdfWebOptimizationService
{
    /** True when both Ghostscript and qpdf are on PATH. */
    public function toolsAvailable(): bool
    {
        return $this->bin('gs') !== null && $this->bin('qpdf') !== null;
    }

    /**
     * Build a downsampled + linearized copy of $srcAbs at $dpi.
     * Returns an absolute temp-file path on success, or null on failure.
     * The caller owns the returned file (move it into place, then it is gone).
     */
    public function optimize(string $srcAbs, int $dpi = 200): ?string
    {
        if (! is_file($srcAbs) || ! $this->toolsAvailable()) {
            return null;
        }

        $dpi = max(72, min(600, $dpi));
        $mono = min(600, (int) round($dpi * 1.5));   // keep bilevel text crisper than tone images
        $work = sys_get_temp_dir().'/ahg-pdfopt-'.bin2hex(random_bytes(6));
        @mkdir($work, 0775, true);
        $down = $work.'/down.pdf';
        $out = $work.'/web.pdf';

        $gs = $this->bin('gs');
        $qpdf = $this->bin('qpdf');

        // 1) Ghostscript downsample. /ebook is the size-sensible base; the explicit
        //    resolution flags override its per-image-type defaults with our target DPI.
        $gsCmd = sprintf(
            '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.5 -dPDFSETTINGS=/ebook '
            .'-dNOPAUSE -dBATCH -dQUIET -dAutoRotatePages=/None -dDetectDuplicateImages=true '
            .'-dDownsampleColorImages=true -dColorImageResolution=%d -dColorImageDownsampleThreshold=1.0 '
            .'-dDownsampleGrayImages=true -dGrayImageResolution=%d -dGrayImageDownsampleThreshold=1.0 '
            .'-dDownsampleMonoImages=true -dMonoImageResolution=%d '
            .'-sOutputFile=%s %s 2>&1',
            escapeshellcmd($gs), $dpi, $dpi, $mono,
            escapeshellarg($down), escapeshellarg($srcAbs)
        );
        exec($gsCmd, $gsOut, $gsRc);
        if ($gsRc !== 0 || ! is_file($down) || filesize($down) < 1024) {
            Log::warning('[pdf-web-opt] ghostscript failed', ['src' => $srcAbs, 'rc' => $gsRc, 'tail' => array_slice($gsOut, -3)]);
            $this->cleanup($work);

            return null;
        }

        // 2) Linearize for fast web view.
        $qCmd = sprintf('%s --linearize %s %s 2>&1', escapeshellcmd($qpdf), escapeshellarg($down), escapeshellarg($out));
        exec($qCmd, $qOut, $qRc);
        // qpdf returns 3 for warnings-only (still produces valid output); accept 0 and 3.
        if (($qRc !== 0 && $qRc !== 3) || ! is_file($out) || filesize($out) < 1024) {
            // Fall back to the un-linearized (but still downsampled) file - the size win alone helps.
            if (is_file($down) && filesize($down) >= 1024) {
                @unlink($out);

                return $down;   // caller cleans the parent dir via dirname()
            }
            Log::warning('[pdf-web-opt] qpdf linearize failed', ['src' => $srcAbs, 'rc' => $qRc, 'tail' => array_slice($qOut, -3)]);
            $this->cleanup($work);

            return null;
        }

        @unlink($down);

        return $out;
    }

    /** Remove a temp work dir produced by optimize(). */
    public function cleanupDirOf(string $file): void
    {
        $this->cleanup(dirname($file));
    }

    private function cleanup(string $dir): void
    {
        if (! $dir || ! is_dir($dir) || ! str_contains($dir, 'ahg-pdfopt-')) {
            return;
        }
        foreach (glob($dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    /** Resolve an executable on PATH (or null). */
    private function bin(string $name): ?string
    {
        $p = trim((string) @shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));

        return $p !== '' ? $p : null;
    }
}
