<?php

/**
 * ConditionProposalTest - condition flags through the proposals API
 * (heratio#1558 flag_condition): queued as pending AI condition assessments,
 * confirmed or dismissed by a reviewer, and reported back to the proposer.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConditionProposalTest extends TestCase
{
    use DatabaseTransactions;

    private string $slug;

    private int $io;

    private string $grade;

    private string $type;

    private ?string $severity;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasColumn('ahg_ai_condition_assessment', 'review_status')) {
            $this->markTestSkipped('ahg_ai_condition_assessment.review_status missing: run the migration.');
        }
        // A fresh install (CI) may not have the condition dropdowns seeded:
        // add one value where a group is empty, inside the test transaction.
        $code = function (string $group): string {
            $found = DB::table('ahg_dropdown')->where('taxonomy', $group)->where('is_active', 1)->orderBy('sort_order')->value('code');
            if ($found) {
                return $found;
            }
            DB::table('ahg_dropdown')->insert(['taxonomy' => $group, 'taxonomy_label' => $group, 'code' => 'test_'.$group, 'label' => 'Test '.$group, 'is_active' => 1]);

            return 'test_'.$group;
        };
        [$this->grade, $this->type, $this->severity] = [$code('condition_grade'), $code('damage_type'), $code('damage_severity')];

        $now = now();
        $this->io = DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $this->io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $this->io, 'culture' => 'en', 'title' => 'Condition flag test']);
        $this->slug = 'condition-flag-'.$this->io;
        DB::table('slug')->insert(['object_id' => $this->io, 'slug' => $this->slug]);
    }

    private function user(int $groupId): int
    {
        $user = app(UserService::class)->create(['username' => 'cf'.bin2hex(random_bytes(4)), 'email' => 'cf'.bin2hex(random_bytes(4)).'@test.invalid',
            'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => 'Condition flagger', 'active' => 1, 'groups' => []]);
        DB::table('acl_user_group')->insert(['user_id' => $user, 'group_id' => $groupId]);
        Cache::forget("acl_groups_{$user}");

        return $user;
    }

    private function flag(string $key, array $conditions)
    {
        return $this->withHeaders(['X-API-Key' => $key])->postJson("/api/v2/descriptions/{$this->slug}/proposals", ['conditions' => $conditions, 'source' => 'glam-mcp']);
    }

    public function test_a_flag_is_queued_reviewed_and_reported_back(): void
    {
        $editor = $this->user(\AhgCore\Models\AclGroup::EDITOR_ID);
        $key = app(\AhgApi\Services\ApiKeyService::class)->issue($editor, 'condition test', ['read', 'write'])['api_key'];
        $flag = ['grade' => $this->grade, 'note' => 'Tear along the lower fold of the scan.', 'model' => 'test-model',
            'damages' => [['type' => $this->type, 'severity' => $this->severity ?? '', 'description' => 'About 4 cm', 'confidence' => 0.8]]];

        $res = $this->flag($key, [$flag])->assertCreated();
        $this->assertCount(1, $res->json('data.conditions'));
        $id = $res->json('data.conditions.0.id');

        $row = DB::table('ahg_ai_condition_assessment')->where('id', $id)->first();
        $this->assertSame(['api', 'pending', 0, $this->io, $this->grade, 'test-model'],
            [$row->source, $row->review_status, (int) $row->is_confirmed, (int) $row->information_object_id, $row->condition_grade, $row->model_version]);
        $damage = DB::table('ahg_ai_condition_damage')->where('assessment_id', $id)->first();
        $this->assertSame([$this->type, $this->severity, '0.800'], [$damage->damage_type, $damage->severity, (string) $damage->confidence]);

        // The same flag again is not queued twice.
        $this->assertSame(1, $this->flag($key, [$flag])->assertOk()->json('data.conditions_skipped_existing'));

        $mine = fn () => collect($this->withHeaders(['X-API-Key' => $key])->getJson('/api/v2/proposals?mine=1')->assertOk()->json('data.results'))
            ->firstWhere('kind', 'condition');
        $this->assertSame('pending', $mine()['status']);

        // A reviewer opens it and dismisses it; the proposer then sees the outcome.
        $admin = User::findOrFail($this->user(100));
        $this->actingAs($admin)->get(route('admin.ai.condition.view', $id))->assertOk()
            ->assertSee('Tear along the lower fold')->assertSee(__('Dismiss'));
        $this->actingAs($admin)->post(route('admin.ai.condition.decision', $id), ['decision' => 'dismissed'])
            ->assertRedirect(route('admin.ai.condition.view', $id));
        $this->assertSame('dismissed', DB::table('ahg_ai_condition_assessment')->where('id', $id)->value('review_status'));
        $this->actingAs($admin)->get(route('admin.ai.condition.browse', ['status' => 'dismissed']))->assertOk()
            ->assertSee(route('admin.ai.condition.view', $id), false);
        $this->app['auth']->forgetGuards(); // back to the proposer's key, not the reviewer's session
        $this->assertSame('dismissed', $mine()['status']);
    }

    public function test_values_outside_the_dropdowns_are_refused(): void
    {
        $editor = $this->user(\AhgCore\Models\AclGroup::EDITOR_ID);
        $key = app(\AhgApi\Services\ApiKeyService::class)->issue($editor, 'condition test', ['read', 'write'])['api_key'];

        $this->flag($key, [['note' => 'x', 'damages' => [['type' => 'zz-not-a-type']]]])->assertStatus(400);
        $this->flag($key, [['note' => 'x', 'grade' => 'zz-not-a-grade']])->assertStatus(400);
        $this->flag($key, [['grade' => $this->grade]])->assertStatus(400); // a note is required
        $this->assertSame(0, DB::table('ahg_ai_condition_assessment')->where('information_object_id', $this->io)->count());
    }
}
