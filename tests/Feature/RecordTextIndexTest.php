<?php

/**
 * RecordTextIndexTest - heratio#1763. The text inside a record's files - OCR,
 * reviewed transcripts, audio/video transcriptions, caption tracks - goes
 * into the search index, except for a record whose files privacy screening
 * still withholds.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgSearch\Services\RecordTextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecordTextIndexTest extends TestCase
{
    use DatabaseTransactions;

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** A record with one master file; returns [record, file]. */
    private function record(int $lft): array
    {
        $io = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $io, 'source_culture' => 'en', 'lft' => $lft, 'rgt' => $lft + 1]);
        $do = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $do, 'object_id' => $io, 'usage_id' => 140, 'name' => 'f.jpg', 'path' => '/uploads/r/x/', 'mime_type' => 'image/jpeg']);

        return [$io, $do];
    }

    public function test_file_text_is_collected_and_withheld_files_are_not(): void
    {
        foreach (['iiif_ocr_text', 'media_transcription', 'media_caption_track'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t missing");
            }
        }
        DB::table('ahg_settings')->whereIn('setting_key', ['privacy_screening_repositories', 'privacy_screening_collections'])->delete();

        [$a, $doA] = $this->record(8000000);
        DB::table('iiif_ocr_text')->insert(['digital_object_id' => $doA, 'object_id' => $a, 'full_text' => 'Lay in state at the Union Buildings', 'format' => 'plain', 'created_at' => now()]);
        DB::table('media_transcription')->insert(['digital_object_id' => $doA, 'object_id' => $a, 'language' => 'en', 'full_text' => 'The ocean floor rises five miles', 'created_at' => now()]);
        DB::table('media_caption_track')->insert(['digital_object_id' => $doA, 'track_type' => 'subtitle', 'label' => 'English', 'language_code' => 'en', 'vtt_content' => "WEBVTT\n\n1\n00:00:01.000 --> 00:00:03.000\nMourners line the street\n", 'created_at' => now()]);
        $prop = $this->objectRow('QubitProperty');
        DB::table('property')->insert(['id' => $prop, 'object_id' => $doA, 'name' => 'transcript', 'source_culture' => 'en']);
        DB::table('property_i18n')->insert(['id' => $prop, 'culture' => 'en', 'value' => 'Reviewed: the funeral train left Pretoria']);

        [$b, $doB] = $this->record(8000002);
        DB::table('iiif_ocr_text')->insert(['digital_object_id' => $doB, 'object_id' => $b, 'full_text' => 'A private name in an unscreened file', 'format' => 'plain', 'created_at' => now()]);
        // Record B is under a collection whose files must be screened first.
        DB::table('ahg_settings')->insert(['setting_key' => 'privacy_screening_collections', 'setting_value' => (string) $b, 'setting_group' => 'privacy']);

        $text = RecordTextService::forRecords([$a, $b]);

        $this->assertStringContainsString('Union Buildings', $text[$a]);
        $this->assertStringContainsString('ocean floor rises', $text[$a]);
        $this->assertStringContainsString('Mourners line the street', $text[$a]);
        $this->assertStringNotContainsString('-->', $text[$a], 'caption timings are not text');
        $this->assertStringContainsString('funeral train left Pretoria', $text[$a]);
        $this->assertArrayNotHasKey($b, $text, 'withheld by screening: nothing to search');
    }
}
