<?php

/**
 * RicRoundTripTest - #1321 portability guarantee.
 *
 * Proves the Linked Data export <-> import contract: a record serialized to
 * JSON-LD / Turtle and re-imported through RdfImportService reconstitutes the
 * same core fields (title, identifier, description). This is the "both must
 * round-trip" requirement in issue #1321 - the anti-lock-in proof from the
 * governance pin.
 *
 * Runs against the pre-built heratio_test schema and rolls back each test
 * (DatabaseTransactions - the ~995 base tables must survive; #1136).
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

use AhgRic\Support\JsonLdConverter;
use AhgRic\Services\RdfImportService;
use AhgRic\Services\RicSerializationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RicRoundTripTest extends TestCase
{
    use DatabaseTransactions;

    private RicSerializationService $serializer;

    private RdfImportService $importer;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['information_object', 'information_object_i18n', 'object', 'slug'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Core table {$t} not provisioned in the test DB.");
            }
        }

        $this->serializer = app(RicSerializationService::class);
        $this->importer = app(RdfImportService::class);
    }

    /**
     * Create a minimal archival record (object -> information_object ->
     * information_object_i18n + slug) and return [id, fields].
     */
    private function makeRecord(): array
    {
        $oid = (int) \DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fields = [
            'title'             => 'Round-Trip Record '.Str::random(6),
            'identifier'        => 'RT-'.$oid,
            'scope_and_content' => 'Scope and content body for the round-trip portability test.',
        ];

        \DB::table('information_object')->insert([
            'id'             => $oid,
            'identifier'     => $fields['identifier'],
            'source_culture' => 'en',
        ]);
        \DB::table('information_object_i18n')->insert([
            'id'                => $oid,
            'culture'           => 'en',
            'title'             => $fields['title'],
            'scope_and_content' => $fields['scope_and_content'],
        ]);
        \DB::table('slug')->insert([
            'object_id' => $oid,
            'slug'      => 'rt-'.$oid,
        ]);

        return [$oid, $fields];
    }

    /** The serialized JSON-LD carries the three core fields. */
    public function test_export_emits_core_fields(): void
    {
        [$id, $fields] = $this->makeRecord();

        $doc = $this->serializer->serializeRecord($id);

        $this->assertSame($fields['title'], $doc['rico:title'] ?? null);
        $this->assertSame($fields['identifier'], $doc['rico:identifier'] ?? null);
        $this->assertSame($fields['scope_and_content'], $doc['rico:description'] ?? null);
    }

    /** Turtle export -> dry-run import maps every core predicate (nothing critical falls in unmapped). */
    public function test_turtle_dry_run_maps_core_predicates(): void
    {
        [$id] = $this->makeRecord();

        $ttl = JsonLdConverter::toTurtle($this->serializer->serializeRecord($id));
        $report = $this->importer->dryRun($ttl, 'turtle');

        $this->assertSame(1, $report['would_create']['information_object'] ?? 0,
            'The exported record should classify as exactly one information_object.');

        $mapped = array_keys($report['mapped_predicates'] ?? []);
        $this->assertContains('rico:identifier', $mapped);
        $this->assertContains('rico:description', $mapped);
        // The serializer emits rico:title; the importer MUST accept it for a
        // lossless round-trip (regression guard for the rico:title<->rico:name gap).
        $this->assertContains('rico:title', $mapped,
            'rico:title from the serializer is not mapped on import - title would be lost.');
        $this->assertArrayNotHasKey('rico:title', $report['unmapped_predicates'] ?? []);
    }

    /**
     * Full round-trip: export -> import (commit) -> re-export reproduces the
     * same title / identifier / description. Both JSON-LD and Turtle.
     *
     * @dataProvider formats
     */
    public function test_full_round_trip_preserves_core_fields(string $format): void
    {
        [$id, $fields] = $this->makeRecord();

        $doc = $this->serializer->serializeRecord($id);
        $payload = $format === 'turtle'
            ? JsonLdConverter::toTurtle($doc)
            : json_encode($doc, JSON_UNESCAPED_SLASHES);

        $result = $this->importer->commit($payload, $format, 'en');

        $this->assertNotEmpty($result['created_io'] ?? [],
            "Commit ({$format}) should create one information_object.");
        $newId = (int) $result['created_io'][0];
        $this->assertNotSame($id, $newId);

        $rt = $this->serializer->serializeRecord($newId);
        $this->assertSame($fields['title'], $rt['rico:title'] ?? null,
            "Title did not survive the {$format} round-trip.");
        $this->assertSame($fields['identifier'], $rt['rico:identifier'] ?? null,
            "Identifier did not survive the {$format} round-trip.");
        $this->assertSame($fields['scope_and_content'], $rt['rico:description'] ?? null,
            "Description did not survive the {$format} round-trip.");
    }

    public static function formats(): array
    {
        return ['turtle' => ['turtle'], 'jsonld' => ['jsonld']];
    }
}
