<?php

namespace Tests\Feature;

use AhgCore\Models\InformationObject;
use AhgCore\Models\InformationObjectI18n;
use Database\Factories\ActorFactory;
use Database\Factories\EventFactory;
use Database\Factories\InformationObjectFactory;
use Database\Factories\TermFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * CRUD tests for Information Objects (Archival Descriptions)
 *
 * Tests the real AtoM i18n schema: information_object + information_object_i18n tables.
 */
class InformationObjectCrudTest extends TestCase
{
    use DatabaseTransactions;

    // ========================================================================
    // CREATE TESTS
    // ========================================================================

    public function test_can_create_collection(): void
    {
        $io = InformationObjectFactory::new()->collection()
            ->withI18n(['title' => 'Collection: Test Papers'])
            ->create();

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            'level_of_description_id' => 238,
        ]);
        $this->assertDatabaseHas('information_object_i18n', [
            'id' => $io->id,
            'title' => 'Collection: Test Papers',
        ]);
    }

    public function test_can_create_series(): void
    {
        $io = InformationObjectFactory::new()->series()
            ->withI18n(['title' => 'Series: Admin Records'])
            ->create();

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            'level_of_description_id' => 239,
        ]);
    }

    public function test_can_create_with_identifier(): void
    {
        $io = InformationObjectFactory::new()->create([
            'identifier' => 'MS-2024-001',
        ]);

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            'identifier' => 'MS-2024-001',
        ]);
    }

    public function test_can_create_hierarchical_structure(): void
    {
        $collection = InformationObjectFactory::new()->collection()->create();
        $series = InformationObjectFactory::new()->series()->create(['parent_id' => $collection->id]);
        $file = InformationObjectFactory::new()->file()->create(['parent_id' => $series->id]);
        $item = InformationObjectFactory::new()->item()->create(['parent_id' => $file->id]);

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

    public function test_can_get_title_via_i18n(): void
    {
        $io = InformationObjectFactory::new()
            ->withI18n(['title' => 'I18n Title Test'])
            ->create();

        $this->assertEquals('I18n Title Test', $io->getTitle('en'));
    }

    public function test_can_get_children_of_parent(): void
    {
        $parent = InformationObjectFactory::new()->series()->create();
        InformationObjectFactory::new()->count(5)->file()->create(['parent_id' => $parent->id]);

        $children = InformationObject::where('parent_id', $parent->id)->get();

        $this->assertCount(5, $children);
    }

    public function test_can_search_io_by_title(): void
    {
        InformationObjectFactory::new()->withI18n(['title' => 'UniquePhotographs 1950-1960'])->create();
        InformationObjectFactory::new()->withI18n(['title' => 'UniqueFinancial Records 1970'])->create();
        InformationObjectFactory::new()->withI18n(['title' => 'Letters and UniquePhotographs'])->create();

        $results = InformationObject::whereHas('i18n', function ($q) {
            $q->where('title', 'LIKE', '%UniquePhotographs%');
        })->get();

        $this->assertCount(2, $results);
    }

    public function test_can_filter_by_level_of_description(): void
    {
        $before = InformationObject::where('level_of_description_id', 238)->count();
        InformationObjectFactory::new()->count(2)->collection()->create();

        $this->assertEquals($before + 2, InformationObject::where('level_of_description_id', 238)->count());
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_title(): void
    {
        $io = InformationObjectFactory::new()
            ->withI18n(['title' => 'Original Title'])
            ->create();

        InformationObjectI18n::where('id', $io->id)->where('culture', 'en')
            ->update(['title' => 'Updated Title']);

        $this->assertDatabaseHas('information_object_i18n', [
            'id' => $io->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_can_update_scope_and_content(): void
    {
        $io = InformationObjectFactory::new()->create();

        InformationObjectI18n::where('id', $io->id)->where('culture', 'en')
            ->update(['scope_and_content' => 'Updated scope and content description.']);

        $this->assertEquals(
            'Updated scope and content description.',
            $io->getTranslated('scope_and_content')
        );
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

        $io->update(['level_of_description_id' => 242]); // Item

        $this->assertEquals(242, $io->fresh()->level_of_description_id);
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

    // ========================================================================
    // RELATIONS TESTS
    // ========================================================================

    public function test_io_can_have_creator(): void
    {
        $io = InformationObjectFactory::new()->create();
        $creator = ActorFactory::new()->corporateBody()
            ->withI18n(['authorized_form_of_name' => 'National Archives'])
            ->create();

        $event = EventFactory::new()
            ->withObject($io->id)
            ->withActor($creator->id)
            ->creation()
            ->create();

        $this->assertDatabaseHas('event', [
            'id' => $event->id,
            'object_id' => $io->id,
            'actor_id' => $creator->id,
        ]);
    }

    // ========================================================================
    // PAGINATION TESTS
    // ========================================================================

    public function test_io_can_be_paginated(): void
    {
        InformationObjectFactory::new()->count(25)->create();

        $paginated = InformationObject::paginate(10);

        $this->assertEquals(10, $paginated->perPage());
        $this->assertGreaterThanOrEqual(25, $paginated->total());
    }

    // ========================================================================
    // FULL HIERARCHY TESTS
    // ========================================================================

    public function test_can_build_full_hierarchy(): void
    {
        $collection = InformationObjectFactory::new()->collection()->create();

        $series1 = InformationObjectFactory::new()->series()->create(['parent_id' => $collection->id]);
        $series2 = InformationObjectFactory::new()->series()->create(['parent_id' => $collection->id]);

        $file1 = InformationObjectFactory::new()->file()->create(['parent_id' => $series1->id]);
        $file2 = InformationObjectFactory::new()->file()->create(['parent_id' => $series1->id]);

        InformationObjectFactory::new()->item()->create(['parent_id' => $file1->id]);

        $this->assertEquals(2, $collection->children()->count());
        $this->assertEquals(2, $series1->children()->count());
        $this->assertEquals(1, $file1->children()->count());
    }
}
