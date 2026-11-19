<?php

/**
 * IngestAiStepJob - runs one per-record AI step chosen in the ingest wizard
 * (OCR, summarise, spellcheck, translate) off the commit, through the same
 * service or command an operator would run by hand.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgAiServices\Jobs;

use AhgAiServices\Services\OcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class IngestAiStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const STEPS = ['ocr', 'summarize', 'spellcheck', 'translate'];

    /** Vision OCR reads a page in strips through the gateway: minutes, not seconds. */
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public string $step,
        public int $ioId,
        public int $doId = 0,
        public ?string $lang = null,
    ) {
    }

    public function handle(): void
    {
        $code = match ($this->step) {
            // ponytail: images only - OcrService reads one page image; PDFs
            // go through ahg:pdf-rebuild-text-layer.
            'ocr' => $this->ocr(),
            'summarize' => Artisan::call('ahg:ai-summarize', ['--object' => $this->ioId]),
            'spellcheck' => Artisan::call('ahg:ai-spellcheck', ['--object' => $this->ioId]),
            'translate' => Artisan::call('ahg:ai-translate', ['--object' => $this->ioId, '--to' => $this->lang ?: 'af']),
            default => 0,
        };
        if ($code !== 0) {
            Log::warning('[ingest-ai-step] '.$this->step.' exited '.$code, ['io_id' => $this->ioId, 'output' => substr(Artisan::output(), 0, 500)]);
        }
    }

    private function ocr(): int
    {
        // The commit moves the staged file into uploads, so find the master by its row.
        $do = $this->doId > 0 ? \Illuminate\Support\Facades\DB::table('digital_object')->where('id', $this->doId)->first() : null;
        $path = $do ? \AhgCore\Services\DigitalObjectService::resolveDiskPath($do) : null;
        if (! $path || ! is_file($path) || ! str_starts_with((string) ($do->mime_type ?? ''), 'image/')) {
            return 0;
        }
        $r = app(OcrService::class)->ocrImage($path, ['io_id' => $this->ioId, 'digital_object_id' => $this->doId, 'persist' => true]);

        return $r['success'] ? 0 : 1;
    }
}
