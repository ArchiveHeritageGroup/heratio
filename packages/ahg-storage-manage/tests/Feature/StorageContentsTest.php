<?php

/**
 * StorageContentsTest - heratio#1528 (parity twin of atom-ahg-plugins#193).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgStorageManage\Services\StorageFlatMigrationService;
use AhgStorageManage\Services\StorageLocationService;
use AhgStorageManage\Services\StorageMovementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Port of the behaviour the plugin's testing/storage-contents-check.php asserts:
 * the type list and its fallbacks, nested containers, the capacity roll-up,
 * placing unplaced objects, and the migration of the flat location fields (dry,
 * applied, re-run, with free text and strongrooms).
 */
class StorageContentsTest extends TestCase
{
    use DatabaseTransactions;

    private StorageLocationService $locations;

    private StorageMovementService $movements;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ahg_storage_movement') || ! Schema::hasTable('physical_object_extended')) {
            $this->markTestSkipped('storage tables not installed.');
        }

        AclService::forgetUser();
        $this->movements = new StorageMovementService;
        $this->locations = new StorageLocationService;
    }

    private function location(string $name, string $type = 'room', ?int $parent = null, array $extra = []): int
    {
        return $this->locations->create(['name' => $name, 'location_type' => $type, 'parent_id' => $parent] + $extra);
    }

    private function object(string $name, array $flat = [], ?string $freeText = null): int
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitPhysicalObject', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('physical_object')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('physical_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name, 'location' => $freeText]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'zz-contents-'.$id]);
        if ($flat) {
            DB::table('physical_object_extended')->insert(['physical_object_id' => $id] + $flat);
        }

        return $id;
    }

    private function user(int $group): User
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id,
            'username' => 'storagecontents-'.$id,
            'email' => uniqid('storagecontents-', true).'@example.test',
            'password_hash' => Hash::make('secret'),
            'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function slugOf(int $locationId): string
    {
        return (string) DB::table('ahg_storage_location')->where('id', $locationId)->value('slug');
    }

    // ---------- Location types ------------------------------------------

    public function test_types_come_from_the_dropdown_and_a_retired_type_stays_on_its_location(): void
    {
        $this->assertSame('Storage Unit', $this->locations->types()['storage_unit'] ?? null, 'the nine types are seeded in the Dropdown Manager');

        $cabinet = 'zz_cabinet_'.substr(md5(uniqid('', true)), 0, 6);
        DB::table('ahg_dropdown')->insert([
            'taxonomy' => StorageLocationService::TYPE_TAXONOMY, 'taxonomy_label' => 'Storage Location Type',
            'code' => $cabinet, 'label' => 'ZZ Cabinet', 'sort_order' => 999, 'is_active' => 1,
        ]);
        // A fresh service: types() is read once per instance.
        $id = (new StorageLocationService)->create(['name' => 'ZZ Plan Cabinet', 'location_type' => $cabinet]);
        $this->assertSame('ZZ Cabinet', (new StorageLocationService)->typeLabel($cabinet));

        DB::table('ahg_dropdown')->where('taxonomy', StorageLocationService::TYPE_TAXONOMY)->where('code', $cabinet)->update(['is_active' => 0]);
        $fresh = new StorageLocationService;
        $this->assertArrayNotHasKey($cabinet, $fresh->types(), 'a retired type is no longer offered');

        // Kept through an edit, including one that resends the type.
        $fresh->update($id, ['name' => 'ZZ Plan Cabinet 2', 'location_type' => $cabinet]);
        $this->assertSame($cabinet, $fresh->getById($id)->location_type);

        // ... and the edit route accepts it too.
        $admin = $this->user(100);
        $this->actingAs($admin)->post('/storagelocation/'.$this->slugOf($id).'/edit', [
            'name' => 'ZZ Plan Cabinet 3', 'location_type' => $cabinet,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('ZZ Plan Cabinet 3', $fresh->getById($id)->name);

        // But no new location may be given it.
        $this->expectException(\RuntimeException::class);
        $fresh->create(['name' => 'ZZ Another Cabinet', 'location_type' => $cabinet]);
    }

    public function test_an_empty_type_list_falls_back_to_the_shipped_nine(): void
    {
        DB::table('ahg_dropdown')->where('taxonomy', StorageLocationService::TYPE_TAXONOMY)->update(['is_active' => 0]);

        $types = (new StorageLocationService)->types();

        $this->assertSame(StorageLocationService::TYPES, array_keys($types));
        $this->assertSame('Storage unit', $types['storage_unit']);
    }

    // ---------- Containers inside containers ----------------------------

    public function test_a_location_lists_what_is_held_beneath_it_and_moving_a_pallet_is_one_move(): void
    {
        $bay = $this->location('ZZ Bay 1', 'bay');
        $pallet = $this->location('ZZ Pallet 7', 'storage_unit', $bay);
        $carton = $this->location('ZZ Carton A', 'container', $pallet);
        $boxA = $this->object('ZZ Box A');
        $boxB = $this->object('ZZ Box B');
        $loose = $this->object('ZZ Loose box');

        $this->movements->moveObject($boxA, $carton);
        $this->movements->moveObject($boxB, $carton);
        $this->movements->moveObject($loose, $bay);

        $under = $this->movements->objectsUnder($bay);
        $this->assertCount(3, $under);
        $this->assertSame([0, 2, 2], array_map(fn ($r) => (int) $r['depth'], $under), 'held here first, then beneath');
        $this->assertSame('ZZ Carton A', $under[1]['location_name'], 'each row names the location the object is actually in');

        // The pallet moves to another bay: one location move in the log, and the
        // boxes go with it without a row of their own.
        $otherBay = $this->location('ZZ Bay 2', 'bay');
        $before = DB::table('ahg_storage_movement')->count();
        $this->locations->update($pallet, ['parent_id' => $otherBay]);
        $this->assertSame($before + 1, DB::table('ahg_storage_movement')->count());

        $this->assertCount(1, $this->movements->objectsUnder($bay));
        $this->assertCount(2, $this->movements->objectsUnder($otherBay));

        // The location page shows it.
        $admin = $this->user(100);
        $this->actingAs($admin)->get('/storagelocation/'.$this->slugOf($otherBay))
            ->assertOk()->assertSee('Objects in locations beneath this one')->assertSee('ZZ Box A')->assertSee('ZZ Carton A');
    }

    // ---------- Capacity roll-up ---------------------------------------

    public function test_capacity_is_summed_per_unit_and_kept_apart_from_the_figure_declared_here(): void
    {
        $room = $this->location('ZZ Cap Room', 'room', null, ['capacity_value' => 100, 'capacity_unit' => 'boxes']);
        $shelf1 = $this->location('ZZ Cap Shelf 1', 'shelf', $room, ['capacity_value' => 40, 'capacity_unit' => 'boxes']);
        $this->location('ZZ Cap Shelf 2', 'shelf', $room, ['capacity_value' => 30, 'capacity_unit' => 'boxes']);
        $rack = $this->location('ZZ Cap Rack', 'rack', $room, ['capacity_value' => 12.5, 'capacity_unit' => 'linear_metres']);
        $this->location('ZZ Cap Shelf 3', 'shelf', $rack);   // declares nothing

        $this->movements->moveObject($this->object('ZZ Cap Box 1'), $room);
        $this->movements->moveObject($this->object('ZZ Cap Box 2'), $shelf1);
        $this->movements->moveObject($this->object('ZZ Cap Box 3'), $shelf1);

        $rollup = $this->locations->capacityRollup($room);

        $this->assertSame(['value' => 100.0, 'unit' => 'boxes'], $rollup['own']);
        $this->assertSame(['boxes' => 70.0, 'linear_metres' => 12.5], $rollup['beneath'], 'per unit, never added across units, and never added to the own figure');
        $this->assertSame(3, $rollup['declared_beneath']);
        $this->assertSame(1, $rollup['objects_here']);
        $this->assertSame(2, $rollup['objects_beneath']);
        $this->assertSame(3, $rollup['objects_total']);
        $this->assertArrayNotHasKey('percent', $rollup);

        $leaf = $this->locations->capacityRollup($shelf1);
        $this->assertSame([], $leaf['beneath']);
        $this->assertSame(2, $leaf['objects_total']);
    }

    // ---------- Placing objects ----------------------------------------

    public function test_unplaced_objects_are_listed_and_placed_in_one_batch(): void
    {
        $shelf = $this->location('ZZ Place Shelf', 'shelf');
        $other = $this->location('ZZ Place Elsewhere', 'shelf');
        $one = $this->object('ZZ Unplaced one');
        $two = $this->object('ZZ Unplaced two');
        $placed = $this->object('ZZ Already placed');
        $this->movements->moveObject($placed, $other);

        $found = $this->movements->unplacedObjects('ZZ Unplaced');
        $this->assertSame(2, $found['total']);
        $this->assertSame([$one, $two], array_map(fn ($r) => (int) $r['physical_object_id'], $found['rows']));
        $this->assertCount(1, $this->movements->unplacedObjects('ZZ Unplaced', 1)['rows'], 'the list is capped');

        $slug = $this->slugOf($shelf);

        // A contributor neither sees the panel nor can post to it.
        $contributor = $this->user(102);
        $this->actingAs($contributor)->post("/storagelocation/{$slug}/place-objects", ['object_ids' => [$one]])->assertForbidden();
        $this->assertNull($this->movements->currentLocationOf($one));

        AclService::forgetUser();
        $admin = $this->user(100);
        $this->actingAs($admin)->get("/storagelocation/{$slug}?q=ZZ+Unplaced")
            ->assertOk()->assertSee('Place objects here')->assertSee('ZZ Unplaced two');

        // An object that already has a place is not pulled across by this form.
        $this->actingAs($admin)->post("/storagelocation/{$slug}/place-objects", [
            'object_ids' => [$one, $two, $placed],
            'note' => 'first shelving',
        ])->assertRedirect();

        $this->assertSame($shelf, $this->movements->currentLocationOf($one));
        $this->assertSame($shelf, $this->movements->currentLocationOf($two));
        $this->assertSame($other, $this->movements->currentLocationOf($placed));

        $rows = DB::table('ahg_storage_movement')->where('to_location_id', $shelf)->get();
        $this->assertCount(2, $rows);
        $this->assertNull($rows[0]->from_location_id, 'a first placement has no from');
        $this->assertNotNull($rows[0]->batch_id);
        $this->assertSame($rows[0]->batch_id, $rows[1]->batch_id, 'one batch');
        $this->assertSame('first shelving', $rows[0]->note);

        $this->actingAs($admin)->post("/storagelocation/{$slug}/place-objects", [])->assertSessionHasErrors('object_ids');
    }

    // ---------- Migration of the flat fields ---------------------------

    public function test_the_flat_field_migration_is_dry_unless_applied_and_can_be_rerun(): void
    {
        $structured = $this->object('ZZ Mig Structured', ['building' => 'ZZ Main', 'floor' => '1', 'room' => 'C3', 'shelf' => '4']);
        $sameRoom = $this->object('ZZ Mig Same room', ['building' => 'zz main ', 'floor' => 'Floor 1', 'room' => 'room c3']);
        $freeOnly = $this->object('ZZ Mig Free text', [], 'Delmas');
        $nothing = $this->object('ZZ Mig Nothing');

        $strongroomId = (int) DB::table('ahg_strongroom')->insertGetId([
            'slug' => 'zz-vault-'.uniqid(), 'name' => 'ZZ Vault', 'location_description' => 'Basement vault',
            'capacity_value' => 80, 'capacity_unit' => 'boxes',
        ]);
        DB::table('ahg_strongroom')->insert(['slug' => 'zz-empty-'.uniqid(), 'name' => 'ZZ Empty Vault', 'capacity_unit' => 'boxes']);
        $inVault = $this->object('ZZ Mig In vault');
        DB::table('ahg_physical_object_storage')->insert(['physical_object_id' => $inVault, 'strongroom_id' => $strongroomId, 'size_units_used' => 2]);

        $service = new StorageFlatMigrationService;
        $counts = fn () => [
            DB::table('ahg_storage_location')->count(),
            DB::table('ahg_storage_movement')->count(),
            DB::table('ahg_physical_object_location')->count(),
        ];
        $before = $counts();

        // Dry run: reports, writes nothing.
        $dry = $service->run(false);
        $this->assertSame($before, $counts(), 'a dry run changes nothing');
        $this->assertContains('ZZ Main > Floor 1 > Room C3 > Shelf 4 (shelf)', $dry['locations_created']);
        $this->assertContains('ZZ Vault (room)', $dry['locations_created']);
        $this->assertContains('ZZ Empty Vault (room)', $dry['locations_created']);
        $this->assertCount(6, $dry['locations_created'], 'Main, Floor 1, Room C3, Shelf 4, two vaults: Room C3 typed two ways is one room');
        $placedIds = array_column($dry['placed'], 'object_id');
        sort($placedIds);
        $this->assertSame([$structured, $sameRoom, $inVault], $placedIds);
        $reasons = array_column($dry['skipped'], 'reason', 'object_id');
        $this->assertSame('free text only: "Delmas"', $reasons[$freeOnly]);
        $this->assertSame('no location recorded', $reasons[$nothing]);

        // Through the command as well: still nothing written.
        $this->assertSame(0, Artisan::call('ahg:storage-migrate-flat-locations'));
        $this->assertStringContainsString('Dry run, nothing changed', Artisan::output());
        $this->assertSame($before, $counts());

        // Applied, through the command.
        $this->assertSame(0, Artisan::call('ahg:storage-migrate-flat-locations', ['--apply' => true]));
        $this->assertStringContainsString('Done: 6 location(s) created', Artisan::output());

        $shelf = $this->movements->currentLocationOf($structured);
        $this->assertSame(
            ['ZZ Main', 'Floor 1', 'Room C3', 'Shelf 4'],
            array_map(fn ($r) => $r->name, $this->locations->getPath($shelf))
        );
        $room = $this->movements->currentLocationOf($sameRoom);
        $this->assertSame('Room C3', $this->locations->getById($room)->name);
        $this->assertSame((int) $this->locations->getById($shelf)->parent_id, $room, 'both boxes share one room');

        $vault = $this->locations->getById($this->movements->currentLocationOf($inVault));
        $this->assertSame(['ZZ Vault', 'room', 'Basement vault', 80.0, 'boxes'],
            [$vault->name, $vault->location_type, $vault->description, (float) $vault->capacity_value, $vault->capacity_unit]);
        $this->assertNotNull($this->locations->findChild(null, 'room', 'zz empty vault'), 'an empty strongroom is still a room');

        $move = DB::table('ahg_storage_movement')->where('subject_id', $structured)->where('subject_type', 'physical_object')->first();
        $this->assertNull($move->from_location_id);
        $this->assertSame(StorageFlatMigrationService::ACTOR, $move->username);
        $this->assertStringStartsWith(StorageFlatMigrationService::NOTE, $move->note);

        // Nothing overwritten, nothing deleted.
        $this->assertSame('1', DB::table('physical_object_extended')->where('physical_object_id', $structured)->value('floor'));
        $this->assertSame(1, DB::table('ahg_physical_object_storage')->where('physical_object_id', $inVault)->count());

        // A re-run reuses what exists and moves nobody twice.
        $after = $counts();
        $again = $service->run(true);
        $this->assertSame($after, $counts());
        $this->assertSame([], $again['locations_created']);
        $this->assertSame([], $again['placed']);

        // Free text only moves when the operator says what it is.
        $free = $service->run(true, ['free_text_type' => 'building']);
        $this->assertSame([$freeOnly], array_column($free['placed'], 'object_id'));
        $this->assertSame('Delmas', $this->locations->getById($this->movements->currentLocationOf($freeOnly))->name);
    }

    public function test_the_migration_refuses_an_unknown_free_text_type_and_changes_nothing(): void
    {
        $this->object('ZZ Mig Free', [], 'Somewhere');
        $before = DB::table('ahg_storage_location')->count();

        $this->assertSame(1, Artisan::call('ahg:storage-migrate-flat-locations', ['--apply' => true, '--free-text-type' => 'not-a-type']));
        $this->assertStringContainsString('Nothing was changed', Artisan::output());
        $this->assertSame($before, DB::table('ahg_storage_location')->count());
    }
}
