<?php

/**
 * AiContextHintsTest - pure DTO behaviour for the embedded-metadata
 * context hint DTO used by NerService / HtrService / DonutService /
 * LlmService (issue #750).
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Author: Johan Pieterse <johan@plainsailingisystems.co.za>
 *
 * This file is part of Heratio.
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

declare(strict_types=1);

namespace AhgAiServices\Tests\Unit;

use AhgAiServices\DTO\AiContextHints;
use PHPUnit\Framework\TestCase;

class AiContextHintsTest extends TestCase
{
    public function test_empty_dto_is_empty(): void
    {
        $h = AiContextHints::empty();
        $this->assertTrue($h->isEmpty());
        $this->assertSame('', $h->toPromptPrefix());
    }

    public function test_prompt_prefix_includes_all_present_hints(): void
    {
        $h = new AiContextHints(
            dateHint: '1969-07-20 20:17',
            placeHint: '28.0473,-26.2041',
            creatorHint: 'Neil Armstrong',
            subjectHints: ['Apollo 11', 'Moon landing'],
        );
        $prefix = $h->toPromptPrefix();

        $this->assertStringStartsWith('Hints from image metadata:', $prefix);
        $this->assertStringContainsString('date=1969-07-20', $prefix);
        $this->assertStringContainsString('location=28.0473,-26.2041', $prefix);
        $this->assertStringContainsString('creator=Neil Armstrong', $prefix);
        $this->assertStringContainsString('subjects=Apollo 11, Moon landing', $prefix);
        $this->assertStringContainsString('Use these to disambiguate entities.', $prefix);
    }

    public function test_prompt_prefix_skips_null_fields(): void
    {
        $h = new AiContextHints(dateHint: '2020-01-15', creatorHint: 'A. Photographer');
        $prefix = $h->toPromptPrefix();

        $this->assertStringContainsString('date=2020-01-15', $prefix);
        $this->assertStringContainsString('creator=A. Photographer', $prefix);
        $this->assertStringNotContainsString('location=', $prefix);
        $this->assertStringNotContainsString('subjects=', $prefix);
    }

    public function test_to_array_includes_suppressed_reasons(): void
    {
        $h = new AiContextHints(
            dateHint: '1969-07-20',
            suppressedReasons: ['GPS suppressed by PII finding #42'],
        );
        $arr = $h->toArray();

        $this->assertSame('1969-07-20', $arr['date']);
        $this->assertNull($arr['place']);
        $this->assertNull($arr['creator']);
        $this->assertSame([], $arr['subjects']);
        $this->assertSame(['GPS suppressed by PII finding #42'], $arr['suppressed_reasons']);
    }

    public function test_only_subjects_still_renders_prefix(): void
    {
        $h = new AiContextHints(subjectHints: ['Cape Town', 'Table Mountain']);
        $this->assertFalse($h->isEmpty());
        $this->assertStringContainsString('subjects=Cape Town, Table Mountain', $h->toPromptPrefix());
    }
}
