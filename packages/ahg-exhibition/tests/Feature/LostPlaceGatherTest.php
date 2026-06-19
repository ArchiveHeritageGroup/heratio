<?php

/**
 * LostPlaceGatherTest - #1323 Lost Places POC, increment 1 coverage.
 *
 * Builds a place access point + two linked records (one with an image, one with
 * a PDF) and asserts LostPlaceGatherService resolves the place, gathers both
 * records, counts media by kind, and reports the coverage band.
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

use AhgExhibition\Services\LostPlaceGatherService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LostPlaceGatherTest extends TestCase
{
    use DatabaseTransactions;

    private const PLACE_TAXONOMY_ID = 42;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['term', 'term_i18n', 'object_term_relation', 'information_object', 'digital_object'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Core table {$t} not provisioned in the test DB.");
            }
        }
    }

    public function test_gather_resolves_place_counts_media_and_reports_coverage(): void
    {
        $placeName = 'Lost Hamlet '.Str::random(6);
        $termId = $this->makeTerm($placeName);

        $withImage = $this->makeRecord('Aerial photo of the hamlet');
        $this->makeDigitalObject($withImage, 'image/jpeg');

        $withPdf = $this->makeRecord('Survey report');
        $this->makeDigitalObject($withPdf, 'application/pdf');

        foreach ([$withImage, $withPdf] as $ioId) {
            // object_term_relation is itself a QubitObject subtype (CTI): its id
            // comes from an object row.
            $relId = (int) DB::table('object')->insertGetId([
                'class_name' => 'QubitObjectTermRelation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('object_term_relation')->insert([
                'id'        => $relId,
                'object_id' => $ioId,
                'term_id'   => $termId,
            ]);
        }

        $result = app(LostPlaceGatherService::class)->gather($placeName);

        $this->assertNotNull($result['place']);
        $this->assertSame($termId, $result['place']['term_id']);

        $cov = $result['coverage'];
        $this->assertSame(2, $cov['records_total']);
        $this->assertSame(2, $cov['records_with_media']);
        $this->assertSame(1, $cov['image_total']);
        $this->assertSame(1, $cov['document_total']);
        $this->assertSame(100, $cov['coverage_pct']);
        // image_total == 1 -> the "sparse" band (heavily inferred reconstruction).
        $this->assertSame('sparse', $cov['reconstruction_level']);
    }

    public function test_discover_degrades_when_place_has_no_seed_imagery(): void
    {
        $placeName = 'Imageless Place '.Str::random(6);
        $termId = $this->makeTerm($placeName);
        $rec = $this->makeRecord('Text-only survey');
        $this->makeDigitalObject($rec, 'application/pdf'); // no image to seed from
        $relId = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitObjectTermRelation', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('object_term_relation')->insert(['id' => $relId, 'object_id' => $rec, 'term_id' => $termId]);

        $d = app(\AhgExhibition\Services\LostPlaceGatherService::class)->discoverCandidates($termId);

        $this->assertTrue($d['available']);
        $this->assertSame(0, $d['seeds']);
        $this->assertSame([], $d['candidates']);
        $this->assertStringContainsString('#1272', (string) $d['note']);
    }

    public function test_gather_returns_empty_for_unknown_place(): void
    {
        $result = app(LostPlaceGatherService::class)->gather('No Such Place '.Str::random(8));

        $this->assertNull($result['place']);
        $this->assertSame(0, $result['coverage']['records_total']);
        $this->assertSame('insufficient', $result['coverage']['reconstruction_level']);
    }

    private function makeTerm(string $name): int
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitTerm',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('term')->insert([
            'id'             => $id,
            'taxonomy_id'    => self::PLACE_TAXONOMY_ID,
            'source_culture' => 'en',
        ]);
        DB::table('term_i18n')->insert([
            'id'      => $id,
            'culture' => 'en',
            'name'    => $name,
        ]);

        return $id;
    }

    private function makeRecord(string $title): int
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('information_object')->insert([
            'id'             => $id,
            'source_culture' => 'en',
        ]);
        DB::table('information_object_i18n')->insert([
            'id'      => $id,
            'culture' => 'en',
            'title'   => $title,
        ]);

        return $id;
    }

    private function makeDigitalObject(int $ioId, string $mime): void
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('digital_object')->insert([
            'id'        => $id,
            'object_id' => $ioId,
            'name'      => 'evidence-'.$id,
            'path'      => 'uploads/test/evidence-'.$id,
            'mime_type' => $mime,
            'parent_id' => null,
        ]);
    }
}
