<?php

namespace Tests\Feature\Api;

use AhgCore\Models\Term;
use Database\Factories\TermFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * API Tests for Terms/Taxonomy
 *
 * Tests API endpoints using the real AtoM i18n schema.
 */
class TermApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Route::has('api.term.index')) {
            $this->markTestSkipped('API routes not yet implemented');
        }
    }

    // ========================================================================
    // BROWSE / LIST TESTS
    // ========================================================================

    public function test_can_list_terms_by_taxonomy(): void
    {
        TermFactory::new()->count(5)->subject()->create();

        $response = $this->getJson('/api/v1/taxonomies/35/terms');

        $response->assertStatus(200);
    }

    public function test_can_get_subjects(): void
    {
        TermFactory::new()->count(5)->subject()->create();

        $response = $this->getJson('/api/v1/taxonomies/35/terms');

        $response->assertStatus(200);
    }

    public function test_can_get_places(): void
    {
        TermFactory::new()->count(3)->place()->create();

        $response = $this->getJson('/api/v1/taxonomies/42/terms');

        $response->assertStatus(200);
    }

    // ========================================================================
    // CREATE TESTS
    // ========================================================================

    public function test_can_create_term(): void
    {
        $data = [
            'name' => 'World War II',
            'taxonomy_id' => 35,
        ];

        $response = $this->postJson('/api/term', $data);

        $response->assertStatus(201);
    }

    public function test_can_create_term_with_code(): void
    {
        $data = [
            'name' => 'Photographs',
            'code' => 'PHO',
            'taxonomy_id' => 35,
        ];

        $response = $this->postJson('/api/term', $data);

        $response->assertStatus(201);
    }

    public function test_create_requires_name(): void
    {
        $response = $this->postJson('/api/term', [
            'taxonomy_id' => 35,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    // ========================================================================
    // SHOW / READ TESTS
    // ========================================================================

    public function test_can_show_term(): void
    {
        $term = TermFactory::new()
            ->withI18n(['name' => 'Test Term'])
            ->create();

        $response = $this->getJson("/api/term/{$term->id}");

        $response->assertStatus(200);
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
        $term = TermFactory::new()
            ->withI18n(['name' => 'Original Name'])
            ->create();

        $response = $this->putJson("/api/term/{$term->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
    }

    // ========================================================================
    // DELETE TESTS
    // ========================================================================

    public function test_can_delete_term(): void
    {
        $term = TermFactory::new()->create();
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
        TermFactory::new()->withI18n(['name' => 'UniqueWorld War I'])->create();
        TermFactory::new()->withI18n(['name' => 'UniqueWorld War II'])->create();

        $response = $this->getJson('/api/term/search?q=UniqueWorld');

        $response->assertStatus(200);
    }
}
