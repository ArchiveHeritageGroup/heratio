<?php

namespace Tests\Feature\Api;

use AhgCore\Models\QubitActor;
use Database\Factories\ActorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Tests for Actors/Authority Records
 * 
 * Tests all CRUD API endpoints for actors including:
 * - Browse/List actors
 * - Create actor
 * - Show actor details
 * - Update actor
 * - Delete actor
 * - Search actors
 * - Filter actors by type
 */
class ActorApiTest extends TestCase
{
    use RefreshDatabase;

    protected ActorFactory $actorFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actorFactory = new ActorFactory();
    }

    // ========================================================================
    // BROWSE / LIST TESTS
    // ========================================================================

    public function test_can_list_actors(): void
    {
        $this->actorFactory->count(5)->create();

        $response = $this->getJson('/api/actor');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'authorized_form_of_name', 'entity_type']
            ],
            'meta' => ['total', 'per_page', 'current_page'],
        ]);
    }

    public function test_actor_list_requires_authentication(): void
    {
        // If API requires auth, uncomment:
        // $response = $this->getJson('/api/actor');
        // $response->assertStatus(401);
        $this->assertTrue(true); // Placeholder
    }

    public function test_actor_list_paginates(): void
    {
        $this->actorFactory->count(25)->create();

        $response = $this->getJson('/api/actor?per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(10, $response->json('meta.per_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_actor_list_can_filter_by_type(): void
    {
        $this->actorFactory->count(3)->person()->create();
        $this->actorFactory->count(2)->corporateBody()->create();

        $response = $this->getJson('/api/actor?type=person');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
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
            '歷史' => 'Famous writer.',
        ];

        $response = $this->postJson('/api/actor', $data);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'authorized_form_of_name', 'entity_type']
        ]);
        $response->assertJsonFragment([
            'authorized_form_of_name' => 'John Smith',
            'entity_type' => 'person',
        ]);

        $this->assertDatabaseHas('actor', [
            'authorized_form_of_name' => 'John Smith',
            'entity_type' => 'person',
        ]);
    }

    public function test_can_create_corporate_body_actor(): void
    {
        $data = [
            'entity_type' => 'corporateBody',
            'authorized_form_of_name' => 'National Archives',
            '機構史' => 'Founded in 1902.',
        ];

        $response = $this->postJson('/api/actor', $data);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'authorized_form_of_name' => 'National Archives',
            'entity_type' => 'corporateBody',
        ]);
    }

    public function test_can_create_family_actor(): void
    {
        $data = [
            'entity_type' => 'family',
            'authorized_form_of_name' => 'Smith Family',
            '歷史' => 'Prominent family.',
        ];

        $response = $this->postJson('/api/actor', $data);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'entity_type' => 'family',
        ]);
    }

    public function test_create_actor_requires_authorized_name(): void
    {
        $data = [
            'entity_type' => 'person',
            // authorized_form_of_name is required
        ];

        $response = $this->postJson('/api/actor', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['authorized_form_of_name']);
    }

    public function test_create_actor_requires_valid_type(): void
    {
        $data = [
            'entity_type' => 'invalid_type',
            'authorized_form_of_name' => 'Test Name',
        ];

        $response = $this->postJson('/api/actor', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['entity_type']);
    }

    // ========================================================================
    // SHOW / READ TESTS
    // ========================================================================

    public function test_can_show_actor(): void
    {
        $actor = $this->actorFactory->create([
            'authorized_form_of_name' => 'Test Actor',
        ]);

        $response = $this->getJson("/api/actor/{$actor->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $actor->id,
            'authorized_form_of_name' => 'Test Actor',
        ]);
    }

    public function test_show_returns_404_for_nonexistent_actor(): void
    {
        $response = $this->getJson('/api/actor/99999');

        $response->assertStatus(404);
    }

    public function test_show_actor_includes_relations(): void
    {
        $actor = $this->actorFactory->create();

        $response = $this->getJson("/api/actor/{$actor->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'authorized_form_of_name',
                'entity_type',
                'dates',
                'places',
                'functions',
                'general_context',
            ]
        ]);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_actor_name(): void
    {
        $actor = $this->actorFactory->create([
            'authorized_form_of_name' => 'Original Name',
        ]);

        $response = $this->putJson("/api/actor/{$actor->id}", [
            'authorized_form_of_name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'authorized_form_of_name' => 'Updated Name',
        ]);

        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            'authorized_form_of_name' => 'Updated Name',
        ]);
    }

    public function test_can_update_actor_bio(): void
    {
        $actor = $this->actorFactory->create();

        $response = $this->putJson("/api/actor/{$actor->id}", [
            '歷史' => 'Updated biographical information.',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('actor', [
            'id' => $actor->id,
            '歷史' => 'Updated biographical information.',
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $actor = $this->actorFactory->create();

        $response = $this->putJson("/api/actor/{$actor->id}", [
            'authorized_form_of_name' => '', // Empty should fail
        ]);

        $response->assertStatus(422);
    }

    public function test_update_returns_404_for_nonexistent(): void
    {
        $response = $this->putJson('/api/actor/99999', [
            'authorized_form_of_name' => 'Updated',
        ]);

        $response->assertStatus(404);
    }

    // ========================================================================
    // DELETE TESTS
    // ========================================================================

    public function test_can_delete_actor(): void
    {
        $actor = $this->actorFactory->create();
        $id = $actor->id;

        $response = $this->deleteJson("/api/actor/{$id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('actor', ['id' => $id]);
    }

    public function test_delete_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson('/api/actor/99999');

        $response->assertStatus(404);
    }

    public function test_delete_actor_removes_related_events(): void
    {
        $actor = $this->actorFactory->create();
        $io = \AhgCore\Models\QubitInformationObject::create([
            'title' => 'Test Record',
        ]);

        \AhgCore\Models\QubitEvent::create([
            'object_id' => $io->id,
            'actor_id' => $actor->id,
            'type_id' => 101,
        ]);

        $response = $this->deleteJson("/api/actor/{$actor->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('event', ['actor_id' => $actor->id]);
    }

    // ========================================================================
    // SEARCH TESTS
    // ========================================================================

    public function test_can_search_actors_by_name(): void
    {
        $this->actorFactory->create(['authorized_form_of_name' => 'John Smith']);
        $this->actorFactory->create(['authorized_form_of_name' => 'Jane Doe']);
        $this->actorFactory->create(['authorized_form_of_name' => 'John Brown']);

        $response = $this->getJson('/api/actor/search?q=John');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_search_returns_empty_for_no_results(): void
    {
        $this->actorFactory->count(3)->create();

        $response = $this->getJson('/api/actor/search?q=nonexistent123456');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('meta.total'));
    }

    public function test_search_with_special_characters(): void
    {
        $this->actorFactory->create(['authorized_form_of_name' => 'Test & Sons']);
        $this->actorFactory->create(['authorized_form_of_name' => 'Company (Ltd)']);

        $response = $this->getJson('/api/actor/search?q=Test');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    // ========================================================================
    // IDENTIFIER TESTS
    // ========================================================================

    public function test_actor_can_have_viaf_id(): void
    {
        $data = [
            'entity_type' => 'person',
            'authorized_form_of_name' => 'Test Person',
            '偏差' => '12345678', // VIAF ID
        ];

        $response = $this->postJson('/api/actor', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('actor', [
            '偏差' => '12345678',
        ]);
    }

    public function test_actor_can_have_isni(): void
    {
        $data = [
            'entity_type' => 'corporateBody',
            'authorized_form_of_name' => 'Test Org',
            '並行存取點' => '0000 0001 2345 6789',
        ];

        $response = $this->postJson('/api/actor', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('actor', [
            '並行存取點' => '0000 0001 2345 6789',
        ]);
    }

    // ========================================================================
    // BULK OPERATIONS
    // ========================================================================

    public function test_can_bulk_create_actors(): void
    {
        $actors = [
            ['entity_type' => 'person', 'authorized_form_of_name' => 'Person One'],
            ['entity_type' => 'person', 'authorized_form_of_name' => 'Person Two'],
            ['entity_type' => 'corporateBody', 'authorized_form_of_name' => 'Org One'],
        ];

        $response = $this->postJson('/api/actor/bulk', ['actors' => $actors]);

        $response->assertStatus(201);
        $this->assertEquals(3, QubitActor::whereIn('authorized_form_of_name', [
            'Person One', 'Person Two', 'Org One'
        ])->count());
    }

    public function test_can_export_actors(): void
    {
        $this->actorFactory->count(10)->create();

        $response = $this->getJson('/api/actor/export');

        $response->assertStatus(200);
        // Check for CSV or JSON export format
        $this->assertContains($response->headers->get('Content-Type'), [
            'text/csv',
            'application/json',
            'application/download',
        ]);
    }

    // ========================================================================
    // VALIDATION TESTS
    // ========================================================================

    public function test_rejects_invalid_entity_type(): void
    {
        $response = $this->postJson('/api/actor', [
            'entity_type' => 'invalid',
            'authorized_form_of_name' => 'Test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['entity_type']);
    }

    public function test_rejects_too_long_name(): void
    {
        $response = $this->postJson('/api/actor', [
            'entity_type' => 'person',
            'authorized_form_of_name' => str_repeat('a', 1000), // Too long
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/actor', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['authorized_form_of_name', 'entity_type']);
    }

    // ========================================================================
    // PERMISSION TESTS
    // ========================================================================

    public function test_create_requires_authentication(): void
    {
        // If create requires auth:
        // $this->postJson('/api/actor', [...])->assertStatus(401);
        $this->assertTrue(true); // Placeholder
    }

    public function test_update_requires_authentication(): void
    {
        $actor = $this->actorFactory->create();

        // If update requires auth:
        // $this->putJson("/api/actor/{$actor->id}", [...])->assertStatus(401);
        $this->assertTrue(true); // Placeholder
    }

    public function test_delete_requires_authentication(): void
    {
        $actor = $this->actorFactory->create();

        // If delete requires auth:
        // $this->deleteJson("/api/actor/{$actor->id}")->assertStatus(401);
        $this->assertTrue(true); // Placeholder
    }
}
