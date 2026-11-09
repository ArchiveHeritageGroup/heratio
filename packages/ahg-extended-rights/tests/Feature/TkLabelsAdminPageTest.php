<?php

/**
 * TkLabelsAdminPageTest - the admin TK labels page renders its assignment list
 * (heratio#1587: it 500ed on `$assign->slug` once one label was assigned).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TkLabelsAdminPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_page_lists_an_assignment_with_its_label_and_record_link(): void
    {
        if (! Schema::hasTable('icip_tk_label') || ! Schema::hasTable('icip_tk_label_type')) {
            $this->markTestSkipped('ICIP label tables not installed.');
        }
        $io = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => 'TK page test record']);
        DB::table('slug')->insert(['object_id' => $io, 'slug' => 'tk-page-test-'.$io]);
        $type = (int) (DB::table('icip_tk_label_type')->where('code', 'zz_tk_test')->value('id')
            ?: DB::table('icip_tk_label_type')->insertGetId(['code' => 'zz_tk_test', 'category' => 'TK', 'name' => 'ZZ Test Label']));
        DB::table('icip_tk_label')->insert(['information_object_id' => $io, 'label_type_id' => $type]);

        $uid = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $uid, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $uid, 'username' => 'tk-'.$uid, 'email' => uniqid('tk-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $uid, 'group_id' => 100]);
        Cache::forget("acl_groups_{$uid}");

        $this->actingAs(User::findOrFail($uid))->get(route('ext-rights-admin.tk-labels'))->assertOk()
            ->assertSee('ZZ Test Label')->assertSee('TK page test record')->assertSee(url('tk-page-test-'.$io), false);
    }
}
