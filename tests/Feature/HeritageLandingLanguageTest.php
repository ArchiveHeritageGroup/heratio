<?php

/**
 * HeritageLandingLanguageTest - the heritage landing page's hero text can be set
 * per language (27 Nov 2026). Before, heritage_landing_config held one tagline
 * for every language, so an Afrikaans visitor saw the English headline. Now
 * /heritage/admin/config?lang=af stores Afrikaans text, the landing page shows
 * it to Afrikaans visitors, and blank fields fall back to the main text.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
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

class HeritageLandingLanguageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('heritage_landing_config_i18n')) {
            (require base_path('database/migrations/2026_11_27_120000_create_heritage_landing_config_i18n.php'))->up();
        }
    }

    private function enable(string $code): void
    {
        if (DB::table('setting')->where('scope', 'i18n_languages')->where('name', $code)->exists()) {
            DB::table('setting')->where('scope', 'i18n_languages')->where('name', $code)->update(['editable' => 1]);
            return;
        }
        $id = DB::table('object')->insertGetId(['class_name' => 'QubitSetting', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('setting')->insert(['id' => $id, 'name' => $code, 'scope' => 'i18n_languages', 'editable' => 1, 'deleteable' => 1, 'source_culture' => 'en']);
    }

    private function admin(): User
    {
        \AhgCore\Services\AclService::forgetUser();
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'hlang-'.$id, 'email' => uniqid('hlang-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_afrikaans_text_is_saved_and_shown_with_fallback(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->enable('en');
        $this->enable('af');
        $cfg = DB::table('heritage_landing_config')->first();
        $cfgId = $cfg ? $cfg->id : DB::table('heritage_landing_config')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('heritage_landing_config')->where('id', $cfgId)->update(['hero_tagline' => 'Main tagline', 'hero_subtext' => 'Main subtext']);

        $this->actingAs($this->admin())
            ->post('/heritage/admin/config', ['lang' => 'af', 'hero_tagline' => 'Afrikaanse slagspreuk', 'hero_subtext' => '', 'suggested_searches' => "begrafnis\nDoornkloof"])
            ->assertRedirect();

        $row = DB::table('heritage_landing_config_i18n')->where('config_id', $cfgId)->where('culture', 'af')->first();
        $this->assertSame('Afrikaanse slagspreuk', $row->hero_tagline);
        $this->assertSame(['begrafnis', 'Doornkloof'], json_decode($row->suggested_searches, true));
        $this->assertSame('Main tagline', DB::table('heritage_landing_config')->where('id', $cfgId)->value('hero_tagline'), 'the main text is untouched');

        $af = $this->get('/heritage?sf_culture=af')->getContent();
        $this->assertStringContainsString('Afrikaanse slagspreuk', $af);
        $this->assertStringContainsString('Main subtext', $af, 'a blank Afrikaans field falls back to the main text');

        $en = $this->get('/heritage?sf_culture=en')->getContent();
        $this->assertStringContainsString('Main tagline', $en);
        $this->assertStringNotContainsString('Afrikaanse slagspreuk', $en);
    }
}
