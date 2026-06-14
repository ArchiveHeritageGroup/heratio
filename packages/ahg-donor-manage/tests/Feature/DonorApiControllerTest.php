<?php

/**
 * DonorApiControllerTest - #1259 coverage for the v1 donor REST reads.
 *
 * #1258 placed `api.auth:read` on these endpoints (they expose contact PII),
 * so unauthenticated requests -> 401. Session auth grants full scopes in
 * ApiAuthenticate, so we actingAs an existing user for the 200 cases. Asserts
 * the paginated index envelope, the show payload, and the 404 for an unknown
 * slug. Complements the ahg-api DonorApiAuthTest (#1258), which covers the
 * raw auth gate.
 *
 * Runs against the pre-built heratio_test DB and rolls back each test
 * (DatabaseTransactions, NOT RefreshDatabase, per #1136).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgDonorManage\Services\DonorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonorApiControllerTest extends TestCase
{
    use DatabaseTransactions;

    private ?User $user = null;

    private DonorService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DonorService('en');
        $this->user = User::query()->first();
    }

    private function requireUser(): void
    {
        if (! $this->user) {
            $this->markTestSkipped('No user row in heratio_test to authenticate as.');
        }
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/donors')->assertStatus(401);
    }

    public function test_index_returns_paginated_envelope(): void
    {
        $this->requireUser();

        $this->actingAs($this->user)
            ->getJson('/api/v1/donors?page=1&limit=5')
            ->assertStatus(200)
            ->assertJsonStructure(['total', 'page', 'limit', 'results'])
            ->assertJson(['page' => 1, 'limit' => 5]);
    }

    public function test_show_returns_donor_for_known_slug(): void
    {
        $this->requireUser();

        $name = $this->uniqueName('Api Donor');
        $id = $this->svc->create(['authorized_form_of_name' => $name]);
        $slug = $this->svc->getSlug($id);

        $this->actingAs($this->user)
            ->getJson('/api/v1/donors/'.$slug)
            ->assertStatus(200)
            ->assertJson([
                'id' => $id,
                'slug' => $slug,
                'authorized_form_of_name' => $name,
            ]);
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->requireUser();

        $this->actingAs($this->user)
            ->getJson('/api/v1/donors/no-such-donor-'.Str::random(8))
            ->assertStatus(404)
            ->assertJson(['error' => 'Not found']);
    }

    private function uniqueName(string $prefix): string
    {
        return $prefix.' '.Str::random(8);
    }
}
