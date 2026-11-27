<?php

/**
 * ApiPrivacyRoutesGateTest - the privacy registers and API log are for
 * administrators, and the internal count routes are for staff (26 Nov 2026).
 * Before, /api/v2 privacy/dsars, privacy/breaches and audit needed only the
 * read scope, and /api/reports/pending-counts and /api/export-preview were
 * public.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiPrivacyRoutesGateTest extends TestCase
{
    use DatabaseTransactions;

    private const ADMIN_ONLY = ['/api/v2/privacy/dsars', '/api/v2/privacy/breaches', '/api/v2/audit'];

    private const STAFF_ONLY = ['/api/reports/pending-counts', '/api/export-preview'];

    private function userIn(int $group, array $notIn = []): ?User
    {
        $q = DB::table('acl_user_group')->where('group_id', $group);
        if ($notIn) {
            $q->whereNotIn('user_id', DB::table('acl_user_group')->whereIn('group_id', $notIn)->pluck('user_id'));
        }
        $id = (int) $q->value('user_id');

        return $id ? User::find($id) : null;
    }

    public function test_guests_get_none_of_them(): void
    {
        foreach ([...self::ADMIN_ONLY, ...self::STAFF_ONLY] as $p) {
            $this->assertSame(401, $this->getJson($p)->status(), $p);
        }
    }

    public function test_an_editor_gets_the_counts_but_not_the_registers(): void
    {
        // An editor who is not an administrator, made inside this test's transaction.
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'apigate-'.$id, 'email' => uniqid('apigate-', true).'@example.test',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 101]);
        \Illuminate\Support\Facades\Cache::forget("acl_groups_{$id}");
        \AhgCore\Services\AclService::forgetUser();
        $editor = User::findOrFail($id);
        foreach (self::ADMIN_ONLY as $p) {
            $this->assertSame(403, $this->actingAs($editor)->getJson($p)->status(), $p);
        }
        foreach (self::STAFF_ONLY as $p) {
            $this->assertSame(200, $this->actingAs($editor)->getJson($p)->status(), $p);
        }
    }

    public function test_an_administrator_gets_everything(): void
    {
        \AhgCore\Services\AclService::forgetUser();
        $admin = $this->userIn(100);
        if (! $admin) {
            $this->markTestSkipped('No administrator in this install.');
        }
        foreach ([...self::ADMIN_ONLY, ...self::STAFF_ONLY] as $p) {
            $this->assertSame(200, $this->actingAs($admin)->getJson($p)->status(), $p);
        }
    }
}
