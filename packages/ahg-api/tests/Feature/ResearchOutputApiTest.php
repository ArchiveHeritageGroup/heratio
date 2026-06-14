<?php

/**
 * ResearchOutputApiTest - #1255
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 *
 * Feature coverage for the v1 Research Outputs REST API (the CRIS / RIM
 * register, epic #1222). Runs against the pre-built heratio_test DB with
 * DatabaseTransactions (NOT RefreshDatabase, per #1136 - that would drop the
 * ~995 base tables this suite relies on). Each test rolls back.
 *
 * Auth model (ApiAuthenticate): a logged-in session is granted full scopes
 * (read/write/delete), so actingAs() satisfies every scope these routes
 * require. Unauthenticated requests get 401.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResearchOutputApiTest extends TestCase
{
    use DatabaseTransactions;

    /** Authenticate as a real AhgCore user, creating one if the table is empty. */
    private function actingAsApiUser(): self
    {
        $user = \AhgCore\Models\User::query()->first();

        if (! $user) {
            $provisioner = app(\AhgResearch\Contracts\UserProvisionerInterface::class);
            $username = 'apitest_' . Str::random(6);
            $userId = $provisioner->createUser($username, $username . '@example.test', 'Password123!');
            $user = \AhgCore\Models\User::query()->find($userId);
        }

        $this->assertNotNull($user, 'Could not obtain a user to authenticate as.');

        return $this->actingAs($user);
    }

    /** A real research_project id to scope outputs to (created if none exist). */
    private function projectId(): int
    {
        $existing = DB::table('research_project')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('research_project')->insertGetId([
            'title' => 'API test project ' . Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/research-outputs')->assertStatus(401);
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/v1/research-outputs/1')->assertStatus(401);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/research-outputs', [])->assertStatus(401);
    }

    public function test_authenticated_index_returns_envelope(): void
    {
        $this->actingAsApiUser()
            ->getJson('/api/v1/research-outputs')
            ->assertStatus(200)
            ->assertJsonStructure(['total', 'page', 'limit', 'results']);
    }

    public function test_show_unknown_id_returns_404(): void
    {
        $this->actingAsApiUser()
            ->getJson('/api/v1/research-outputs/999999999')
            ->assertStatus(404)
            ->assertJson(['error' => 'Not found']);
    }

    public function test_store_creates_a_research_output_row(): void
    {
        $projectId = $this->projectId();

        $payload = [
            'project_id' => $projectId,
            'title' => 'A Test Output ' . Str::random(5),
            'output_type' => 'journal_article',
            'authors' => 'Doe, J.; Smith, A.',
            'venue' => 'Journal of Testing',
            'identifier_type' => 'doi',
            'identifier' => '10.1234/test.abcd',
            'output_date' => '2026-01-15',
            'status' => 'published',
        ];

        $response = $this->actingAsApiUser()
            ->postJson('/api/v1/research-outputs', $payload)
            ->assertStatus(201)
            ->assertJsonFragment([
                'title' => $payload['title'],
                'output_type' => 'journal_article',
            ]);

        $id = (int) $response->json('id');
        $this->assertGreaterThan(0, $id);

        $row = DB::table('research_output')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame($projectId, (int) $row->project_id);
        $this->assertSame('10.1234/test.abcd', $row->identifier);

        // Resolver decorates the response with a citable URL.
        $this->assertSame('https://doi.org/10.1234/test.abcd', $response->json('url'));
    }

    public function test_store_validation_fails_on_missing_required_fields(): void
    {
        $this->actingAsApiUser()
            ->postJson('/api/v1/research-outputs', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_id', 'title', 'output_type']);
    }

    public function test_store_rejects_bad_output_type(): void
    {
        $this->actingAsApiUser()
            ->postJson('/api/v1/research-outputs', [
                'project_id' => $this->projectId(),
                'title' => 'Bad type output',
                'output_type' => 'not_a_real_type',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['output_type']);
    }

    public function test_update_round_trip(): void
    {
        $projectId = $this->projectId();
        $id = (int) DB::table('research_output')->insertGetId([
            'project_id' => $projectId,
            'output_type' => 'report',
            'title' => 'Original title',
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsApiUser()
            ->putJson('/api/v1/research-outputs/' . $id, [
                'title' => 'Updated title',
                'output_type' => 'dataset',
                'status' => 'published',
            ])
            ->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Updated title',
                'output_type' => 'dataset',
                'status' => 'published',
            ]);

        $row = DB::table('research_output')->where('id', $id)->first();
        $this->assertSame('Updated title', $row->title);
        $this->assertSame('dataset', $row->output_type);
        $this->assertSame('published', $row->status);
    }

    public function test_update_unknown_id_returns_404(): void
    {
        $this->actingAsApiUser()
            ->putJson('/api/v1/research-outputs/999999999', [
                'title' => 'x',
                'output_type' => 'report',
            ])
            ->assertStatus(404);
    }

    public function test_destroy_round_trip(): void
    {
        $projectId = $this->projectId();
        $id = (int) DB::table('research_output')->insertGetId([
            'project_id' => $projectId,
            'output_type' => 'software',
            'title' => 'To be deleted',
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsApiUser()
            ->deleteJson('/api/v1/research-outputs/' . $id)
            ->assertStatus(200)
            ->assertJson(['deleted' => true, 'id' => $id]);

        $this->assertNull(DB::table('research_output')->where('id', $id)->first());
    }

    public function test_destroy_unknown_id_returns_404(): void
    {
        $this->actingAsApiUser()
            ->deleteJson('/api/v1/research-outputs/999999999')
            ->assertStatus(404);
    }
}
