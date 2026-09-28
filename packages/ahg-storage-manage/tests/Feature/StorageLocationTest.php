<?php

/**
 * StorageLocationTest - heratio#1514 / atom-ahg-plugins#193 (option 2).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgStorageManage\Services\StorageLocationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The closure table is an index over parent_id, so the property that matters
 * is: after any create / move / delete, the incrementally maintained closure
 * equals a full rebuild from parent_id. Each test checks that, plus the reads
 * that depend on it and the route gates.
 */
class StorageLocationTest extends TestCase
{
    use DatabaseTransactions;

    private StorageLocationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ahg_storage_location_closure')) {
            $this->markTestSkipped('storage-location tables not installed.');
        }
        $this->service = new StorageLocationService;
    }

    private function make(string $name, string $type, ?int $parent = null): int
    {
        return $this->service->create(['name' => $name, 'location_type' => $type, 'parent_id' => $parent]);
    }

    /** Closure rows as a sorted list, for exact comparison. */
    private function closure(): array
    {
        return DB::table('ahg_storage_location_closure')
            ->orderBy('ancestor')->orderBy('descendant')
            ->get()->map(fn ($r) => "{$r->ancestor}>{$r->descendant}:{$r->depth}")->all();
    }

    private function assertClosureMatchesRebuild(): void
    {
        $incremental = $this->closure();
        Artisan::call('ahg:build-closure', ['--table' => 'ahg_storage_location']);
        $this->assertSame($incremental, $this->closure(), 'incremental closure differs from a rebuild from parent_id');
    }

    private function names(array $rows): array
    {
        return array_map(fn ($r) => $r->name, $rows);
    }

    public function test_nested_create_path_descendants_and_level(): void
    {
        $b = $this->make('ZZ Building', 'building');
        $f = $this->make('ZZ Floor', 'floor', $b);
        $r = $this->make('ZZ Room', 'room', $f);
        $s = $this->make('ZZ Shelf', 'shelf', $r);

        $this->assertSame(['ZZ Building', 'ZZ Floor', 'ZZ Room', 'ZZ Shelf'], $this->names($this->service->getPath($s)));
        $this->assertCount(3, $this->service->getDescendants($b));
        $this->assertSame(3, (int) $this->service->getById($s)->level);
        $this->assertClosureMatchesRebuild();
    }

    public function test_move_subtree_updates_closure_and_levels(): void
    {
        $a = $this->make('ZZ Building A', 'building');
        $b = $this->make('ZZ Building B', 'building');
        $f = $this->make('ZZ Floor', 'floor', $a);
        $r = $this->make('ZZ Room', 'room', $f);
        $s = $this->make('ZZ Shelf', 'shelf', $r);

        $this->service->update($r, ['parent_id' => $b]);

        $this->assertSame(['ZZ Building B', 'ZZ Room', 'ZZ Shelf'], $this->names($this->service->getPath($s)));
        $this->assertSame(2, (int) $this->service->getById($s)->level);
        $this->assertCount(1, $this->service->getDescendants($a));
        $this->assertClosureMatchesRebuild();

        $this->service->update($r, ['parent_id' => '']);   // move to root
        $this->assertNull($this->service->getById($r)->parent_id);
        $this->assertSame(1, (int) $this->service->getById($s)->level);
        $this->assertClosureMatchesRebuild();
    }

    public function test_move_inside_own_subtree_is_refused(): void
    {
        $b = $this->make('ZZ Building', 'building');
        $s = $this->make('ZZ Shelf', 'shelf', $this->make('ZZ Room', 'room', $b));

        $this->expectException(RuntimeException::class);
        $this->service->update($b, ['parent_id' => $s]);
    }

    public function test_delete_refuses_parent_and_clears_closure_for_leaf(): void
    {
        $b = $this->make('ZZ Building', 'building');
        $r = $this->make('ZZ Room', 'room', $b);

        try {
            $this->service->delete($b);
            $this->fail('deleting a location with children should be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('children', $e->getMessage());
        }

        $this->service->delete($r);
        $this->assertSame(0, DB::table('ahg_storage_location_closure')
            ->where('ancestor', $r)->orWhere('descendant', $r)->count());
        $this->assertClosureMatchesRebuild();
    }

    public function test_type_must_come_from_dropdown_and_slugs_disambiguate(): void
    {
        $one = $this->make('ZZ Room One', 'room');
        $two = $this->make('ZZ Room One', 'room');
        $this->assertSame($this->service->getById($one)->slug.'-2', $this->service->getById($two)->slug);

        $this->expectException(RuntimeException::class);
        $this->make('ZZ Nowhere', 'not-a-dropdown-code');
    }

    public function test_routes_are_staff_only_and_render_for_admin(): void
    {
        $b = $this->make('ZZ Route Building', 'building');
        $slug = $this->service->getById($b)->slug;

        $this->get('/storagelocation/browse')->assertRedirect();
        $this->assertAdminGated($this->get("/storagelocation/{$slug}/delete"));

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get('/storagelocation/browse')->assertOk()->assertSee('ZZ Route Building');
        $this->actingAs($admin)->get("/storagelocation/{$slug}")->assertOk()->assertSee('ZZ Route Building');
        $this->actingAs($admin)->get('/storagelocation/add?parent_id='.$b)->assertOk();
        $this->actingAs($admin)->get("/storagelocation/{$slug}/edit")->assertOk();
        $this->actingAs($admin)->getJson('/storagelocation/api/tree')->assertOk()->assertJsonPath('success', true);

        // The admin menu links the page (ahg-admin-menu, Storage section).
        $this->actingAs($admin)->get('/strongroom/browse')->assertOk()
            ->assertSee(route('storagelocation.browse'), false)->assertSee('Storage locations');

        $this->actingAs($admin)->post('/storagelocation/add', [
            'name' => 'ZZ Route Room', 'location_type' => 'room', 'parent_id' => $b,
        ])->assertRedirect();
        $this->assertSame(['ZZ Route Building', 'ZZ Route Room'],
            $this->names($this->service->getPath((int) DB::table('ahg_storage_location')->where('name', 'ZZ Route Room')->value('id'))));
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id,
            'username' => 'storageloc-admin-'.$id,
            'email' => uniqid('storageloc-', true).'@example.test',
            'password_hash' => Hash::make('secret'),
            'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
