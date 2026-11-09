<?php

/**
 * TranslationMtSkipTest - the opus-mt fill leaves URLs, codes and acronyms in
 * English, since the model mangles them.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Unit;

use AhgCore\Commands\TranslationMtBatchCommand as Mt;
use PHPUnit\Framework\TestCase;

class TranslationMtSkipTest extends TestCase
{
    public function test_urls_codes_and_acronyms_are_left_alone_and_sentences_are_not(): void
    {
        foreach (['https://sushi.provider.co.za/sushi/v5', 'CLI', 'DPIA:', 'ISO 8601', 'originInfo', 'admin@example.org'] as $s) {
            $this->assertTrue(Mt::untranslatable($s), $s);
        }
        foreach (['Save', 'The grid has unsaved rows.', 'CSV format', 'Edges Only'] as $s) {
            $this->assertFalse(Mt::untranslatable($s), $s);
        }
    }
}
