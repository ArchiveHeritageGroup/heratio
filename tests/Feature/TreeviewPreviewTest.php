<?php

/**
 * TreeviewPreviewTest - the hierarchy hover card shows a record's preview to
 * the public only when the record is publicly disclosable.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TreeviewPreviewTest extends TestCase
{
    use DatabaseTransactions;

    private function io(string $title, bool $published): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'source_culture' => 'en', 'lft' => 9600000 + $id % 1000, 'rgt' => 9600001 + $id % 1000]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title, 'scope_and_content' => '# Heading'."\n\n".'Cuttings on the funeral.']);
        $sid = (int) DB::table('object')->insertGetId(['class_name' => 'QubitStatus', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('status')->insert(['id' => $sid, 'object_id' => $id, 'type_id' => 158, 'status_id' => $published ? 160 : 159]);

        return $id;
    }

    public function test_public_sees_published_records_only(): void
    {
        $pub = $this->io('Published record', true);
        $draft = $this->io('Draft record', false);

        $this->getJson('/informationobject/treeview-preview/'.$pub)->assertOk()
            ->assertJson(['title' => 'Published record'])
            ->assertJsonPath('scope', 'Heading Cuttings on the funeral.');
        $this->getJson('/informationobject/treeview-preview/'.$draft)->assertNotFound();
        $this->getJson('/informationobject/treeview-preview/1')->assertNotFound();
    }
}
