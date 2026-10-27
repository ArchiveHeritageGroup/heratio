<?php

/**
 * ColourContrastTest - heratio#1532: WCAG 2.x contrast for theme colours, and
 * the shading that brings an administrator's colour pair up to 4.5:1.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Tests\Unit;

use AhgCore\Support\ColourContrast;
use PHPUnit\Framework\TestCase;

class ColourContrastTest extends TestCase
{
    public function test_ratios_match_the_wcag_formula(): void
    {
        $this->assertEqualsWithDelta(21.0, ColourContrast::ratio('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, ColourContrast::ratio('#777', '#777777'), 0.001);
        // The description-bar failure axe reported on 27 Oct 2026: 3.09:1.
        $this->assertEqualsWithDelta(3.09, ColourContrast::ratio('#619e8f', '#ffffff'), 0.01);
        $this->assertNull(ColourContrast::ratio('var(--ahg-primary)', '#ffffff'));
    }

    public function test_a_failing_background_is_shaded_just_enough_and_keeps_its_hue(): void
    {
        $bg = ColourContrast::ensure('#619e8f', '#ffffff');
        $this->assertGreaterThanOrEqual(ColourContrast::AA, ColourContrast::ratio($bg, '#ffffff'));
        $this->assertLessThan(5.0, ColourContrast::ratio($bg, '#ffffff'), 'only as dark as needed');
        [$r, $g, $b] = ColourContrast::rgb($bg);
        $this->assertTrue($g > $b && $b > $r, 'still the same green-teal');

        // Dark text on a dark ground is lightened instead.
        $light = ColourContrast::ensure('#555555', '#222222');
        $this->assertGreaterThanOrEqual(ColourContrast::AA, ColourContrast::ratio($light, '#222222'));
        $this->assertGreaterThan(ColourContrast::luminance(ColourContrast::rgb('#555555')), ColourContrast::luminance(ColourContrast::rgb($light)));
    }

    public function test_passing_pairs_and_non_hex_values_are_left_alone(): void
    {
        $this->assertSame('#005837', ColourContrast::ensure('#005837', '#ffffff'));
        $this->assertSame('var(--ahg-primary)', ColourContrast::ensure('var(--ahg-primary)', '#ffffff'));
        $this->assertSame('#f8f9fa', ColourContrast::ensure('#f8f9fa', 'inherit'));
    }
}
