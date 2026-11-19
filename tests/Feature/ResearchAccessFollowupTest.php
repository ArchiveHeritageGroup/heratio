<?php

/**
 * ResearchAccessFollowupTest - heratio#1777 follow-ups. A private research
 * workspace is not shown to other researchers; extraction results are
 * decided only by their own researcher or project; a proposed entity match
 * is decided by staff or the researcher who proposed it.
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

class ResearchAccessFollowupTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['research_researcher', 'research_workspace', 'research_extraction_job', 'research_extraction_result', 'research_validation_queue', 'research_entity_resolution', 'research_project_collaborator'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t not present");
            }
        }
        AclService::forgetUser();
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array{0: User, 1: int} */
    private function researcher(): array
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'raf-'.$id, 'email' => uniqid('raf-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        Cache::forget("acl_groups_{$id}");
        $rid = (int) DB::table('research_researcher')->insertGetId(['user_id' => $id, 'first_name' => 'T', 'last_name' => 'R', 'email' => 'raf-'.$id.'@example.test', 'status' => 'approved']);

        return [User::findOrFail($id), $rid];
    }

    public function test_a_private_workspace_is_hidden_from_other_researchers(): void
    {
        [$owner, $ownerRid] = $this->researcher();
        [$other] = $this->researcher();
        $ws = DB::table('research_workspace')->insertGetId(['owner_id' => $ownerRid, 'name' => 'ZZ private', 'visibility' => 'private']);

        $this->actingAs($other)->get(route('research.viewWorkspace', $ws))->assertNotFound();
        AclService::forgetUser();
        $this->actingAs($owner)->get(route('research.viewWorkspace', $ws))->assertOk();

        DB::table('research_workspace')->where('id', $ws)->update(['visibility' => 'public']);
        AclService::forgetUser();
        $this->actingAs($other)->get(route('research.viewWorkspace', $ws))->assertOk();
    }

    public function test_extraction_results_are_decided_only_on_their_own_project(): void
    {
        [$owner, $ownerRid] = $this->researcher();
        [$other] = $this->researcher();
        $job = DB::table('research_extraction_job')->insertGetId(['project_id' => 987654, 'researcher_id' => $ownerRid, 'extraction_type' => 'ner']);
        $res = DB::table('research_extraction_result')->insertGetId(['job_id' => $job, 'object_id' => 1, 'result_type' => 'entity']);
        DB::table('research_validation_queue')->insert(['result_id' => $res, 'researcher_id' => $ownerRid, 'status' => 'pending']);

        $this->actingAs($other)->postJson(route('research.validateResult', $res), ['form_action' => 'reject'])->assertForbidden();
        $this->actingAs($other)->postJson(route('research.bulkValidate'), ['form_action' => 'reject', 'result_ids' => [$res]])->assertForbidden();
        $this->assertSame('pending', DB::table('research_validation_queue')->where('result_id', $res)->value('status'));

        AclService::forgetUser();
        $this->actingAs($owner)->postJson(route('research.validateResult', $res), ['form_action' => 'reject'])->assertOk();
    }

    public function test_an_entity_match_is_decided_by_staff_or_its_proposer(): void
    {
        [$proposer, $proposerRid] = $this->researcher();
        [$other] = $this->researcher();
        $er = DB::table('research_entity_resolution')->insertGetId([
            'entity_a_type' => 'actor', 'entity_a_id' => 1, 'entity_b_type' => 'actor', 'entity_b_id' => 2,
            'status' => 'proposed', 'proposer_id' => $proposerRid,
        ]);

        $this->actingAs($other)->postJson(route('research.resolveEntityResolution', $er), ['status' => 'rejected'])->assertForbidden();
        AclService::forgetUser();
        $this->actingAs($proposer)->postJson(route('research.resolveEntityResolution', $er), ['status' => 'rejected'])->assertOk();
        $this->assertSame('rejected', DB::table('research_entity_resolution')->where('id', $er)->value('status'));
    }
}
