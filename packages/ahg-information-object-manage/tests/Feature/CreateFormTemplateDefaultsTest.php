<?php

/**
 * CreateFormTemplateDefaultsTest - new descriptions open with template defaults.
 *
 * heratio#1537: form-template default values applied on edit only; the create
 * form opened blank. It now pre-fills from the template that resolves for the
 * new record, for that one request only, so a default never sticks to a later
 * form (the failure the old sticky _old_input write caused).
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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateFormTemplateDefaultsTest extends TestCase
{
    use DatabaseTransactions;

    private const DEFAULT_TEXT = 'Default scope from the template (heratio#1537)';

    private int $fieldId;

    /**
     * ahg_form_template.config is missing on installs built from the old
     * install.sql, heratio_test included (the 2026_10_08 migration adds it on
     * deploy). Add it before DatabaseTransactions opens, since DDL commits.
     */
    protected function setUpTraits()
    {
        if (Schema::hasTable('ahg_form_template') && ! Schema::hasColumn('ahg_form_template', 'config')) {
            Schema::table('ahg_form_template', fn ($t) => $t->text('config')->nullable());
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ahg_form_template', 'ahg_form_field', 'ahg_form_assignment'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("{$t} missing");
            }
        }

        // Only this template may resolve: park existing assignments (rolled back).
        DB::table('ahg_form_assignment')->update(['is_active' => 0]);
        $templateId = (int) DB::table('ahg_form_template')->insertGetId([
            'name' => 'ZZ defaults test', 'form_type' => 'information_object',
            'is_default' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->fieldId = (int) DB::table('ahg_form_field')->insertGetId([
            'template_id' => $templateId, 'field_name' => 'scopeAndContent', 'field_type' => 'textarea',
            'label' => 'Scope and content', 'default_value' => self::DEFAULT_TEXT, 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_create_form_opens_with_the_template_default(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('informationobject.create'))
            ->assertOk()
            ->assertSee(self::DEFAULT_TEXT);
    }

    public function test_the_default_does_not_stick_to_a_later_form(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('informationobject.create'))->assertSee(self::DEFAULT_TEXT);

        DB::table('ahg_form_field')->where('id', $this->fieldId)->update(['default_value' => null]);

        $this->actingAs($admin)->get(route('informationobject.create'))
            ->assertOk()
            ->assertDontSee(self::DEFAULT_TEXT);
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'tpl-admin-'.$id, 'email' => uniqid('tpl-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
