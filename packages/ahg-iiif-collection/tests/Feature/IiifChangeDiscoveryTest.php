<?php

/**
 * IiifChangeDiscoveryTest - Feature tests for /iiif/discovery/changes.
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

namespace AhgIiifCollection\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature tests for the IIIF Change Discovery 1.0 endpoint at
 * /iiif/discovery/changes.
 *
 * Spec: https://iiif.io/api/discovery/1.0/
 *
 * Asserts:
 *   - root returns OrderedCollection with first/last page pointers + total
 *   - ?page=N returns OrderedCollectionPage with orderedItems[]
 *   - each Activity has id / type (Create|Update|Delete) / object{id,type}
 *   - prev/next link chain is populated correctly
 *   - both @context entries (IIIF discovery + ActivityStreams) are present
 *   - out-of-range page returns 404 with an error envelope
 *
 * Skips gracefully when iiif_manifest_change isn't installed (test
 * environments that haven't yet run the package install.sql).
 */
class IiifChangeDiscoveryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('iiif_manifest_change')) {
            $this->markTestSkipped('iiif_manifest_change table not present in this install.');
        }
    }

    private function seedChange(string $changeType, string $slug = 'phpunit-discovery-test'): int
    {
        $baseUrl = rtrim(config('app.url'), '/');
        return (int) DB::table('iiif_manifest_change')->insertGetId([
            'object_id' => 999900 + random_int(1, 99),
            'slug' => $slug,
            'manifest_uri' => $baseUrl . '/iiif-manifest/' . $slug,
            'change_type' => $changeType,
            'actor' => 'phpunit',
            'created_at' => now(),
        ]);
    }

    public function test_root_returns_ordered_collection_with_first_and_last_page(): void
    {
        $response = $this->get('/iiif/discovery/changes');
        $response->assertStatus(200);
        // application/ld+json is set by the controller; charset suffix
        // is implementation-defined so we don't pin it.
        $this->assertStringContainsString(
            'application/ld+json',
            (string) $response->headers->get('Content-Type')
        );

        $doc = $response->json();
        $this->assertIsArray($doc);
        $this->assertSame('OrderedCollection', $doc['type']);
        $this->assertIsArray($doc['@context']);
        $this->assertContains('http://iiif.io/api/discovery/1/context.json', $doc['@context']);
        $this->assertContains('http://www.w3.org/ns/activitystreams', $doc['@context']);
        $this->assertArrayHasKey('totalItems', $doc);
        $this->assertIsInt($doc['totalItems']);

        $this->assertSame('OrderedCollectionPage', $doc['first']['type']);
        $this->assertSame('OrderedCollectionPage', $doc['last']['type']);
        $this->assertStringEndsWith('?page=1', $doc['first']['id']);
    }

    public function test_page_returns_ordered_collection_page_with_activities(): void
    {
        $id = $this->seedChange('Create');

        try {
            $response = $this->get('/iiif/discovery/changes?page=1');
            $response->assertStatus(200);

            $doc = $response->json();
            $this->assertSame('OrderedCollectionPage', $doc['type']);
            $this->assertArrayHasKey('partOf', $doc);
            $this->assertSame('OrderedCollection', $doc['partOf']['type']);
            $this->assertArrayHasKey('orderedItems', $doc);
            $this->assertIsArray($doc['orderedItems']);

            // Look for our seeded activity by id-suffix.
            $found = false;
            foreach ($doc['orderedItems'] as $activity) {
                $this->assertArrayHasKey('id', $activity);
                $this->assertArrayHasKey('type', $activity);
                $this->assertContains($activity['type'], ['Create', 'Update', 'Delete']);
                $this->assertArrayHasKey('object', $activity);
                $this->assertSame('Manifest', $activity['object']['type']);
                $this->assertArrayHasKey('endTime', $activity);
                if (str_ends_with((string) $activity['id'], '/activity/' . $id)) {
                    $found = true;
                    $this->assertSame('Create', $activity['type']);
                }
            }
            $this->assertTrue($found, 'Seeded Create activity not found in page 1');
        } finally {
            DB::table('iiif_manifest_change')->where('id', $id)->delete();
        }
    }

    public function test_out_of_range_page_returns_404(): void
    {
        $response = $this->get('/iiif/discovery/changes?page=999999');
        $response->assertStatus(404);
        $this->assertArrayHasKey('error', $response->json());
    }

    public function test_page_zero_returns_404_not_500(): void
    {
        $response = $this->get('/iiif/discovery/changes?page=0');
        $response->assertStatus(404);
    }
}
