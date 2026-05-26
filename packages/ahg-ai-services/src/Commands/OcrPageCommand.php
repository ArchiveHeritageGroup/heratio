<?php

/**
 * OcrPageCommand - OCR a single image (or a directory of pages) using
 * the production Tesseract wrapper.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

declare(strict_types=1);

namespace AhgAiServices\Commands;

use AhgAiServices\Services\OcrService;
use Illuminate\Console\Command;

/**
 * Ergonomic operator-side OCR runner. Replaces the inline `tesseract`
 * exec() in AiController::htrFsOverlayOcr for batch / debugging work.
 *
 * Examples:
 *   php artisan ahg:ocr:page /mnt/nas/heratio/archive/page-001.tif
 *   php artisan ahg:ocr:page page.jpg --lang=eng+afr --psm=4
 *   php artisan ahg:ocr:page page.jpg --io=12345 --do=4242 --persist
 *   php artisan ahg:ocr:page page.jpg --llm-correct
 */
class OcrPageCommand extends Command
{
    protected $signature = 'ahg:ocr:page
        {path : Path to the image file to OCR}
        {--lang= : Tesseract language spec (e.g. eng+afr). Defaults to per-IO resolution or osd+eng+afr}
        {--psm=3 : Tesseract page-segmentation mode}
        {--oem=3 : Tesseract OCR-engine mode}
        {--io= : information_object.id to link the PREMIS event + persistence row}
        {--do= : digital_object.id to link the PREMIS event + persistence row}
        {--persist : Persist into iiif_ocr_text + iiif_ocr_block (requires --do)}
        {--llm-correct : Run LLM post-correction even if the operator-default is off}
        {--json : Emit JSON instead of human-readable output}';

    protected $description = 'Run Tesseract on a single image (multi-lang + optional LLM post-correction + PREMIS event).';

    public function handle(OcrService $ocr): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            return 2;
        }
        $opts = [
            'psm' => (int) $this->option('psm'),
            'oem' => (int) $this->option('oem'),
        ];
        if ($this->option('lang') !== null) {
            $opts['lang'] = (string) $this->option('lang');
        }
        if ($this->option('io') !== null) {
            $opts['io_id'] = (int) $this->option('io');
        }
        if ($this->option('do') !== null) {
            $opts['digital_object_id'] = (int) $this->option('do');
        }
        if ($this->option('persist')) {
            $opts['persist'] = true;
        }
        if ($this->option('llm-correct')) {
            $opts['llm_correct'] = true;
        }

        $result = $ocr->ocrImage($path, $opts);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $result['success'] ? 0 : 1;
        }

        if (! $result['success']) {
            $this->error('OCR failed: '.($result['error'] ?? 'unknown'));
            return 1;
        }

        $this->info(sprintf(
            'OCR ok: %d words, mean confidence %s, lang=%s, %dms',
            count($result['words']),
            $result['confidence'] === null ? 'n/a' : number_format($result['confidence'], 2),
            $result['language'],
            $result['duration_ms']
        ));
        if (! empty($result['llm_corrected'])) {
            $this->info(sprintf(
                'LLM post-correction applied: %d edits via %s',
                count($result['corrections'] ?? []),
                $result['llm_model'] ?? 'unknown'
            ));
        }
        $this->line('');
        $this->line($result['text']);
        return 0;
    }
}
