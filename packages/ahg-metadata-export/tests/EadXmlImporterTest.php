<?php

/**
 * EadXmlImporterTest - round-trip + schema-validation coverage for the
 * EAD2002/EAD3 importer (#657 Phase 1).
 *
 * Mirrors the MarcXmlImporterTest skip-on-stub-DB pattern so the suite
 * stays green when the heratio schema isn't reachable.
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

use AhgMetadataExport\Services\Exporters\Ead2002Serializer;
use AhgMetadataExport\Services\Exporters\Ead3Serializer;
use AhgMetadataExport\Services\Importers\EadXmlImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EadXmlImporterTest extends TestCase
{
    public function test_detects_ead3_variant_by_namespace(): void
    {
        $xml = $this->ead3Fixture(
            recordid: 'HER-EAD3-001',
            unittitle: 'Diary of Mrs Brown',
            scope: 'Daily entries, 1903-1907.',
            extent: '3 volumes',
        );
        $importer = new EadXmlImporter();
        $this->assertSame('ead3', $importer->detectVariant($xml));
    }

    public function test_detects_ead2002_variant_by_namespace(): void
    {
        $xml = $this->ead2002Fixture(
            eadid: 'HER-EAD2002-001',
            unittitle: 'Letters of Mr Adams',
            scope: 'Personal correspondence.',
            extent: '20 letters',
        );
        $importer = new EadXmlImporter();
        $this->assertSame('ead2002', $importer->detectVariant($xml));
    }

    public function test_static_ead3_parses_into_hierarchical_tree(): void
    {
        $xml = $this->ead3Fixture(
            recordid: 'HER-EAD3-002',
            unittitle: 'Smith Family Papers',
            scope: 'Records of three generations.',
            extent: '5 boxes',
            withChild: true,
        );
        $importer = new EadXmlImporter();
        [$valid, $errors] = $importer->validate($xml);
        $this->assertTrue($valid, 'EAD3 fixture rejected: '.implode('; ', $errors));

        $tree = $importer->preview($xml, 'en');
        $this->assertNotEmpty($tree);
        $root = $tree[0];
        $this->assertSame('Smith Family Papers', $root['title']);
        $this->assertSame('Records of three generations.', $root['scope_and_content']);
        $this->assertSame('5 boxes', $root['extent_and_medium']);
        $this->assertSame('HER-EAD3-002', $root['eadid']);
        $this->assertCount(1, $root['children']);
        $this->assertSame('Series 1: Correspondence', $root['children'][0]['title']);
    }

    public function test_static_ead2002_parses_into_hierarchical_tree(): void
    {
        $xml = $this->ead2002Fixture(
            eadid: 'HER-EAD2002-002',
            unittitle: 'Wilson Estate Records',
            scope: 'Estate ledgers and rent rolls.',
            extent: '12 ledgers',
            withChild: true,
        );
        $importer = new EadXmlImporter();
        [$valid, $errors] = $importer->validate($xml);
        $this->assertTrue($valid, 'EAD2002 fixture rejected: '.implode('; ', $errors));

        $tree = $importer->preview($xml, 'en');
        $root = $tree[0];
        $this->assertSame('Wilson Estate Records', $root['title']);
        $this->assertSame('Estate ledgers and rent rolls.', $root['scope_and_content']);
        $this->assertSame('12 ledgers', $root['extent_and_medium']);
        $this->assertSame('HER-EAD2002-002', $root['eadid']);
        $this->assertCount(1, $root['children']);
    }

    public function test_round_trip_recovers_core_fields(): void
    {
        try {
            if (! Schema::hasTable('information_object')) {
                $this->markTestSkipped('information_object table not present');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
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

        $serializer = new Ead3Serializer();
        $xml = $serializer->serializeRecord((int) $ioId, 'en', true);
        $this->assertNotEmpty($xml, 'EAD3 serializer returned empty document');

        // Wrap with a real XML declaration so libxml accepts it standalone
        $wrapped = '<?xml version="1.0" encoding="UTF-8"?>'."\n".$xml;

        $importer = new EadXmlImporter();
        $this->assertSame('ead3', $importer->detectVariant($wrapped));

        $tree = $importer->preview($wrapped, 'en');
        $this->assertNotEmpty($tree, 'Importer returned no tree');
        $root = $tree[0];

        // Title is mandatory at the exporter end - must round-trip.
        $expectedTitle = DB::table('information_object_i18n')
            ->where('id', $ioId)->where('culture', 'en')
            ->value('title');
        $this->assertNotEmpty($root['title'], 'title lost in round-trip');
        $this->assertSame((string) $expectedTitle, (string) $root['title']);
    }

    public function test_legalstatus_emits_when_publication_status_set(): void
    {
        try {
            if (! Schema::hasTable('status')) {
                $this->markTestSkipped('status table not present');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }
        // Find any IO that has a published-status row
        $ioId = DB::table('information_object as io')
            ->join('status as s', function ($j) {
                $j->on('s.object_id', '=', 'io.id')
                    ->where('s.type_id', 158)
                    ->where('s.status_id', 160);
            })
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', 'en');
            })
            ->whereNotNull('i18n.title')
            ->limit(1)
            ->value('io.id');
        if (! $ioId) {
            $this->markTestSkipped('No published IO available to exercise legalstatus emit');
        }
        $xml = (new Ead3Serializer())->serializeRecord((int) $ioId, 'en', false);
        $this->assertStringContainsString('<legalstatus>Published</legalstatus>', $xml);
    }

    public function test_malformed_xml_is_rejected(): void
    {
        $importer = new EadXmlImporter();
        [$valid, $errors] = $importer->validate('<<not an ead doc>>');
        $this->assertFalse($valid);
        $this->assertNotEmpty($errors);
    }

    private function ead3Fixture(string $recordid, string $unittitle, string $scope, string $extent, bool $withChild = false): string
    {
        $r = htmlspecialchars($recordid, ENT_XML1);
        $t = htmlspecialchars($unittitle, ENT_XML1);
        $s = htmlspecialchars($scope, ENT_XML1);
        $e = htmlspecialchars($extent, ENT_XML1);
        $childXml = $withChild
            ? <<<C
      <c level="series">
        <did>
          <unitid>S1</unitid>
          <unittitle>Series 1: Correspondence</unittitle>
        </did>
        <scopecontent><p>Letters in chronological order.</p></scopecontent>
      </c>
C
            : '';
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ead xmlns="http://ead3.archivists.org/schema/">
  <control>
    <recordid>{$r}</recordid>
    <filedesc><titlestmt><titleproper>{$t}</titleproper></titlestmt></filedesc>
  </control>
  <archdesc level="fonds">
    <did>
      <unitid>{$r}</unitid>
      <unittitle>{$t}</unittitle>
      <physdescstructured physdescstructuredtype="spaceoccupied">
        <quantity>1</quantity>
        <unittype>{$e}</unittype>
      </physdescstructured>
    </did>
    <scopecontent><p>{$s}</p></scopecontent>
    <dsc dsctype="combined">{$childXml}
    </dsc>
  </archdesc>
</ead>
XML;
    }

    private function ead2002Fixture(string $eadid, string $unittitle, string $scope, string $extent, bool $withChild = false): string
    {
        $r = htmlspecialchars($eadid, ENT_XML1);
        $t = htmlspecialchars($unittitle, ENT_XML1);
        $s = htmlspecialchars($scope, ENT_XML1);
        $e = htmlspecialchars($extent, ENT_XML1);
        $childXml = $withChild
            ? <<<C
      <c01 level="series">
        <did>
          <unitid>S1</unitid>
          <unittitle>Series 1: Ledgers</unittitle>
          <physdesc>4 volumes</physdesc>
        </did>
        <scopecontent><p>Estate ledgers covering 1820-1870.</p></scopecontent>
      </c01>
C
            : '';
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ead xmlns="urn:isbn:1-931666-22-9">
  <eadheader>
    <eadid>{$r}</eadid>
    <filedesc><titlestmt><titleproper>{$t}</titleproper></titlestmt></filedesc>
  </eadheader>
  <archdesc level="fonds">
    <did>
      <unitid>{$r}</unitid>
      <unittitle>{$t}</unittitle>
      <physdesc>{$e}</physdesc>
    </did>
    <scopecontent><p>{$s}</p></scopecontent>
    <dsc>{$childXml}
    </dsc>
  </archdesc>
</ead>
XML;
    }
}
