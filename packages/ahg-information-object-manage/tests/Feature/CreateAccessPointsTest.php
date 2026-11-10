<?php

/**
 * CreateAccessPointsTest - access points entered on the ADD form are saved
 * (10 Nov 2026). The add form's subject/place/genre/name boxes had no script
 * behind them and store() never read them, so a new description could only
 * get access points afterwards, in Edit. Both forms now use the same
 * autocomplete, and store() and update() share saveAccessPoints().
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
use Tests\TestCase;

class CreateAccessPointsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_add_form_has_working_access_point_fields(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('informationobject.create'))->assertOk()
            ->assertSee('subjectAccessPointNames[]', false)->assertSee('nameAccessPointNames[]', false)
            ->assertDontSee('Type to add subject...');
    }

    public function test_access_points_entered_when_creating_are_saved(): void
    {
        $this->actingAs($this->makeAdmin())->post(route('informationobject.store'), [
            'title' => 'ZZ access points on create',
            'subjectAccessPointNames' => ['ZZ test subject harbours'],
            'placeAccessPointNames' => ['ZZ test place Simon\'s Town'],
            'nameAccessPointNames' => ['ZZ Test Harbour Board'],
        ])->assertRedirect();

        $io = (int) DB::table('information_object_i18n')->where('title', 'ZZ access points on create')->value('id');
        $this->assertGreaterThan(0, $io);
        $terms = DB::table('object_term_relation as r')->join('term_i18n as t', 't.id', '=', 'r.term_id')
            ->where('r.object_id', $io)->pluck('t.name')->all();
        $this->assertContains('ZZ test subject harbours', $terms);
        $this->assertContains('ZZ test place Simon\'s Town', $terms);
        $names = DB::table('relation as r')->join('actor_i18n as a', 'a.id', '=', 'r.object_id')
            ->where('r.subject_id', $io)->where('r.type_id', 161)->pluck('a.authorized_form_of_name')->all();
        $this->assertContains('ZZ Test Harbour Board', $names);
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'ap-admin-'.$id, 'email' => uniqid('ap-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
