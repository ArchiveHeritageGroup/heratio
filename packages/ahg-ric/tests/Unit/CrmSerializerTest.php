<?php

/**
 * CrmSerializerTest - DOMDocument parse + structural assertions for
 * the per-IO CIDOC-CRM serialiser. Skips when the heratio DB is
 * unreachable so the package test suite stays green on CI runners
 * without a live database.
 *
 * Phase 1 of issue #659.
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

namespace Tests\Unit;

use AhgRic\Crm\CrmSerializer;
use AhgRic\Crm\RicToCrmMapper;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmSerializerTest extends TestCase
{
    /**
     * Smoke test: pick the first information_object, serialise it,
     * confirm the output parses and carries the expected CRM types
     * + predicates derived through RicToCrmMapper.
     */
    public function test_round_trip_rdfxml(): void
    {
        $ioId = $this->pickIoId();
        if ($ioId === null) {
            $this->markTestSkipped('No information_object rows available');
        }

        $serializer = new CrmSerializer();
        $xml = $serializer->serializeRecord($ioId, 'en', CrmSerializer::FORMAT_RDFXML);
        $this->assertNotEmpty($xml, 'Serializer returned empty document');

        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'CRM RDF/XML failed to parse');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('rdf', CrmSerializer::NS_RDF);
        $xpath->registerNamespace('rdfs', CrmSerializer::NS_RDFS);
        $xpath->registerNamespace('crm', CrmSerializer::NS_CRM);

        // The IO node must declare an rdf:type pointing at the CRM
        // class the mapper says rico:Record maps to.
        $crmRecord = RicToCrmMapper::expand(RicToCrmMapper::classFor('rico:Record'));
        $typeNodes = $xpath->query('//rdf:type[@rdf:resource="' . $crmRecord . '"]');
        $this->assertGreaterThanOrEqual(
            1,
            $typeNodes->length,
            'Expected at least one rdf:type pointing at E73_Information_Object'
        );

        // crm:P102_has_title is mandatory on the record node.
        $this->assertGreaterThanOrEqual(
            1,
            $xpath->query('//crm:P102_has_title')->length,
            'CRM document missing crm:P102_has_title'
        );

        // rdfs:label is mandatory on the record node so generic RDF
        // browsers (Protege, Skosmos) can display it.
        $this->assertGreaterThanOrEqual(
            1,
            $xpath->query('//rdfs:label')->length,
            'CRM document missing rdfs:label'
        );
    }

    /**
     * The Turtle output reuses the same mapper - assert it carries
     * the expected prefix declarations and at least one E73 type
     * statement.
     */
    public function test_turtle_output_carries_record_class(): void
    {
        $ioId = $this->pickIoId();
        if ($ioId === null) {
            $this->markTestSkipped('No information_object rows available');
        }

        $serializer = new CrmSerializer();
        $ttl = $serializer->serializeRecord($ioId, 'en', CrmSerializer::FORMAT_TURTLE);
        $this->assertNotEmpty($ttl, 'Turtle serialiser returned empty body');

        $this->assertStringContainsString('@prefix crm: <' . CrmSerializer::NS_CRM . '>', $ttl);
        $this->assertStringContainsString('@prefix rico: <' . CrmSerializer::NS_RIC . '>', $ttl);
        $this->assertStringContainsString('E73_Information_Object', $ttl);
    }

    /**
     * Missing record returns the empty string, never an exception -
     * the controller depends on this to emit a clean 404.
     */
    public function test_missing_record_returns_empty_string(): void
    {
        try {
            if (! Schema::hasTable('information_object')) {
                $this->markTestSkipped('information_object table not present');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: ' . $e->getMessage());
        }

        $serializer = new CrmSerializer();
        $this->assertSame('', $serializer->serializeRecord(999999999, 'en'));
    }

    public function test_format_constants(): void
    {
        $this->assertSame('rdfxml', CrmSerializer::FORMAT_RDFXML);
        $this->assertSame('turtle', CrmSerializer::FORMAT_TURTLE);
        $this->assertSame('http://www.cidoc-crm.org/cidoc-crm/', CrmSerializer::NS_CRM);
    }

    /**
     * Pick an information_object id for the smoke tests. Returns
     * null (-> markTestSkipped) when the DB is unreachable or the
     * table is empty.
     */
    protected function pickIoId(): ?int
    {
        try {
            if (! Schema::hasTable('information_object')) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        try {
            $row = DB::table('information_object as io')
                ->join('information_object_i18n as i18n', 'io.id', '=', 'i18n.id')
                ->where('io.id', '>', 1)
                ->where('i18n.culture', 'en')
                ->limit(1)
                ->value('io.id');
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? (int) $row : null;
    }
}
