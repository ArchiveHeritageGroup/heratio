<?php

/**
 * SettingsLanguagesRegionalCodeTest - the Settings > Languages form can enable
 * the regional and variant locales Heratio ships (pt_BR, de_AT, de_CH,
 * ca@valencia), not only two- and three-letter codes. Case and separator are
 * normalised to the lang/*.json name, and malformed codes are still refused.
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
use Tests\TestCase;

class SettingsLanguagesRegionalCodeTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'langs-'.$id, 'email' => uniqid('langs-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        $this->admin = User::findOrFail($id);
    }

    private function add(string $input): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.languages'), ['action' => 'add', 'languageCode' => $input])
            ->assertRedirect(route('settings.languages'));
    }

    private function enabled(string $code): bool
    {
        return DB::table('setting')->where('scope', 'i18n_languages')->whereRaw('BINARY name = ?', [$code])->exists();
    }

    private function forget(string $code): void
    {
        $ids = DB::table('setting')->where('scope', 'i18n_languages')->where('name', $code)->pluck('id');
        DB::table('setting_i18n')->whereIn('id', $ids)->delete();
        DB::table('setting')->whereIn('id', $ids)->delete();
    }

    public function test_a_region_locale_can_be_enabled(): void
    {
        $this->forget('pt_BR');
        $this->add('pt_BR');

        $this->assertTrue($this->enabled('pt_BR'));
    }

    public function test_case_and_separator_are_normalised_to_the_locale_file_name(): void
    {
        $this->forget('de_AT');
        $this->add('de-at');

        $this->assertTrue($this->enabled('de_AT'));
        $this->assertFalse($this->enabled('de-at'));
    }

    public function test_a_variant_locale_can_be_enabled(): void
    {
        $this->forget('ca@valencia');
        $this->add('ca@valencia');

        $this->assertTrue($this->enabled('ca@valencia'));
    }

    public function test_a_plain_code_still_works(): void
    {
        $this->forget('tlh');
        $this->add('TLH');

        $this->assertTrue($this->enabled('tlh'));
    }

    public function test_malformed_codes_are_refused(): void
    {
        foreach (['english', 'pt_BRA', 'pt_', 'p', 'ca@x', 'en;drop'] as $bad) {
            $before = DB::table('setting')->where('scope', 'i18n_languages')->count();
            $this->actingAs($this->admin)
                ->post(route('settings.languages'), ['action' => 'add', 'languageCode' => $bad])
                ->assertRedirect(route('settings.languages'))
                ->assertSessionHas('error');
            $this->assertSame($before, DB::table('setting')->where('scope', 'i18n_languages')->count(), "accepted: {$bad}");
        }
    }
}
