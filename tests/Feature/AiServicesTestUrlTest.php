<?php

/**
 * AiServicesTestUrlTest - the AI settings "Test Connection" button may reach
 * the gateway address an administrator saved, even when it resolves to an
 * internal address; any other internal address is still refused.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AiServicesTestUrlTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_saved_gateway_is_trusted_and_other_internal_hosts_are_not(): void
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'ait-'.$id, 'email' => uniqid('ait-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();
        DB::table('ahg_ner_settings')->updateOrInsert(['setting_key' => 'api_url'], ['setting_value' => 'http://localhost/ai/v1']);

        $admin = User::findOrFail($id);
        $saved = $this->actingAs($admin)->postJson('/admin/ahgSettings/aiServices/test', ['url' => 'http://localhost/ai/v1'])->json();
        $this->assertStringNotContainsString('internal network', (string) ($saved['error'] ?? ''), 'the saved gateway is tested, not refused');

        $other = $this->actingAs($admin)->postJson('/admin/ahgSettings/aiServices/test', ['url' => 'http://10.0.0.5/ai/v1'])->json();
        $this->assertFalse($other['success']);
        $this->assertStringContainsString('internal network', $other['error']);
    }
}
