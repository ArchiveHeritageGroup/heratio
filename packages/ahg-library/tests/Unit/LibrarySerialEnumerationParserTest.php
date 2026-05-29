<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Unit;

use AhgLibrary\Services\LibrarySerialEnumerationParser;
use AhgLibrary\Tests\AhgLibraryTestCase;

/**
 * Unit coverage for the serials enumeration / chronology parser (heratio#1092).
 * Pure parsing + increment logic - no DB or framework needed.
 */
class LibrarySerialEnumerationParserTest extends AhgLibraryTestCase
{
    private LibrarySerialEnumerationParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new LibrarySerialEnumerationParser();
    }

    // ── parse ───────────────────────────────────────────────────────────

    public function test_parses_full_volume_issue_chronology(): void
    {
        $p = $this->parser->parse('Vol. 1-no.12 (Jan-Dec 2025)');

        $this->assertSame('1', $p['volume']);
        $this->assertSame('12', $p['issue']);
        $this->assertSame(1, $p['month_start']);
        $this->assertSame(12, $p['month_end']);
        $this->assertSame(2025, $p['year']);
    }

    public function test_parses_volume_and_issue_with_single_month(): void
    {
        $p = $this->parser->parse('Vol. 12, No. 3 (Mar 2025)');

        $this->assertSame('12', $p['volume']);
        $this->assertSame('3', $p['issue']);
        $this->assertSame(3, $p['month_start']);
        $this->assertNull($p['month_end']);
        $this->assertSame(2025, $p['year']);
    }

    public function test_parses_short_v_n_form(): void
    {
        $p = $this->parser->parse('v.5 no.2 (2024)');

        $this->assertSame('5', $p['volume']);
        $this->assertSame('2', $p['issue']);
        $this->assertSame(2024, $p['year']);
    }

    public function test_parses_volume_only_annual(): void
    {
        $p = $this->parser->parse('Vol 7 (2023)');

        $this->assertSame('7', $p['volume']);
        $this->assertNull($p['issue']);
        $this->assertSame(2023, $p['year']);
    }

    public function test_parses_season(): void
    {
        $p = $this->parser->parse('No. 145 (Spring 2025)');

        $this->assertSame('145', $p['issue']);
        $this->assertSame('spring', $p['season']);
        $this->assertSame(2025, $p['year']);
    }

    public function test_fall_normalises_to_autumn(): void
    {
        $p = $this->parser->parse('No. 9 (Fall 2024)');
        $this->assertSame('autumn', $p['season']);
    }

    public function test_parses_volume_range(): void
    {
        $p = $this->parser->parse('Vol. 1-3 (2020-2022)');
        $this->assertSame('1', $p['volume']);
        $this->assertSame('3', $p['volume_end']);
    }

    // ── increment ───────────────────────────────────────────────────────

    public function test_increments_issue_within_volume(): void
    {
        $next = $this->parser->increment(['volume' => '5', 'issue' => '3'], 'monthly');

        $this->assertSame('5', $next['volume']);
        $this->assertSame('4', $next['issue_number']);
    }

    public function test_rolls_over_volume_at_year_end_for_monthly(): void
    {
        // Monthly = 12 issues/year. Issue 12 -> volume bump, issue resets to 1.
        $next = $this->parser->increment(['volume' => '5', 'issue' => '12'], 'monthly');

        $this->assertSame('6', $next['volume']);
        $this->assertSame('1', $next['issue_number']);
    }

    public function test_rolls_over_volume_for_quarterly(): void
    {
        // Quarterly = 4 issues/year.
        $next = $this->parser->increment(['volume' => '2', 'issue' => '4'], 'quarterly');

        $this->assertSame('3', $next['volume']);
        $this->assertSame('1', $next['issue_number']);
    }

    public function test_volume_only_serial_increments_volume(): void
    {
        $next = $this->parser->increment(['volume' => '7', 'issue' => null], 'annual');

        $this->assertSame('8', $next['volume']);
        $this->assertNull($next['issue_number']);
    }

    public function test_irregular_never_rolls_over(): void
    {
        // issuesPerYear = 0 for irregular, so no rollover regardless of issue.
        $next = $this->parser->increment(['volume' => '1', 'issue' => '99'], 'irregular');

        $this->assertSame('1', $next['volume']);
        $this->assertSame('100', $next['issue_number']);
    }

    // ── chronology label / format ───────────────────────────────────────

    public function test_chronology_label_month_range(): void
    {
        $label = $this->parser->chronologyLabel($this->parser->parse('Vol. 1-no.12 (Jan-Dec 2025)'));
        $this->assertSame('Jan-Dec 2025', $label);
    }

    public function test_chronology_label_season(): void
    {
        $label = $this->parser->chronologyLabel($this->parser->parse('No. 1 (Spring 2025)'));
        $this->assertSame('Spring 2025', $label);
    }

    public function test_chronology_label_year_only(): void
    {
        $label = $this->parser->chronologyLabel($this->parser->parse('Vol. 7 (2023)'));
        $this->assertSame('2023', $label);
    }

    public function test_format_round_trip(): void
    {
        $formatted = $this->parser->format([
            'volume'       => '5',
            'issue_number' => '3',
            'chronology'   => 'Mar 2025',
        ]);
        $this->assertSame('Vol. 5, No. 3 (Mar 2025)', $formatted);
    }

    public function test_issues_per_year_mapping(): void
    {
        $this->assertSame(52, $this->parser->issuesPerYear('weekly'));
        $this->assertSame(12, $this->parser->issuesPerYear('monthly'));
        $this->assertSame(4, $this->parser->issuesPerYear('quarterly'));
        $this->assertSame(1, $this->parser->issuesPerYear('annual'));
        $this->assertSame(0, $this->parser->issuesPerYear('irregular'));
    }
}
