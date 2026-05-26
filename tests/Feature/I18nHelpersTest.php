<?php

/**
 * Issue #675 Phase 3 - locale-aware formatting helpers.
 *
 * Smoke tests for the six ahg_* helpers exposed by app/Helpers/i18n.php.
 * Each test flips App::setLocale(), formats a known value, and asserts the
 * exact ICU-rendered string. ICU CLDR data does shift between releases, so
 * if a string assertion drifts after an icu upgrade, update the expected
 * value here and add a comment noting the CLDR version that produced it.
 *
 * Skipped wholesale when ext-intl is missing (helpers fall back to raw
 * input in that case, which is exercised in I18nHelpersFallbackTest).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

class I18nHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('intl')) {
            $this->markTestSkipped('ext-intl not available; ahg_* helpers fall back to raw input.');
        }
    }

    public function test_ahg_date_renders_in_en(): void
    {
        App::setLocale('en');

        $this->assertSame('Aug 2, 2026', ahg_date('2026-08-02'));
    }

    public function test_ahg_date_renders_in_af(): void
    {
        App::setLocale('af');

        // Afrikaans CLDR (ICU 73+): "02 Aug. 2026" — note the leading zero on
        // the day. Spec doc gives "2 Aug. 2026" as the approximate shape; the
        // canonical ICU output (which is what the helper emits) is asserted
        // here.
        $this->assertSame('02 Aug. 2026', ahg_date('2026-08-02'));
    }

    public function test_ahg_datetime_renders_in_en(): void
    {
        App::setLocale('en');

        // ICU 73+ inserts U+202F (NARROW NO-BREAK SPACE) between the time and
        // the AM/PM marker - looks identical, behaves like a non-breaking
        // separator. The literal codepoint is part of the surface.
        $this->assertSame("Aug 2, 2026, 12:34\u{202F}PM", ahg_datetime('2026-08-02 12:34'));
    }

    public function test_ahg_datetime_renders_in_af(): void
    {
        App::setLocale('af');

        $this->assertSame('02 Aug. 2026 12:34', ahg_datetime('2026-08-02 12:34'));
    }

    public function test_ahg_time_renders_in_en(): void
    {
        App::setLocale('en');

        // ICU 73+ NNBSP between digits and AM/PM (see test_ahg_datetime_renders_in_en).
        $this->assertSame("12:34\u{202F}PM", ahg_time('2026-08-02 12:34'));
    }

    public function test_ahg_number_renders_thousands_separators_en(): void
    {
        App::setLocale('en');

        $this->assertSame('1,234,567.89', ahg_number(1234567.89));
    }

    public function test_ahg_number_renders_thousands_separators_af(): void
    {
        App::setLocale('af');

        // Afrikaans CLDR: NBSP (U+00A0) for thousands, comma for decimal.
        $this->assertSame("1\u{00A0}234\u{00A0}567,89", ahg_number(1234567.89));
    }

    public function test_ahg_currency_en_zar(): void
    {
        App::setLocale('en');

        // en+ZAR -> ICU renders the ISO code (no localised symbol in en data
        // for ZAR). NBSP between code and value.
        $this->assertSame("ZAR\u{00A0}1,234.50", ahg_currency(1234.5, 'ZAR'));
    }

    public function test_ahg_currency_af_zar(): void
    {
        App::setLocale('af');

        // af+ZAR -> ICU renders the localised "R" symbol with NBSP separators.
        $this->assertSame("R\u{00A0}1\u{00A0}234,50", ahg_currency(1234.5, 'ZAR'));
    }

    public function test_ahg_percent_in_en(): void
    {
        App::setLocale('en');

        $this->assertSame('12.5%', ahg_percent(0.125));
    }

    public function test_ahg_date_passthrough_on_empty(): void
    {
        App::setLocale('en');

        $this->assertSame('', ahg_date(''));
        $this->assertSame('', ahg_date(null));
    }

    public function test_ahg_currency_uses_config_default(): void
    {
        App::setLocale('af');
        config(['app.currency' => 'ZAR']);

        $this->assertSame("R\u{00A0}10,00", ahg_currency(10));
    }
}
