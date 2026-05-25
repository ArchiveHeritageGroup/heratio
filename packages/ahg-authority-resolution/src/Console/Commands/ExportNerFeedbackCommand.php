<?php

/**
 * ExportNerFeedbackCommand - Console command for Heratio
 *
 * Task 9 export step. Reads every unexported ahg_ner_feedback row, writes
 * a flat training file under storage/app/auth-res/ner-feedback/, and marks
 * those rows training_exported=1. Operator copies the file across to
 * /opt/ahg-ai for the next NER retraining pass.
 *
 * Usage:
 *   php artisan auth-res:export-ner-feedback                # JSONL (default)
 *   php artisan auth-res:export-ner-feedback --format=jsonl
 *   php artisan auth-res:export-ner-feedback --format=conll
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Console\Commands;

use AhgAuthorityResolution\Services\NerFeedbackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportNerFeedbackCommand extends Command
{
    protected $signature = 'auth-res:export-ner-feedback
                            {--format=jsonl : jsonl (default) or conll}';

    protected $description = 'Export unexported NER false-positive feedback rows to a training file.';

    public function handle(NerFeedbackService $feedback): int
    {
        $format = strtolower((string) $this->option('format'));
        if (! in_array($format, ['jsonl', 'conll'], true)) {
            $this->error("Unknown --format='{$format}'. Use jsonl or conll.");

            return self::FAILURE;
        }

        $unexportedCount = (int) DB::table('ahg_ner_feedback')->where('training_exported', 0)->count();
        $this->info("Unexported rows: {$unexportedCount}");

        try {
            $result = $feedback->exportUnexported($format);
        } catch (\Throwable $e) {
            $this->error('Export failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Exported %d row(s) to %s', $result['count'], $result['path']));

        if ($result['count'] > 0 && is_file($result['path'])) {
            $size = filesize($result['path']);
            $this->line('  size: '.number_format((int) $size).' bytes');
            $head = (string) @file_get_contents($result['path'], false, null, 0, 200);
            if ($head !== '') {
                $this->line('  head: '.trim($head));
            }
        }

        return self::SUCCESS;
    }
}
