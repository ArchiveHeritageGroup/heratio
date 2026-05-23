<?php

/**
 * StrongroomTest - heratio#144 service + capacity-validation tests.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgStorageManage\Services\StrongroomService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wraps every test in a transaction (DatabaseTransactions) so the heratio_test
 * DB is never polluted by leftover rows.
 */
class StrongroomTest extends TestCase
{
    use DatabaseTransactions;

    private StrongroomService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StrongroomService();
    }

    // -----------------------------------------------------------------
    // Service-level
    // -----------------------------------------------------------------

    public function test_create_and_retrieve_by_slug_and_id(): void
    {
        $id = $this->service->create([
            'name'                 => 'Test Room Alpha',
            'capacity_value'       => 100,
            'capacity_unit'        => 'linear_meters',
            'location_description' => 'Basement, north wing',
            'notes'                => 'unit-test row',
        ]);
        $this->assertGreaterThan(0, $id);

        $byId = $this->service->getById($id);
        $this->assertNotNull($byId);
        $this->assertSame('Test Room Alpha', $byId->name);
        $this->assertSame('linear_meters', $byId->capacity_unit);
        $this->assertEquals(100.00, (float) $byId->capacity_value);
        $this->assertSame('test-room-alpha', $byId->slug);

        $bySlug = $this->service->getBySlug('test-room-alpha');
        $this->assertNotNull($bySlug);
        $this->assertEquals($id, $bySlug->id);
    }

    public function test_unique_slug_collision_appends_suffix(): void
    {
        $id1 = $this->service->create(['name' => 'Same Name']);
        $id2 = $this->service->create(['name' => 'Same Name']);
        $id3 = $this->service->create(['name' => 'Same Name']);

        $this->assertSame('same-name', $this->service->getById($id1)->slug);
        $this->assertSame('same-name-2', $this->service->getById($id2)->slug);
        $this->assertSame('same-name-3', $this->service->getById($id3)->slug);
    }

    public function test_create_normalises_invalid_capacity_unit(): void
    {
        $id = $this->service->create([
            'name'          => 'Unit Coercion',
            'capacity_unit' => 'parsecs',
        ]);
        $this->assertSame('linear_meters', $this->service->getById($id)->capacity_unit);
    }

    public function test_create_requires_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->create(['name' => '   ']);
    }

    public function test_update_preserves_slug_and_normalises_unit(): void
    {
        $id = $this->service->create(['name' => 'Renamable']);
        $originalSlug = $this->service->getById($id)->slug;

        $this->service->update($id, [
            'name'           => 'Renamed',
            'capacity_unit'  => 'parsecs',
            'capacity_value' => 50,
        ]);

        $room = $this->service->getById($id);
        $this->assertSame('Renamed', $room->name);
        $this->assertSame($originalSlug, $room->slug, 'slug must stay sticky across renames');
        $this->assertSame('linear_meters', $room->capacity_unit);
        $this->assertEquals(50.0, (float) $room->capacity_value);
    }

    // -----------------------------------------------------------------
    // Assignment + capacity
    // -----------------------------------------------------------------

    public function test_assign_and_retrieve_assignment(): void
    {
        $roomId = $this->service->create([
            'name' => 'Assignment Test', 'capacity_value' => 50, 'capacity_unit' => 'boxes',
        ]);
        $poId = $this->makePhysicalObject();

        $this->service->assign($poId, $roomId, 7.5);
        $a = $this->service->getAssignment($poId);

        $this->assertNotNull($a);
        $this->assertEquals($roomId, $a->strongroom_id);
        $this->assertEquals(7.5, (float) $a->size_units_used);
        $this->assertSame('Assignment Test', $a->strongroom_name);
        $this->assertSame('boxes', $a->capacity_unit);
    }

    public function test_used_and_remaining_capacity(): void
    {
        $roomId = $this->service->create([
            'name' => 'Cap Test', 'capacity_value' => 100, 'capacity_unit' => 'linear_meters',
        ]);
        $a = $this->makePhysicalObject();
        $b = $this->makePhysicalObject();

        $this->service->assign($a, $roomId, 30);
        $this->service->assign($b, $roomId, 25);

        $this->assertEqualsWithDelta(55.0, $this->service->getUsedCapacity($roomId), 0.001);
        $this->assertEqualsWithDelta(45.0, $this->service->getRemainingCapacity($roomId), 0.001);
    }

    public function test_capacity_overflow_detects_overage_and_zero(): void
    {
        $roomId = $this->service->create([
            'name' => 'Overflow', 'capacity_value' => 10, 'capacity_unit' => 'boxes',
        ]);
        $existing = $this->makePhysicalObject();
        $this->service->assign($existing, $roomId, 6);

        $newPo = $this->makePhysicalObject();
        $this->assertEqualsWithDelta(1.0, $this->service->capacityOverflow($roomId, 5, $newPo), 0.001);
        $this->assertSame(0.0, $this->service->capacityOverflow($roomId, 4, $newPo));
        $this->assertSame(0.0, $this->service->capacityOverflow($roomId, 1, $newPo));
    }

    public function test_capacity_overflow_excludes_re_assignment_double_count(): void
    {
        $roomId = $this->service->create([
            'name' => 'Self-Reassign', 'capacity_value' => 10, 'capacity_unit' => 'boxes',
        ]);
        $poId = $this->makePhysicalObject();
        $this->service->assign($poId, $roomId, 8);

        $this->assertSame(0.0, $this->service->capacityOverflow($roomId, 9, $poId));
    }

    public function test_capacity_overflow_returns_null_when_capacity_unset(): void
    {
        $roomId = $this->service->create(['name' => 'No Cap']);
        $this->assertNull($this->service->capacityOverflow($roomId, 1000));
    }

    public function test_unassign_removes_record(): void
    {
        $roomId = $this->service->create(['name' => 'Unassign Test', 'capacity_value' => 10]);
        $poId = $this->makePhysicalObject();

        $this->service->assign($poId, $roomId, 3);
        $this->assertNotNull($this->service->getAssignment($poId));

        $this->service->unassign($poId);
        $this->assertNull($this->service->getAssignment($poId));
    }

    // -----------------------------------------------------------------
    // Delete safety
    // -----------------------------------------------------------------

    public function test_delete_with_occupants_is_blocked(): void
    {
        $roomId = $this->service->create(['name' => 'Has Occupants']);
        $poId = $this->makePhysicalObject();
        $this->service->assign($poId, $roomId, 1);

        try {
            $this->service->delete($roomId);
            $this->fail('Delete should have thrown RuntimeException when occupants exist');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('occupant', strtolower($e->getMessage()));
        }

        $this->assertNotNull($this->service->getById($roomId));
    }

    public function test_delete_empty_room_succeeds(): void
    {
        $roomId = $this->service->create(['name' => 'To Be Deleted']);
        $this->service->delete($roomId);
        $this->assertNull($this->service->getById($roomId));
    }

    // -----------------------------------------------------------------
    // Browse aggregation
    // -----------------------------------------------------------------

    public function test_browse_returns_room_with_utilisation(): void
    {
        $roomId = $this->service->create([
            'name' => 'Browseable', 'capacity_value' => 20, 'capacity_unit' => 'boxes',
        ]);
        $a = $this->makePhysicalObject();
        $b = $this->makePhysicalObject();
        $this->service->assign($a, $roomId, 5);
        $this->service->assign($b, $roomId, 7);

        $page = $this->service->browse('Browseable', 25);
        $rows = collect($page->items())->where('id', $roomId);
        $this->assertNotEmpty($rows);

        $row = $rows->first();
        $this->assertEquals(12.0, (float) $row->used_units);
        $this->assertEquals(2, (int) $row->occupant_count);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * AtoM models physical_object as a subtype of `object` (shared id, with
     * AUTO_INCREMENT on `object.id`). Insert into `object` first, then into
     * `physical_object` with that id.
     */
    private function makePhysicalObject(): int
    {
        $now = now();
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitPhysicalObject',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('physical_object')->insert([
            'id'             => $id,
            'source_culture' => 'en',
        ]);

        return $id;
    }
}
