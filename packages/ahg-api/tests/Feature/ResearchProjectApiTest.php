<?php

/**
 * ResearchProjectApiTest - #1255
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 *
 * The v1 research-project endpoints expose non-public research data, so every
 * route requires an authenticated key (read/write/delete scopes). Runs against
 * the pre-built heratio_test DB with DatabaseTransactions (NOT RefreshDatabase,
 * per #1136). Authenticates as an AhgCore\Models\User (the populated `user`
 * table), NOT App\Models\User (the empty `users` table).
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgResearch\Services\UserProvisioner\EloquentUserProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResearchProjectApiTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Resolve a researcher whose user row exists in `user`, or provision one,
     * so actingAs() + the owner-resolution path both work.
     */
    protected function ownerResearcher(): object
    {
        $researcher = DB::table('research_researcher as r')
            ->join('user as u', 'u.id', '=', 'r.user_id')
            ->select('r.*')
            ->first();

        if ($researcher) {
            return $researcher;
        }

        // No joinable researcher: provision a user + a minimal researcher row.
        $provisioner = new EloquentUserProvisioner;
        $uniq = 'apitest_' . uniqid();
        $userId = $provisioner->createUser($uniq, $uniq . '@example.test', 'Secret123!');

        $researcherId = DB::table('research_researcher')->insertGetId([
            'user_id' => $userId,
            'first_name' => 'Api',
            'last_name' => 'Tester',
            'email' => $uniq . '@example.test',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return DB::table('research_researcher')->where('id', $researcherId)->first();
    }

    protected function actingUser(int $userId): User
    {
        // #1395: API write scopes are granted by ACL role, so the acting user
        // must be authorised. Join the ADMINISTRATOR group (idempotent) so the
        // create/update/delete/publish scopes resolve; a plain authenticated
        // user is read-only by design after the hardening.
        DB::table('acl_user_group')->updateOrInsert(['user_id' => $userId, 'group_id' => 100]);
        \Illuminate\Support\Facades\Cache::forget("acl_groups_{$userId}");

        return User::query()->findOrFail($userId);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/research-projects')->assertStatus(401);
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/v1/research-projects/1')->assertStatus(401);
    }

    public function test_authenticated_index_returns_envelope(): void
    {
        $owner = $this->ownerResearcher();

        $this->actingAs($this->actingUser($owner->user_id))
            ->getJson('/api/v1/research-projects')
            ->assertStatus(200)
            ->assertJsonStructure(['total', 'page', 'limit', 'results']);
    }

    public function test_show_unknown_id_returns_404(): void
    {
        $owner = $this->ownerResearcher();

        $this->actingAs($this->actingUser($owner->user_id))
            ->getJson('/api/v1/research-projects/2147483600')
            ->assertStatus(404)
            ->assertJson(['error' => 'Not found']);
    }

    public function test_store_creates_research_project_row(): void
    {
        $owner = $this->ownerResearcher();

        $payload = [
            'title' => 'API Test Project ' . uniqid(),
            'description' => 'Created by the v1 research-project API test.',
            'project_type' => 'personal',
            'status' => 'planning',
        ];

        $response = $this->actingAs($this->actingUser($owner->user_id))
            ->postJson('/api/v1/research-projects', $payload)
            ->assertStatus(201)
            ->assertJson(['title' => $payload['title']]);

        $id = $response->json('id');
        $this->assertNotNull($id);
        $this->assertDatabaseHas('research_project', [
            'id' => $id,
            'title' => $payload['title'],
            'owner_id' => $owner->id,
        ]);
        // Owner registered as accepted collaborator (mirrors the web flow).
        $this->assertDatabaseHas('research_project_collaborator', [
            'project_id' => $id,
            'researcher_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $owner = $this->ownerResearcher();

        $this->actingAs($this->actingUser($owner->user_id))
            ->postJson('/api/v1/research-projects', ['description' => 'no title'])
            ->assertStatus(422)
            ->assertJsonStructure(['error', 'messages' => ['title']]);
    }

    public function test_update_and_destroy_round_trip(): void
    {
        $owner = $this->ownerResearcher();
        $acting = $this->actingUser($owner->user_id);

        $createId = $this->actingAs($acting)
            ->postJson('/api/v1/research-projects', [
                'title' => 'Round Trip ' . uniqid(),
                'status' => 'planning',
            ])
            ->assertStatus(201)
            ->json('id');

        // Update
        $this->actingAs($acting)
            ->putJson('/api/v1/research-projects/' . $createId, [
                'title' => 'Round Trip Updated',
                'status' => 'active',
            ])
            ->assertStatus(200)
            ->assertJson(['id' => $createId, 'title' => 'Round Trip Updated', 'status' => 'active']);

        $this->assertDatabaseHas('research_project', [
            'id' => $createId,
            'title' => 'Round Trip Updated',
            'status' => 'active',
        ]);

        // Destroy
        $this->actingAs($acting)
            ->deleteJson('/api/v1/research-projects/' . $createId)
            ->assertStatus(200)
            ->assertJson(['deleted' => true, 'id' => $createId]);

        $this->assertDatabaseMissing('research_project', ['id' => $createId]);

        // 404 after delete
        $this->actingAs($acting)
            ->getJson('/api/v1/research-projects/' . $createId)
            ->assertStatus(404);
    }
}
