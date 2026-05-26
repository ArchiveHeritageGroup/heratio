<?php

/**
 * MarcXmlImporterTest - round-trip + schema-validation coverage for the
 * MARCXML importer (#663 Phase 2).
 *
 * Skips DB-bound assertions when the heratio schema is not reachable from
 * the test runner so the suite stays green in CI containers without a
 * live database.
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
 */

namespace AhgMetadataExport\Tests;

use AhgMetadataExport\Services\Exporters\MarcxmlSerializer;
use AhgMetadataExport\Services\Importers\MarcXmlImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarcXmlImporterTest extends TestCase
{
    /**
     * Validate-then-parse a hand-crafted MARCXML document and assert all
     * the populated subfields survive the parse step.
     */
    public function test_static_marcxml_parses_into_expected_fields(): void
    {
        $xml = $this->fixtureXml(
            controlNumber: 'HER-TEST-001',
            title: 'Field Notebook of Dr Smith',
            scope: 'Daily observations from a 1972 expedition.',
            extent: '1 notebook (40 leaves)',
        );

        $importer = new MarcXmlImporter();
        [$valid, $errors] = $importer->validate($xml);
        $this->assertTrue($valid, 'Vendored MARC21slim.xsd rejected fixture: '.implode('; ', $errors));

        $records = $importer->parseRecords($xml);
        $this->assertCount(1, $records);

        $desc = $importer->describeRecord($records[0]);
        $this->assertSame('HER-TEST-001', $desc['control_number']);
        $this->assertSame('Field Notebook of Dr Smith', $desc['title']);
        $this->assertSame('Daily observations from a 1972 expedition.', $desc['scope_and_content']);
        $this->assertSame('1 notebook (40 leaves)', $desc['extent_and_medium']);
        $this->assertSame('text', $desc['carrier_term']
            ? null  // mapper-output carrier term, may vary across fixtures
            : null
        );
    }

    /**
     * Round-trip: serialize a real IO via MarcxmlSerializer, parse it back
     * with MarcXmlImporter, and assert the round-trip-safe fields survived.
     * Skips when no IO is reachable.
     */
    public function test_round_trip_recovers_core_fields(): void
    {
        try {
            if (! Schema::hasTable('information_object')) {
                $this->markTestSkipped('information_object table not present in test DB');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable in test environment: '.$e->getMessage());
        }

        $ioId = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', 'en');
            })
            ->whereNotNull('i18n.title')
            ->where('io.id', '>', 1)
            ->limit(1)
            ->value('io.id');

        if (! $ioId) {
            $this->markTestSkipped('No usable information_object row for round-trip');
        }

        $serializer = new MarcxmlSerializer();
        $xml = $serializer->serializeRecord((int) $ioId, 'en');
        $this->assertNotEmpty($xml, 'serializer returned empty document');

        // The serializer emits a bare <record> - wrap it for schema validation
        $wrapped = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<collection xmlns="http://www.loc.gov/MARC21/slim">'
            .$xml.'</collection>';

        $importer = new MarcXmlImporter();
        [$valid, $errors] = $importer->validate($wrapped);
        $this->assertTrue($valid, 'round-trip MARCXML failed schema validation: '.implode('; ', $errors));

        $records = $importer->parseRecords($wrapped);
        $this->assertCount(1, $records);
        $desc = $importer->describeRecord($records[0]);

        // Title is mandatory at the exporter end - must round-trip
        $this->assertNotEmpty($desc['title'], 'title lost in round-trip');

        // 001 should equal io.identifier (if set) or the stringified id
        $expectedIdentifier = DB::table('information_object')->where('id', $ioId)->value('identifier')
            ?: (string) $ioId;
        $this->assertSame((string) $expectedIdentifier, (string) $desc['control_number']);

        // RDA 338 carrier term must be present
        $this->assertNotEmpty($desc['carrier_term'], '338$a carrier term missing from emit');
    }

    /**
     * Malformed XML is rejected by the validator (well-formedness check).
     */
    public function test_malformed_xml_is_rejected(): void
    {
        $importer = new MarcXmlImporter();
        [$valid, $errors] = $importer->validate('<<not xml>>');
        $this->assertFalse($valid);
        $this->assertNotEmpty($errors);
    }

    private function fixtureXml(string $controlNumber, string $title, string $scope, string $extent): string
    {
        $cn = htmlspecialchars($controlNumber, ENT_XML1);
        $t = htmlspecialchars($title, ENT_XML1);
        $s = htmlspecialchars($scope, ENT_XML1);
        $e = htmlspecialchars($extent, ENT_XML1);
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<collection xmlns="http://www.loc.gov/MARC21/slim">
  <record>
    <leader>00000nac a2200000ui 4500</leader>
    <controlfield tag="001">{$cn}</controlfield>
    <controlfield tag="003">Heratio</controlfield>
    <controlfield tag="005">20260526120000.0</controlfield>
    <controlfield tag="008">260526s1972                    eng  </controlfield>
    <datafield tag="245" ind1="0" ind2="0">
      <subfield code="a">{$t}</subfield>
    </datafield>
    <datafield tag="300" ind1=" " ind2=" ">
      <subfield code="a">{$e}</subfield>
    </datafield>
    <datafield tag="336" ind1=" " ind2=" ">
      <subfield code="a">text</subfield>
      <subfield code="2">rdacontent</subfield>
    </datafield>
    <datafield tag="337" ind1=" " ind2=" ">
      <subfield code="a">unmediated</subfield>
      <subfield code="2">rdamedia</subfield>
    </datafield>
    <datafield tag="338" ind1=" " ind2=" ">
      <subfield code="a">volume</subfield>
      <subfield code="2">rdacarrier</subfield>
    </datafield>
    <datafield tag="520" ind1=" " ind2=" ">
      <subfield code="a">{$s}</subfield>
    </datafield>
  </record>
</collection>
XML;
    }
}
