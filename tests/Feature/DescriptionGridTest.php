<?php

/**
 * DescriptionGridTest - grid editing of archival descriptions (heratio#1581).
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
use AhgCore\Services\AclService;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DescriptionGridTest extends TestCase
{
    use DatabaseTransactions;

    private int $fonds;

    private int $item;

    protected function setUp(): void
    {
        parent::setUp();
        AclService::forgetUser();
        $this->fonds = Io::create(['title' => 'ZZ Grid fonds', 'identifier' => 'ZZG'], 'en');
        $this->item = Io::create(['title' => 'ZZ Grid item', 'identifier' => 'ZZG/1', 'parent_id' => $this->fonds,
            'extent_and_medium' => '3 boxes', 'scope_and_content' => 'Letters'], 'en');
        // A known, older timestamp, so a save that changes content visibly moves it.
        DB::table('object')->where('id', $this->item)->update(['updated_at' => '2026-01-01 10:00:00']);
    }

    public function test_a_row_saves_through_the_service(): void
    {
        $this->actingAs($this->user(101))
            ->postJson(route('informationobject.grid.save'), ['id' => $this->item, 'updated_at' => '2026-01-01 10:00:00',
                'fields' => ['title' => 'ZZ Grid item renamed', 'scope_and_content' => 'Hello<script>alert(1)</script>']])
            ->assertOk()->assertJson(['ok' => true]);

        $row = DB::table('information_object_i18n')->where('id', $this->item)->where('culture', 'en')->first();
        $this->assertSame('ZZ Grid item renamed', $row->title);
        // The service sanitises scope and content and moves "last modified".
        $this->assertStringNotContainsString('<script', (string) $row->scope_and_content);
        $this->assertNotSame('2026-01-01 10:00:00', (string) DB::table('object')->where('id', $this->item)->value('updated_at'));
    }

    public function test_a_row_changed_since_the_grid_loaded_is_refused(): void
    {
        $this->actingAs($this->user(101))
            ->postJson(route('informationobject.grid.save'), ['id' => $this->item, 'updated_at' => '2025-12-31 09:00:00',
                'fields' => ['title' => 'ZZ Grid overwrite']])
            ->assertStatus(409)->assertJson(['ok' => false, 'updated_at' => '2026-01-01 10:00:00']);

        $this->assertSame('ZZ Grid item', DB::table('information_object_i18n')->where('id', $this->item)->where('culture', 'en')->value('title'));
    }

    public function test_only_the_chosen_fields_change(): void
    {
        $before = (array) DB::table('information_object_i18n')->where('id', $this->item)->where('culture', 'en')->first();
        $ioBefore = (array) DB::table('information_object')->where('id', $this->item)->first();

        $this->actingAs($this->user(101))
            ->postJson(route('informationobject.grid.save'), ['id' => $this->item, 'updated_at' => '2026-01-01 10:00:00',
                'fields' => ['extent_and_medium' => '4 boxes']])
            ->assertOk();

        $after = (array) DB::table('information_object_i18n')->where('id', $this->item)->where('culture', 'en')->first();
        $this->assertSame('4 boxes', $after['extent_and_medium']);
        unset($before['extent_and_medium'], $after['extent_and_medium']);
        $this->assertSame($before, $after);
        $this->assertSame($ioBefore, (array) DB::table('information_object')->where('id', $this->item)->first());

        // An unknown column is refused, not ignored.
        $this->actingAs($this->user(101))
            ->postJson(route('informationobject.grid.save'), ['id' => $this->item, 'updated_at' => (string) DB::table('object')->where('id', $this->item)->value('updated_at'),
                'fields' => ['parent_id' => 1]])
            ->assertStatus(422);
    }

    public function test_the_grid_is_for_staff_only(): void
    {
        $plain = $this->user();
        $this->actingAs($plain)->get(route('informationobject.grid', ['collection' => $this->fonds]))->assertForbidden();
        $this->actingAs($plain)->postJson(route('informationobject.grid.save'), ['id' => $this->item, 'updated_at' => '2026-01-01 10:00:00',
            'fields' => ['title' => 'ZZ Grid by visitor']])->assertForbidden();
        $this->assertSame('ZZ Grid item', DB::table('information_object_i18n')->where('id', $this->item)->where('culture', 'en')->value('title'));

        $html = $this->actingAs($this->user(101))->get(route('informationobject.grid', ['collection' => $this->fonds]))
            ->assertOk()->getContent();
        $this->assertStringContainsString('role="grid"', $html);
        $this->assertStringContainsString('ZZ Grid item', $html);
        $this->assertStringContainsString('data-updated="2026-01-01 10:00:00"', $html);
    }

    public function test_the_current_culture_is_honoured(): void
    {
        $this->actingAs($this->user(101))->withSession(['locale' => 'fr'])
            ->postJson(route('informationobject.grid.save'), ['id' => $this->item, 'updated_at' => '2026-01-01 10:00:00',
                'fields' => ['title' => 'ZZ Grille article', 'extent_and_medium' => '3 boxes']])
            ->assertOk();

        $fr = DB::table('information_object_i18n')->where('id', $this->item)->where('culture', 'fr')->first();
        $this->assertSame('ZZ Grille article', $fr->title);
        // Unchanged from the English source text: not stored for French (translation-aware save).
        $this->assertNull($fr->extent_and_medium);
        $this->assertSame('ZZ Grid item', DB::table('information_object_i18n')->where('id', $this->item)->where('culture', 'en')->value('title'));
    }

    private function user(?int $group = null): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'dgt-'.$id, 'email' => uniqid('dgt-', true).'@example.test',
            'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
