<?php

/**
 * SmallDefects1590Test - heratio#1590: ingest list without duplicates, the API
 * docs open for a signed-in admin, help front matter stripped, the AI condition
 * dashboard counting dismissals, and the bare condition path redirecting.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgHelp\Commands\IngestHelpArticleCommand;
use AhgIngest\Services\IngestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmallDefects1590Test extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        // Two languages for the same person: the old join listed each session twice.
        DB::table('actor_i18n')->insert([['id' => $id, 'culture' => 'en', 'authorized_form_of_name' => 'Ingest Admin'], ['id' => $id, 'culture' => 'fr', 'authorized_form_of_name' => null]]);
        DB::table('user')->insert(['id' => $id, 'username' => 'sd-'.$id, 'email' => uniqid('sd-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_each_ingest_session_is_listed_once_with_its_user(): void
    {
        $admin = $this->admin();
        $svc = new IngestService();
        $sid = $svc->createSession($admin->id, ['title' => 'ZZ once']);

        $rows = array_values(array_filter($svc->getSessions($admin->id), fn ($r) => (int) $r->id === $sid));
        $this->assertCount(1, $rows);
        $this->assertSame('Ingest Admin', $rows[0]->user_name);
    }

    public function test_the_api_docs_open_for_a_signed_in_admin(): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'openapi_public'], ['setting_value' => '0']);
        \AhgCore\Services\AhgSettingsService::clearCache();

        $this->get('/api/docs')->assertForbidden();
        $this->actingAs($this->admin())->get('/api/docs')->assertOk()->assertSee('Swagger', false);
    }

    public function test_help_front_matter_is_stripped_and_a_later_rule_kept(): void
    {
        $this->assertSame("# Title\n\nBody\n", IngestHelpArticleCommand::stripFrontMatter("---\ntitle: T\nslug: t\n---\n# Title\n\nBody\n"));
        $this->assertSame("# Title\n---\nx\n", IngestHelpArticleCommand::stripFrontMatter("# Title\n---\nx\n"));
    }

    public function test_the_condition_dashboard_does_not_count_a_dismissed_flag_as_pending_and_the_bare_path_redirects(): void
    {
        if (! Schema::hasColumn('ahg_ai_condition_assessment', 'review_status')) {
            $this->markTestSkipped('review_status missing: run the migrations.');
        }
        $admin = $this->admin();
        $this->actingAs($admin);
        $stats = fn () => app(\AhgAiServices\Controllers\AiController::class)->conditionDashboard()->getData()['stats'];
        $before = $stats();
        DB::table('ahg_ai_condition_assessment')->insert(['condition_grade' => 'good', 'review_status' => 'dismissed', 'source' => 'test', 'created_at' => now()]);
        $after = $stats();
        $this->get(route('admin.ai.condition.dashboard'))->assertOk();

        $this->assertSame($before['pending'], $after['pending'], 'a dismissed flag is not pending');
        $this->assertSame($before['dismissed'] + 1, $after['dismissed']);

        $this->actingAs($admin)->get('/admin/ai/condition')->assertRedirect(route('admin.ai.condition.browse'));
    }
}
