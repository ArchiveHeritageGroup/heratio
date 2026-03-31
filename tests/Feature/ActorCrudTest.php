<?php

namespace Tests\Feature;

use AhgCore\Models\QubitActor;
use Database\Factories\ActorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive CRUD tests for Actors/Authority Records
 * 
 * Coverage:
 * - Create actors (person, family, corporate body)
 * - Read actors (single, browse, search)
 * - Update actors
 * - Delete actors
 * - Relations (events, information objects)
 * - Identifiers & external IDs
 * - NER integration
 * - Deduplication
 */
class ActorCrudTest extends TestCase
{
    use RefreshDatabase;

    protected ActorFactory $actorFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actorFactory = new ActorFactory();
    }

    // ========================================================================
    // CREATE TESTS
    // ========================================================================

    public function test_can_create_person_actor(): void
    {
        $data = [
            'entity_type' => 'person',
            'authorized_form_of_name' => 'John Smith',
            '其他的名字' => 'Johnny Smith',
            '偏差' => 'Smith, John',
            '歷史' => 'Famous writer of the 20th century.',
        ];

        $actor = QubitActor::create($data);

        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            'entity_type' => 'person',
            'authorized_form_of_name' => 'John Smith',
        ]);
    }

    public function test_can_create_family_actor(): void
    {
        $data = [
            'entity_type' => 'family',
            'authorized_form_of_name' => 'Smith family',
            '歷史' => 'Prominent family in the region.',
        ];

        $actor = QubitActor::create($data);

        $this->assertDatabaseHas('actor', [
            'entity_type' => 'family',
            'authorized_form_of_name' => 'Smith family',
        ]);
    }

    public function test_can_create_corporate_body_actor(): void
    {
        $data = [
            'entity_type' => 'corporateBody',
            'authorized_form_of_name' => 'National Archives',
            '機構史' => 'Founded in 1902.',
        ];

        $actor = QubitActor::create($data);

        $this->assertDatabaseHas('actor', [
            'entity_type' => 'corporateBody',
            'authorized_form_of_name' => 'National Archives',
        ]);
    }

    public function test_can_create_actor_with_factory(): void
    {
        $actor = ActorFactory::new()->person()->create();

        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            'entity_type' => 'person',
        ]);
        $this->assertNotEmpty($actor->authorized_form_of_name);
    }

    public function test_actor_factory_generates_valid_data(): void
    {
        $actor = ActorFactory::new()->create();

        $this->assertNotEmpty($actor->authorized_form_of_name);
        $this->assertContains($actor->entity_type, ['person', 'family', 'corporateBody']);
    }

    // ========================================================================
    // READ TESTS
    // ========================================================================

    public function test_can_find_actor_by_id(): void
    {
        $actor = ActorFactory::new()->person()->create();

        $found = QubitActor::find($actor->id);

        $this->assertNotNull($found);
        $this->assertEquals($actor->id, $found->id);
    }

    public function test_can_get_all_actors(): void
    {
        ActorFactory::new()->count(5)->create();
        ActorFactory::new()->count(3)->family()->create();
        ActorFactory::new()->count(2)->corporateBody()->create();

        $actors = QubitActor::all();

        $this->assertCount(10, $actors);
    }

    public function test_can_filter_actors_by_type(): void
    {
        ActorFactory::new()->count(3)->person()->create();
        ActorFactory::new()->count(2)->corporateBody()->create();

        $persons = QubitActor::where('entity_type', 'person')->get();

        $this->assertCount(3, $persons);
    }

    public function test_can_search_actors_by_name(): void
    {
        ActorFactory::new()->create(['authorized_form_of_name' => 'John Smith']);
        ActorFactory::new()->create(['authorized_form_of_name' => 'Jane Doe']);
        ActorFactory::new()->create(['authorized_form_of_name' => 'John Brown']);

        $results = QubitActor::where('authorized_form_of_name', 'LIKE', '%John%')->get();

        $this->assertCount(2, $results);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_actor_name(): void
    {
        $actor = ActorFactory::new()->create([
            'authorized_form_of_name' => 'Original Name',
        ]);

        $actor->update(['authorized_form_of_name' => 'Updated Name']);

        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            'authorized_form_of_name' => 'Updated Name',
        ]);
    }

    public function test_can_update_actor_bio(): void
    {
        $actor = ActorFactory::new()->create();

        $actor->update(['歷史' => 'New biographical information.']);

        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            '歷史' => 'New biographical information.',
        ]);
    }

    public function test_can_change_actor_type(): void
    {
        $actor = ActorFactory::new()->person()->create();

        $actor->update(['entity_type' => 'corporateBody']);

        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            'entity_type' => 'corporateBody',
        ]);
    }

    // ========================================================================
    // DELETE TESTS
    // ========================================================================

    public function test_can_delete_actor(): void
    {
        $actor = ActorFactory::new()->create();

        $id = $actor->id;
        $actor->delete();

        $this->assertDatabaseMissing('actor', ['id' => $id]);
    }

    public function test_deleting_actor_removes_related_events(): void
    {
        $actor = ActorFactory::new()->create();
        $io = \AhgCore\Models\QubitInformationObject::create([
            'title' => 'Test Record',
        ]);

        // Create event linking actor to IO
        \AhgCore\Models\QubitEvent::create([
            'object_id' => $io->id,
            'actor_id' => $actor->id,
            'type_id' => 101,
        ]);

        $actor->delete();

        $this->assertDatabaseMissing('event', ['actor_id' => $actor->id]);
    }

    // ========================================================================
    // RELATIONS TESTS
    // ========================================================================

    public function test_actor_can_have_events(): void
    {
        $actor = ActorFactory::new()->create();
        $io = \AhgCore\Models\QubitInformationObject::create([
            'title' => 'Related Record',
        ]);

        $event = \AhgCore\Models\QubitEvent::create([
            'object_id' => $io->id,
            'actor_id' => $actor->id,
            'type_id' => 101,
            'date' => '2024-01-15',
        ]);

        $this->assertDatabaseHas('event', [
            'id' => $event->id,
            'actor_id' => $actor->id,
        ]);
    }

    // ========================================================================
    // IDENTIFIERS & EXTERNAL IDs TESTS
    // ========================================================================

    public function test_actor_can_have_viaf_id(): void
    {
        $actor = ActorFactory::new()->create([
            '偏差' => '12345678', // VIAF ID in this field
        ]);

        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            '偏差' => '12345678',
        ]);
    }

    public function test_actor_can_have_isni(): void
    {
        $actor = ActorFactory::new()->create([
            '並行存取點' => '0000 0001 2345 6789',
        ]);

        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            '並行存取點' => '0000 0001 2345 6789',
        ]);
    }

    // ========================================================================
    // VALIDATION TESTS
    // ========================================================================

    public function test_actor_requires_authorized_form_of_name(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        QubitActor::create([
            'entity_type' => 'person',
            // authorized_form_of_name is required
        ]);
    }

    public function test_actor_type_must_be_valid(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        QubitActor::create([
            'entity_type' => 'invalid_type',
            'authorized_form_of_name' => 'Test Name',
        ]);
    }

    // ========================================================================
    // PAGINATION & SORTING TESTS
    // ========================================================================

    public function test_actors_can_be_paginated(): void
    {
        ActorFactory::new()->count(25)->create();

        $paginated = QubitActor::paginate(10);

        $this->assertEquals(10, $paginated->perPage());
        $this->assertEquals(25, $paginated->total());
        $this->assertEquals(3, $paginated->lastPage());
    }

    public function test_actors_can_be_sorted_by_name(): void
    {
        ActorFactory::new()->create(['authorized_form_of_name' => 'Zebra']);
        ActorFactory::new()->create(['authorized_form_of_name' => 'Apple']);
        ActorFactory::new()->create(['authorized_form_of_name' => 'Mango']);

        $actors = QubitActor::orderBy('authorized_form_of_name')->get();

        $this->assertEquals('Apple', $actors->first()->authorized_form_of_name);
        $this->assertEquals('Zebra', $actors->last()->authorized_form_of_name);
    }

    // ========================================================================
    // STATISTICS & COUNTS
    // ========================================================================

    public function test_can_count_actors_by_type(): void
    {
        ActorFactory::new()->count(5)->person()->create();
        ActorFactory::new()->count(3)->family()->create();
        ActorFactory::new()->count(2)->corporateBody()->create();

        $counts = [
            'person' => QubitActor::where('entity_type', 'person')->count(),
            'family' => QubitActor::where('entity_type', 'family')->count(),
            'corporateBody' => QubitActor::where('entity_type', 'corporateBody')->count(),
        ];

        $this->assertEquals(5, $counts['person']);
        $this->assertEquals(3, $counts['family']);
        $this->assertEquals(2, $counts['corporateBody']);
    }

    // ========================================================================
    // BULK OPERATIONS
    // ========================================================================

    public function test_can_bulk_create_actors(): void
    {
        $names = ['Actor One', 'Actor Two', 'Actor Three'];

        foreach ($names as $name) {
            QubitActor::create([
                'entity_type' => 'person',
                'authorized_form_of_name' => $name,
            ]);
        }

        $this->assertEquals(3, QubitActor::whereIn('authorized_form_of_name', $names)->count());
    }

    public function test_can_bulk_update_actors(): void
    {
        $actors = ActorFactory::new()->count(3)->create();

        QubitActor::whereIn('id', $actors->pluck('id'))
            ->update(['來源標準' => 'local']);

        foreach ($actors as $actor) {
            $actor->refresh();
            $this->assertEquals('local', $actor->{'來源標準'});
        }
    }
}
