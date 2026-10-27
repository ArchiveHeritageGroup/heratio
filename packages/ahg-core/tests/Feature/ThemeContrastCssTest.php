<?php

/**
 * ThemeContrastCssTest - heratio#1532: the theme CSS that every page loads
 * shades a failing colour pair to WCAG AA, and sets the list-group active
 * colours on .list-group, where Bootstrap would otherwise override them.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Tests\Feature;

use AhgCore\Support\ColourContrast;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThemeContrastCssTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_theme_css_brings_a_failing_pair_up_to_aa(): void
    {
        foreach (['ahg_descbar_bg' => '#619e8f', 'ahg_descbar_text' => '#ffffff'] as $key => $value) {
            DB::table('ahg_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value, 'setting_group' => 'general']);
        }

        $css = $this->get(route('settings.dynamic-css'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/--ahg-descbar-bg: (#[0-9a-f]{6});/', $css);
        preg_match('/--ahg-descbar-bg: (#[0-9a-f]{6});/', $css, $m);
        $this->assertNotSame('#619e8f', $m[1], 'the failing colour is not served as is');
        $this->assertGreaterThanOrEqual(ColourContrast::AA, ColourContrast::ratio($m[1], '#ffffff'));
        $this->assertStringContainsString('.list-group { --bs-list-group-active-bg: var(--ahg-list-active-bg);', $css);
    }
}
