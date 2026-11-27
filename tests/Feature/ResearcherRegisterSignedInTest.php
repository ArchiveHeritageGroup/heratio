<?php

/**
 * ResearcherRegisterSignedInTest - a signed-in user who is not yet a researcher
 * gets the "register as a researcher" form, not the front page (27 Nov 2026).
 * My Workspace and every other research page send such a user to
 * /research/register; the public sign-up form at that URL sent anyone signed in
 * to "/", so "My Workspace" opened the landing page.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResearcherRegisterSignedInTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        \AhgCore\Services\AclService::forgetUser();
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'rreg-'.$id, 'email' => uniqid('rreg-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 101]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_my_workspace_leads_a_signed_in_non_researcher_to_the_registration_form(): void
    {
        $this->actingAs($this->user());
        $this->get('/research/workspace')->assertRedirect(route('researcher.register'));

        $res = $this->get('/research/register');
        $this->assertNotSame(url('/'), $res->headers->get('Location'), 'not sent to the front page');
        $res->assertOk();
    }

    public function test_guests_still_get_the_public_sign_up_form(): void
    {
        $this->get('/research/register')->assertOk()->assertSee('name="password"', false);
    }
}
