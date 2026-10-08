<?php

/**
 * VisionOcrServiceTest - vision-model OCR checks (heratio#1522).
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
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgAiServices\Tests\Unit;

use AhgAiServices\Services\VisionOcrService;
use PHPUnit\Framework\TestCase;

class VisionOcrServiceTest extends TestCase
{
    private const TESS = "SEAFORTH HIGHLANDERS BRILLIANT WOLK\nThe column was apparently trekking for Van Reenen's Pass\nwhen the telegraph reached the regiment at Ladysmith";

    public function test_a_faithful_reading_passes_the_cross_check(): void
    {
        $vision = "SEAFORTH HIGHLANDERS' BRILLIANT WORK.\nThe column was apparently trekking for Van Reenen's Pass\nwhen the telegraph reached the regiment at Ladysmith";

        $this->assertSame([], (new VisionOcrService(fn () => ''))->problems($vision, self::TESS));
    }

    public function test_short_repeating_or_divergent_readings_are_caught(): void
    {
        $svc = new VisionOcrService(fn () => '');

        $this->assertContains('much_shorter', $svc->problems('SEAFORTH', self::TESS));
        $this->assertContains('repeats_itself', $svc->problems(str_repeat("The column was apparently trekking\n", 3), self::TESS));
        $this->assertContains('shares_few_words', $svc->problems('Something else entirely about another subject, written fluently and at length here.', self::TESS));
    }

    public function test_model_remarks_are_removed_but_bylines_stay(): void
    {
        $svc = new VisionOcrService(fn () => '');
        $out = $svc->clean("system\n[Top of image is cut off]\n[By Telegraph.-From Our Special Correspondent.]\nLADYSMITH, Monday.");

        $this->assertSame("[By Telegraph.-From Our Special Correspondent.]\nLADYSMITH, Monday.", $out);
    }

    public function test_a_tall_page_is_read_in_strips_and_a_failed_strip_is_reread(): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('imagick missing');
        }
        $path = tempnam(sys_get_temp_dir(), 'vocr').'.png';
        $img = new \Imagick;
        $img->newImage(600, 3000, 'white');
        $img->setImageFormat('png');
        $img->writeImage($path);

        $calls = [];
        $reader = function (string $model, string $png) use (&$calls) {
            $calls[] = $model;

            // The primary model returns nothing useful for the first strip.
            return count($calls) === 1 ? '' : 'The column was apparently trekking for Van Reenen\'s Pass';
        };
        $words = [];
        foreach (['The', 'column', 'was', 'apparently', 'trekking', 'for', 'Van', 'Reenen\'s', 'Pass'] as $i => $w) {
            $words[] = ['text' => $w, 'x' => 10 + $i * 50, 'y' => 100, 'width' => 40, 'height' => 20];
        }

        $out = (new VisionOcrService($reader))->read($path, $words);
        @unlink($path);

        $this->assertTrue($out['success']);
        $this->assertGreaterThanOrEqual(2, count($out['strips']), 'a 3000 px page is cut into strips');
        $this->assertSame(VisionOcrService::DEFAULT_FALLBACK_MODEL, $out['strips'][0]['model'], 'the failed first strip was re-read with the fallback model');
        $this->assertFalse($out['strips'][0]['flagged']);
        $this->assertStringContainsString('apparently trekking', $out['text']);
    }
}
