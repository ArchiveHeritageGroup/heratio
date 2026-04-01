<?php

namespace Tests\Feature;

use AhgCore\Models\Actor;
use AhgCore\Models\ActorI18n;
use AhgCore\Models\Event;
use AhgCore\Models\InformationObject;
use Database\Factories\ActorFactory;
use Database\Factories\EventFactory;
use Database\Factories\InformationObjectFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * CRUD tests for Actors/Authority Records
 *
 * Tests the real AtoM i18n schema: actor + actor_i18n tables.
 * Entity type IDs: 131 = Corporate body, 132 = Person, 133 = Family
 */
class ActorCrudTest extends TestCase
{
    use DatabaseTransactions;

    // ========================================================================
    // CREATE TESTS
    // ========================================================================

    public function test_can_create_person_actor(): void
    {
        $actor = ActorFactory::new()->person()
            ->withI18n(['authorized_form_of_name' => 'John Smith'])
            ->create();

        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            'entity_type_id' => 132,
        ]);
        $this->assertDatabaseHas('actor_i18n', [
            'id' => $actor->id,
            'culture' => 'en',
            'authorized_form_of_name' => 'John Smith',
        ]);
    }

    public function test_can_create_family_actor(): void
    {
        $actor = ActorFactory::new()->family()
            ->withI18n(['authorized_form_of_name' => 'Smith family'])
            ->create();

        $this->assertDatabaseHas('actor', ['id' => $actor->id, 'entity_type_id' => 133]);
        $this->assertDatabaseHas('actor_i18n', [
            'id' => $actor->id,
            'authorized_form_of_name' => 'Smith family',
        ]);
    }

    public function test_can_create_corporate_body_actor(): void
    {
        $actor = ActorFactory::new()->corporateBody()
            ->withI18n(['authorized_form_of_name' => 'National Archives'])
            ->create();

        $this->assertDatabaseHas('actor', ['id' => $actor->id, 'entity_type_id' => 131]);
        $this->assertDatabaseHas('actor_i18n', [
            'id' => $actor->id,
            'authorized_form_of_name' => 'National Archives',
        ]);
    }

    public function test_can_create_actor_with_history(): void
    {
        $actor = ActorFactory::new()->person()
            ->withI18n([
                'authorized_form_of_name' => 'Test Person',
                'history' => 'Famous writer of the 20th century.',
            ])
            ->create();

        $this->assertDatabaseHas('actor_i18n', [
            'id' => $actor->id,
            'history' => 'Famous writer of the 20th century.',
        ]);
    }

    public function test_actor_factory_generates_valid_data(): void
    {
        $actor = ActorFactory::new()->create();

        $this->assertDatabaseHas('actor', ['id' => $actor->id]);
        $this->assertNotNull($actor->getTranslated('authorized_form_of_name'));
        $this->assertContains($actor->entity_type_id, [131, 132, 133]);
    }

    // ========================================================================
    // READ TESTS
    // ========================================================================

    public function test_can_find_actor_by_id(): void
    {
        $actor = ActorFactory::new()->person()->create();

        $found = Actor::find($actor->id);

        $this->assertNotNull($found);
        $this->assertEquals($actor->id, $found->id);
    }

    public function test_can_get_actors_with_i18n(): void
    {
        $actor = ActorFactory::new()->person()
            ->withI18n(['authorized_form_of_name' => 'Lookup Test'])
            ->create();

        $name = $actor->getTranslated('authorized_form_of_name');

        $this->assertEquals('Lookup Test', $name);
    }

    public function test_can_filter_actors_by_type(): void
    {
        ActorFactory::new()->count(3)->person()->create();
        ActorFactory::new()->count(2)->corporateBody()->create();

        $persons = Actor::where('entity_type_id', 132)->get();

        $this->assertGreaterThanOrEqual(3, $persons->count());
    }

    public function test_can_search_actors_by_name(): void
    {
        ActorFactory::new()->withI18n(['authorized_form_of_name' => 'John Unique Smith'])->create();
        ActorFactory::new()->withI18n(['authorized_form_of_name' => 'Jane Unique Doe'])->create();
        ActorFactory::new()->withI18n(['authorized_form_of_name' => 'John Unique Brown'])->create();

        $results = Actor::whereHas('i18n', function ($q) {
            $q->where('authorized_form_of_name', 'LIKE', '%John Unique%');
        })->get();

        $this->assertCount(2, $results);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_actor_name(): void
    {
        $actor = ActorFactory::new()
            ->withI18n(['authorized_form_of_name' => 'Original Name'])
            ->create();

        ActorI18n::where('id', $actor->id)->where('culture', 'en')
            ->update(['authorized_form_of_name' => 'Updated Name']);

        $this->assertDatabaseHas('actor_i18n', [
            'id' => $actor->id,
            'authorized_form_of_name' => 'Updated Name',
        ]);
    }

    public function test_can_update_actor_history(): void
    {
        $actor = ActorFactory::new()->create();

        ActorI18n::where('id', $actor->id)->where('culture', 'en')
            ->update(['history' => 'New biographical information.']);

        $this->assertEquals('New biographical information.', $actor->getTranslated('history'));
    }

    public function test_can_change_actor_type(): void
    {
        $actor = ActorFactory::new()->person()->create();

        $actor->update(['entity_type_id' => 131]); // Change to corporate body

        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            'entity_type_id' => 131,
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

    // ========================================================================
    // RELATIONS TESTS
    // ========================================================================

    public function test_actor_can_have_events(): void
    {
        $actor = ActorFactory::new()->create();
        $io = InformationObjectFactory::new()->create();

        $event = EventFactory::new()
            ->withObject($io->id)
            ->withActor($actor->id)
            ->creation()
            ->create();

        $this->assertDatabaseHas('event', [
            'id' => $event->id,
            'actor_id' => $actor->id,
            'object_id' => $io->id,
        ]);
    }

    // ========================================================================
    // PAGINATION & SORTING TESTS
    // ========================================================================

    public function test_actors_can_be_paginated(): void
    {
        ActorFactory::new()->count(25)->create();

        $paginated = Actor::paginate(10);

        $this->assertEquals(10, $paginated->perPage());
        $this->assertGreaterThanOrEqual(25, $paginated->total());
    }

    public function test_can_count_actors_by_type(): void
    {
        $personsBefore = Actor::where('entity_type_id', 132)->count();
        $familiesBefore = Actor::where('entity_type_id', 133)->count();

        ActorFactory::new()->count(5)->person()->create();
        ActorFactory::new()->count(3)->family()->create();

        $this->assertEquals($personsBefore + 5, Actor::where('entity_type_id', 132)->count());
        $this->assertEquals($familiesBefore + 3, Actor::where('entity_type_id', 133)->count());
    }
}
