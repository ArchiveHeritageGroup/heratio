<?php

/**
 * ahg_html_lang() turns Heratio locale codes (the lang/*.json filenames)
 * into BCP 47 tags for <html lang>. pt_BR, de_AT and ca@valencia are
 * valid locale codes here but not valid language tags, so layouts that
 * printed app()->getLocale() directly told browsers and screen readers
 * nothing usable for regional locales.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

class HtmlLangTagTest extends TestCase
{
    public function test_plain_language_codes_are_unchanged(): void
    {
        $this->assertSame('en', ahg_html_lang('en'));
        $this->assertSame('pt', ahg_html_lang('pt'));
        $this->assertSame('fil', ahg_html_lang('fil'));
    }

    public function test_region_underscore_becomes_hyphen(): void
    {
        $this->assertSame('pt-BR', ahg_html_lang('pt_BR'));
        $this->assertSame('de-AT', ahg_html_lang('de_AT'));
        $this->assertSame('de-CH', ahg_html_lang('de_CH'));
    }

    public function test_variant_marker_becomes_hyphen(): void
    {
        $this->assertSame('ca-valencia', ahg_html_lang('ca@valencia'));
    }

    public function test_defaults_to_the_active_locale(): void
    {
        App::setLocale('pt_BR');

        $this->assertSame('pt-BR', ahg_html_lang());
    }

    public function test_empty_locale_falls_back_to_en(): void
    {
        $this->assertSame('en', ahg_html_lang(''));
    }
}
