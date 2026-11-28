<?php

/**
 * RicLinkedDataDisclosureTest - the public RiC linked-data API (/api/ric/v1/records,
 * /records/{slug}, /records/{slug}/export) must refuse or leave out every record the
 * shared DisclosureGate withholds (embargo, security classification, ICIP restriction,
 * ODRL 'use' prohibition), not only drafts and community-protocol terms.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Support\StatusRow;
use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RicLinkedDataDisclosureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
    }

    /** A published record under the root with a slug and title. */
    private function record(string $title, int $parent = 1): array
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent, 'identifier' => 'ricld-'.$id, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        $slug = 'ricld-'.$id.'-'.uniqid();
        DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);
        StatusRow::set($id, 158, 160);

        return [$id, $slug];
    }

    /** The gate memoises its restricted set per request; tests make several in one app. */
    private function freshGate(): void
    {
        $this->app->forgetInstance(\AhgCore\Services\DisclosureGate::class);
    }

    /** @return array<string, callable(int): void> */
    private function restrictions(): array
    {
        return [
            'embargo' => fn (int $id) => DB::table('embargo')->insert(['object_id' => $id, 'embargo_type' => 'full', 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'status' => 'active', 'is_active' => 1]),
            'classified' => fn (int $id) => DB::table('object_security_classification')->insert(['object_id' => $id, 'classification_id' => DB::table('security_classification')->where('level', '>', 0)->value('id'), 'assigned_at' => now(), 'active' => 1, 'inherit_to_children' => 1]),
            'icip' => fn (int $id) => DB::table('icip_access_restriction')->insert(['information_object_id' => $id, 'restriction_type' => 'community_only', 'applies_to_descendants' => 1]),
            'odrl' => fn (int $id) => DB::table('research_rights_policy')->insert(['target_type' => 'archival_description', 'target_id' => $id, 'policy_type' => 'prohibition', 'action_type' => 'use']),
        ];
    }

    public function test_a_visitor_cannot_read_a_restricted_record_or_its_export(): void
    {
        foreach ($this->restrictions() as $kind => $restrict) {
            [$id, $slug] = $this->record("RicLD secret $kind ".uniqid());
            $restrict($id);
            $this->freshGate();
            $this->assertSame(404, $this->getJson("/api/ric/v1/records/$slug")->status(), "$kind: record");
            $this->assertSame(404, $this->get("/api/ric/v1/records/$slug/export")->status(), "$kind: export");
        }
    }

    public function test_the_record_list_leaves_restricted_records_out(): void
    {
        $titles = [];
        foreach ($this->restrictions() as $kind => $restrict) {
            [$id] = $this->record($titles[] = "RicLD listed $kind ".uniqid());
            $restrict($id);
        }
        $this->freshGate();
        $body = '';
        for ($page = 1; $page <= 400; $page++) {
            $json = $this->getJson("/api/ric/v1/records?limit=200&page=$page")->assertOk()->json();
            $body .= json_encode($json['ric:items'] ?? []);
            if (count($json['ric:items'] ?? []) < 200) {
                break;
            }
        }
        foreach ($titles as $t) {
            $this->assertStringNotContainsString($t, $body);
        }
    }

    public function test_an_export_leaves_out_a_restricted_descendant(): void
    {
        [$root, $slug] = $this->record('RicLD open root '.uniqid());
        [$child] = $this->record($secret = 'RicLD embargoed child '.uniqid(), $root);
        $this->restrictions()['embargo']($child);

        $this->get("/api/ric/v1/records/$slug/export")->assertOk()->assertDontSee($secret);
    }

    public function test_an_open_record_is_still_served(): void
    {
        [, $slug] = $this->record($t = 'RicLD open '.uniqid());
        $this->getJson("/api/ric/v1/records/$slug")->assertOk()->assertSee($t);
    }

    /** A user in $group (100 = administrator, 99 = authenticated only). */
    private function user(int $group): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'ricld-'.$id, 'email' => uniqid('ricld-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_staff_see_an_embargoed_record_and_a_plain_signed_in_user_does_not(): void
    {
        [$id, $slug] = $this->record($t = 'RicLD staff-only '.uniqid());
        $this->restrictions()['embargo']($id);
        $this->freshGate();

        $this->actingAs($this->user(100))->getJson("/api/ric/v1/records/$slug")->assertOk()->assertSee($t);
        \AhgCore\Services\AclService::forgetUser();
        $this->freshGate();
        $this->actingAs($this->user(99))->getJson("/api/ric/v1/records/$slug")->assertNotFound();
    }
}
