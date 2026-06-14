<?php

/**
 * DonorApiAuthTest - #1258
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 *
 * The v1 donor read endpoints expose donor contact PII (email/phone/address),
 * so they must require an authenticated key with the `read` scope - unlike the
 * public catalogue reads. Runs against the pre-built heratio_test DB with
 * DatabaseTransactions (NOT RefreshDatabase, per #1136).
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DonorApiAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_donor_index_requires_authentication()
    {
        $this->getJson('/api/v1/donors')->assertStatus(401);
    }

    public function test_donor_show_requires_authentication()
    {
        $this->getJson('/api/v1/donors/any-slug')->assertStatus(401);
    }

    public function test_authenticated_user_can_read_donors()
    {
        $user = \AhgCore\Models\User::query()->first();
        if (! $user) {
            $this->markTestSkipped('No user row in heratio_test to authenticate as.');
        }

        // Session auth grants full scopes (incl. read) in ApiAuthenticate.
        $this->actingAs($user)->getJson('/api/v1/donors')->assertStatus(200);
    }
}
