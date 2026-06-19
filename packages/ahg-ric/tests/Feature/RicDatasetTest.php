<?php

/**
 * RicDatasetTest - #1321 increment 2.
 *
 * Covers the versioned, self-describing Linked Data surface:
 *  - the DCAT/VoID dataset descriptor (distributions + pinned standards + version),
 *  - the change log feed,
 *  - owl:deprecated emission for entities in the deprecate-not-delete register.
 *
 * DatabaseTransactions against the pre-built heratio_test schema (#1136).
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

namespace Tests\Feature;

use AhgRic\Services\RicDatasetService;
use AhgRic\Services\RicDeprecationService;
use AhgRic\Services\RicSerializationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RicDatasetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_descriptor_is_a_dcat_dataset_with_distributions_and_pins(): void
    {
        $doc = app(RicDatasetService::class)->descriptor();

        $this->assertContains('dcat:Dataset', (array) $doc['@type']);
        $this->assertContains('void:Dataset', (array) $doc['@type']);
        $this->assertNotEmpty($doc['dcat:distribution']);
        $this->assertCount(5, $doc['dcat:distribution']); // SPARQL, JSON-LD, Turtle, RDF/XML, OAI-PMH
        $this->assertNotEmpty($doc['dcterms:conformsTo']);
        $this->assertArrayHasKey('void:sparqlEndpoint', $doc);
        $this->assertNotEmpty($doc['dcterms:hasVersion']);
    }

    public function test_changelog_lists_changes_and_pinned_standards(): void
    {
        $log = app(RicDatasetService::class)->changelog();

        $this->assertNotEmpty($log['changes']);
        $this->assertArrayHasKey('version', $log['changes'][0]);
        $this->assertArrayHasKey('impact', $log['changes'][0]);
        $this->assertNotEmpty($log['pinned_standards']);
    }

    public function test_dataset_and_changelog_endpoints_respond(): void
    {
        $this->getJson('/api/ric/v1/dataset')->assertOk()
            ->assertJsonFragment(['dcterms:title' => 'Heratio RiC-O Linked Data']);
        $this->getJson('/api/ric/v1/changelog')->assertOk()
            ->assertJsonStructure(['dataset_version', 'pinned_standards', 'changes']);
    }

    public function test_deprecated_record_is_flagged_in_export(): void
    {
        if (! Schema::hasTable('ric_deprecated_entity')) {
            $this->markTestSkipped('deprecation register not provisioned.');
        }

        $id = $this->makeRecord();
        $successor = 'https://ric.theahg.co.za/ric/informationobject/successor-1';

        app(RicDeprecationService::class)->markDeprecated(
            'information_object', $id, 'Superseded by a corrected record.', $successor
        );

        $doc = app(RicSerializationService::class)->serializeRecord($id);

        $this->assertTrue($doc['owl:deprecated'] ?? false);
        $this->assertSame($successor, $doc['dcterms:isReplacedBy']['@id'] ?? null);
        $this->assertSame('http://www.w3.org/2002/07/owl#', $doc['@context']['owl'] ?? null);

        // A live (non-deprecated) record carries no owl:deprecated key.
        $live = app(RicSerializationService::class)->serializeRecord($this->makeRecord());
        $this->assertArrayNotHasKey('owl:deprecated', $live);
    }

    private function makeRecord(): int
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert([
            'id' => $id, 'culture' => 'en', 'title' => 'Deprecation Subject '.$id,
        ]);

        return $id;
    }
}
