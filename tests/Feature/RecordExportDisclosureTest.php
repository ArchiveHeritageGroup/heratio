<?php

/**
 * RecordExportDisclosureTest - the public per-record exports and provenance
 * pages refuse drafts to
 * visitors, leave withheld branches out, and nest records that have no
 * nested-set bounds at their real depth (heratio#1541).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\ClosureMaintenanceService;
use AhgCore\Support\StatusRow;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RecordExportDisclosureTest extends TestCase
{
    use DatabaseTransactions;

    private int $fonds;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        $this->fonds = Io::create(['title' => 'ZZ Export fonds'], 'en');
        StatusRow::set($this->fonds, 158, 160);
    }

    /** A record under $parent with a closure node and no lft/rgt. */
    private function child(int $parent, string $title, int $status): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent, 'lft' => null, 'rgt' => null, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'zz-export-'.$id]);
        app(ClosureMaintenanceService::class)->addNode('information_object', $id, $parent);
        StatusRow::set($id, 158, $status);

        return $id;
    }

    private function slug(int $id): string
    {
        return (string) DB::table('slug')->where('object_id', $id)->value('slug');
    }

    private function admin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'export-'.$id, 'email' => uniqid('export-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_a_visitor_cannot_export_a_draft(): void
    {
        $draft = $this->slug($this->child($this->fonds, 'ZZ Export draft', 159));
        foreach (['dc', 'ead', 'ead3', 'mods', 'marcxml', 'csv'] as $format) {
            $this->get("/informationobject/$draft/export/$format")->assertNotFound();
        }
        $this->get("/$draft/provenance")->assertNotFound();
        $this->get("/provenance/$draft/timeline")->assertNotFound();
        $this->get("/provenance/$draft/export-csv")->assertNotFound();
        $this->actingAs($this->admin())->get("/informationobject/$draft/export/ead")->assertOk()->assertSee('ZZ Export draft');
    }

    public function test_ead_nests_records_without_bounds_and_leaves_withheld_branches_out(): void
    {
        $series = $this->child($this->fonds, 'ZZ Export series', 160);
        $this->child($series, 'ZZ Export file', 160);
        $withheld = $this->child($this->fonds, 'ZZ Export withheld', 159);
        $this->child($withheld, 'ZZ Export orphan', 160);

        $ead = $this->get('/informationobject/'.$this->slug($this->fonds).'/export/ead')->assertOk()->getContent();
        $csv = $this->get('/informationobject/'.$this->slug($this->fonds).'/export/csv')->assertOk()->streamedContent();
        $oai = (new \AhgMetadataExport\Services\Exporters\Ead2002Serializer)->serializeRecord($this->fonds, 'en', true);

        foreach ([$ead, $oai] as $xml) {
            // The file sits inside the series' <c>, which closes after it.
            $this->assertMatchesRegularExpression('~ZZ Export series</unittitle>.*?<c [^>]*>.*?ZZ Export file</unittitle>.*?</c>\s*</c>~s', $xml);
            $this->assertStringNotContainsString('ZZ Export withheld', $xml);
            $this->assertStringNotContainsString('ZZ Export orphan', $xml);
        }
        $this->assertStringContainsString('ZZ Export file', $csv);
        $this->assertStringNotContainsString('ZZ Export withheld', $csv);
        $this->assertStringNotContainsString('ZZ Export orphan', $csv);
    }
}
