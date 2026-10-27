<?php

/**
 * RicExplorerDisclosureTest - the public RiC explorer endpoints refuse a draft
 * record to visitors and leave it out of a published record's relations,
 * while staff still see it (AhgRic\Support\RicDisclosure).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RicExplorerDisclosureTest extends TestCase
{
    use DatabaseTransactions;

    private int $draft;

    private int $published;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        $this->draft = $this->record(159, 'Ric draft '.uniqid());
        $this->published = $this->record(160, 'Ric published '.uniqid());
        $rel = $this->objectRow('QubitRelation');
        DB::table('relation')->insert(['id' => $rel, 'subject_id' => $this->draft, 'object_id' => $this->published, 'source_culture' => 'en']);
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(int $status, string $title): int
    {
        $id = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'identifier' => 'ric-'.$id, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        StatusRow::set($id, 158, $status);

        return $id;
    }

    private function admin(): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'ric-'.$id, 'email' => uniqid('ric-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_a_visitor_gets_404_for_a_draft_on_every_explorer_endpoint(): void
    {
        $d = $this->draft;
        foreach (["/admin/ric/timeline-data?id=$d", "/admin/ric/data?id=$d", "/ric-api/timeline/$d", "/ric-api/relations/$d",
            "/ric-api/graph-summary/$d", "/ric-api/connections/$d", "/ric-api/explain/$d/{$this->published}",
            "/admin/ric/export/jsonld?id=$d", "/admin/ric/validate/record/$d", "/admin/export/crm/id/$d"] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_a_published_records_relations_leave_the_draft_out(): void
    {
        $this->get("/ric-api/connections/{$this->published}")->assertOk()->assertDontSee('Ric draft');
        $this->assertStringNotContainsString('"target_id":'.$this->draft, $this->get("/ric-api/relations/{$this->published}")->getContent());
    }

    public function test_staff_still_see_the_draft(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get("/admin/ric/timeline-data?id={$this->draft}")->assertOk()->assertSee('Ric draft');
        $this->actingAs($admin)->get("/ric-api/connections/{$this->published}")->assertOk()->assertSee('Ric draft');
    }
}
