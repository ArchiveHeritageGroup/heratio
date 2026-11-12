<?php

/**
 * ExportFallsBackToSourceCultureTest - a record with text only in its source
 * language still exports when the interface is in another language. 12 Nov
 * 2026: heratio.org error log, a crawler browsing in another language got a
 * 500 on /export/marc ("Marc21BinaryEncoder: invalid MARCXML input") because
 * the MARCXML came back empty.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgMetadataExport\Services\Exporters\Marc21BinaryEncoder;
use AhgMetadataExport\Services\Exporters\MarcxmlSerializer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExportFallsBackToSourceCultureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_record_with_english_only_exports_in_french(): void
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'source_culture' => 'en', 'identifier' => 'ZZ-MARC-1']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => 'ZZ English only record']);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'zz-english-only-'.$id]);

        $body = (new MarcxmlSerializer())->serializeRecord($id, 'fr');
        $this->assertStringContainsString('ZZ English only record', $body);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".$body;
        $binary = (new Marc21BinaryEncoder())->encodeFromMarcxml($xml);
        $this->assertNotSame('', $binary);
        $this->assertStringContainsString('ZZ English only record', $binary);
    }
}
