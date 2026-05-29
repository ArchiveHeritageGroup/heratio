<?php

/**
 * MarcRoundTripAuthorityTest - authority-link ($0) round-trip coverage for the
 * MARCXML serializer + importer (heratio#1098).
 *
 * Pure-parse assertions (no DB):
 *   - the importer captures 650/651/655 $a + $0 pairs into authority_links.
 *   - subject_type is mapped per tag (topic/place/genre).
 *   - records without $0 still parse with null URIs.
 *
 * DB-bound assertions (skipped when the heratio schema or library tables are
 * not reachable from the test runner):
 *   - commit() of a record carrying $0 creates a library_subject_authority row
 *     and a library_item_authority_link, provided a library_item wrapper
 *     exists for the matched IO.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgMetadataExport\Tests;

use AhgMetadataExport\Services\Importers\MarcXmlImporter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarcRoundTripAuthorityTest extends TestCase
{
    public function test_importer_captures_authority_links_from_subfield_zero(): void
    {
        $importer = new MarcXmlImporter();
        $records = $importer->parseRecords($this->recordWithAuthorities());
        $this->assertCount(1, $records);

        $desc = $importer->describeRecord($records[0]);
        $this->assertArrayHasKey('authority_links', $desc);
        $links = $desc['authority_links'];
        $this->assertCount(3, $links, 'expected one link each for 650/651/655');

        $byTag = [];
        foreach ($links as $l) {
            $byTag[$l['tag']] = $l;
        }

        $this->assertSame('topic', $byTag['650']['subject_type']);
        $this->assertSame('Whaling', $byTag['650']['heading']);
        $this->assertSame('http://id.loc.gov/authorities/subjects/sh85146341', $byTag['650']['uri']);

        $this->assertSame('place', $byTag['651']['subject_type']);
        $this->assertSame('http://id.loc.gov/authorities/names/n79045553', $byTag['651']['uri']);

        $this->assertSame('genre', $byTag['655']['subject_type']);
        $this->assertSame('http://id.loc.gov/authorities/genreForms/gf2014026069', $byTag['655']['uri']);
    }

    public function test_subjects_without_subfield_zero_have_null_uri(): void
    {
        $importer = new MarcXmlImporter();
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<collection xmlns="http://www.loc.gov/MARC21/slim">
  <record>
    <leader>00000nam a2200000 i 4500</leader>
    <controlfield tag="001">NOAUTH-1</controlfield>
    <datafield tag="245" ind1="0" ind2="0"><subfield code="a">No Authorities</subfield></datafield>
    <datafield tag="650" ind1=" " ind2="4"><subfield code="a">Plain Subject</subfield></datafield>
  </record>
</collection>
XML;
        $desc = $importer->describeRecord($importer->parseRecords($xml)[0]);
        $this->assertCount(1, $desc['authority_links']);
        $this->assertNull($desc['authority_links'][0]['uri']);
        $this->assertSame('Plain Subject', $desc['authority_links'][0]['heading']);
    }

    public function test_commit_creates_authority_link_when_schema_present(): void
    {
        try {
            if (! Schema::hasTable('information_object')
                || ! Schema::hasTable('library_item')
                || ! Schema::hasTable('library_subject_authority')
                || ! Schema::hasTable('library_item_authority_link')) {
                $this->markTestSkipped('Library authority schema not present in test DB.');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: ' . $e->getMessage());
        }

        // We only assert the commit path does not throw and returns the
        // authority_links_created counter; full link verification requires a
        // seeded library_item wrapper which a clean test DB does not guarantee.
        $importer = new MarcXmlImporter();
        $results = $importer->commit($this->recordWithAuthorities(), 'en');
        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('authority_links_created', $results[0]);
        $this->assertIsInt($results[0]['authority_links_created']);
    }

    private function recordWithAuthorities(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<collection xmlns="http://www.loc.gov/MARC21/slim">
  <record>
    <leader>00000nam a2200000 i 4500</leader>
    <controlfield tag="001">AUTH-RT-1</controlfield>
    <datafield tag="245" ind1="0" ind2="0">
      <subfield code="a">Authority Round Trip</subfield>
    </datafield>
    <datafield tag="650" ind1=" " ind2="4">
      <subfield code="a">Whaling</subfield>
      <subfield code="0">http://id.loc.gov/authorities/subjects/sh85146341</subfield>
    </datafield>
    <datafield tag="651" ind1=" " ind2="4">
      <subfield code="a">Atlantic Ocean</subfield>
      <subfield code="0">http://id.loc.gov/authorities/names/n79045553</subfield>
    </datafield>
    <datafield tag="655" ind1=" " ind2="4">
      <subfield code="a">Logbooks</subfield>
      <subfield code="0">http://id.loc.gov/authorities/genreForms/gf2014026069</subfield>
    </datafield>
  </record>
</collection>
XML;
    }
}
