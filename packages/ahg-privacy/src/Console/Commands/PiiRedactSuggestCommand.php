<?php

/**
 * ahg:pii-redact-suggest - black out the personal information found in a
 * record's PDF as pending, reviewable redaction regions (PiiRedactionSuggester).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgPrivacy\Console\Commands;

use AhgPrivacy\Services\PiiRedactionSuggester;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PiiRedactSuggestCommand extends Command
{
    protected $signature = 'ahg:pii-redact-suggest {record : Information object id or slug}';

    protected $description = 'Suggest redaction regions for the personal information found in a record\'s PDF (OCR for scans)';

    public function handle(PiiRedactionSuggester $suggester): int
    {
        $arg = (string) $this->argument('record');
        $id = ctype_digit($arg) ? (int) $arg : (int) DB::table('slug')->where('slug', $arg)->value('object_id');
        if ($id <= 0) {
            $this->error("No record {$arg}.");

            return self::FAILURE;
        }
        $r = $suggester->suggest($id);
        if (isset($r['error'])) {
            $this->error($r['error']);

            return self::FAILURE;
        }
        $this->info("{$r['findings']} finding(s) on {$r['pages']} page(s)".($r['ocr'] ? ' (OCR)' : '').": {$r['added']} new region(s).");
        foreach ($r['missing'] as $m) {
            $this->warn("  not located on page {$m['page']}: {$m['value']}");
        }

        return self::SUCCESS;
    }
}
