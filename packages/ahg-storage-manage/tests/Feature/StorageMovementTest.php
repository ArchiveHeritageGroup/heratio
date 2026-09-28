<?php

/**
 * StorageMovementTest - heratio#1514 / atom-ahg-plugins#193.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgStorageManage\Services\StorageLocationService;
use AhgStorageManage\Services\StorageMovementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The log is the record and ahg_physical_object_location is an index over it,
 * so the property that matters is: the current-location index always equals the
 * latest movement per object. Ported from the plugin's
 * testing/storage-movement-check.php, which asserts the same invariants.
 */
class StorageMovementTest extends TestCase
{
    use DatabaseTransactions;

    private StorageMovementService $movements;

    private StorageLocationService $locations;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ahg_storage_movement')) {
            $this->markTestSkipped('storage-movement tables not installed.');
        }

        $this->movements = new StorageMovementService;
        $this->locations = new StorageLocationService;
    }

    /** Same admin factory as StorageLocationTest: group 100 is the admin ACL group. */
    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id,
            'username' => 'storagemove-admin-'.$id,
            'email' => uniqid('storagemove-', true).'@example.test',
            'password_hash' => Hash::make('secret'),
            'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function location(string $name, string $type = 'room', ?int $parent = null): int
    {
        return $this->locations->create(['name' => $name, 'location_type' => $type, 'parent_id' => $parent]);
    }

    /**
     * A physical object with a name to snapshot. Class table inheritance:
     * physical_object.id has no AUTO_INCREMENT, it carries the id minted by the
     * parent `object` row, so that goes in first (as StrongroomTest does).
     */
    private function object(string $name): int
    {
        $now = now();
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitPhysicalObject',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('physical_object')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('physical_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);

        return $id;
    }

    /**
     * The invariant the whole design rests on: for every object in the index,
     * its location equals the to_location_id of its newest movement row.
     */
    private function assertIndexMatchesLatestMovement(): void
    {
        foreach (DB::table('ahg_physical_object_location')->get() as $indexed) {
            $latest = DB::table('ahg_storage_movement')
                ->where('subject_type', StorageMovementService::SUBJECT_OBJECT)
                ->where('subject_id', $indexed->physical_object_id)
                ->orderByDesc('moved_at')->orderByDesc('id')
                ->first();

            $this->assertNotNull($latest, "object {$indexed->physical_object_id} is in the index with no movement row");
            $this->assertSame(
                (int) $indexed->location_id,
                (int) $latest->to_location_id,
                "object {$indexed->physical_object_id}: index and latest movement disagree"
            );
        }
    }

    public function test_first_placement_has_a_null_from_and_lands_in_the_index(): void
    {
        $room = $this->location('ZZ Move Room A');
        $box = $this->object('ZZ Box 1');

        $row = $this->movements->moveObject($box, $room);

        $this->assertNull($row['from_location_id'], 'a first placement must record a null from');
        $this->assertSame($room, $row['to_location_id']);
        $this->assertSame('ZZ Box 1', $row['subject_name']);
        $this->assertSame('ZZ Move Room A', $row['to_location_name']);
        $this->assertSame($room, $this->movements->currentLocationOf($box));
        $this->assertIndexMatchesLatestMovement();
    }

    public function test_removal_from_storage_clears_the_index_and_keeps_the_history(): void
    {
        $room = $this->location('ZZ Move Room B');
        $box = $this->object('ZZ Box 2');

        $this->movements->moveObject($box, $room);
        $row = $this->movements->moveObject($box, null);

        $this->assertSame($room, $row['from_location_id']);
        $this->assertNull($row['to_location_id'], 'removal from storage must record a null to');
        $this->assertNull($this->movements->currentLocationOf($box));
        $this->assertCount(2, $this->movements->historyFor(StorageMovementService::SUBJECT_OBJECT, $box));
    }

    public function test_moving_to_where_it_already_is_gets_refused(): void
    {
        $room = $this->location('ZZ Move Room C');
        $box = $this->object('ZZ Box 3');
        $this->movements->moveObject($box, $room);

        $this->expectException(RuntimeException::class);
        $this->movements->moveObject($box, $room);
    }

    public function test_bulk_move_shares_one_batch_id_and_skips_objects_already_there(): void
    {
        $from = $this->location('ZZ Move Room D');
        $to = $this->location('ZZ Move Room E');
        $one = $this->object('ZZ Box 4');
        $two = $this->object('ZZ Box 5');
        $already = $this->object('ZZ Box 6');

        $this->movements->moveObject($one, $from);
        $this->movements->moveObject($two, $from);
        $this->movements->moveObject($already, $to);

        $written = $this->movements->moveObjects([$one, $two, $already], $to);

        $this->assertCount(2, $written, 'the object already in the destination must not be logged as moved');
        $batchId = $written[0]['batch_id'];
        $this->assertNotNull($batchId);
        $this->assertSame($batchId, $written[1]['batch_id'], 'a bulk move shares one batch id');
        $this->assertCount(2, $this->movements->batch($batchId));
        $this->assertCount(3, $this->movements->objectsIn($to));
        $this->assertIndexMatchesLatestMovement();
    }

    public function test_a_rename_logs_nothing_and_a_reparent_logs_exactly_one_row(): void
    {
        $a = $this->location('ZZ Parent A', 'building');
        $b = $this->location('ZZ Parent B', 'building');
        $room = $this->location('ZZ Moving Room', 'room', $a);

        $before = DB::table('ahg_storage_movement')->count();
        $this->locations->update($room, ['name' => 'ZZ Renamed Room']);
        $this->assertSame($before, DB::table('ahg_storage_movement')->count(), 'a rename is not a move');

        $this->locations->update($room, ['parent_id' => $b]);

        $history = $this->movements->historyFor(StorageMovementService::SUBJECT_LOCATION, $room);
        $this->assertCount(1, $history, 'a reparent logs exactly one row, for the location that moved');
        $this->assertSame($a, (int) $history[0]['from_location_id'], 'from holds the OLD parent');
        $this->assertSame($b, (int) $history[0]['to_location_id'], 'to holds the NEW parent');
    }

    public function test_history_still_reads_after_the_location_is_renamed(): void
    {
        $room = $this->location('ZZ Old Name Room');
        $box = $this->object('ZZ Box 7');
        $this->movements->moveObject($box, $room);

        $this->locations->update($room, ['name' => 'ZZ New Name Room']);

        $history = $this->movements->historyFor(StorageMovementService::SUBJECT_OBJECT, $box);
        $this->assertSame('ZZ Old Name Room', $history[0]['to_location_name'], 'the snapshot keeps the name it had at the time');
    }

    public function test_the_move_form_and_the_recorded_move_go_through_the_routes(): void
    {
        $room = $this->location('ZZ Route Room A');
        $other = $this->location('ZZ Route Room B');
        $box = $this->object('ZZ Route Box');
        $slug = (string) DB::table('slug')->where('object_id', $box)->value('slug');
        if ($slug === '') {
            $slug = 'zz-route-box-'.$box;
            DB::table('slug')->insert(['object_id' => $box, 'slug' => $slug]);
        }

        // Storage layout is staff-only (#1364), so an anonymous visitor gets nothing.
        $this->get("/physicalobject/{$slug}/move")->assertRedirect();

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get("/physicalobject/{$slug}/move")->assertOk()->assertSee('ZZ Route Room A');

        $this->actingAs($admin)->post("/physicalobject/{$slug}/move", [
            'to_location_id' => $room,
            'note' => 'first placement',
        ])->assertRedirect();
        $this->assertSame($room, $this->movements->currentLocationOf($box));

        // Neither a destination nor the removal tick: refused, nothing recorded.
        $before = DB::table('ahg_storage_movement')->count();
        $this->actingAs($admin)->post("/physicalobject/{$slug}/move", ['note' => 'nowhere'])
            ->assertSessionHasErrors('to_location_id');
        $this->assertSame($before, DB::table('ahg_storage_movement')->count());

        $roomSlug = (string) DB::table('ahg_storage_location')->where('id', $room)->value('slug');
        $this->actingAs($admin)->post("/storagelocation/{$roomSlug}/move-objects", [
            'object_ids' => [$box],
            'to_location_id' => $other,
        ])->assertRedirect();

        $this->assertSame($other, $this->movements->currentLocationOf($box));
        $this->assertIndexMatchesLatestMovement();
    }

    public function test_a_location_holding_objects_or_named_in_history_cannot_be_deleted(): void
    {
        $room = $this->location('ZZ Undeletable Room');
        $box = $this->object('ZZ Box 8');
        $this->movements->moveObject($box, $room);

        try {
            $this->locations->delete($room);
            $this->fail('deleting a location that holds objects should be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('still holds objects', $e->getMessage());
        }

        $this->movements->moveObject($box, null);

        try {
            $this->locations->delete($room);
            $this->fail('deleting a location named in the movement history should be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('movement history', $e->getMessage());
        }
    }
}
