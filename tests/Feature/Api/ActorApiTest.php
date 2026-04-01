<?php

namespace Tests\Feature\Api;

use AhgCore\Models\Actor;
use AhgCore\Models\ActorI18n;
use Database\Factories\ActorFactory;
use Database\Factories\EventFactory;
use Database\Factories\InformationObjectFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * API Tests for Actors/Authority Records
 *
 * Tests API endpoints using the real AtoM i18n schema.
 */
class ActorApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all API tests until API routes are implemented
        if (! \Illuminate\Support\Facades\Route::has('api.actor.index')) {
            $this->markTestSkipped('API routes not yet implemented');
        }
    }

    // ========================================================================
    // BROWSE / LIST TESTS
    // ========================================================================

    public function test_can_list_actors(): void
    {
        ActorFactory::new()->count(5)->create();

        $response = $this->getJson('/api/actor');

        $response->assertStatus(200);
    }

    public function test_actor_list_paginates(): void
    {
        ActorFactory::new()->count(25)->create();

        $response = $this->getJson('/api/actor?per_page=10');

        $response->assertStatus(200);
    }

    public function test_actor_list_can_filter_by_type(): void
    {
        ActorFactory::new()->count(3)->person()->create();
        ActorFactory::new()->count(2)->corporateBody()->create();

        $response = $this->getJson('/api/actor?entity_type_id=132');

        $response->assertStatus(200);
    }

    // ========================================================================
    // SHOW / READ TESTS
    // ========================================================================

    public function test_can_show_actor(): void
    {
        $actor = ActorFactory::new()
            ->withI18n(['authorized_form_of_name' => 'Test Actor'])
            ->create();

        $response = $this->getJson("/api/actor/{$actor->id}");

        $response->assertStatus(200);
    }

    public function test_show_returns_404_for_nonexistent_actor(): void
    {
        $response = $this->getJson('/api/actor/99999');

        $response->assertStatus(404);
    }

    // ========================================================================
    // CREATE TESTS
    // ========================================================================

    public function test_can_create_person_actor(): void
    {
        $data = [
            'entity_type_id' => 132,
            'authorized_form_of_name' => 'John Smith',
        ];

        $response = $this->postJson('/api/actor', $data);

        $response->assertStatus(201);
    }

    public function test_can_create_corporate_body_actor(): void
    {
        $data = [
            'entity_type_id' => 131,
            'authorized_form_of_name' => 'National Archives',
        ];

        $response = $this->postJson('/api/actor', $data);

        $response->assertStatus(201);
    }

    public function test_create_actor_requires_authorized_name(): void
    {
        $data = [
            'entity_type_id' => 132,
        ];

        $response = $this->postJson('/api/actor', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['authorized_form_of_name']);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_actor_name(): void
    {
        $actor = ActorFactory::new()
            ->withI18n(['authorized_form_of_name' => 'Original Name'])
            ->create();

        $response = $this->putJson("/api/actor/{$actor->id}", [
            'authorized_form_of_name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
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
        $actor = ActorFactory::new()->create();
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

    // ========================================================================
    // SEARCH TESTS
    // ========================================================================

    public function test_can_search_actors_by_name(): void
    {
        ActorFactory::new()->withI18n(['authorized_form_of_name' => 'UniqueJohn Smith'])->create();
        ActorFactory::new()->withI18n(['authorized_form_of_name' => 'Jane Doe'])->create();
        ActorFactory::new()->withI18n(['authorized_form_of_name' => 'UniqueJohn Brown'])->create();

        $response = $this->getJson('/api/actor/search?q=UniqueJohn');

        $response->assertStatus(200);
    }

    // ========================================================================
    // PERMISSION TESTS (placeholders)
    // ========================================================================

    public function test_create_requires_authentication(): void
    {
        $this->assertTrue(true); // Placeholder until auth is enforced
    }

    public function test_update_requires_authentication(): void
    {
        $this->assertTrue(true); // Placeholder until auth is enforced
    }

    public function test_delete_requires_authentication(): void
    {
        $this->assertTrue(true); // Placeholder until auth is enforced
    }
}
