<?php

/**
 * RicRecordSetExportTest - the public RiC record-set export
 * (GET /api/ric/v1/records/{slug}/export) holds every descendant to the same
 * rules as the record itself.
 *
 * Before 26 Nov 2026 only the root was checked: children were found by
 * reading an id back from their slug URI (a slug gave id 0, an error node;
 * a slug starting with digits exported a different record), drafts and their
 * subtrees were not left out for guests, and no record carried the
 * restriction block that GET /records/{slug} adds (addIscapCompliance).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RicRecordSetExportTest extends TestCase
{
    use DatabaseTransactions;

    private function record(int $parentId, string $slug, string $title, bool $published, int $lft): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parentId, 'source_culture' => 'en', 'lft' => $lft, 'rgt' => $lft + 1]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);
        StatusRow::set($id, 158, $published ? 160 : 159);

        return $id;
    }

    public function test_guest_export_has_every_visible_descendant_with_restrictions_and_no_drafts(): void
    {
        foreach (['object', 'information_object', 'information_object_i18n', 'slug', 'status', 'personal_data_log'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        \AhgCore\Services\AclService::forgetUser();

        $u = uniqid();
        $root = $this->record(1, "ricexp-root-$u", "RicExp root $u", true, 900000001);
        // A slug starting with digits used to export the record with that id.
        $child = $this->record($root, "1-ricexp-child-$u", "RicExp child $u", true, 900000002);
        $draft = $this->record($root, "ricexp-draft-$u", "RicExp draft $u", false, 900000004);
        $this->record($draft, "ricexp-under-draft-$u", "RicExp under draft $u", true, 900000005);
        DB::table('personal_data_log')->insert(['object_id' => $child, 'data_type' => 'name', 'action' => 'test']);

        $res = $this->get("/api/ric/v1/records/ricexp-root-$u/export");

        $res->assertOk();
        $graph = $res->json('@graph');
        $titles = array_column($graph, 'rico:title');
        $this->assertSame([], array_values(array_filter($graph, fn ($n) => isset($n['error']))), 'no error nodes');
        $this->assertContains("RicExp child $u", $titles);
        $this->assertNotContains("RicExp draft $u", $titles, 'a draft is left out for guests');
        $this->assertNotContains("RicExp under draft $u", $titles, 'and so is everything under it');
        $this->assertCount(2, $graph, 'root and the one visible child only');
        $node = collect($graph)->firstWhere('rico:title', "RicExp child $u");
        $this->assertTrue($node['rico:containsPersonalData'] ?? false, 'descendants carry the restriction block');
    }
}
