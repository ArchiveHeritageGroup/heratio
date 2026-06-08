<?php

/**
 * CombineFolderCommand - Console command for Heratio
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

namespace AhgPdfTools\Console;

use AhgCore\Services\DigitalObjectService;
use AhgPdfTools\Services\TiffPdfMergeService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;

/**
 * Combine a SERVER folder of TIFF/image pages into one PDF/A, memory-safe and
 * without the browser-upload size cap - the large-volume path for Heratio
 * (twin of the AtoM ahg:tiff-combine-watch / importFolder). Optionally attaches
 * the result to an information object as a master digital object; the shipped
 * ahg:optimize-pdfs then gives it a fast .web.pdf sibling.
 */
class CombineFolderCommand extends Command
{
    protected $signature = 'ahg:pdf-combine '
        .'{folder : Server folder containing the page TIFFs/images (in filename order)} '
        .'{--out= : Output PDF/A path (default: <folder>.pdf)} '
        .'{--dpi=200 : Target DPI for colour/grey images} '
        .'{--quality=85 : JPEG quality 0-100} '
        .'{--id= : Attach the PDF/A to this information_object id as a master digital object} '
        .'{--no-web : Do not auto-create the fast web-optimized derivative after attaching} '
        .'{--clear-source : Delete the source page files after a successful combine (fresh start, used by the FTP-upload flow)}';

    protected $description = 'Combine a server folder of TIFFs into one PDF/A (memory-safe, large-volume)';

    public function handle(TiffPdfMergeService $merge): int
    {
        $folder = rtrim((string) $this->argument('folder'), '/');
        if (! is_dir($folder)) {
            $this->error("Folder not found: {$folder}");

            return 1;
        }
        if (! $merge->isImageMagickAvailable()) {
            $this->error('ImageMagick (convert) not installed.');

            return 1;
        }

        $pages = $this->listImages($folder);
        if (empty($pages)) {
            $this->error('No TIFF/image files found in folder.');

            return 1;
        }

        // Default output name = folder name, with long names (e.g. long record
        // slugs) truncated so the filename stays sane.
        $baseName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($folder));
        $baseName = trim(preg_replace('/_+/', '_', $baseName), '_');
        if (strlen($baseName) > 80) {
            $baseName = rtrim(substr($baseName, 0, 80), '_');
        }
        if ($baseName === '') {
            $baseName = 'merged_document';
        }
        $out = (string) ($this->option('out') ?: dirname($folder).'/'.$baseName.'.pdf');
        $this->info('Combining '.count($pages).' page(s) -> '.$out.' (PDF/A, dpi='.$this->option('dpi').')');

        try {
            $merge->merge($pages, $out, [
                'dpi' => (int) $this->option('dpi'),
                'quality' => (int) $this->option('quality'),
                'pageSize' => 'a4',
                'pdfaVersion' => '2b',
            ]);
        } catch (\Throwable $e) {
            $this->error('Combine failed: '.$e->getMessage());

            return 1;
        }

        if (! is_file($out)) {
            $this->error('Combine completed but output was not created.');

            return 1;
        }
        $this->info('Created '.round(filesize($out) / 1048576, 2).' MB PDF/A: '.$out);

        // Optional attach to a record as a master digital object.
        if ($this->option('id')) {
            $objectId = (int) $this->option('id');
            try {
                // upload() MOVES the file into the uploads tree, so work on a copy
                // unless the operator does not need the standalone output kept.
                $uploaded = new UploadedFile($out, basename($out), 'application/pdf', null, true);
                $doId = DigitalObjectService::upload($objectId, $uploaded);
                DigitalObjectService::generateDerivativesForMaster($doId);
                $this->info("Attached to information object #{$objectId} as digital object #{$doId}.");

                // Auto-create the fast web-optimized PDF derivative now (instead of
                // waiting for the daily ahg:optimize-pdfs schedule).
                if (! $this->option('no-web')) {
                    try {
                        Artisan::call('ahg:optimize-pdfs', ['--commit' => true, '--id' => $objectId, '--min-mb' => 0]);
                        $this->line('Web derivative: '.trim(Artisan::output()));
                    } catch (\Throwable $we) {
                        $this->warn('Web derivative step skipped: '.$we->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('PDF/A created but attach failed: '.$e->getMessage().' (file kept at '.$out.')');

                return 0;
            }
        }

        // Start fresh: remove the combined source pages so the next convert is
        // clean and never re-combines stale leftovers. Only the page files that
        // were just combined are removed, plus the folder if it is now empty.
        if ($this->option('clear-source')) {
            $removed = 0;
            foreach ($pages as $p) {
                if (is_file($p) && @unlink($p)) {
                    $removed++;
                }
            }
            if (is_dir($folder) && count(glob($folder.'/*') ?: []) === 0) {
                @rmdir($folder);
            }
            $this->info("Cleared {$removed} source file(s) after combine (fresh start).");
        }

        return 0;
    }

    /** List image/PDF files in a folder, natural-sorted so page order follows filenames. */
    private function listImages(string $dir): array
    {
        $out = [];
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir.'/'.$f;
            if (! is_file($p)) {
                continue;
            }
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, ['tif', 'tiff', 'jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp', 'pdf'], true)) {
                $out[] = $p;
            }
        }
        natcasesort($out);

        return array_values($out);
    }
}
