<?php

/**
 * ResearchPortalSecurityTest - access control in the research portal: projects,
 * reports, collaboration, studio, journals, shared directories, sign-in of
 * unapproved researchers, staff-only dashboard data, password resets, the cite
 * page and TomSelect rendering.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResearchPortalSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        foreach (['research_researcher', 'research_project', 'research_project_collaborator', 'research_report_section', 'research_journal'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /** @return array{0: User, 1: int} user and research_researcher.id */
    private function researcher(string $status = 'approved', bool $admin = false, string $password = 'secret-pass-1'): array
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        $email = uniqid('rsec-', true).'@example.test';
        DB::table('user')->insert(['id' => $id, 'username' => 'rsec-'.$id, 'email' => $email, 'active' => 1]
            + LoginController::hashPassword($password));
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $admin ? 100 : 99]);
        Cache::forget("acl_groups_{$id}");
        $rid = (int) DB::table('research_researcher')->insertGetId([
            'user_id' => $id, 'first_name' => 'Test', 'last_name' => 'Researcher '.$id, 'email' => $email,
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [User::findOrFail($id), $rid];
    }

    private function project(int $ownerRid): int
    {
        $pid = (int) DB::table('research_project')->insertGetId(['owner_id' => $ownerRid, 'title' => 'Private project', 'created_at' => now()]);
        DB::table('research_project_collaborator')->insert(['project_id' => $pid, 'researcher_id' => $ownerRid, 'role' => 'owner', 'status' => 'accepted']);

        return $pid;
    }

    public function test_a_stranger_cannot_open_someone_elses_project_or_its_studio_and_collaboration(): void
    {
        [$owner, $ownerRid] = $this->researcher();
        [$stranger] = $this->researcher();
        $pid = $this->project($ownerRid);

        $this->actingAs($owner)->get(route('research.viewProject', $pid))->assertOk();
        $this->actingAs($owner)->get(route('research.collabPoll', $pid))->assertOk();

        $this->actingAs($stranger)->get(route('research.viewProject', $pid))->assertForbidden();
        $this->actingAs($stranger)->get(route('research.studio', $pid))->assertForbidden();
        $this->actingAs($stranger)->get(route('research.collabPoll', $pid))->assertForbidden();
    }

    public function test_a_report_cannot_edit_or_delete_another_reports_sections(): void
    {
        [$me, $myRid] = $this->researcher();
        [, $otherRid] = $this->researcher();
        $mine = (int) DB::table('research_report')->insertGetId(['researcher_id' => $myRid, 'title' => 'Mine', 'created_at' => now()]);
        $theirs = (int) DB::table('research_report')->insertGetId(['researcher_id' => $otherRid, 'title' => 'Theirs', 'created_at' => now()]);
        $section = (int) DB::table('research_report_section')->insertGetId(['report_id' => $theirs, 'title' => 'Original', 'sort_order' => 0]);

        $this->actingAs($me)->post(route('research.viewReport', $mine), ['form_action' => 'update_section', 'section_id' => $section, 'title' => 'Hijacked', 'content' => 'x']);
        $this->actingAs($me)->post(route('research.viewReport', $mine), ['form_action' => 'delete_section', 'section_id' => $section]);

        $this->assertSame('Original', DB::table('research_report_section')->where('id', $section)->value('title'));
    }

    public function test_the_journals_list_shows_only_the_researchers_own_journals(): void
    {
        [$me, $myRid] = $this->researcher();
        [, $otherRid] = $this->researcher();
        DB::table('research_journal')->insert(['title' => 'My own journal', 'researcher_id' => $myRid, 'kind' => 'manuscript', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('research_journal')->insert(['title' => 'Somebody elses journal', 'researcher_id' => $otherRid, 'kind' => 'manuscript', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($me)->get(route('research.journal-builder.index'))
            ->assertOk()->assertSee('My own journal')->assertDontSee('Somebody elses journal');
    }

    public function test_an_article_cannot_be_filed_under_another_journals_issue(): void
    {
        [$me, $myRid] = $this->researcher();
        [, $otherRid] = $this->researcher();
        $mine = (int) DB::table('research_journal')->insertGetId(['title' => 'Mine', 'researcher_id' => $myRid, 'created_at' => now(), 'updated_at' => now()]);
        $theirs = (int) DB::table('research_journal')->insertGetId(['title' => 'Theirs', 'researcher_id' => $otherRid, 'created_at' => now(), 'updated_at' => now()]);
        $issue = (int) DB::table('research_journal_issue')->insertGetId(['journal_id' => $theirs, 'title' => 'Their issue']);

        $this->actingAs($me)->post(route('research.journal-builder.article-store', $mine), ['title' => 'Stray', 'issue_id' => $issue])
            ->assertSessionHasErrors('issue_id');
    }

    public function test_only_admins_change_shared_templates_and_the_target_journal_directory(): void
    {
        [$me] = $this->researcher();
        [$admin] = $this->researcher('approved', true);

        $this->actingAs($me)->post(route('research.reportTemplates'), ['form_action' => 'create', 'name' => 'Sneaky'])->assertForbidden();
        $this->actingAs($me)->post(route('research.target-journal.store'), ['title' => 'Sneaky'])->assertForbidden();
        $this->actingAs($me)->post(route('research.target-journal.seed-dhet'))->assertForbidden();

        $this->actingAs($admin)->post(route('research.target-journal.store'), ['title' => 'Bad link', 'homepage_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('homepage_url');
    }

    public function test_a_researcher_awaiting_approval_cannot_sign_in(): void
    {
        [$pending] = $this->researcher('pending');

        $this->from(route('login'))->post('/login', ['email' => $pending->email, 'password' => 'secret-pass-1'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_dashboard_hides_site_wide_figures_from_researchers(): void
    {
        [$me] = $this->researcher();

        $this->actingAs($me)->get(route('research.dashboard'))->assertOk()
            ->assertDontSee('Registered Researchers')->assertDontSee("Today's Schedule", false);

        [$admin] = $this->researcher('approved', true);
        $this->actingAs($admin)->get(route('research.dashboard'))->assertOk()->assertSee('Registered Researchers');
    }

    public function test_an_admin_password_reset_emails_a_link_and_shows_no_password(): void
    {
        Mail::fake();
        [$admin] = $this->researcher('approved', true);
        [, $rid] = $this->researcher();
        $hashBefore = DB::table('user')->join('research_researcher as r', 'r.user_id', '=', 'user.id')->where('r.id', $rid)->value('password_hash');

        $this->actingAs($admin)->post(route('research.resetPassword', $rid))->assertSessionMissing('error');

        $flash = (string) session('success');
        $this->assertStringNotContainsString('New password', $flash);
        $this->assertStringContainsString('emailed', $flash);
        $this->assertSame($hashBefore, DB::table('user')->join('research_researcher as r', 'r.user_id', '=', 'user.id')->where('r.id', $rid)->value('password_hash'));
    }

    public function test_the_cite_page_does_not_disclose_a_draft_record(): void
    {
        $io = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $io, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => 'Secret draft '.$io]);
        DB::table('slug')->insert(['object_id' => $io, 'slug' => 'rsec-draft-'.$io]);
        \AhgCore\Support\StatusRow::set($io, 158, 159);

        $this->get(route('research.cite', 'rsec-draft-'.$io))->assertNotFound();
    }

    public function test_every_tomselect_renderer_in_the_research_views_escapes(): void
    {
        $bad = [];
        foreach (glob(base_path('packages/ahg-research/resources/views/research/*.blade.php')) as $f) {
            if (preg_match_all('/\b(?:option|item)\s*:\s*function\s*\(\s*\w+\s*\)/', (string) file_get_contents($f), $m)) {
                $bad[] = basename($f).': '.implode(', ', $m[0]);
            }
        }
        $this->assertSame([], $bad, 'TomSelect renderers must take and use escape()');
    }
}
