<?php

/**
 * Article30ExportCommand - privacy:article-30-export - regulator-ready export
 * of the GDPR Article 30 register (ahg_processing_activity).
 *
 * Usage:
 *   php artisan privacy:article-30-export --format=json --out=/tmp/art30.json
 *   php artisan privacy:article-30-export --format=csv
 *   php artisan privacy:article-30-export --format=markdown --out=art30.md
 *
 * Issue #669 Phase 1.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Console\Commands;

use AhgPrivacy\Services\Article30Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class Article30ExportCommand extends Command
{
    protected $signature = 'privacy:article-30-export
        {--format=json : One of csv | json | markdown}
        {--out= : Optional output file path. If omitted, write to STDOUT.}';

    protected $description = 'Export the GDPR Article 30 register (record of processing activities) for regulator submission.';

    public function handle(Article30Service $service): int
    {
        if (! Schema::hasTable('ahg_processing_activity')) {
            $this->error('ahg_processing_activity table is missing - run the Phase 1 install SQL first.');
            return self::FAILURE;
        }

        $format = strtolower((string) $this->option('format'));
        $payload = match ($format) {
            'csv'      => $service->exportCsv(),
            'markdown', 'md' => $service->exportMarkdown(),
            'json', '' => $service->exportJson(),
            default    => null,
        };

        if ($payload === null) {
            $this->error(sprintf('Unsupported format "%s". Use csv, json or markdown.', $format));
            return self::FAILURE;
        }

        $out = (string) $this->option('out');
        if ($out !== '') {
            $bytes = @file_put_contents($out, $payload);
            if ($bytes === false) {
                $this->error(sprintf('Failed to write %s.', $out));
                return self::FAILURE;
            }
            $this->info(sprintf('Wrote %d bytes to %s.', $bytes, $out));
            return self::SUCCESS;
        }

        $this->line($payload);
        return self::SUCCESS;
    }
}
