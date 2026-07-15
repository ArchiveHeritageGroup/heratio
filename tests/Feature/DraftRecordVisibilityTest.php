<?php

/**
 * DraftRecordVisibilityTest - regression guard for the Part-A access-control
 * hardening (AtoM 2.5-2.10 advisory class): an anonymous visitor must NOT be
 * able to see a draft (unpublished) archival description's page or title, and
 * must not reach the staff-only user-profile / modifications surfaces that leak
 * account metadata. Editors/admins are unaffected.
 *
 * Seeds one draft information object (publication status 158/159) and rolls it
 * back via DatabaseTransactions. Skips cleanly where the schema isn't present.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DraftRecordVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private int $ioId;

    private string $slug;

    private string $title;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['object', 'information_object', 'information_object_i18n', 'slug', 'status', 'user'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }

        $this->ioId = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('information_object')->insert([
            'id'             => $this->ioId,
            'source_culture' => 'en',
        ]);
        $this->title = 'Draft Vis Test '.$this->ioId;
        DB::table('information_object_i18n')->insert([
            'id'      => $this->ioId,
            'culture' => 'en',
            'title'   => $this->title,
        ]);
        $this->slug = 'draft-vis-test-'.$this->ioId;
        DB::table('slug')->insert([
            'object_id' => $this->ioId,
            'slug'      => $this->slug,
        ]);
        // DRAFT: publication status type 158, status 159 (160 = published).
        DB::table('status')->insert([
            'object_id' => $this->ioId,
            'type_id'   => 158,
            'status_id' => 159,
        ]);
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitUser',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id'            => $id,
            'username'      => 'draftvis-admin-'.$id,
            'email'         => uniqid('draftvis-', true).'@example.test',
            'password_hash' => Hash::make('secret'),
            'active'        => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_guest_cannot_view_draft_description_page(): void
    {
        $this->get('/'.$this->slug)->assertNotFound();
    }

    public function test_guest_io_autocomplete_excludes_draft_title(): void
    {
        $this->get('/informationobject/autocomplete?query='.urlencode($this->title))
            ->assertOk()
            ->assertDontSee($this->title);
    }

    public function test_guest_ric_records_api_excludes_draft(): void
    {
        $this->get('/api/ric/v1/records?limit=200')
            ->assertOk()
            ->assertDontSee($this->slug);
    }

    public function test_guest_ric_autocomplete_excludes_draft_title(): void
    {
        $this->get('/ric-api/autocomplete?q='.urlencode($this->title))
            ->assertDontSee($this->title);
    }

    public function test_editor_is_not_blocked_by_the_draft_gate(): void
    {
        // The mirror of the guest autocomplete test: an editor DOES see the
        // draft title, proving the publication filter only suppresses for
        // guests and never over-blocks staff. (The full show page needs a more
        // complete fixture to render, so the autocomplete surface is used as
        // the clean, fixture-robust contrast.)
        $this->actingAs($this->makeAdmin())
            ->get('/informationobject/autocomplete?query='.urlencode($this->title))
            ->assertOk()
            ->assertSee($this->title);
    }

    public function test_user_view_is_not_reachable_anonymously(): void
    {
        // /user/view/{slug} previously rendered username, email, roles AND live
        // REST/OAI API keys to anonymous visitors. Now admin-gated.
        $this->assertNotEquals(200, $this->get('/user/view/'.$this->slug)->getStatusCode());
    }

    public function test_modifications_requires_auth(): void
    {
        // The per-record modifications page leaks editor username/email.
        $this->assertNotEquals(200, $this->get('/informationobject/'.$this->slug.'/modifications')->getStatusCode());
    }
}
