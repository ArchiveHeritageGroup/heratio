<?php

/**
 * RetentionScheduleTypeTest - heratio#1737. A retention schedule records its
 * type (from the Dropdown Manager, e.g. "Schedule of records other than
 * correspondence") and its disposal authority number, and shows both.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RetentionScheduleTypeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_schedule_type_and_disposal_authority_are_saved_and_shown(): void
    {
        if (! Schema::hasColumn('rm_retention_schedule', 'disposal_authority_number')) {
            $this->markTestSkipped('Not migrated');
        }
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'rs-'.$id, 'email' => uniqid('rs-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();
        $admin = User::findOrFail($id);

        $this->actingAs($admin)->post(route('records.schedules.store'), [
            'schedule_ref' => 'ZZ-ROTC-1', 'title' => 'ZZ Records other than correspondence',
            'schedule_type' => 'records_other_than_correspondence', 'disposal_authority_number' => 'DA-2026/017',
        ])->assertRedirect();

        $row = DB::table('rm_retention_schedule')->where('schedule_ref', 'ZZ-ROTC-1')->first();
        $this->assertSame('records_other_than_correspondence', $row->schedule_type);
        $this->assertSame('DA-2026/017', $row->disposal_authority_number);
        $this->actingAs($admin)->get(route('records.schedules.show', $row->id))->assertOk()
            ->assertSee('Schedule of records other than correspondence')->assertSee('DA-2026/017');

        $this->actingAs($admin)->post(route('records.schedules.store'), [
            'schedule_ref' => 'ZZ-BAD', 'title' => 'x', 'schedule_type' => 'not_a_type',
        ])->assertSessionHasErrors('schedule_type');
    }
}
