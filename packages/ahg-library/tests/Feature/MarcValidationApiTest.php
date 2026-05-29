<?php

/**
 * MarcValidationApiTest - exercises MarcValidationService directly (the engine
 * behind POST /api/cataloguing/marc/validate).
 *
 * These are pure-logic assertions against the validation engine: no HTTP
 * round-trip, no database, so they run green in any environment. The API
 * controller is a thin pass-through over the service, so service coverage is
 * the meaningful coverage.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Services\MarcValidationService;
use AhgLibrary\Tests\AhgLibraryTestCase;

class MarcValidationApiTest extends AhgLibraryTestCase
{
    private MarcValidationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new MarcValidationService();
    }

    public function test_valid_record_passes(): void
    {
        $report = $this->svc->validate($this->goodRecord());
        $this->assertTrue($report['valid'], 'expected valid, errors: ' . json_encode($report['records']));
        $this->assertSame(0, $report['error_count']);
        $this->assertCount(1, $report['records']);
        $this->assertSame('A Valid Title', $report['records'][0]['title']);
        $this->assertSame('HER-1', $report['records'][0]['control_number']);
    }

    public function test_malformed_xml_reports_parse_error(): void
    {
        $report = $this->svc->validate('<<not xml>>');
        $this->assertFalse($report['valid']);
        $this->assertNotEmpty($report['parse_error']);
    }

    public function test_short_leader_is_an_error(): void
    {
        $xml = str_replace('00000nam a2200000 i 4500', '00000nam', $this->goodRecord());
        $report = $this->svc->validate($xml);
        $this->assertFalse($report['valid']);
        $this->assertNotEmpty(array_filter(
            $report['records'][0]['errors'],
            fn ($e) => str_contains($e, 'Leader') && str_contains($e, '24 characters')
        ));
    }

    public function test_invalid_encoding_level_byte_17_is_an_error(): void
    {
        // Replace leader/17 (encoding level) with an invalid 'Q'.
        // Leader: positions 0-23. We craft one with a bad byte 17.
        $leader = '00000nam a2200000Qi 4500'; // byte17 = 'Q'
        $xml = str_replace('00000nam a2200000 i 4500', $leader, $this->goodRecord());
        $report = $this->svc->validate($xml);
        $this->assertFalse($report['valid']);
        $this->assertNotEmpty(array_filter(
            $report['records'][0]['errors'],
            fn ($e) => str_contains($e, 'Leader/17')
        ));
    }

    public function test_missing_245_is_an_error(): void
    {
        $xml = preg_replace('#<datafield tag="245".*?</datafield>#s', '', $this->goodRecord());
        $report = $this->svc->validate($xml);
        $this->assertFalse($report['valid']);
        $this->assertNotEmpty(array_filter(
            $report['records'][0]['errors'],
            fn ($e) => str_contains($e, '245')
        ));
    }

    public function test_invalid_subfield_code_is_an_error(): void
    {
        // Inject a subfield with an illegal multi-char code.
        $xml = str_replace(
            '<subfield code="a">A Valid Title</subfield>',
            '<subfield code="a">A Valid Title</subfield><subfield code="XX">bad</subfield>',
            $this->goodRecord()
        );
        $report = $this->svc->validate($xml);
        $this->assertFalse($report['valid']);
        $this->assertNotEmpty(array_filter(
            $report['records'][0]['errors'],
            fn ($e) => str_contains($e, 'subfield code')
        ));
    }

    public function test_control_field_with_subfield_is_an_error(): void
    {
        $xml = str_replace(
            '<controlfield tag="001">HER-1</controlfield>',
            '<controlfield tag="001"><subfield code="a">x</subfield></controlfield>',
            $this->goodRecord()
        );
        $report = $this->svc->validate($xml);
        $this->assertFalse($report['valid']);
        $this->assertNotEmpty(array_filter(
            $report['records'][0]['errors'],
            fn ($e) => str_contains($e, 'must not contain subfields')
        ));
    }

    public function test_short_008_is_a_warning_not_an_error(): void
    {
        // A 28-char 008 (legacy). Should warn, not fail.
        $xml = str_replace(
            '<controlfield tag="008">260101s2020    xx                  eng  </controlfield>',
            '<controlfield tag="008">260101s2020    xx     eng</controlfield>',
            $this->goodRecord()
        );
        $report = $this->svc->validate($xml);
        $this->assertTrue($report['valid'], 'short 008 should warn, not fail');
        $this->assertNotEmpty(array_filter(
            $report['records'][0]['warnings'],
            fn ($w) => str_contains($w, '008')
        ));
    }

    private function goodRecord(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<collection xmlns="http://www.loc.gov/MARC21/slim">
  <record>
    <leader>00000nam a2200000 i 4500</leader>
    <controlfield tag="001">HER-1</controlfield>
    <controlfield tag="008">260101s2020    xx                  eng  </controlfield>
    <datafield tag="245" ind1="0" ind2="0">
      <subfield code="a">A Valid Title</subfield>
    </datafield>
  </record>
</collection>
XML;
    }
}
