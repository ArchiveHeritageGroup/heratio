<?php

/**
 * SettingsBlankFieldSaveTest - saving a settings form with a blank field stores
 * an empty value instead of failing with a TypeError (27 Nov 2026). Laravel turns
 * a blank field into null before the controller sees it, and
 * SettingsService::saveSetting() only took a string, so Admin > Settings > Site
 * information gave a 500 whenever, for example, the base URL was left empty.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgSettings\Services\SettingsService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsBlankFieldSaveTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_null_value_is_stored_as_empty(): void
    {
        app(SettingsService::class)->saveSetting('siteBaseUrl', null, null, 'en');
        $id = DB::table('setting')->where('name', 'siteBaseUrl')->whereNull('scope')->value('id');
        $this->assertNotNull($id);
        $this->assertSame('', (string) DB::table('setting_i18n')->where('id', $id)->where('culture', 'en')->value('value'));
    }

    public function test_site_information_saves_with_a_blank_field(): void
    {
        \AhgCore\Services\AclService::forgetUser();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'blank-'.$id, 'email' => uniqid('blank-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        $this->actingAs(User::findOrFail($id))
            ->post('/admin/settings/site-information', ['siteTitle' => 'Test site', 'siteDescription' => '', 'siteBaseUrl' => ''])
            ->assertRedirect();
    }
}
