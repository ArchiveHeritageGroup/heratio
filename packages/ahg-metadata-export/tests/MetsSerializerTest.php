<?php

/**
 * MetsSerializerTest - DOMDocument parse + structural assertions for the
 * per-IO METS serializer. Skips when the heratio DB is unreachable from
 * the test runner so it stays green in CI environments without a live
 * database.
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

use AhgMetadataExport\Services\Exporters\MetsSerializer;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MetsSerializerTest extends TestCase
{
    /**
     * Smoke test: pick the first information_object in the DB, render METS,
     * and verify the four canonical sections are present.
     */
    public function test_mets_contains_required_sections(): void
    {
        try {
            if (! Schema::hasTable('information_object')) {
                $this->markTestSkipped('information_object table not present in test DB');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable in test environment: '.$e->getMessage());
        }

        $row = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id');
            })
            ->where('io.id', '>', 1)
            ->limit(1)
            ->value('io.id');

        if (! $row) {
            $this->markTestSkipped('No information_object rows in test DB');
        }

        $serializer = new MetsSerializer();
        $xml = $serializer->serializeRecord((int) $row, 'en');

        $this->assertNotEmpty($xml, 'Serializer returned empty document');

        $dom = new DOMDocument();
        $loaded = $dom->loadXML($xml);
        $this->assertTrue($loaded, 'METS XML failed to parse');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('mets', MetsSerializer::NS_METS);

        $this->assertSame(1, $xpath->query('//mets:dmdSec')->length, 'expected exactly one <dmdSec>');
        $this->assertSame(1, $xpath->query('//mets:amdSec')->length, 'expected exactly one <amdSec>');
        $this->assertSame(1, $xpath->query('//mets:fileSec')->length, 'expected exactly one <fileSec>');
        $this->assertGreaterThanOrEqual(1, $xpath->query('//mets:structMap')->length, 'expected at least one <structMap>');

        // PROFILE attribute on the root element
        $root = $dom->documentElement;
        $this->assertSame(MetsSerializer::PROFILE, $root->getAttribute('PROFILE'));
    }

    /**
     * Constants stay stable — guard against silent renames.
     */
    public function test_namespace_constants(): void
    {
        $this->assertSame('http://www.loc.gov/METS/', MetsSerializer::NS_METS);
        $this->assertSame('http://www.w3.org/1999/xlink', MetsSerializer::NS_XLINK);
        $this->assertSame('https://heratio.theahg.co.za/profiles/mets/io-v1', MetsSerializer::PROFILE);
    }
}
