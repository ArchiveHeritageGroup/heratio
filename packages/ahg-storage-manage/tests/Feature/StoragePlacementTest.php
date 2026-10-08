<?php

/**
 * StoragePlacementTest - heratio#1545 (parity with atom-ahg-plugins v3.116.x).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgStorageManage\Services\StorageLocationService;
use AhgStorageManage\Services\StorageMovementService;
use AhgStorageManage\Services\StoragePlacementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The physical storage form as the way into the location tree: find
 * case-insensitively within parent and type, create only for editors and
 * administrators, place in the innermost filled level through the movement log,
 * and do nothing on a resave with the same places. Port of the plugin's
 * testing/storage-placement-check.php.
 */
class StoragePlacementTest extends TestCase
{
    use DatabaseTransactions;

    private StorageLocationService $locations;

    private StorageMovementService $movements;

    private StoragePlacementService $placement;

    protected function setUp(): void
    {
        parent::setUp();

        if (! StoragePlacementService::available() || ! Schema::hasTable('physical_object_extended')) {
            $this->markTestSkipped('storage tables not installed.');
        }

        AclService::forgetUser();
        $this->movements = new StorageMovementService;
        $this->locations = new StorageLocationService;
        $this->placement = new StoragePlacementService;
    }

    private function user(int $group): User
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id,
            'username' => 'storageplace-'.$id,
            'email' => uniqid('storageplace-', true).'@example.test',
            'password_hash' => Hash::make('secret'),
            'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function object(string $name): int
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitPhysicalObject', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('physical_object')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('physical_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'zz-place-'.$id]);

        return $id;
    }

    private function names(int $locationId): array
    {
        return array_map(fn ($r) => $r->name, $this->locations->getPath($locationId));
    }

    private function moves(int $objectId): int
    {
        return DB::table('ahg_storage_movement')
            ->where('subject_type', StorageMovementService::SUBJECT_OBJECT)
            ->where('subject_id', $objectId)->count();
    }

    public function test_levels_resolve_case_insensitively_within_parent_and_type_skipping_empty_levels(): void
    {
        $main = $this->locations->create(['name' => 'ZZ Main Building', 'location_type' => 'building']);
        $room = $this->locations->create(['name' => 'Room C3', 'location_type' => 'room', 'parent_id' => $main]);
        // Same name, other parent: not the one meant.
        $annex = $this->locations->create(['name' => 'ZZ Annex', 'location_type' => 'building']);
        $this->locations->create(['name' => 'Room C3', 'location_type' => 'room', 'parent_id' => $annex]);
        // Same name and parent, other type: not the one meant either.
        $this->locations->create(['name' => 'Room C3', 'location_type' => 'floor', 'parent_id' => $main]);

        $this->assertSame($room, $this->placement->resolve(['building' => ' zz main building', 'room' => 'ROOM c3'], false));
        $this->assertSame($room, $this->placement->resolve(['building' => 'ZZ Main Building', 'floor' => '', 'room' => 'c3'], false), 'a bare code gets its level in front');
        $this->assertNull($this->placement->resolve([], false), 'no level filled, no place');
        $this->assertFalse($this->placement->resolve(['building' => 'ZZ Main Building', 'room' => 'Room Q9'], false), 'missing and may not create');

        $before = DB::table('ahg_storage_location')->count();
        $made = $this->placement->resolve(['building' => 'ZZ Main Building', 'room' => 'Room Q9', 'shelf' => '2'], true);
        $this->assertSame(['ZZ Main Building', 'Room Q9', 'Shelf 2'], $this->names($made));
        $this->assertSame($before + 2, DB::table('ahg_storage_location')->count(), 'only the missing levels are created');
    }

    public function test_save_places_in_the_innermost_level_and_a_resave_with_the_same_places_does_nothing(): void
    {
        $box = $this->object('ZZ Placement Box');
        $levels = ['building' => 'ZZ Depot', 'floor' => '2', 'room' => 'Strongroom B', 'shelf' => '7'];

        $this->assertSame(StoragePlacementService::MOVED, $this->placement->saveFromForm($box, $levels, true));
        $shelf = $this->movements->currentLocationOf($box);
        $this->assertSame(['ZZ Depot', 'Floor 2', 'Strongroom B', 'Shelf 7'], $this->names($shelf));
        $this->assertSame(1, $this->moves($box));
        $this->assertSame('Physical storage form', DB::table('ahg_storage_movement')->where('subject_id', $box)->value('note'));

        // Same places, typed differently: no move.
        $this->assertSame(StoragePlacementService::UNCHANGED, $this->placement->saveFromForm($box, ['building' => 'zz depot', 'floor' => 'Floor 2', 'room' => 'STRONGROOM B', 'shelf' => '7'], true));
        $this->assertSame(1, $this->moves($box));

        // Moved into a carton below its shelf elsewhere: a resave of the form
        // (which shows the shelf levels) does not pull it back up.
        $carton = $this->locations->create(['name' => 'ZZ Carton', 'location_type' => 'container', 'parent_id' => $shelf]);
        $this->movements->moveObject($box, $carton);
        $this->assertSame(['building' => 'ZZ Depot', 'floor' => 'Floor 2', 'room' => 'Strongroom B', 'shelf' => 'Shelf 7'], $this->placement->levelsFor($box));
        $this->assertSame(StoragePlacementService::UNCHANGED, $this->placement->saveFromForm($box, $this->placement->levelsFor($box), true));
        $this->assertSame($carton, $this->movements->currentLocationOf($box));

        // A different shelf: one logged move from the carton.
        $this->assertSame(StoragePlacementService::MOVED, $this->placement->saveFromForm($box, ['shelf' => '8'] + $levels, true));
        $latest = DB::table('ahg_storage_movement')->where('subject_id', $box)->orderByDesc('id')->first();
        $this->assertSame($carton, (int) $latest->from_location_id);
        $this->assertSame(['ZZ Depot', 'Floor 2', 'Strongroom B', 'Shelf 8'], $this->names((int) $latest->to_location_id));

        // Clearing every level leaves the box where it is.
        $this->assertSame(StoragePlacementService::UNCHANGED, $this->placement->saveFromForm($box, [], true));
        $this->assertSame((int) $latest->to_location_id, $this->movements->currentLocationOf($box));
    }

    public function test_only_editors_and_administrators_may_add_places(): void
    {
        $this->assertTrue(StoragePlacementService::mayCreate($this->user(100)));
        $this->assertTrue(StoragePlacementService::mayCreate($this->user(101)));
        $this->assertFalse(StoragePlacementService::mayCreate($this->user(102)));
        $this->assertFalse(StoragePlacementService::mayCreate($this->user(103)));

        $box = $this->object('ZZ Contributor Box');
        $before = DB::table('ahg_storage_location')->count();
        $this->assertSame(StoragePlacementService::NOT_ALLOWED, $this->placement->saveFromForm($box, ['building' => 'ZZ Nowhere Yet'], false));
        $this->assertSame($before, DB::table('ahg_storage_location')->count(), 'nothing created');
        $this->assertNull($this->movements->currentLocationOf($box), 'and the box not moved');

        // Existing places are open to everybody who can edit the box.
        $existing = $this->locations->create(['name' => 'ZZ Somewhere', 'location_type' => 'building']);
        $this->assertSame(StoragePlacementService::MOVED, $this->placement->saveFromForm($box, ['building' => 'zz somewhere'], false));
        $this->assertSame($existing, $this->movements->currentLocationOf($box));
    }

    public function test_the_box_form_places_the_box_writes_the_flat_fields_and_shows_the_path(): void
    {
        $admin = $this->user(100);

        $this->actingAs($admin)->get('/physicalobject/add')->assertOk()
            ->assertSee('ahg-loc-building', false)
            ->assertDontSee('strongroom_action', false);

        $this->actingAs($admin)->post('/physicalobject/add', [
            'name' => 'ZZ Form Box',
            'building' => 'ZZ Form Building',
            'room' => '12',
            'shelf' => 'A',
        ])->assertRedirect();

        $box = (int) DB::table('physical_object_i18n')->where('name', 'ZZ Form Box')->value('id');
        $location = $this->movements->currentLocationOf($box);
        $this->assertNotNull($location);
        $this->assertSame(['ZZ Form Building', 'Room 12', 'A'], $this->names($location), 'empty floor skipped; a word is kept as typed');
        $this->assertSame('12', DB::table('physical_object_extended')->where('physical_object_id', $box)->value('room'), 'the flat columns are still written');

        $slug = (string) DB::table('slug')->where('object_id', $box)->value('slug');

        // The edit form shows the levels from the tree; a resave moves nothing.
        $this->actingAs($admin)->get("/physicalobject/{$slug}/edit")->assertOk()->assertSee('value="Room 12"', false);
        $this->actingAs($admin)->post("/physicalobject/{$slug}/edit", [
            'name' => 'ZZ Form Box', 'building' => 'ZZ Form Building', 'room' => 'Room 12', 'shelf' => 'A',
        ])->assertRedirect();
        $this->assertSame(1, $this->moves($box));

        $this->actingAs($admin)->get("/physicalobject/{$slug}")->assertOk()
            ->assertSee('Latest moves')
            ->assertSee(route('storagelocation.show', $this->locations->getById($location)->slug), false);

        // The list shows the tree path.
        $this->actingAs($admin)->get('/physicalobject/browse?subquery=ZZ+Form')->assertOk()
            ->assertSee(route('storagelocation.show', $this->locations->getById($location)->slug), false);
    }

    public function test_an_editor_adds_places_from_the_form_and_a_refused_place_is_reported(): void
    {
        $editor = $this->user(101);
        $box = $this->object('ZZ Editor Box');
        $slug = (string) DB::table('slug')->where('object_id', $box)->value('slug');

        $this->actingAs($editor)->post("/physicalobject/{$slug}/edit", [
            'name' => 'ZZ Editor Box', 'building' => 'ZZ Editor Building',
        ])->assertRedirect()->assertSessionMissing('warning');
        $this->assertNotNull($this->movements->currentLocationOf($box));

        // A non-creator reaching the save is told, and nothing changes.
        $result = $this->placement->saveFromForm($box, ['building' => 'ZZ Unknown Building'], StoragePlacementService::mayCreate($this->user(102)));
        $this->assertSame(StoragePlacementService::NOT_ALLOWED, $result);
    }
}
