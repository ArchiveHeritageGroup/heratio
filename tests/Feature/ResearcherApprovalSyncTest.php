<?php

/**
 * ResearcherApprovalSyncTest - approving a researcher registration from either
 * admin page approves both the researcher and their access request
 * (27 Nov 2026). Before, approving the researcher left the access request
 * pending (the admin banner kept counting it), and approving the access request
 * left the researcher pending (no workspace).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgAccessRequest\Services\AccessRequestService;
use AhgResearch\Services\ResearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResearcherApprovalSyncTest extends TestCase
{
    use DatabaseTransactions;

    /** A pending researcher and its pending access request; returns [researcher id, access request id]. */
    private function pendingRegistration(): array
    {
        $uid = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $uid, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $uid, 'username' => 'rsync-'.$uid, 'email' => uniqid('rsync-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        $rid = (int) DB::table('research_researcher')->insertGetId(['user_id' => $uid, 'first_name' => 'Test', 'last_name' => 'Researcher',
            'email' => 'rsync-'.$uid.'@example.test', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $aid = (int) DB::table('access_request')->insertGetId(['request_type' => 'researcher', 'scope_type' => 'single', 'user_id' => $uid, 'requested_classification_id' => 2,
            'current_classification_id' => 1, 'reason' => 'Researcher registration', 'urgency' => 'normal',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return [$rid, $aid];
    }

    public function test_approving_the_researcher_closes_the_access_request(): void
    {
        [$rid, $aid] = $this->pendingRegistration();
        app(ResearchService::class)->approveResearcher($rid, 1);
        $this->assertSame('approved', DB::table('access_request')->where('id', $aid)->value('status'));
    }

    public function test_approving_the_access_request_approves_the_researcher(): void
    {
        [$rid, $aid] = $this->pendingRegistration();
        app(AccessRequestService::class)->approveRequest($aid, 1);
        $this->assertSame('approved', DB::table('research_researcher')->where('id', $rid)->value('status'));
    }
}
