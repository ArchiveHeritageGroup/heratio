<?php

/**
 * OcrLlmCorrectorTest - pure unit tests for the inline-marker parser and
 * the prompt builder. No Laravel bootstrap, no DB, no real LLM.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

declare(strict_types=1);

namespace AhgAiServices\Tests\Unit;

use AhgAiServices\Services\OcrLlmCorrector;
use PHPUnit\Framework\TestCase;

class OcrLlmCorrectorTest extends TestCase
{
    private function svc(): OcrLlmCorrector
    {
        // The LlmService is only used inside ::correct(), which we don't
        // exercise here. Pass null; the parser + prompt builder are
        // independent of it.
        return new OcrLlmCorrector(null);
    }

    public function test_parse_inline_extracts_corrections_and_strips_markers(): void
    {
        $svc = $this->svc();
        $resp = 'The [rn->m]odem broke during transrnission in the year [O->0]987.';
        $parsed = $svc->parseInline($resp);
        $this->assertSame('The modem broke during transrnission in the year 0987.', $parsed['text']);
        $this->assertCount(2, $parsed['corrections']);
        $this->assertSame('rn', $parsed['corrections'][0]['orig']);
        $this->assertSame('m', $parsed['corrections'][0]['corrected']);
        $this->assertSame('O', $parsed['corrections'][1]['orig']);
        $this->assertSame('0', $parsed['corrections'][1]['corrected']);
    }

    public function test_parse_inline_with_no_markers_returns_text_unchanged(): void
    {
        $svc = $this->svc();
        $parsed = $svc->parseInline('Plain text with no corrections.');
        $this->assertSame('Plain text with no corrections.', $parsed['text']);
        $this->assertSame([], $parsed['corrections']);
    }

    public function test_build_prompt_includes_strict_rules_and_language_hint(): void
    {
        $svc = $this->svc();
        $prompt = $svc->buildPrompt('sample OCR text', [
            'language' => 'eng+afr',
            'tesseract_confidence' => 64.2,
        ]);
        $this->assertStringContainsString('archival OCR proof-reader', $prompt);
        $this->assertStringContainsString('[orig->corrected]', $prompt);
        $this->assertStringContainsString('Preserve archaic spelling', $prompt);
        $this->assertStringContainsString('eng+afr', $prompt);
        $this->assertStringContainsString('64.2', $prompt);
        $this->assertStringContainsString('sample OCR text', $prompt);
    }

    /**
     * heratio#1525 regression: on the Brenthurst cuttings the model "corrected"
     * Vol. 2 p. 5 into fluent, wrong English. The guard must reject it.
     */
    public function test_the_brenthurst_rewrite_is_rejected(): void
    {
        $raw = 'The column was apparently trekking for Van Reenen\'s Pass';
        $rewrite = 'The column was trying to get to Van Reenen\'s Pass';

        $this->assertTrue(OcrLlmCorrector::rewriteGuard($raw, $rewrite)['rejected']);
    }

    public function test_a_typical_ocr_fix_passes_the_guard(): void
    {
        $raw = 'SEAFORTH HIGHLANDERS BRILLIANT WOLK at the river crossing on the second day of the advance';
        $fixed = 'SEAFORTH HIGHLANDERS BRILLIANT WORK at the river crossing on the second day of the advance';

        $guard = OcrLlmCorrector::rewriteGuard($raw, $fixed);
        $this->assertFalse($guard['rejected']);
        $this->assertLessThanOrEqual(OcrLlmCorrector::MAX_WORD_CHANGE, $guard['word_change']);
    }

    public function test_a_large_length_change_is_rejected_even_with_few_word_edits(): void
    {
        $raw = str_repeat('word ', 50);
        $this->assertTrue(OcrLlmCorrector::rewriteGuard($raw, $raw.str_repeat('extra ', 10))['rejected']);
    }
}
