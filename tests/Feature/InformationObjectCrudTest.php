<?php

namespace Tests\Feature;

use AhgCore\Models\Actor;
use AhgCore\Models\Event;
use AhgCore\Models\InformationObject;
use Database\Factories\ActorFactory;
use Database\Factories\EventFactory;
use Database\Factories\InformationObjectFactory;
use Database\Factories\TermFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Comprehensive CRUD tests for Information Objects (Archival Descriptions)
 * 
 * Coverage:
 * - Create descriptions (collection, series, file, item)
 * - Hierarchical structure (parent-child)
 * - Read, update, delete
 * - Relations with actors/events
 * - Subject access points
 * - Rights and copyright
 * - Publish/unpublish workflow
 */
class InformationObjectCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected InformationObjectFactory $ioFactory;
    protected ActorFactory $actorFactory;
    protected EventFactory $eventFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ioFactory = new InformationObjectFactory();
        $this->actorFactory = new ActorFactory();
        $this->eventFactory = new EventFactory();
    }

    // ========================================================================
    // CREATE TESTS - By Level of Description
    // ========================================================================

    public function test_can_create_collection(): void
    {
        $io = InformationObjectFactory::new()->collection()->create();

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
        ]);
        $this->assertStringContains('Collection', $io->title);
    }

    public function test_can_create_series(): void
    {
        $io = InformationObjectFactory::new()->series()->create();

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
        ]);
        $this->assertStringContains('Series', $io->title);
    }

    public function test_can_create_file(): void
    {
        $io = InformationObjectFactory::new()->file()->create();

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
        ]);
        $this->assertStringContains('file', $io->title);
    }

    public function test_can_create_item(): void
    {
        $io = InformationObjectFactory::new()->item()->create();

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
        ]);
        $this->assertStringContains('item', $io->title);
    }

    public function test_can_create_with_identifier(): void
    {
        $io = InformationObjectFactory::new()->create([
            'identifier' => 'MS-2024-001',
            'title' => 'Personal Papers',
        ]);

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            'identifier' => 'MS-2024-001',
        ]);
    }

    public function test_can_create_hierarchical_structure(): void
    {
        $collection = InformationObjectFactory::new()->collection()->create([
            'title' => 'Main Collection',
        ]);

        $series = InformationObjectFactory::new()->series()->create([
            'parent_id' => $collection->id,
            'title' => 'Series A',
        ]);

        $file = InformationObjectFactory::new()->file()->create([
            'parent_id' => $series->id,
            'title' => 'File 1',
        ]);

        $item = InformationObjectFactory::new()->item()->create([
            'parent_id' => $file->id,
            'title' => 'Item 1',
        ]);

        $this->assertEquals($collection->id, $series->parent_id);
        $this->assertEquals($series->id, $file->parent_id);
        $this->assertEquals($file->id, $item->parent_id);
    }

    // ========================================================================
    // READ TESTS
    // ========================================================================

    public function test_can_find_io_by_id(): void
    {
        $io = InformationObjectFactory::new()->create();

        $found = InformationObject::find($io->id);

        $this->assertNotNull($found);
        $this->assertEquals($io->id, $found->id);
    }

    public function test_can_get_children_of_parent(): void
    {
        $parent = InformationObjectFactory::new()->series()->create();
        InformationObjectFactory::new()->count(3)->file()->create(['parent_id' => $parent->id]);
        InformationObjectFactory::new()->count(2)->item()->create(['parent_id' => $parent->id]);

        $children = InformationObject::where('parent_id', $parent->id)->get();

        $this->assertCount(5, $children);
    }

    public function test_can_get_root_level_items(): void
    {
        $root1 = InformationObjectFactory::new()->collection()->create();
        $root2 = InformationObjectFactory::new()->collection()->create();
        InformationObjectFactory::new()->series()->create(['parent_id' => $root1->id]);

        $roots = InformationObject::whereNull('parent_id')->get();

        $this->assertCount(2, $roots);
    }

    public function test_can_search_io_by_title(): void
    {
        InformationObjectFactory::new()->create(['title' => 'Photographs 1950-1960']);
        InformationObjectFactory::new()->create(['title' => 'Financial Records 1970']);
        InformationObjectFactory::new()->create(['title' => 'Letters and Photographs']);

        $results = InformationObject::where('title', 'LIKE', '%Photographs%')->get();

        $this->assertCount(2, $results);
    }

    public function test_can_filter_by_level_of_description(): void
    {
        InformationObjectFactory::new()->count(2)->collection()->create();
        InformationObjectFactory::new()->count(3)->series()->create();
        InformationObjectFactory::new()->count(4)->file()->create();

        $collections = InformationObject::where('level_of_description_id', 1)->get();

        $this->assertCount(2, $collections);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_title(): void
    {
        $io = InformationObjectFactory::new()->create([
            'title' => 'Original Title',
        ]);

        $io->update(['title' => 'Updated Title']);

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_can_update_scope_and_content(): void
    {
        $io = InformationObjectFactory::new()->create();

        $io->update(['檔案材料內容' => 'Updated scope and content description.']);

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            '檔案材料內容' => 'Updated scope and content description.',
        ]);
    }

    public function test_can_move_io_to_new_parent(): void
    {
        $parent1 = InformationObjectFactory::new()->collection()->create();
        $parent2 = InformationObjectFactory::new()->collection()->create();
        $child = InformationObjectFactory::new()->series()->create(['parent_id' => $parent1->id]);

        $child->update(['parent_id' => $parent2->id]);

        $this->assertEquals($parent2->id, $child->fresh()->parent_id);
    }

    public function test_can_change_level_of_description(): void
    {
        $io = InformationObjectFactory::new()->file()->create();

        $io->update(['level_of_description_id' => 4]); // Change to item

        $this->assertEquals(4, $io->fresh()->level_of_description_id);
    }

    // ========================================================================
    // DELETE TESTS
    // ========================================================================

    public function test_can_delete_leaf_io(): void
    {
        $io = InformationObjectFactory::new()->item()->create();

        $id = $io->id;
        $io->delete();

        $this->assertDatabaseMissing('information_object', ['id' => $id]);
    }

    public function test_deleting_parent_orphans_children(): void
    {
        $parent = InformationObjectFactory::new()->series()->create();
        $child = InformationObjectFactory::new()->file()->create(['parent_id' => $parent->id]);

        $parent->delete();

        // Child should have null parent_id (orphaned)
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_cascade_delete_related_events(): void
    {
        $io = InformationObjectFactory::new()->create();
        $actor = $this->actorFactory->create();

        $event = $this->eventFactory->withObject($io->id)->withActor($actor->id)->create();

        $io->delete();

        $this->assertDatabaseMissing('event', ['id' => $event->id]);
    }

    // ========================================================================
    // RELATIONS TESTS
    // ========================================================================

    public function test_io_can_have_creator(): void
    {
        $io = InformationObjectFactory::new()->create();
        $creator = $this->actorFactory->corporateBody()->create([
            'authorized_form_of_name' => 'National Archives',
        ]);

        $event = $this->eventFactory->withObject($io->id)->withActor($creator->id)->creation()->create([
            'date' => '1920-01-01',
        ]);

        $this->assertDatabaseHas('event', [
            'id' => $event->id,
            'object_id' => $io->id,
            'actor_id' => $creator->id,
        ]);
    }

    public function test_io_can_have_subject_access_points(): void
    {
        $io = InformationObjectFactory::new()->create();
        $subject = TermFactory::new()->subject()->create(['name' => 'World War I']);

        \AhgCore\Models\ObjectTermRelation::create([
            'object_id' => $io->id,
            'object_class' => 'InformationObject',
            'term_id' => $subject->id,
        ]);

        $this->assertDatabaseHas('object_term_relation', [
            'object_id' => $io->id,
            'term_id' => $subject->id,
        ]);
    }

    // ========================================================================
    // RIGHTS TESTS
    // ========================================================================

    public function test_io_can_have_copyright_status(): void
    {
        $io = InformationObjectFactory::new()->create([
            '版權狀態' => 'copyright',
            'rights_holder' => 'Estate of the Author',
        ]);

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            '版權狀態' => 'copyright',
            'rights_holder' => 'Estate of the Author',
        ]);
    }

    public function test_io_can_be_public_domain(): void
    {
        $io = InformationObjectFactory::new()->create([
            '版權狀態' => 'public',
        ]);

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            '版權狀態' => 'public',
        ]);
    }

    // ========================================================================
    // ACCESS CONDITIONS TESTS
    // ========================================================================

    public function test_io_can_have_access_conditions(): void
    {
        $io = InformationObjectFactory::new()->create([
            '存取條件' => 'Open - Personal data protected',
            '利用條件' => 'Cite as: Collection Name',
        ]);

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            '存取條件' => 'Open - Personal data protected',
        ]);
    }

    // ========================================================================
    // VALIDATION TESTS
    // ========================================================================

    public function test_io_requires_title(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        InformationObject::create([
            // title is required
        ]);
    }

    public function test_io_must_have_valid_level(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        InformationObject::create([
            'title' => 'Test',
            'level_of_description_id' => 999,
        ]);
    }

    // ========================================================================
    // PAGINATION & BROWSE TESTS
    // ========================================================================

    public function test_io_can_be_paginated(): void
    {
        InformationObjectFactory::new()->count(50)->create();

        $paginated = InformationObject::paginate(20);

        $this->assertEquals(20, $paginated->perPage());
        $this->assertEquals(50, $paginated->total());
        $this->assertEquals(3, $paginated->lastPage());
    }

    public function test_can_browse_by_level(): void
    {
        InformationObjectFactory::new()->count(5)->collection()->create();
        InformationObjectFactory::new()->count(10)->series()->create();
        InformationObjectFactory::new()->count(15)->file()->create();

        $browse = InformationObject::whereIn('level_of_description_id', [1, 2])
            ->orderBy('level_of_description_id')
            ->get();

        $this->assertCount(15, $browse);
    }

    // ========================================================================
    // STATISTICS TESTS
    // ========================================================================

    public function test_can_count_io_by_level(): void
    {
        InformationObjectFactory::new()->count(3)->collection()->create();
        InformationObjectFactory::new()->count(7)->series()->create();
        InformationObjectFactory::new()->count(20)->file()->create();
        InformationObjectFactory::new()->count(10)->item()->create();

        $counts = InformationObject::selectRaw('level_of_description_id, COUNT(*) as cnt')
            ->groupBy('level_of_description_id')
            ->pluck('cnt', 'level_of_description_id')
            ->toArray();

        $this->assertEquals(3, $counts[1] ?? 0);
        $this->assertEquals(7, $counts[2] ?? 0);
    }

    public function test_can_count_io_by_repository(): void
    {
        $repo1 = $this->faker->numberBetween(1, 100);
        $repo2 = $this->faker->numberBetween(1, 100);

        InformationObjectFactory::new()->count(5)->create(['repository_id' => $repo1]);
        InformationObjectFactory::new()->count(3)->create(['repository_id' => $repo2]);

        $counts = InformationObject::selectRaw('repository_id, COUNT(*) as cnt')
            ->whereNotNull('repository_id')
            ->groupBy('repository_id')
            ->pluck('cnt', 'repository_id')
            ->toArray();

        $this->assertEquals(5, $counts[$repo1] ?? 0);
        $this->assertEquals(3, $counts[$repo2] ?? 0);
    }

    // ========================================================================
    // FULL HIERARCHY TESTS
    // ========================================================================

    public function test_can_build_full_hierarchy(): void
    {
        // Create a full collection hierarchy
        $collection = InformationObjectFactory::new()->collection()->create(['title' => 'Historical Records']);

        $series1 = InformationObjectFactory::new()->series()->create([
            'parent_id' => $collection->id,
            'title' => 'Administrative Records',
        ]);
        $series2 = InformationObjectFactory::new()->series()->create([
            'parent_id' => $collection->id,
            'title' => 'Personal Papers',
        ]);

        $file1 = InformationObjectFactory::new()->file()->create([
            'parent_id' => $series1->id,
            'title' => 'Budget 1950-1959',
        ]);
        $file2 = InformationObjectFactory::new()->file()->create([
            'parent_id' => $series1->id,
            'title' => 'Budget 1960-1969',
        ]);

        $item1 = InformationObjectFactory::new()->item()->create([
            'parent_id' => $file1->id,
            'title' => 'Budget 1955',
        ]);

        // Verify hierarchy
        $this->assertEquals(2, $collection->children()->count());
        $this->assertEquals(2, $series1->children()->count());
        $this->assertEquals(1, $file1->children()->count());
    }
}
