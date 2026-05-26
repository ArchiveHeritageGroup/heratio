<?php

/**
 * OcrServiceTest - pure unit tests for the Tesseract TSV parser + language
 * resolution helpers. No Laravel bootstrap, no DB, no real Tesseract.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

declare(strict_types=1);

namespace AhgAiServices\Tests\Unit;

use AhgAiServices\Services\OcrService;
use PHPUnit\Framework\TestCase;

class OcrServiceTest extends TestCase
{
    private function svc(): OcrService
    {
        // Construct without the Laravel container; LLM + corrector deps
        // are unused by parseTsv / iso639ToTesseract / normaliseLangSpec.
        return new OcrService(null, null);
    }

    public function test_parse_tsv_extracts_word_boxes_and_text(): void
    {
        $tsv = "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext\n"
             . "5\t1\t1\t1\t1\t1\t10\t20\t30\t12\t95\tHello\n"
             . "5\t1\t1\t1\t1\t2\t50\t20\t30\t12\t90\tworld\n"
             . "5\t1\t1\t1\t2\t1\t10\t40\t40\t12\t80\tFoo\n";
        $parsed = $this->svc()->parseTsv($tsv);
        $this->assertSame("Hello world\nFoo", $parsed['text']);
        $this->assertCount(3, $parsed['words']);
        $this->assertSame('Hello', $parsed['words'][0]['text']);
        $this->assertSame(10, $parsed['words'][0]['x']);
        $this->assertEqualsWithDelta(88.33, $parsed['mean_confidence'], 0.5);
    }

    public function test_parse_tsv_returns_empty_on_garbage_input(): void
    {
        $this->assertSame(
            ['text' => '', 'words' => [], 'mean_confidence' => null],
            $this->svc()->parseTsv('')
        );
    }

    public function test_iso639_maps_sadc_codes_to_tesseract_tags(): void
    {
        $s = $this->svc();
        $this->assertSame('afr', $s->iso639ToTesseract('af'));
        $this->assertSame('zul', $s->iso639ToTesseract('zu'));
        $this->assertSame('nld', $s->iso639ToTesseract('nl'));
        $this->assertSame('eng', $s->iso639ToTesseract('en'));
        $this->assertSame('sna', $s->iso639ToTesseract('sn')); // Shona for Zimbabwe
        $this->assertSame('por', $s->iso639ToTesseract('pt')); // Mozambique
    }

    public function test_iso639_passes_through_unknown_codes(): void
    {
        // Custom traineddata operator may have installed: leave alone.
        $this->assertSame('xy_custom', $this->svc()->iso639ToTesseract('xy_custom'));
    }
}
