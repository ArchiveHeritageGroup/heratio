<?php

/**
 * IconStylesheetTest - the icon fonts the views actually depend on.
 *
 * The views use 1002 bi-* icons across 180 files, but no page loaded the
 * Bootstrap Icons stylesheet, so all of them rendered as blank space: on
 * 23 Sep 2026 the WhatsApp bubble was an empty green circle and the voice
 * mic button an empty blue one. The theme's own bundle is Font Awesome and
 * carries no bi-* rules, so the layout must load both.
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

namespace Tests\Unit;

use Tests\TestCase;

class IconStylesheetTest extends TestCase
{
    private const CSS = 'vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css';

    public function test_a_page_on_the_shared_layout_loads_the_bootstrap_icons_stylesheet(): void
    {
        $this->get('/heritage')->assertOk()->assertSee(self::CSS, false);
    }

    /** The link is worthless if the asset is not there to serve. */
    public function test_the_stylesheet_and_its_font_are_present_and_define_the_icons_in_use(): void
    {
        $css = public_path(self::CSS);
        $this->assertFileExists($css);
        $this->assertFileExists(public_path('vendor/bootstrap-icons/1.11.3/fonts/bootstrap-icons.woff2'));

        $rules = (string) file_get_contents($css);
        foreach (['bi-mic', 'bi-whatsapp', 'bi-universal-access'] as $icon) {
            $this->assertStringContainsString('.'.$icon.'::before', $rules);
        }
    }
}
