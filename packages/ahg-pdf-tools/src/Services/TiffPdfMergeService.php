<?php

/**
 * TiffPdfMergeService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */


use Illuminate\Support\Facades\Log;

/**
 * TIFF/PDF Merge Service.
 *
 * Merges multiple image files (TIFF, JPG, PNG, etc.) into a single PDF
 * using ImageMagick's convert command. Supports PDF/A generation via Ghostscript.
 *
 * Ported from ahgTiffPdfMergePlugin.
 */
class TiffPdfMergeService
{
    /** Supported input image formats */
    private const SUPPORTED_FORMATS = ['tiff', 'tif', 'jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp'];

    /** Supported page sizes for output */
    private const PAGE_SIZES = ['letter', 'a4', 'legal', 'a3', 'a5'];

    /** Supported PDF/A versions */
    private const PDFA_VERSIONS = ['1b', '2b', '3b'];

    /**
     * Merge multiple input files into a single PDF.
     *
     * Uses ImageMagick: convert input1 input2 ... -quality X -density Y output.pdf
     * For PDF/A output, pipes through Ghostscript.
     *
     * @param array  $inputPaths Array of file paths to merge
     * @param string $outputPath Output PDF file path
     * @param array  $options    {
     *     @type int    $quality     JPEG quality 0-100 (default 90)
     *     @type int    $dpi         Resolution 72-600 (default 150)
     *     @type string $pageSize    letter|a4|legal|a3|a5 (default a4)
     *     @type string $orientation portrait|landscape (default portrait)
     *     @type bool   $pdfa        Generate PDF/A output (default false)
     *     @type string $pdfaVersion PDF/A version: 1b|2b|3b (default 2b)
     * }
     * @return bool True on success
     */
    public function merge(array $inputPaths, string $outputPath, array $options = []): bool
    {
        if (empty($inputPaths)) {
            throw new \InvalidArgumentException('No input files provided');
        }

        if (!$this->isImageMagickAvailable()) {
            throw new \RuntimeException('ImageMagick (convert) is not installed. Install with: sudo apt install imagemagick');
        }

        // Validate input files
        foreach ($inputPaths as $path) {
            if (!file_exists($path)) {
                throw new \RuntimeException("Input file not found: {$path}");
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, self::SUPPORTED_FORMATS) && $ext !== 'pdf') {
                throw new \InvalidArgumentException("Unsupported format: {$ext}. Supported: " . implode(', ', self::SUPPORTED_FORMATS));
            }
        }

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Parse options
        $quality     = max(0, min(100, (int) ($options['quality'] ?? 90)));
        $dpi         = max(72, min(600, (int) ($options['dpi'] ?? 150)));
        $pageSize    = in_array($options['pageSize'] ?? 'a4', self::PAGE_SIZES) ? ($options['pageSize'] ?? 'a4') : 'a4';
        $orientation = ($options['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $pdfa        = (bool) ($options['pdfa'] ?? false);
        $pdfaVersion = in_array($options['pdfaVersion'] ?? '2b', self::PDFA_VERSIONS) ? ($options['pdfaVersion'] ?? '2b') : '2b';

        // Build page geometry
        $pageDimensions = $this->getPageDimensions($pageSize, $orientation, $dpi);

        // Build convert command
        $inputArgs = implode(' ', array_map('escapeshellarg', $inputPaths));

        // Determine output path (use temp if PDF/A conversion needed)
        $convertOutput = $pdfa && $this->isPdfASupported()
            ? sys_get_temp_dir() . '/heratio_merge_' . uniqid() . '.pdf'
            : $outputPath;

        $command = sprintf(
            'convert %s -density %d -quality %d -page %s %s 2>&1',
            $inputArgs,
            $dpi,
            $quality,
            escapeshellarg($pageDimensions),
            escapeshellarg($convertOutput)
        );

        $output = [];
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('TiffPdfMerge: convert failed: ' . implode("\n", $output));

            // Clean up temp file
            if ($convertOutput !== $outputPath && file_exists($convertOutput)) {
                unlink($convertOutput);
            }

            throw new \RuntimeException('ImageMagick convert failed: ' . implode("\n", $output));
        }

        // PDF/A conversion via Ghostscript if requested
        if ($pdfa && $this->isPdfASupported()) {
            try {
                $this->generatePdfA($convertOutput, $outputPath, $pdfaVersion);
            } finally {
                // Clean up temp file
                if (file_exists($convertOutput)) {
                    unlink($convertOutput);
                }
            }
        }

        return file_exists($outputPath);
    }

    /**
     * Check if Ghostscript is available for PDF/A generation.
     */
    public function isPdfASupported(): bool
    {
        return $this->isCommandAvailable('gs');
    }

    /**
     * Convert a PDF to PDF/A using Ghostscript.
     *
     * Supported versions: 1b, 2b, 3b
     *
     * @param string $inputPdf   Input PDF path
     * @param string $outputPdf  Output PDF/A path
     * @param string $version    PDF/A version (1b, 2b, 3b)
     * @return bool True on success
     */
    public function generatePdfA(string $inputPdf, string $outputPdf, string $version = '2b'): bool
    {
        if (!file_exists($inputPdf)) {
            throw new \RuntimeException("Input PDF not found: {$inputPdf}");
        }

        if (!$this->isPdfASupported()) {
            throw new \RuntimeException('Ghostscript is not installed. Install with: sudo apt install ghostscript');
        }

        if (!in_array($version, self::PDFA_VERSIONS)) {
            throw new \InvalidArgumentException("Unsupported PDF/A version: {$version}. Supported: " . implode(', ', self::PDFA_VERSIONS));
        }

        // Map version to Ghostscript PDFA compatibility level
        $compatMap = [
            '1b' => '1',
            '2b' => '2',
            '3b' => '3',
        ];
        $compat = $compatMap[$version];

        // Build Ghostscript command
        $command = sprintf(
            'gs -dPDFA=%s -dBATCH -dNOPAUSE -dNOOUTERSAVE '
            . '-sColorConversionStrategy=UseDeviceIndependentColor '
            . '-sDEVICE=pdfwrite '
            . '-dPDFACompatibilityPolicy=1 '
            . '-sOutputFile=%s %s 2>&1',
            escapeshellarg($compat),
            escapeshellarg($outputPdf),
            escapeshellarg($inputPdf)
        );

        $output = [];
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('TiffPdfMerge: Ghostscript PDF/A conversion failed: ' . implode("\n", $output));
            throw new \RuntimeException('Ghostscript PDF/A conversion failed: ' . implode("\n", $output));
        }

        return file_exists($outputPdf);
    }

    /**
     * Get the list of supported input formats.
     *
     * @return string[]
     */
    public function getSupportedInputFormats(): array
    {
        return self::SUPPORTED_FORMATS;
    }

    /**
     * Get supported page sizes.
     *
     * @return string[]
     */
    public function getSupportedPageSizes(): array
    {
        return self::PAGE_SIZES;
    }

    /**
     * Get supported PDF/A versions.
     *
     * @return string[]
     */
    public function getSupportedPdfAVersions(): array
    {
        return self::PDFA_VERSIONS;
    }

    /**
     * Check if ImageMagick (convert) is available.
     */
    public function isImageMagickAvailable(): bool
    {
        return $this->isCommandAvailable('convert');
    }

    /**
     * Get ImageMagick version string.
     */
    public function getImageMagickVersion(): ?string
    {
        if (!$this->isImageMagickAvailable()) {
            return null;
        }

        $output = [];
        exec('convert -version 2>&1', $output);
        $line = $output[0] ?? '';

        if (preg_match('/ImageMagick (\S+)/', $line, $m)) {
            return $m[1];
        }

        return $line ?: null;
    }

    /**
     * Get Ghostscript version string.
     */
    public function getGhostscriptVersion(): ?string
    {
        if (!$this->isPdfASupported()) {
            return null;
        }

        $output = [];
        exec('gs --version 2>&1', $output);
        return trim($output[0] ?? '') ?: null;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Get page dimensions string for ImageMagick -page flag.
     */
    private function getPageDimensions(string $pageSize, string $orientation, int $dpi): string
    {
        // Standard page sizes in points (1/72 inch)
        $sizes = [
            'letter' => [612, 792],
            'a4'     => [595, 842],
            'legal'  => [612, 1008],
            'a3'     => [842, 1191],
            'a5'     => [420, 595],
        ];

        [$w, $h] = $sizes[$pageSize] ?? $sizes['a4'];

        if ($orientation === 'landscape') {
            [$w, $h] = [$h, $w];
        }

        // Convert points to pixels at given DPI
        $pw = (int) round($w * $dpi / 72);
        $ph = (int) round($h * $dpi / 72);

        return "{$pw}x{$ph}";
    }

    /**
     * Check if a system command is available.
     */
    private function isCommandAvailable(string $command): bool
    {
        $result = shell_exec("which {$command} 2>/dev/null");
        return !empty(trim($result ?? ''));
    }
}
