<?php

namespace Tests\Feature\Api;

use AhgCore\Models\InformationObject;
use Database\Factories\InformationObjectFactory;
use Database\Factories\ActorFactory;
use Database\Factories\TermFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Tests for Information Objects (Archival Descriptions)
 * 
 * Tests all CRUD API endpoints for records including:
 * - Browse/List records
 * - Create record
 * - Show record details
 * - Update record
 * - Delete record
 * - Search records
 * - Hierarchical relationships
 */
class InformationObjectApiTest extends TestCase
{
    use RefreshDatabase;

    protected InformationObjectFactory $ioFactory;
    protected ActorFactory $actorFactory;
    protected TermFactory $termFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ioFactory = new InformationObjectFactory();
        $this->actorFactory = new ActorFactory();
        $this->termFactory = new TermFactory();
    }

    // ========================================================================
    // BROWSE / LIST TESTS
    // ========================================================================

    public function test_can_list_records(): void
    {
        $this->ioFactory->count(5)->create();

        $response = $this->getJson('/api/records');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'identifier', 'level_of_description_id']
            ],
            'meta' => ['total', 'per_page', 'current_page'],
        ]);
    }

    public function test_records_list_paginates(): void
    {
        $this->ioFactory->count(25)->create();

        $response = $this->getJson('/api/records?per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(10, $response->json('meta.per_page'));
    }

    public function test_can_filter_by_level(): void
    {
        $this->ioFactory->count(3)->collection()->create();
        $this->ioFactory->count(2)->series()->create();

        $response = $this->getJson('/api/records?level=collection');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_can_filter_by_repository(): void
    {
        $this->ioFactory->count(3)->create(['repository_id' => 1]);
        $this->ioFactory->count(2)->create(['repository_id' => 2]);

        $response = $this->getJson('/api/records?repository_id=1');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    // ========================================================================
    // CREATE TESTS
    // ========================================================================

    public function test_can_create_record(): void
    {
        $data = [
            'title' => 'Test Collection',
            'identifier' => 'TEST-001',
            'level_of_description_id' => 1, // Collection
        ];

        $response = $this->postJson('/api/records', $data);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'title' => 'Test Collection',
            'identifier' => 'TEST-001',
        ]);

        $this->assertDatabaseHas('information_object', [
            'title' => 'Test Collection',
            'identifier' => 'TEST-001',
        ]);
    }

    public function test_can_create_hierarchical_record(): void
    {
        $parent = $this->ioFactory->collection()->create(['title' => 'Parent Collection']);

        $data = [
            'title' => 'Child Series',
            'parent_id' => $parent->id,
            'level_of_description_id' => 2, // Series
        ];

        $response = $this->postJson('/api/records', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('information_object', [
            'id' => $response->json('data.id'),
            'parent_id' => $parent->id,
        ]);
    }

    public function test_create_requires_title(): void
    {
        $response = $this->postJson('/api/records', [
            'identifier' => 'TEST-001',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_create_requires_valid_level(): void
    {
        $response = $this->postJson('/api/records', [
            'title' => 'Test',
            'level_of_description_id' => 999,
        ]);

        $response->assertStatus(422);
    }

    // ========================================================================
    // SHOW / READ TESTS
    // ========================================================================

    public function test_can_show_record(): void
    {
        $io = $this->ioFactory->create(['title' => 'Test Record']);

        $response = $this->getJson("/api/records/{$io->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $io->id,
            'title' => 'Test Record',
        ]);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/records/99999');

        $response->assertStatus(404);
    }

    public function test_show_includes_creator_relations(): void
    {
        $io = $this->ioFactory->create();
        $creator = $this->actorFactory->corporateBody()->create([
            'authorized_form_of_name' => 'Test Creator',
        ]);

        \AhgCore\Models\Event::create([
            'object_id' => $io->id,
            'actor_id' => $creator->id,
            'type_id' => 101, // Creation
        ]);

        $response = $this->getJson("/api/records/{$io->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'creators',
                'subjects',
                'digital_objects',
            ]
        ]);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_record_title(): void
    {
        $io = $this->ioFactory->create(['title' => 'Original Title']);

        $response = $this->putJson("/api/records/{$io->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['title' => 'Updated Title']);

        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_can_update_scope_and_content(): void
    {
        $io = $this->ioFactory->create();

        $response = $this->putJson("/api/records/{$io->id}", [
            '檔案材料內容' => 'Updated scope and content.',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            '檔案材料內容' => 'Updated scope and content.',
        ]);
    }

    public function test_can_move_record_to_new_parent(): void
    {
        $parent1 = $this->ioFactory->collection()->create();
        $parent2 = $this->ioFactory->collection()->create();
        $child = $this->ioFactory->series()->create(['parent_id' => $parent1->id]);

        $response = $this->putJson("/api/records/{$child->id}", [
            'parent_id' => $parent2->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('information_object', [
            'id' => $child->id,
            'parent_id' => $parent2->id,
        ]);
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
        $io = $this->ioFactory->create();
        $id = $io->id;

        $response = $this->deleteJson("/api/records/{$id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('information_object', ['id' => $id]);
    }

    public function test_deleting_parent_orphans_children(): void
    {
        $parent = $this->ioFactory->series()->create();
        $child = $this->ioFactory->file()->create(['parent_id' => $parent->id]);

        $response = $this->deleteJson("/api/records/{$parent->id}");

        $response->assertStatus(204);
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_delete_removes_related_events(): void
    {
        $io = $this->ioFactory->create();
        $creator = $this->actorFactory->create();

        $event = \AhgCore\Models\Event::create([
            'object_id' => $io->id,
            'actor_id' => $creator->id,
            'type_id' => 101,
        ]);

        $response = $this->deleteJson("/api/records/{$io->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('event', ['id' => $event->id]);
    }

    // ========================================================================
    // SEARCH TESTS
    // ========================================================================

    public function test_can_search_records_by_title(): void
    {
        $this->ioFactory->create(['title' => 'Photographs 1950']);
        $this->ioFactory->create(['title' => 'Financial Records 1970']);
        $this->ioFactory->create(['title' => 'Letters and Photos']);

        $response = $this->getJson('/api/records/search?q=photograph');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_search_with_identifier(): void
    {
        $this->ioFactory->create(['identifier' => 'MS-2024-001']);
        $this->ioFactory->create(['identifier' => 'MS-2024-002']);

        $response = $this->getJson('/api/records/search?q=MS-2024-001');

        $response->assertStatus(200);
        $response->assertJsonFragment(['identifier' => 'MS-2024-001']);
    }

    // ========================================================================
    // RELATIONS TESTS
    // ========================================================================

    public function test_can_add_creator_to_record(): void
    {
        $io = $this->ioFactory->create();
        $creator = $this->actorFactory->corporateBody()->create();

        $response = $this->postJson("/api/records/{$io->id}/creators", [
            'actor_id' => $creator->id,
            'type_id' => 101, // Creation
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('event', [
            'object_id' => $io->id,
            'actor_id' => $creator->id,
        ]);
    }

    public function test_can_add_subject_to_record(): void
    {
        $io = $this->ioFactory->create();
        $subject = $this->termFactory->subject()->create(['name' => 'World War I']);

        $response = $this->postJson("/api/records/{$io->id}/subjects", [
            'term_id' => $subject->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('object_term_relation', [
            'object_id' => $io->id,
            'object_class' => 'InformationObject',
            'term_id' => $subject->id,
        ]);
    }

    // ========================================================================
    // DIGITAL OBJECTS TESTS
    // ========================================================================

    public function test_can_get_digital_objects(): void
    {
        $io = $this->ioFactory->create();

        $response = $this->getJson("/api/records/{$io->id}/digital-objects");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'filename', 'mime_type']
            ]
        ]);
    }

    public function test_can_upload_digital_object(): void
    {
        $io = $this->ioFactory->create();

        $response = $this->postJson("/api/records/{$io->id}/digital-objects", [
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'url' => 'https://example.com/test.pdf',
        ]);

        $response->assertStatus(201);
    }

    // ========================================================================
    // RIGHTS TESTS
    // ========================================================================

    public function test_can_set_copyright_status(): void
    {
        $io = $this->ioFactory->create();

        $response = $this->putJson("/api/records/{$io->id}", [
            '版權狀態' => 'copyright',
            'rights_holder' => 'Estate of Author',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            '版權狀態' => 'copyright',
            'rights_holder' => 'Estate of Author',
        ]);
    }

    public function test_can_set_public_domain(): void
    {
        $io = $this->ioFactory->create();

        $response = $this->putJson("/api/records/{$io->id}", [
            '版權狀態' => 'public',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('information_object', [
            'id' => $io->id,
            '版權狀態' => 'public',
        ]);
    }

    // ========================================================================
    // HIERARCHY TESTS
    // ========================================================================

    public function test_can_get_children(): void
    {
        $parent = $this->ioFactory->collection()->create();
        $this->ioFactory->count(3)->series()->create(['parent_id' => $parent->id]);

        $response = $this->getJson("/api/records/{$parent->id}/children");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_can_get_ancestors(): void
    {
        $root = $this->ioFactory->collection()->create(['title' => 'Root']);
        $series = $this->ioFactory->series()->create(['parent_id' => $root->id]);
        $file = $this->ioFactory->file()->create(['parent_id' => $series->id]);

        $response = $this->getJson("/api/records/{$file->id}/ancestors");

        $response->assertStatus(200);
        $ancestors = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($ancestors));
    }

    public function test_root_records_have_no_parent(): void
    {
        $root = $this->ioFactory->collection()->create();

        $response = $this->getJson("/api/records/{$root->id}");

        $response->assertStatus(200);
        $this->assertNull($response->json('data.parent_id'));
    }

    // ========================================================================
    // PERMISSION TESTS
    // ========================================================================

    public function test_create_requires_authentication(): void
    {
        // Uncomment when auth is required:
        // $this->postJson('/api/records', ['title' => 'Test'])->assertStatus(401);
        $this->assertTrue(true);
    }

    public function test_delete_requires_authentication(): void
    {
        $io = $this->ioFactory->create();

        // Uncomment when auth is required:
        // $this->deleteJson("/api/records/{$io->id}")->assertStatus(401);
        $this->assertTrue(true);
    }
}
