<?php

/**
 * RicViewModeScopeTest - a visitor's RiC/standard view switch only changes their
 * own session; only staff who may edit set a record's view for everyone. An
 * anonymous POST used to flip a record's default view for every visitor.
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
use AhgRic\Services\RicViewModeService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RicViewModeScopeTest extends TestCase
{
    use DatabaseTransactions;

    private const ID = 999999123;

    private function stored(): ?string
    {
        return DB::table('ric_entity_view')->where('entity_type', 'museum')->where('entity_id', self::ID)->value('view_mode');
    }

    public function test_an_anonymous_switch_changes_only_the_visitors_session(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->post(route('ric.set-view-mode'), ['mode' => 'ric', 'entity_type' => 'museum', 'object_id' => self::ID])
            ->assertRedirect();

        $this->assertNull($this->stored(), 'nothing stored for everyone');
        $this->assertSame('ric', RicViewModeService::mode('museum', self::ID), 'the visitor sees their own choice');

        $this->flushSession();
        $this->assertSame(RicViewModeService::defaultMode(), RicViewModeService::mode('museum', self::ID), 'other visitors are unaffected');
    }

    public function test_an_editor_sets_the_view_for_everyone(): void
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'vm-'.$id, 'email' => uniqid('vm-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        $this->actingAs(User::findOrFail($id))->withoutMiddleware(VerifyCsrfToken::class)
            ->post(route('ric.set-view-mode'), ['mode' => 'ric', 'entity_type' => 'museum', 'object_id' => self::ID])
            ->assertRedirect();

        $this->assertSame('ric', $this->stored());
    }
}
