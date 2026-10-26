<?php

/**
 * TenantIsolationTest - GHSA-fqxh-fv8c-mq23: with multi-tenancy enforced, a
 * tenant's editor cannot read or change another tenant's records, including
 * descendants that carry no repository of their own; administrators see all.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgCore\Tests\Feature;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\DisclosureGate;
use AhgCore\Support\TenantScope;
use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, object> per tenant: repo, fonds, child, user */
    private array $t = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ahg_tenant', 'ahg_tenant_user', 'repository', 'information_object'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("{$table} table not present in this install.");
            }
        }
        $now = now();
        $lft = (int) DB::table('information_object')->max('rgt') + 100;
        foreach (['a', 'b'] as $n) {
            $repo = DB::table('object')->insertGetId(['class_name' => 'QubitRepository', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('actor')->insert(['id' => $repo, 'source_culture' => 'en']);
            DB::table('repository')->insert(['id' => $repo, 'source_culture' => 'en']);

            // A fonds held by the repository and a child with no repository of its own.
            $ids = [];
            foreach ([[$lft, $lft + 3, $repo, 1], [$lft + 1, $lft + 2, null, null]] as [$l, $r, $rid, $parent]) {
                $id = DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now]);
                DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent ?? $ids[0], 'repository_id' => $rid,
                    'lft' => $l, 'rgt' => $r, 'source_culture' => 'en']);
                DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => "Tenant {$n} record {$id}"]);
                DB::table('slug')->insert(['object_id' => $id, 'slug' => "tenant-{$n}-{$id}"]);
                $ids[] = $id;
            }
            $lft += 10;

            $tenant = DB::table('ahg_tenant')->insertGetId(['code' => "ghsa-fqxh-{$n}-{$repo}", 'name' => "Tenant {$n}",
                'repository_id' => $repo, 'is_active' => 1, 'is_default' => 0, 'status' => 'active']);
            $user = app(UserService::class)->create(['username' => "tenant{$n}{$repo}", 'email' => "tenant{$n}{$repo}@test.invalid",
                'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => "Tenant {$n} editor", 'active' => 1, 'groups' => []]);
            DB::table('acl_user_group')->insert(['user_id' => $user, 'group_id' => \AhgCore\Models\AclGroup::EDITOR_ID]);
            DB::table('ahg_tenant_user')->insert(['tenant_id' => $tenant, 'user_id' => $user]);
            Cache::forget("acl_groups_{$user}");

            $this->t[$n] = (object) ['repo' => $repo, 'fonds' => $ids[0], 'child' => $ids[1], 'slug' => "tenant-{$n}-{$ids[1]}", 'user' => $user];
        }
        foreach (['tenant_enabled', 'tenant_enforce_filter'] as $key) {
            DB::table('ahg_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => 'true']);
        }
        AhgSettingsService::clearCache();
        foreach ($this->t as $x) {
            Cache::forget('tenant_scope_outside_'.$x->repo);
        }
    }

    private function as(string $n): void
    {
        $this->actingAs(\AhgCore\Models\User::query()->findOrFail($this->t[$n]->user));
        TenantScope::flush();
        app()->forgetInstance(DisclosureGate::class);
    }

    public function test_an_editor_is_scoped_to_their_own_tenant_including_inherited_children(): void
    {
        $this->as('b');
        $this->assertSame($this->t['b']->repo, TenantScope::getActiveRepoId());

        $outside = TenantScope::outsideIds();
        $this->assertContains($this->t['a']->fonds, $outside);
        $this->assertContains($this->t['a']->child, $outside, 'a child inherits its fonds\'s repository');
        $this->assertNotContains($this->t['b']->child, $outside);
        $this->assertContains($this->t['a']->child, app(DisclosureGate::class)->restrictedIds());

        $q = DB::table('information_object as io')->whereIn('io.id', [$this->t['a']->child, $this->t['b']->child]);
        TenantScope::apply($q, 'io.repository_id');
        $this->assertSame([$this->t['b']->child], $q->pluck('io.id')->map('intval')->all());
    }

    public function test_the_api_refuses_another_tenants_record_on_a_write_path(): void
    {
        $this->as('b');
        $this->putJson('/api/v2/descriptions/'.$this->t['a']->slug, ['title' => 'Changed across tenants'])
            ->assertStatus(404)->assertJsonPath('message', 'Record not found.');
        $this->assertSame("Tenant a record {$this->t['a']->child}",
            DB::table('information_object_i18n')->where('id', $this->t['a']->child)->value('title'));

        $own = $this->putJson('/api/v2/descriptions/'.$this->t['b']->slug, ['title' => 'Tenant b edit']);
        $this->assertNotSame('Record not found.', $own->json('message'));
    }

    public function test_an_api_key_is_scoped_to_its_owners_tenant(): void
    {
        $key = app(\AhgApi\Services\ApiKeyService::class)->issue($this->t['b']->user, 'tenant test', ['read', 'write'])['api_key'];
        TenantScope::flush();
        $this->withHeaders(['X-API-Key' => $key])
            ->putJson('/api/v2/descriptions/'.$this->t['a']->slug, ['title' => 'Changed by key'])
            ->assertStatus(404)->assertJsonPath('message', 'Record not found.');
    }

    public function test_an_administrator_is_not_scoped(): void
    {
        DB::table('acl_user_group')->insert(['user_id' => $this->t['b']->user, 'group_id' => \AhgCore\Models\AclGroup::ADMINISTRATOR_ID]);
        Cache::forget("acl_groups_{$this->t['b']->user}");
        $this->as('b');
        $this->assertNull(TenantScope::getActiveRepoId());
        $this->assertSame([], TenantScope::outsideIds());
    }
}
