<?php

/**
 * DataMigrationExportsTest - Feature tests for the six exports parity actions.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Issue #740 - Data-migration exports parity.
 * PSIS twin: atom-ahg-plugins#86.
 */

namespace Tests\Feature;

use AhgDataMigration\Services\DataMigrationService;
use Tests\TestCase;

class DataMigrationExportsTest extends TestCase
{
    protected string $csvPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->csvPath = tempnam(sys_get_temp_dir(), 'dm_test_').'.csv';
        file_put_contents($this->csvPath, $this->fixtureCsv());
    }

    protected function tearDown(): void
    {
        if ($this->csvPath && file_exists($this->csvPath)) {
            @unlink($this->csvPath);
        }
        parent::tearDown();
    }

    private function fixtureCsv(): string
    {
        return "Identifier,Title,Dates,Creator,Scope\n"
            ."ABC-001,First record,1990-1992,A. Author,Some scope and content text\n"
            ."ABC-002,Second record,1995,B. Builder,Another note\n";
    }

    private function fixtureMapping(): array
    {
        return [
            ['include' => 1, 'source_field' => 'Identifier', 'atom_field' => 'identifier'],
            ['include' => 1, 'source_field' => 'Title',      'atom_field' => 'title'],
            ['include' => 1, 'source_field' => 'Dates',      'atom_field' => 'eventDates'],
            ['include' => 1, 'source_field' => 'Creator',    'atom_field' => 'eventActors'],
            ['include' => 1, 'source_field' => 'Scope',      'atom_field' => 'scopeAndContent'],
            ['include' => 1, 'source_field' => '', 'atom_field' => 'levelOfDescription', 'constant_value' => 'item', 'concat_constant' => 1],
        ];
    }

    public function test_transform_with_mapping_produces_expected_records(): void
    {
        $svc = new DataMigrationService;
        $headers = ['Identifier', 'Title', 'Dates', 'Creator', 'Scope'];
        $rows = [
            ['ABC-001', 'First record', '1990-1992', 'A. Author', 'Scope text'],
        ];

        $records = $svc->transformWithMapping($rows, $headers, $this->fixtureMapping());

        $this->assertCount(1, $records);
        $this->assertSame('ABC-001', $records[0]['identifier']);
        $this->assertSame('First record', $records[0]['title']);
        $this->assertSame('item', $records[0]['levelOfDescription']);
    }

    public function test_generate_ead_xml_emits_well_formed_xml_with_components(): void
    {
        $svc = new DataMigrationService;
        $xml = $svc->generateEadXml([
            ['identifier' => 'ABC-001', 'title' => 'First', 'levelOfDescription' => 'item', 'scopeAndContent' => 'Hi'],
            ['identifier' => 'ABC-002', 'title' => 'Second', 'levelOfDescription' => 'file'],
        ], 'demo.csv');

        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('<ead xmlns="urn:isbn:1-931666-22-9"', $xml);
        $this->assertStringContainsString('<unitid>ABC-001</unitid>', $xml);
        $this->assertStringContainsString('<unittitle>Second</unittitle>', $xml);
        $this->assertStringContainsString('level="item"', $xml);
        $this->assertStringContainsString('level="file"', $xml);

        // Confirm well-formed.
        $dom = new \DOMDocument;
        $this->assertTrue((bool) $dom->loadXML($xml));
    }

    public function test_build_ahg_csv_emits_header_row_and_data_rows(): void
    {
        $svc = new DataMigrationService;
        $csv = $svc->buildAhgCsv([
            ['identifier' => 'A1', 'title' => 'Alpha', 'levelOfDescription' => 'item'],
            ['identifier' => 'A2', 'title' => 'Beta'],
        ]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv); // BOM
        $this->assertStringContainsString('identifier', $csv);
        $this->assertStringContainsString('ahgSecurityClassification', $csv);
        $this->assertStringContainsString('A1', $csv);
        $this->assertStringContainsString('Alpha', $csv);
    }

    public function test_sector_columns_returns_sector_specific_columns(): void
    {
        $svc = new DataMigrationService;

        $archive = $svc->sectorColumns('archive');
        $museum = $svc->sectorColumns('museum');
        $library = $svc->sectorColumns('library');
        $gallery = $svc->sectorColumns('gallery');
        $dam = $svc->sectorColumns('dam');
        $fallback = $svc->sectorColumns('unknown-sector');

        $this->assertArrayHasKey('identifier', $archive);
        $this->assertArrayHasKey('levelOfDescription', $archive);
        $this->assertArrayHasKey('materials', $museum);
        $this->assertArrayHasKey('isbn', $library);
        $this->assertArrayHasKey('artist', $gallery);
        $this->assertArrayHasKey('mimeType', $dam);
        // Unknown sectors collapse to archive defaults.
        $this->assertSame($archive, $fallback);
    }

    public function test_sector_export_csv_uses_sector_columns(): void
    {
        $svc = new DataMigrationService;
        $records = [
            ['identifier' => 'L1', 'title' => 'A library book', 'isbn' => '978-0000', 'author' => 'Author'],
        ];

        $csv = $svc->sectorExportCsv('library', $records);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Call number', $csv); // library label
        $this->assertStringContainsString('ISBN', $csv);
        $this->assertStringContainsString('978-0000', $csv);
        $this->assertStringNotContainsString('Level of description', $csv); // archive-only label
    }

    public function test_detect_spreadsheet_sheets_handles_csv_input(): void
    {
        $svc = new DataMigrationService;
        $result = $svc->detectSpreadsheetSheets($this->csvPath);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['count']);
        $this->assertSame('Sheet1', $result['sheets'][0]['name']);
        $this->assertSame(2, $result['sheets'][0]['rows']);
        $this->assertContains('Identifier', $result['sheets'][0]['headers']);
        $this->assertContains('Title', $result['sheets'][0]['headers']);
    }

    public function test_detect_spreadsheet_sheets_rejects_unknown_extensions(): void
    {
        $svc = new DataMigrationService;
        $junk = tempnam(sys_get_temp_dir(), 'dm_junk_').'.bin';
        file_put_contents($junk, 'not a spreadsheet');

        $result = $svc->detectSpreadsheetSheets($junk);
        @unlink($junk);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unsupported', $result['error']);
    }

    public function test_preview_mapped_returns_projected_rows(): void
    {
        $svc = new DataMigrationService;
        $result = $svc->previewMapped($this->csvPath, $this->fixtureMapping(), 10);

        $this->assertCount(5, $result['headers']);
        $this->assertCount(2, $result['rows']);
        $this->assertCount(2, $result['preview']);
        $this->assertSame('ABC-001', $result['preview'][0]['identifier']);
        $this->assertSame('item', $result['preview'][0]['levelOfDescription']);
    }

    public function test_preview_mapped_returns_empty_payload_for_missing_file(): void
    {
        $svc = new DataMigrationService;
        $result = $svc->previewMapped('/no/such/file.csv', [], 10);

        $this->assertSame([], $result['preview']);
        $this->assertSame([], $result['rows']);
    }
}
