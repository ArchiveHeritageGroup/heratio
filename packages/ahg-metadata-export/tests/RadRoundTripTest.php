<?php

/**
 * RadRoundTripTest - exercise the RAD serializer + importer as a unit so
 * the field map stays in sync. Pure-XML tests run without a database;
 * the importer integration test is skipped when the sidecar table is
 * absent.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 */

namespace AhgMetadataExport\Tests;

use AhgMetadataExport\Services\Exporters\RadSerializer;
use AhgMetadataExport\Services\Importers\RadXmlImporter;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RadRoundTripTest extends TestCase
{
    public function test_synthetic_rad_xml_parses_into_expected_field_map(): void
    {
        $xml = $this->syntheticDocument('AHG-RAD-TEST-1');

        $importer = new RadXmlImporter();
        $preview = $importer->preview($xml, 'en');

        $this->assertNotEmpty($preview, 'preview must produce at least one record');
        $rec = $preview[0];
        $this->assertSame('AHG-RAD-TEST-1', $rec['identifier']);
        $this->assertSame('Synthetic RAD record', $rec['fields']['title_proper'] ?? null);
        $this->assertSame('Series', $rec['fields']['physical_description'] ?? null);
        $this->assertSame('Test custodial history.', $rec['fields']['custodial_history'] ?? null);
        $this->assertSame('full', $rec['fields']['level_of_detail'] ?? null);
    }

    public function test_field_map_covers_every_exported_element(): void
    {
        $xml = $this->syntheticDocument('AHG-RAD-TEST-2');

        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('rad', RadSerializer::NAMESPACE_URI);

        foreach (array_keys(RadSerializer::FIELD_MAP) as $element) {
            // synthetic doc emits every element; the parser must catch
            // all of them via local-name lookup.
            $this->assertGreaterThan(
                0,
                $xpath->query('//rad:'.$element)->length,
                'synthetic doc must include '.$element
            );
        }
    }

    public function test_round_trip_when_db_present(): void
    {
        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('ahg_io_rad')) {
                $this->markTestSkipped('RAD sidecar table not present');
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

        $identifier = DB::table('information_object')->where('id', $ioId)->value('identifier');
        if (! $identifier) {
            $identifier = 'heratio-io-'.$ioId;
        }

        // Round trip: serialize a real IO, then re-parse the output.
        $xml = (new RadSerializer())->serializeRecord((int) $ioId, 'en');
        $this->assertNotSame('', $xml, 'RAD serializer must produce output for IO #'.$ioId);

        $importer = new RadXmlImporter();
        $preview = $importer->preview($xml, 'en');
        $this->assertNotEmpty($preview, 'round-trip preview must find at least one record');
        $this->assertSame((int) $ioId, $preview[0]['matched_io_id'] ?? null);
    }

    private function syntheticDocument(string $identifier): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<radDescription xmlns="'.RadSerializer::NAMESPACE_URI.'" version="1.0">'."\n"
            .'  <identifier>'.$identifier.'</identifier>'."\n"
            .'  <titleProper>Synthetic RAD record</titleProper>'."\n"
            .'  <parallelTitle>Document RAD synthétique</parallelTitle>'."\n"
            .'  <otherTitleInformation>Test material</otherTitleInformation>'."\n"
            .'  <statementsOfResponsibility>Heratio test harness</statementsOfResponsibility>'."\n"
            .'  <edition>1st</edition>'."\n"
            .'  <datesOfCreation>2026</datesOfCreation>'."\n"
            .'  <physicalDescription>Series</physicalDescription>'."\n"
            .'  <custodialHistory>Test custodial history.</custodialHistory>'."\n"
            .'  <scopeAndContent>Scope and content RAD body.</scopeAndContent>'."\n"
            .'  <systemOfArrangement>Chronological by file.</systemOfArrangement>'."\n"
            .'  <languageOfMaterial>English; French</languageOfMaterial>'."\n"
            .'  <findingAids>Inventory PDF available.</findingAids>'."\n"
            .'  <accruals>No further accruals expected.</accruals>'."\n"
            .'  <generalNote>General note text.</generalNote>'."\n"
            .'  <archivistNote>Archivist note text.</archivistNote>'."\n"
            .'  <rulesOrConventions>RAD</rulesOrConventions>'."\n"
            .'  <dateOfDescriptions>2026-05-26</dateOfDescriptions>'."\n"
            .'  <descriptionStatus>final</descriptionStatus>'."\n"
            .'  <levelOfDetail>full</levelOfDetail>'."\n"
            .'</radDescription>';
    }
}
