<?php

/**
 * DublinCoreQualifiedSerializerTest - smoke + structural tests for the
 * #662 Phase 3 Dublin Core qualified serializer. Skips when no DB is
 * reachable so CI without a live MySQL stays green.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 */

namespace AhgMetadataExport\Tests;

use AhgMetadataExport\Services\Exporters\DublinCoreQualifiedSerializer;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DublinCoreQualifiedSerializerTest extends TestCase
{
    public function test_qualified_dc_emits_both_basic_and_qualified_predicates(): void
    {
        try {
            if (! Schema::hasTable('information_object')) {
                $this->markTestSkipped('information_object table not present');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $row = DB::table('information_object')
            ->where('id', '>', 1)
            ->orderBy('id')
            ->limit(1)
            ->value('id');
        if (! $row) {
            $this->markTestSkipped('No information_object rows available');
        }

        $serializer = new DublinCoreQualifiedSerializer();
        $xml = $serializer->serializeRecord((int) $row, 'en');
        $this->assertNotSame('', $xml, 'serializeRecord must produce XML');

        $doc = new DOMDocument();
        $loaded = $doc->loadXML($xml);
        $this->assertTrue($loaded, 'output must be well-formed XML');

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $xpath->registerNamespace('dcterms', 'http://purl.org/dc/terms/');

        // Title must round-trip into both legacy and qualified namespaces.
        $this->assertGreaterThan(0, $xpath->query('//dc:title')->length, 'dc:title required');
        $this->assertGreaterThan(0, $xpath->query('//dcterms:title')->length, 'dcterms:title required');
    }

    public function test_serializer_returns_empty_string_for_missing_record(): void
    {
        try {
            if (! Schema::hasTable('information_object')) {
                $this->markTestSkipped('information_object table not present');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $serializer = new DublinCoreQualifiedSerializer();
        $this->assertSame('', $serializer->serializeRecord(0, 'en'));
    }
}
