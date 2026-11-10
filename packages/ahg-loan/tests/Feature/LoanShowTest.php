<?php

/**
 * LoanShowTest - a loan with objects opens (10 Nov 2026: every loan page was a
 * 500, "Call to a member function isNotEmpty() on array", because
 * LoanService::getObjects() returns an array and the view called collection
 * methods on it).
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

class LoanShowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_loan_with_an_object_opens(): void
    {
        if (! Schema::hasTable('ahg_loan') || ! Schema::hasTable('ahg_loan_object')) {
            $this->markTestSkipped('ahg_loan tables missing');
        }
        $loan = (int) DB::table('ahg_loan')->insertGetId(['loan_number' => 'ZZ-LO-'.uniqid(), 'loan_type' => 'out', 'sector' => 'museum',
            'partner_institution' => 'ZZ Test Museum', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ahg_loan_object')->insert(['loan_id' => $loan, 'object_title' => 'ZZ loaned object', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->makeAdmin())->get(route('loan.show', $loan))->assertOk()->assertSee('ZZ Test Museum')->assertSee('ZZ loaned object');
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'loan-admin-'.$id, 'email' => uniqid('loan-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
