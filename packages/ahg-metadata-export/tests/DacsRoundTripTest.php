<?php

/**
 * DacsRoundTripTest - serialize + parse the DACS sidecar so the field
 * map stays in sync. Pure-XML tests do not require a database; the
 * round-trip from a real IO is skipped when the sidecar is absent.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 */

namespace AhgMetadataExport\Tests;

use AhgMetadataExport\Services\Exporters\DacsSerializer;
use AhgMetadataExport\Services\Importers\DacsXmlImporter;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DacsRoundTripTest extends TestCase
{
    public function test_synthetic_dacs_xml_parses_into_field_map(): void
    {
        $xml = $this->syntheticDocument('AHG-DACS-TEST-1');

        $importer = new DacsXmlImporter();
        $preview = $importer->preview($xml, 'en');

        $this->assertNotEmpty($preview);
        $rec = $preview[0];
        $this->assertSame('AHG-DACS-TEST-1', $rec['record_identifier']);
        $this->assertSame('Synthetic DACS record', $rec['fields']['title_dacs'] ?? null);
        $this->assertSame('English', $rec['fields']['languages_of_material'] ?? null);
        $this->assertSame('DACS 2013', $rec['fields']['dacs_rules'] ?? null);
    }

    public function test_every_field_map_element_appears_in_synthetic_doc(): void
    {
        $xml = $this->syntheticDocument('AHG-DACS-TEST-2');

        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('dacs', DacsSerializer::NAMESPACE_URI);

        foreach (array_keys(DacsSerializer::FIELD_MAP) as $element) {
            $this->assertGreaterThan(
                0,
                $xpath->query('//dacs:'.$element)->length,
                'synthetic doc must include '.$element
            );
        }
    }

    public function test_round_trip_when_db_present(): void
    {
        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('ahg_io_dacs')) {
                $this->markTestSkipped('DACS sidecar table not present');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $ioId = DB::table('information_object')
            ->where('id', '>', 1)
            ->orderBy('id')
            ->limit(1)
            ->value('id');
        if (! $ioId) {
            $this->markTestSkipped('No information_object rows available');
        }

        $xml = (new DacsSerializer())->serializeRecord((int) $ioId, 'en');
        $this->assertNotSame('', $xml);

        $preview = (new DacsXmlImporter())->preview($xml, 'en');
        $this->assertNotEmpty($preview);
        $this->assertSame((int) $ioId, $preview[0]['matched_io_id'] ?? null);
    }

    private function syntheticDocument(string $recordIdentifier): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<dacsDescription xmlns="'.DacsSerializer::NAMESPACE_URI.'" version="1.0">'."\n"
            .'  <recordIdentifier>'.$recordIdentifier.'</recordIdentifier>'."\n"
            .'  <referenceCode>AHG-001</referenceCode>'."\n"
            .'  <nameAndLocation>The Archive and Heritage Group</nameAndLocation>'."\n"
            .'  <title>Synthetic DACS record</title>'."\n"
            .'  <date>2026</date>'."\n"
            .'  <extent>1 box</extent>'."\n"
            .'  <nameOfCreator>Heratio Test Harness</nameOfCreator>'."\n"
            .'  <scopeAndContent>Scope and content DACS body.</scopeAndContent>'."\n"
            .'  <conditionsGoverningAccess>Open</conditionsGoverningAccess>'."\n"
            .'  <languagesOfMaterial>English</languagesOfMaterial>'."\n"
            .'  <biographicalOrHistorical>Bio/hist note.</biographicalOrHistorical>'."\n"
            .'  <immediateSourceOfAcquisition>Donation</immediateSourceOfAcquisition>'."\n"
            .'  <systemOfArrangement>By date.</systemOfArrangement>'."\n"
            .'  <relatedArchivalMaterials>See AHG-002</relatedArchivalMaterials>'."\n"
            .'  <publicationNote>None.</publicationNote>'."\n"
            .'  <processingInformation>Processed 2026-05-26.</processingInformation>'."\n"
            .'  <rules>DACS 2013</rules>'."\n"
            .'</dacsDescription>';
    }
}
