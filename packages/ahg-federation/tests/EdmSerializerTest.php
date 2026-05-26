<?php

/**
 * EdmSerializerTest - DOMDocument parse + structural assertions for the
 * per-IO Europeana EDM serializer. Skips when the heratio DB is
 * unreachable from the test runner so it stays green in CI environments
 * without a live database.
 *
 * Phase 4 of #670 (Federation audit, Europeana EDM publish).
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
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgFederation\Tests;

use AhgFederation\Edm\EdmSerializer;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EdmSerializerTest extends TestCase
{
    /**
     * Smoke test: pick the first information_object in the DB, render
     * EDM, and verify the four canonical EDM classes are present and
     * the namespaces resolve.
     */
    public function test_edm_contains_required_classes(): void
    {
        try {
            if (! Schema::hasTable('information_object')) {
                $this->markTestSkipped('information_object table not present in test DB');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable in test environment: '.$e->getMessage());
        }

        $row = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', 'io.id', '=', 'i18n.id')
            ->where('io.id', '>', 1)
            ->limit(1)
            ->value('io.id');

        if (! $row) {
            $this->markTestSkipped('No information_object rows in test DB');
        }

        $serializer = new EdmSerializer();
        $xml = $serializer->serializeRecord((int) $row, 'en');
        $this->assertNotEmpty($xml, 'Serializer returned empty document');

        $dom = new DOMDocument();
        $loaded = $dom->loadXML($xml);
        $this->assertTrue($loaded, 'EDM XML failed to parse');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('rdf', EdmSerializer::NS_RDF);
        $xpath->registerNamespace('edm', EdmSerializer::NS_EDM);
        $xpath->registerNamespace('ore', EdmSerializer::NS_ORE);
        $xpath->registerNamespace('dc', EdmSerializer::NS_DC);

        // The four canonical EDM classes that Europeana's ingestion
        // checker looks for: edm:ProvidedCHO (exactly one),
        // ore:Aggregation (exactly one), and a non-zero count of
        // edm:WebResource / edm:Agent (digital surrogates + creators
        // depend on the picked record so we assert "at least zero" on
        // those and check edm:type unconditionally).
        $this->assertSame(1, $xpath->query('//edm:ProvidedCHO')->length, 'expected exactly one edm:ProvidedCHO');
        $this->assertSame(1, $xpath->query('//ore:Aggregation')->length, 'expected exactly one ore:Aggregation');
        $this->assertGreaterThanOrEqual(1, $xpath->query('//edm:type')->length, 'edm:type is mandatory');

        $edmType = trim((string) $xpath->query('//edm:type')->item(0)->textContent);
        $this->assertContains($edmType, EdmSerializer::EDM_TYPES, "edm:type must be one of the 5 canonical values, got '{$edmType}'");

        // dc:title is mandatory on edm:ProvidedCHO
        $this->assertGreaterThanOrEqual(1, $xpath->query('//edm:ProvidedCHO/dc:title')->length, 'edm:ProvidedCHO missing dc:title');

        // ore:Aggregation must carry edm:dataProvider + edm:provider +
        // edm:rights + edm:isShownAt for Europeana's ingestion gate.
        $xpath->registerNamespace('edmns', EdmSerializer::NS_EDM);
        $this->assertGreaterThanOrEqual(1, $xpath->query('//ore:Aggregation/edmns:dataProvider')->length, 'edm:dataProvider missing');
        $this->assertGreaterThanOrEqual(1, $xpath->query('//ore:Aggregation/edmns:provider')->length, 'edm:provider missing');
        $this->assertGreaterThanOrEqual(1, $xpath->query('//ore:Aggregation/edmns:rights')->length, 'edm:rights missing');
        $this->assertGreaterThanOrEqual(1, $xpath->query('//ore:Aggregation/edmns:isShownAt')->length, 'edm:isShownAt missing');
    }

    /**
     * Constants stay stable - guard against silent namespace renames
     * that would break downstream EDM consumers without producing a
     * parse error.
     */
    public function test_namespace_constants(): void
    {
        $this->assertSame('http://www.europeana.eu/schemas/edm/', EdmSerializer::NS_EDM);
        $this->assertSame('http://www.openarchives.org/ore/terms/', EdmSerializer::NS_ORE);
        $this->assertSame('http://purl.org/dc/elements/1.1/', EdmSerializer::NS_DC);
        $this->assertSame('http://www.w3.org/2003/01/geo/wgs84_pos#', EdmSerializer::NS_WGS84);
        $this->assertSame(
            ['TEXT', 'IMAGE', 'SOUND', 'VIDEO', '3D'],
            EdmSerializer::EDM_TYPES
        );
    }

    /**
     * listPublishedRecordIds returns a collection (possibly empty) when
     * the DB is reachable and never throws.
     */
    public function test_list_published_record_ids_returns_collection(): void
    {
        try {
            if (! Schema::hasTable('information_object')) {
                $this->markTestSkipped('information_object table not present in test DB');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable in test environment: '.$e->getMessage());
        }

        $serializer = new EdmSerializer();
        $ids = $serializer->listPublishedRecordIds();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $ids);
    }
}
