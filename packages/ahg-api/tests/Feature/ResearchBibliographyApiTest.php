<?php

/**
 * ResearchBibliographyApiTest - #1255
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 *
 * Exercises the v1 research-bibliographies REST API: auth gating, the
 * {total,page,limit,results} envelope, store/update/destroy round-trips, and
 * the nested entries relationship. Runs against the pre-built heratio_test DB
 * with DatabaseTransactions (NOT RefreshDatabase, per #1136), so every row it
 * creates is rolled back.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgResearch\Services\ResearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResearchBibliographyApiTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Authenticate as a user that maps to a research_researcher row, so the
     * store endpoint can resolve an owning researcher from the session user.
     */
    protected function actingResearcher(): ?User
    {
        $map = DB::table('research_researcher')
            ->join('user', 'user.id', '=', 'research_researcher.user_id')
            ->select('user.id')
            ->first();

        if (! $map) {
            return null;
        }

        $user = User::query()->find($map->id);
        if ($user) {
            $this->actingAs($user);
        }

        return $user;
    }

    public function test_index_requires_authentication()
    {
        $this->getJson('/api/v1/research-bibliographies')->assertStatus(401);
    }

    public function test_show_requires_authentication()
    {
        $this->getJson('/api/v1/research-bibliographies/1')->assertStatus(401);
    }

    public function test_store_requires_authentication()
    {
        $this->postJson('/api/v1/research-bibliographies', ['name' => 'X'])->assertStatus(401);
    }

    public function test_authenticated_index_returns_envelope()
    {
        if (! $this->actingResearcher()) {
            $this->markTestSkipped('No researcher-mapped user in heratio_test.');
        }

        $this->getJson('/api/v1/research-bibliographies')
            ->assertStatus(200)
            ->assertJsonStructure(['total', 'page', 'limit', 'results']);
    }

    public function test_store_creates_row_and_validation_fails_without_name()
    {
        if (! $this->actingResearcher()) {
            $this->markTestSkipped('No researcher-mapped user in heratio_test.');
        }

        // Validation failure: name is required.
        $this->postJson('/api/v1/research-bibliographies', ['description' => 'no name'])
            ->assertStatus(422)
            ->assertJsonStructure(['error', 'messages']);

        // Successful create.
        $resp = $this->postJson('/api/v1/research-bibliographies', [
            'name' => 'API Test Bibliography',
            'description' => 'Created by feature test',
            'citation_style' => 'harvard',
        ])->assertStatus(201)
          ->assertJsonFragment(['name' => 'API Test Bibliography', 'citation_style' => 'harvard']);

        $id = $resp->json('id');
        $this->assertNotNull($id);
        $this->assertDatabaseHas('research_bibliography', [
            'id' => $id,
            'name' => 'API Test Bibliography',
        ]);
    }

    public function test_show_returns_entries()
    {
        $user = $this->actingResearcher();
        if (! $user) {
            $this->markTestSkipped('No researcher-mapped user in heratio_test.');
        }

        $researcher = app(ResearchService::class)->getResearcherByUserId((int) $user->id);

        $id = DB::table('research_bibliography')->insertGetId([
            'researcher_id' => $researcher->id,
            'name' => 'Bib with entries',
            'citation_style' => 'chicago',
            'is_public' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        DB::table('research_bibliography_entry')->insert([
            'bibliography_id' => $id,
            'entry_type' => 'book',
            'title' => 'A Cited Work',
            'sort_order' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->getJson('/api/v1/research-bibliographies/' . $id)
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Bib with entries'])
            ->assertJsonPath('entries.0.title', 'A Cited Work');
    }

    public function test_update_round_trip()
    {
        $user = $this->actingResearcher();
        if (! $user) {
            $this->markTestSkipped('No researcher-mapped user in heratio_test.');
        }

        $researcher = app(ResearchService::class)->getResearcherByUserId((int) $user->id);

        $id = DB::table('research_bibliography')->insertGetId([
            'researcher_id' => $researcher->id,
            'name' => 'Before',
            'citation_style' => 'chicago',
            'is_public' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->putJson('/api/v1/research-bibliographies/' . $id, [
            'name' => 'After',
            'citation_style' => 'apa',
        ])->assertStatus(200)
          ->assertJsonFragment(['name' => 'After', 'citation_style' => 'apa']);

        $this->assertDatabaseHas('research_bibliography', ['id' => $id, 'name' => 'After']);
    }

    public function test_update_404_for_missing()
    {
        if (! $this->actingResearcher()) {
            $this->markTestSkipped('No researcher-mapped user in heratio_test.');
        }

        $this->putJson('/api/v1/research-bibliographies/99999999', ['name' => 'X'])
            ->assertStatus(404);
    }

    public function test_destroy_round_trip()
    {
        $user = $this->actingResearcher();
        if (! $user) {
            $this->markTestSkipped('No researcher-mapped user in heratio_test.');
        }

        $researcher = app(ResearchService::class)->getResearcherByUserId((int) $user->id);

        $id = DB::table('research_bibliography')->insertGetId([
            'researcher_id' => $researcher->id,
            'name' => 'To delete',
            'citation_style' => 'chicago',
            'is_public' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->deleteJson('/api/v1/research-bibliographies/' . $id)
            ->assertStatus(200)
            ->assertJsonFragment(['deleted' => true]);

        $this->assertDatabaseMissing('research_bibliography', ['id' => $id]);
    }

    public function test_nested_entry_crud()
    {
        $user = $this->actingResearcher();
        if (! $user) {
            $this->markTestSkipped('No researcher-mapped user in heratio_test.');
        }

        $researcher = app(ResearchService::class)->getResearcherByUserId((int) $user->id);

        $bibId = DB::table('research_bibliography')->insertGetId([
            'researcher_id' => $researcher->id,
            'name' => 'Entry CRUD bib',
            'citation_style' => 'chicago',
            'is_public' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create entry.
        $resp = $this->postJson('/api/v1/research-bibliographies/' . $bibId . '/entries', [
            'entry_type' => 'article',
            'title' => 'Nested Entry',
            'authors' => 'Doe, Jane',
        ])->assertStatus(201)
          ->assertJsonFragment(['title' => 'Nested Entry']);

        $entryId = $resp->json('id');
        $this->assertNotNull($entryId);

        // List entries.
        $this->getJson('/api/v1/research-bibliographies/' . $bibId . '/entries')
            ->assertStatus(200)
            ->assertJsonPath('total', 1);

        // Update entry.
        $this->putJson('/api/v1/research-bibliographies/' . $bibId . '/entries/' . $entryId, [
            'title' => 'Renamed Entry',
        ])->assertStatus(200)
          ->assertJsonFragment(['title' => 'Renamed Entry']);

        // Delete entry.
        $this->deleteJson('/api/v1/research-bibliographies/' . $bibId . '/entries/' . $entryId)
            ->assertStatus(200)
            ->assertJsonFragment(['deleted' => true]);

        $this->assertDatabaseMissing('research_bibliography_entry', ['id' => $entryId]);
    }
}
