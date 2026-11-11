<?php

/**
 * ValidateCommitButtonTest - the validation page's "Commit (N rows)" button
 * goes to the commit step (10 Nov 2026). It posts form_action=commit to the
 * validate route, which handled only "proceed" and re-validated, so the button
 * did nothing; only the longer way through Preview committed.
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

class ValidateCommitButtonTest extends TestCase
{
    use DatabaseTransactions;

    public function test_commit_on_the_validation_page_goes_to_the_commit_step(): void
    {
        $admin = $this->makeAdmin();
        $id = (int) DB::table('ingest_session')->insertGetId([
            'user_id' => $admin->id, 'title' => 'ZZ validate commit', 'sector' => 'archive', 'standard' => 'isadg',
            'parent_placement' => 'top_level', 'output_create_records' => 1, 'status' => 'validate', 'created_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('ingest.validate', $id), ['form_action' => 'commit'])
            ->assertRedirect(route('ingest.commit', $id));
        $this->assertSame('commit', DB::table('ingest_session')->where('id', $id)->value('status'));
    }

    public function test_unticked_checkboxes_on_the_configure_form_are_saved_as_off(): void
    {
        // Only title and sector posted: SIP/AIP/DIP and the rest are unticked.
        $this->actingAs($this->makeAdmin())->post(route('ingest.configure'), ['title' => 'ZZ untick test', 'sector' => 'archive', 'standard' => 'isadg',
            'parent_placement' => 'top_level', 'output_create_records' => '1']);
        $s = DB::table('ingest_session')->where('title', 'ZZ untick test')->orderByDesc('id')->first();
        $this->assertNotNull($s);
        $this->assertSame(1, (int) $s->output_create_records);
        $this->assertSame([0, 0, 0], [(int) $s->output_generate_sip, (int) $s->output_generate_aip, (int) $s->output_generate_dip]);
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'vc-admin-'.$id, 'email' => uniqid('vc-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
