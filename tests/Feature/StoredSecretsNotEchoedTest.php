<?php

/**
 * StoredSecretsNotEchoedTest - settings forms never print a stored password or
 * API key into the page, a blank field keeps the stored value, and a new value
 * replaces it. heratio.org publishes an administrator demo login, so anything
 * an admin page prints is public there.
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

use AhgCore\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StoredSecretsNotEchoedTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ahg_settings')) {
            $this->markTestSkipped('ahg_settings not present.');
        }
        \AhgCore\Services\AclService::forgetUser();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'secrets-'.$id, 'email' => uniqid('secrets-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        $this->admin = User::findOrFail($id);
    }

    public function test_the_datacite_password_is_not_shown_and_a_blank_save_keeps_it(): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_group' => 'doi', 'setting_key' => 'datacite_password'], ['setting_value' => 'DC-SECRET-123']);

        $this->actingAs($this->admin)->get(route('doi.config'))->assertOk()
            ->assertDontSee('DC-SECRET-123')->assertSee('leave blank to keep');

        $this->actingAs($this->admin)->post(route('doi.configSave'), ['datacite_password' => '', 'datacite_prefix' => '10.1234']);
        $this->assertSame('DC-SECRET-123', DB::table('ahg_settings')->where('setting_group', 'doi')->where('setting_key', 'datacite_password')->value('setting_value'));

        $this->actingAs($this->admin)->post(route('doi.configSave'), ['datacite_password' => 'NEW-ONE']);
        $this->assertSame('NEW-ONE', DB::table('ahg_settings')->where('setting_group', 'doi')->where('setting_key', 'datacite_password')->value('setting_value'));
    }

    public function test_archivematica_api_keys_are_not_shown_and_a_blank_save_keeps_them(): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'am_ss_api_key'], ['setting_value' => 'AM-SS-SECRET']);

        $this->actingAs($this->admin)->get(route('archivematica.settings'))->assertOk()->assertDontSee('AM-SS-SECRET');

        $this->actingAs($this->admin)->post(route('archivematica.settings.update'), ['settings' => ['am_ss_api_key' => '', 'am_dip_match_strategy' => 'uuid']]);
        $this->assertSame('AM-SS-SECRET', DB::table('ahg_settings')->where('setting_key', 'am_ss_api_key')->value('setting_value'));
    }

    public function test_the_ai_services_key_is_not_shown_and_a_blank_save_keeps_it(): void
    {
        if (! Schema::hasTable('ahg_ner_settings')) {
            $this->markTestSkipped('ahg_ner_settings not present.');
        }
        DB::table('ahg_ner_settings')->updateOrInsert(['setting_key' => 'api_key'], ['setting_value' => 'AI-GATEWAY-SECRET', 'updated_at' => now()]);

        $this->actingAs($this->admin)->get(route('settings.ai-services'))->assertOk()->assertDontSee('AI-GATEWAY-SECRET');

        $this->actingAs($this->admin)->post(route('settings.ai-services'), ['api_key' => '', 'api_url' => 'https://example.test/ai/v1']);
        $this->assertSame('AI-GATEWAY-SECRET', DB::table('ahg_ner_settings')->where('setting_key', 'api_key')->value('setting_value'));
    }
}
