<?php

/**
 * ResourceSyncTest - Feature tests for the ResourceSync Source endpoint
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

namespace AhgResourceSync\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature tests for the four ResourceSync 1.1 Source-role documents:
 *   - SourceDescription   /.well-known/resourcesync
 *   - CapabilityList      /resourcesync/capabilitylist.xml
 *   - ResourceList        /resourcesync/resourcelist.xml
 *   - ChangeList          /resourcesync/changelist.xml
 *
 * Each test asserts: HTTP 200, valid XML (SimpleXMLElement parse), correct
 * capability declaration, and the cross-document rel="up" link chain that
 * lets an aggregator walk back to the SourceDescription.
 *
 * The ChangeList test also seeds an oai_deleted_record row and asserts
 * a change="deleted" tombstone shows up.
 */
class ResourceSyncTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Parse XML with SimpleXMLElement and register sitemap + rs prefixes
     * so we can run XPath against both.
     */
    private function parseXml(string $xml): \SimpleXMLElement
    {
        $sxe = simplexml_load_string($xml);
        $this->assertNotFalse($sxe, 'response body is not valid XML');
        // Sitemap default namespace -> 'sm' for xpath
        $sxe->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $sxe->registerXPathNamespace('rs', 'http://www.openarchives.org/rs/terms/');

        return $sxe;
    }

    public function test_source_description_returns_valid_xml_with_capability_description(): void
    {
        $response = $this->get('/.well-known/resourcesync');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $sxe = $this->parseXml($response->getContent());

        // Top-level <rs:md capability="description">
        $caps = $sxe->xpath('/sm:urlset/rs:md/@capability');
        $this->assertNotEmpty($caps);
        $this->assertSame('description', (string) $caps[0]);

        // Should point to the CapabilityList
        $locs = $sxe->xpath('/sm:urlset/sm:url/sm:loc');
        $this->assertNotEmpty($locs);
        $this->assertStringContainsString('/resourcesync/capabilitylist.xml', (string) $locs[0]);

        // The inline rs:md on the url entry advertises capabilitylist.
        $inner = $sxe->xpath('/sm:urlset/sm:url/rs:md/@capability');
        $this->assertNotEmpty($inner);
        $this->assertSame('capabilitylist', (string) $inner[0]);
    }

    public function test_capability_list_advertises_resourcelist_and_changelist_and_links_up(): void
    {
        $response = $this->get('/resourcesync/capabilitylist.xml');
        $response->assertStatus(200);

        $sxe = $this->parseXml($response->getContent());

        $caps = $sxe->xpath('/sm:urlset/rs:md/@capability');
        $this->assertSame('capabilitylist', (string) $caps[0]);

        // rel="up" must point back to the SourceDescription.
        $up = $sxe->xpath('/sm:urlset/rs:ln[@rel="up"]/@href');
        $this->assertNotEmpty($up, 'CapabilityList missing rel="up" link');
        $this->assertStringContainsString('/.well-known/resourcesync', (string) $up[0]);

        // Must list both child capabilities.
        $childCaps = array_map(
            fn ($c) => (string) $c,
            $sxe->xpath('/sm:urlset/sm:url/rs:md/@capability')
        );
        $this->assertContains('resourcelist', $childCaps);
        $this->assertContains('changelist', $childCaps);
    }

    public function test_resource_list_returns_valid_xml_with_rel_up_back_to_capabilitylist(): void
    {
        $response = $this->get('/resourcesync/resourcelist.xml');
        $response->assertStatus(200);

        $sxe = $this->parseXml($response->getContent());

        $caps = $sxe->xpath('/sm:urlset/rs:md/@capability');
        $this->assertSame('resourcelist', (string) $caps[0]);

        $up = $sxe->xpath('/sm:urlset/rs:ln[@rel="up"]/@href');
        $this->assertNotEmpty($up, 'ResourceList missing rel="up" link');
        $this->assertStringContainsString('/resourcesync/capabilitylist.xml', (string) $up[0]);

        // 'at' timestamp on the document md
        $at = $sxe->xpath('/sm:urlset/rs:md/@at');
        $this->assertNotEmpty($at);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            (string) $at[0]
        );
    }

    public function test_change_list_includes_deleted_tombstone_when_oai_deleted_record_present(): void
    {
        if (! Schema::hasTable('oai_deleted_record')) {
            $this->markTestSkipped('oai_deleted_record table not present in this install (Phase 2 not applied).');
        }

        // Seed a tombstone within the horizon. Use a high oai_local_id
        // unlikely to collide with seed data.
        $seedId = 999999999;
        DB::table('oai_deleted_record')->updateOrInsert(
            ['oai_local_identifier' => $seedId],
            [
                'oai_local_identifier' => $seedId,
                'deleted_at' => now()->subDay(),
                'reason' => 'phpunit ResourceSyncTest',
            ]
        );

        try {
            $response = $this->get('/resourcesync/changelist.xml');
            $response->assertStatus(200);

            $sxe = $this->parseXml($response->getContent());

            $caps = $sxe->xpath('/sm:urlset/rs:md/@capability');
            $this->assertSame('changelist', (string) $caps[0]);

            $up = $sxe->xpath('/sm:urlset/rs:ln[@rel="up"]/@href');
            $this->assertNotEmpty($up, 'ChangeList missing rel="up" link');
            $this->assertStringContainsString('/resourcesync/capabilitylist.xml', (string) $up[0]);

            // The seeded tombstone must appear with change="deleted"
            // and a loc referencing the synthetic by-oai route.
            $deletedChanges = $sxe->xpath('/sm:urlset/sm:url[rs:md/@change="deleted"]/sm:loc');
            $deletedLocs = array_map(fn ($l) => (string) $l, $deletedChanges);

            $expectedLocFragment = '/informationobject/by-oai/'.$seedId;
            $matches = array_filter(
                $deletedLocs,
                fn ($l) => str_contains($l, $expectedLocFragment)
            );
            $this->assertNotEmpty(
                $matches,
                'Seeded tombstone not surfaced in ChangeList with change="deleted"'
            );
        } finally {
            DB::table('oai_deleted_record')->where('oai_local_identifier', $seedId)->delete();
        }
    }
}
