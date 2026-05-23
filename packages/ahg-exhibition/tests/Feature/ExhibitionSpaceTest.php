<?php

/**
 * ExhibitionSpaceTest - heratio#146 service tests.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgExhibition\Services\ExhibitionSpaceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExhibitionSpaceTest extends TestCase
{
    use DatabaseTransactions;

    private ExhibitionSpaceService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ExhibitionSpaceService();
    }

    public function test_create_with_minimal_data_succeeds(): void
    {
        $id = $this->svc->create(['name' => 'Main Gallery']);
        $space = $this->svc->getById($id);
        $this->assertNotNull($space);
        $this->assertSame('Main Gallery', $space->name);
        $this->assertSame('main-gallery', $space->slug);
        $this->assertSame('gallery', $space->space_type);
        $this->assertSame('linear_wall_meters', $space->capacity_unit);
    }

    public function test_unique_slug_collision_appends_suffix(): void
    {
        $a = $this->svc->create(['name' => 'Hall A']);
        $b = $this->svc->create(['name' => 'Hall A']);
        $this->assertSame('hall-a', $this->svc->getById($a)->slug);
        $this->assertSame('hall-a-2', $this->svc->getById($b)->slug);
    }

    public function test_create_normalises_invalid_space_type(): void
    {
        $id = $this->svc->create(['name' => 'Test', 'space_type' => 'starship']);
        $this->assertSame('gallery', $this->svc->getById($id)->space_type);
    }

    public function test_create_normalises_invalid_capacity_unit(): void
    {
        $id = $this->svc->create(['name' => 'Test', 'capacity_unit' => 'parsecs']);
        $this->assertSame('linear_wall_meters', $this->svc->getById($id)->capacity_unit);
    }

    public function test_create_requires_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->create(['name' => '  ']);
    }

    public function test_place_placement_succeeds_within_capacity(): void
    {
        $spaceId = $this->svc->create(['name' => 'Cap', 'capacity_value' => 100, 'capacity_unit' => 'linear_wall_meters']);
        $ioId = $this->makeInformationObject();

        $pid = $this->svc->placePlacement([
            'exhibition_space_id' => $spaceId,
            'information_object_id' => $ioId,
            'size_units_used' => 30,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-12-31',
        ]);
        $this->assertGreaterThan(0, $pid);
    }

    public function test_placement_overflow_rejects_during_overlap(): void
    {
        $spaceId = $this->svc->create(['name' => 'Cap', 'capacity_value' => 10, 'capacity_unit' => 'plinths']);
        $ioA = $this->makeInformationObject();
        $ioB = $this->makeInformationObject();

        // First placement uses 8 of 10
        $this->svc->placePlacement([
            'exhibition_space_id' => $spaceId, 'information_object_id' => $ioA,
            'size_units_used' => 8, 'starts_at' => '2026-06-01', 'ends_at' => '2026-08-31',
        ]);

        // Second placement of 5 OVERLAPS — should fail (8+5=13 > 10)
        $this->expectException(\RuntimeException::class);
        $this->svc->placePlacement([
            'exhibition_space_id' => $spaceId, 'information_object_id' => $ioB,
            'size_units_used' => 5, 'starts_at' => '2026-07-15', 'ends_at' => '2026-09-30',
        ]);
    }

    public function test_placement_succeeds_when_dates_do_not_overlap(): void
    {
        $spaceId = $this->svc->create(['name' => 'Cap', 'capacity_value' => 10, 'capacity_unit' => 'plinths']);
        $ioA = $this->makeInformationObject();
        $ioB = $this->makeInformationObject();

        // First — Jun-Aug
        $this->svc->placePlacement([
            'exhibition_space_id' => $spaceId, 'information_object_id' => $ioA,
            'size_units_used' => 8, 'starts_at' => '2026-06-01', 'ends_at' => '2026-08-31',
        ]);
        // Second — Sep-Nov, no overlap, even 9 units should fit
        $pid = $this->svc->placePlacement([
            'exhibition_space_id' => $spaceId, 'information_object_id' => $ioB,
            'size_units_used' => 9, 'starts_at' => '2026-09-01', 'ends_at' => '2026-11-30',
        ]);
        $this->assertGreaterThan(0, $pid);
    }

    public function test_delete_blocked_when_placements_exist(): void
    {
        $spaceId = $this->svc->create(['name' => 'Has Plac', 'capacity_value' => 50]);
        $ioId = $this->makeInformationObject();
        $this->svc->placePlacement([
            'exhibition_space_id' => $spaceId, 'information_object_id' => $ioId,
            'size_units_used' => 5, 'starts_at' => '2026-06-01', 'ends_at' => '2026-12-31',
        ]);
        $this->expectException(\RuntimeException::class);
        $this->svc->delete($spaceId);
    }

    public function test_delete_empty_space_succeeds(): void
    {
        $spaceId = $this->svc->create(['name' => 'Empty']);
        $this->svc->delete($spaceId);
        $this->assertNull($this->svc->getById($spaceId));
    }

    public function test_browse_shows_current_utilisation(): void
    {
        $spaceId = $this->svc->create(['name' => 'BrowseTest', 'capacity_value' => 20]);
        $ioA = $this->makeInformationObject();
        $ioB = $this->makeInformationObject();
        $today = date('Y-m-d');
        $this->svc->placePlacement([
            'exhibition_space_id' => $spaceId, 'information_object_id' => $ioA,
            'size_units_used' => 5, 'starts_at' => $today, 'ends_at' => $today,
        ]);
        $this->svc->placePlacement([
            'exhibition_space_id' => $spaceId, 'information_object_id' => $ioB,
            'size_units_used' => 7, 'starts_at' => $today, 'ends_at' => $today,
        ]);

        $page = $this->svc->browse('BrowseTest', 25);
        $rows = collect($page->items())->where('id', $spaceId);
        $this->assertNotEmpty($rows);
        $row = $rows->first();
        $this->assertEquals(12.0, (float) $row->used_units_today);
        $this->assertEquals(2, (int) $row->current_placements);
    }

    private function makeInformationObject(): int
    {
        $now = now();
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('information_object')->insert([
            'id' => $id, 'source_culture' => 'en',
        ]);
        return $id;
    }
}
