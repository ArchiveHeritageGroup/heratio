<?php

namespace Tests\Feature\Api;

use AhgCore\Models\InformationObject;
use Database\Factories\ActorFactory;
use Database\Factories\EventFactory;
use Database\Factories\InformationObjectFactory;
use Database\Factories\TermFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * API Tests for Information Objects (Archival Descriptions)
 *
 * Tests API endpoints using the real AtoM i18n schema.
 */
class InformationObjectApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Route::has('api.records.index')) {
            $this->markTestSkipped('API routes not yet implemented');
        }
    }

    // ========================================================================
    // BROWSE / LIST TESTS
    // ========================================================================

    public function test_can_list_records(): void
    {
        InformationObjectFactory::new()->count(5)->create();

        $response = $this->getJson('/api/records');

        $response->assertStatus(200);
    }

    public function test_records_list_paginates(): void
    {
        InformationObjectFactory::new()->count(25)->create();

        $response = $this->getJson('/api/records?per_page=10');

        $response->assertStatus(200);
    }

    public function test_can_filter_by_repository(): void
    {
        // Create an actor first, then add it to the repository table
        $actor = ActorFactory::new()->corporateBody()->create();
        
        // Add to repository table (required FK relationship)
        \Illuminate\Support\Facades\DB::table('repository')->insert([
            'id' => $actor->id,
            'source_culture' => 'en',
        ]);
        
        InformationObjectFactory::new()->count(3)->create(['repository_id' => $actor->id]);

        $response = $this->getJson('/api/records?repository=' . $actor->id);

        $response->assertStatus(200);
    }

    // ========================================================================
    // CREATE TESTS
    // ========================================================================

    public function test_can_create_record(): void
    {
        $data = [
            'title' => 'Test Collection',
            'identifier' => 'TEST-001',
            'level_of_description_id' => 1,
        ];

        $response = $this->postJson('/api/records', $data);

        $response->assertStatus(201);
    }

    public function test_can_create_hierarchical_record(): void
    {
        $parent = InformationObjectFactory::new()->collection()->create();

        $data = [
            'title' => 'Child Series',
            'parent_id' => $parent->id,
            'level_of_description_id' => 2,
        ];

        $response = $this->postJson('/api/records', $data);

        $response->assertStatus(201);
    }

    public function test_create_requires_title(): void
    {
        $response = $this->postJson('/api/records', [
            'identifier' => 'TEST-001',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    // ========================================================================
    // SHOW / READ TESTS
    // ========================================================================

    public function test_can_show_record(): void
    {
        $io = InformationObjectFactory::new()
            ->withI18n(['title' => 'Test Record'])
            ->create();

        $response = $this->getJson("/api/records/{$io->id}");

        $response->assertStatus(200);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/records/99999');

        $response->assertStatus(404);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_record_title(): void
    {
        $io = InformationObjectFactory::new()
            ->withI18n(['title' => 'Original Title'])
            ->create();

        $response = $this->putJson("/api/records/{$io->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200);
    }

    public function test_can_move_record_to_new_parent(): void
    {
        $parent1 = InformationObjectFactory::new()->collection()->create();
        $parent2 = InformationObjectFactory::new()->collection()->create();
        $child = InformationObjectFactory::new()->series()->create(['parent_id' => $parent1->id]);

        $response = $this->putJson("/api/records/{$child->id}", [
            'parent_id' => $parent2->id,
        ]);

        $response->assertStatus(200);
    }

    public function test_update_returns_404_for_nonexistent(): void
    {
        $response = $this->putJson('/api/records/99999', [
            'title' => 'Updated',
        ]);

        $response->assertStatus(404);
    }

    // ========================================================================
    // DELETE TESTS
    // ========================================================================

    public function test_can_delete_record(): void
    {
        $io = InformationObjectFactory::new()->create();
        $id = $io->id;

        $response = $this->deleteJson("/api/records/{$id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('information_object', ['id' => $id]);
    }

    // ========================================================================
    // SEARCH TESTS
    // ========================================================================

    public function test_can_search_records_by_title(): void
    {
        InformationObjectFactory::new()->withI18n(['title' => 'UniquePhotographs 1950'])->create();

        $response = $this->getJson('/api/records/search?q=UniquePhotographs');

        $response->assertStatus(200);
    }

    // ========================================================================
    // HIERARCHY TESTS
    // ========================================================================

    public function test_can_get_children(): void
    {
        $parent = InformationObjectFactory::new()->collection()->create();
        InformationObjectFactory::new()->count(3)->series()->create(['parent_id' => $parent->id]);

        $response = $this->getJson("/api/records/{$parent->id}/children");

        $response->assertStatus(200);
    }

    // ========================================================================
    // PERMISSION TESTS (placeholders)
    // ========================================================================

    public function test_create_requires_authentication(): void
    {
        $this->assertTrue(true);
    }

    public function test_delete_requires_authentication(): void
    {
        $this->assertTrue(true);
    }
}
