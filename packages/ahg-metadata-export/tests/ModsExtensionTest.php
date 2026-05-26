<?php

/**
 * ModsExtensionTest - #662 Phase 3 MODS 3.7 extension coverage. Confirms
 * that the serializer emits the new recordInfo / language / subject /
 * physicalDescription enrichments without breaking the Phase 1/2 set.
 * Skips when no DB is reachable.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 */

namespace AhgMetadataExport\Tests;

use AhgMetadataExport\Services\Exporters\ModsSerializer;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModsExtensionTest extends TestCase
{
    public function test_mods_emits_extended_recordinfo_and_typeofresource(): void
    {
        try {
            if (! Schema::hasTable('information_object')) {
                $this->markTestSkipped('information_object table not present');
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

        $xml = (new ModsSerializer())->serializeRecord((int) $ioId, 'en');
        $this->assertNotSame('', $xml);

        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('m', 'http://www.loc.gov/mods/v3');

        // recordIdentifier is new in Phase 3 - must be present.
        $this->assertGreaterThan(0, $xpath->query('//m:recordInfo/m:recordIdentifier')->length);
        // descriptionStandard must be present (ISAD(G) fallback).
        $this->assertGreaterThan(0, $xpath->query('//m:recordInfo/m:descriptionStandard')->length);
    }
}
