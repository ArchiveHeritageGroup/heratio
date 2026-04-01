<?php

namespace Tests\Feature\Api;

use AhgCore\Models\Term;
use Database\Factories\TermFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Tests for Terms/Taxonomy
 * 
 * Tests CRUD API endpoints for terms including:
 * - Browse/List terms
 * - Create term
 * - Show term details
 * - Update term
 * - Delete term
 * - Filter by taxonomy
 */
class TermApiTest extends TestCase
{
    use RefreshDatabase;

    protected TermFactory $termFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->termFactory = new TermFactory();
    }

    // ========================================================================
    // BROWSE / LIST TESTS
    // ========================================================================

    public function test_can_list_terms(): void
    {
        $this->termFactory->count(5)->create();

        $response = $this->getJson('/api/term');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'taxonomy_id']
            ],
        ]);
    }

    public function test_can_filter_by_taxonomy(): void
    {
        $this->termFactory->count(3)->subject()->create();
        $this->termFactory->count(2)->place()->create();

        $response = $this->getJson('/api/term?taxonomy=subject');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_can_get_subjects(): void
    {
        $this->termFactory->count(5)->subject()->create();

        $response = $this->getJson('/api/term/subjects');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_can_get_places(): void
    {
        $this->termFactory->count(3)->place()->create();

        $response = $this->getJson('/api/term/places');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    // ========================================================================
    // CREATE TESTS
    // ========================================================================

    public function test_can_create_term(): void
    {
        $data = [
            'name' => 'World War II',
            'taxonomy_id' => 1, // Subject
        ];

        $response = $this->postJson('/api/term', $data);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'name' => 'World War II',
        ]);

        $this->assertDatabaseHas('term', [
            'name' => 'World War II',
        ]);
    }

    public function test_can_create_term_with_code(): void
    {
        $data = [
            'name' => 'Photographs',
            'code' => 'PHO',
            'taxonomy_id' => 1,
        ];

        $response = $this->postJson('/api/term', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('term', [
            'name' => 'Photographs',
            'code' => 'PHO',
        ]);
    }

    public function test_create_requires_name(): void
    {
        $response = $this->postJson('/api/term', [
            'taxonomy_id' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    // ========================================================================
    // SHOW / READ TESTS
    // ========================================================================

    public function test_can_show_term(): void
    {
        $term = $this->termFactory->create(['name' => 'Test Term']);

        $response = $this->getJson("/api/term/{$term->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $term->id,
            'name' => 'Test Term',
        ]);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/term/99999');

        $response->assertStatus(404);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_term(): void
    {
        $term = $this->termFactory->create(['name' => 'Original Name']);

        $response = $this->putJson("/api/term/{$term->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('term', [
            'id' => $term->id,
            'name' => 'Updated Name',
        ]);
    }

    // ========================================================================
    // DELETE TESTS
    // ========================================================================

    public function test_can_delete_term(): void
    {
        $term = $this->termFactory->create();
        $id = $term->id;

        $response = $this->deleteJson("/api/term/{$id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('term', ['id' => $id]);
    }

    public function test_delete_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson('/api/term/99999');

        $response->assertStatus(404);
    }

    // ========================================================================
    // SEARCH TESTS
    // ========================================================================

    public function test_can_search_terms(): void
    {
        $this->termFactory->create(['name' => 'World War I']);
        $this->termFactory->create(['name' => 'World War II']);
        $this->termFactory->create(['name' => 'Cold War']);

        $response = $this->getJson('/api/term/search?q=World');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));
    }
}
