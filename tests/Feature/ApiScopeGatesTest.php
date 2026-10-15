<?php

/**
 * ApiScopeGatesTest - a new API key cannot carry scopes its creator lacks
 * (GHSA-vg9h-jcc6-jvvv), and RiC deletes need the delete scope, not just write
 * (CH-000273).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiScopeGatesTest extends TestCase
{
    use DatabaseTransactions;

    private function keyWith(array $scopes): string
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'scope-'.$id, 'email' => uniqid('scope-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        $raw = 'scope-test-key-'.$id.'-'.implode('-', $scopes);
        DB::table('ahg_api_key')->insert(['user_id' => $id, 'name' => 't', 'api_key' => hash('sha256', $raw), 'api_key_prefix' => substr($raw, 0, 8), 'scopes' => json_encode($scopes), 'is_active' => 1]);

        return $raw;
    }

    public function test_a_key_cannot_mint_a_key_with_more_scopes_than_it_has(): void
    {
        $read = $this->keyWith(['read']);
        $this->withHeader('X-API-Key', $read)->postJson('/api/v2/keys', ['name' => 'up', 'scopes' => ['read', 'write', 'delete', 'batch']])
            ->assertForbidden()->assertJsonFragment(['success' => false]);
        $this->withHeader('X-API-Key', $read)->postJson('/api/v2/keys', ['name' => 'same', 'scopes' => ['read']])->assertCreated();
        $this->withHeader('X-API-Key', $read)->postJson('/api/v2/keys', ['name' => 'default'])->assertCreated();

        $writer = $this->keyWith(['read', 'write']);
        $this->withHeader('X-API-Key', $writer)->postJson('/api/v2/keys', ['name' => 'w', 'scopes' => ['read', 'write']])->assertCreated();
        $this->withHeader('X-API-Key', $writer)->postJson('/api/v2/keys', ['name' => 'd', 'scopes' => ['delete']])->assertForbidden();
    }

    public function test_ric_deletes_need_the_delete_scope(): void
    {
        $this->withHeader('X-API-Key', $this->keyWith(['read', 'write']))
            ->deleteJson('/api/ric/v1/relations/999999999')->assertForbidden();
        $this->withHeader('X-API-Key', $this->keyWith(['read', 'write']))
            ->deleteJson('/api/ric/v1/places/999999999')->assertForbidden();

        $status = $this->withHeader('X-API-Key', $this->keyWith(['read', 'write', 'delete']))
            ->deleteJson('/api/ric/v1/relations/999999999')->status();
        $this->assertNotSame(403, $status, 'a key with the delete scope passes the gate');
    }
}
