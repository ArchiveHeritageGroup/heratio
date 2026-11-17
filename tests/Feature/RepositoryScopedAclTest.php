<?php

/**
 * RepositoryScopedAclTest - heratio#1776.
 *
 * A group whose only write rights are per-repository rules (constants
 * {"repository": "<slug>"}, as the ACL editor saves them) may create, edit,
 * delete and publish inside that repository - including a description that
 * inherits the repository from its parent - and nowhere else; it stays out of
 * the admin area and the API key manager. Editors and administrators behave
 * as before, and a repository DENY narrows an editor in that repository only.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RepositoryScopedAclTest extends TestCase
{
    use DatabaseTransactions;

    private string $slugA;

    private string $slugB;

    private int $repoA;

    private int $repoB;

    private int $topA;

    private int $childA;

    private int $topB;

    private int $scopedGroup;

    protected function setUp(): void
    {
        parent::setUp();
        AclService::forgetUser();

        [$this->repoA, $this->slugA] = $this->repository('sandbox');
        [$this->repoB, $this->slugB] = $this->repository('other');
        $this->topA = $this->io(910000001, 910000004, $this->repoA);
        $this->childA = $this->io(910000002, 910000003, null);   // inherits A from its parent
        $this->topB = $this->io(910000005, 910000006, $this->repoB);

        $this->scopedGroup = (int) DB::table('acl_group')->insertGetId([
            'parent_id' => 99, 'source_culture' => 'en', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['create', 'update', 'delete', 'publish', 'viewDraft'] as $a) {
            $this->rule($this->scopedGroup, $a, self::GRANT_, $this->slugA);
        }
    }

    private const GRANT_ = 1;

    private function rule(int $groupId, string $action, int $grant, string $repoSlug): void
    {
        DB::table('acl_permission')->insert([
            'group_id' => $groupId, 'action' => $action, 'grant_deny' => $grant,
            'constants' => json_encode(['repository' => $repoSlug]),
            'created_at' => now(), 'updated_at' => now(), 'serial_number' => 0,
        ]);
    }

    private function repository(string $tag): array
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitRepository', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('repository')->insert(['id' => $id, 'source_culture' => 'en']);
        $slug = 'test-repo-'.$tag.'-'.uniqid();
        DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);

        return [$id, $slug];
    }

    private function io(int $lft, int $rgt, ?int $repositoryId): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert([
            'id' => $id, 'source_culture' => 'en', 'lft' => $lft, 'rgt' => $rgt, 'parent_id' => 1, 'repository_id' => $repositoryId,
        ]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'test-io-'.$id]);

        return $id;
    }

    private function user(array $groups): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'acl-test-'.$id, 'email' => uniqid('acl-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        foreach ($groups as $g) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $g]);
        }
        Cache::forget("acl_groups_{$id}");
        Cache::forget("acl_repo_rules_{$id}");

        return User::findOrFail($id);
    }

    public function test_scoped_account_writes_inside_its_repository_only(): void
    {
        $u = $this->user([$this->scopedGroup])->id;

        foreach (['create', 'update', 'delete', 'publish'] as $a) {
            $this->assertTrue(AclService::hasPermission($u, $a, $this->topA), "$a on own top-level");
            $this->assertTrue(AclService::hasPermission($u, $a, $this->childA), "$a on inherited child");
            $this->assertFalse(AclService::hasPermission($u, $a, $this->topB), "$a outside");
        }
        // The repository rows are not global rules (they used to be).
        $this->assertFalse(AclService::hasPermission($u, 'update'));
        $this->assertFalse(AclService::hasPermission($u, 'export'));

        $this->assertTrue(AclService::check((object) ['id' => $this->childA], 'update', (object) ['id' => $u]));
        $this->assertFalse(AclService::check((object) ['id' => $this->topB], 'update', (object) ['id' => $u]));

        $this->assertTrue(AclService::isRepositoryScoped($u));
        $this->assertFalse(AclService::canAdmin($u));
    }

    public function test_editor_and_admin_unchanged(): void
    {
        $ed = $this->user([101])->id;
        $this->assertTrue(AclService::hasPermission($ed, 'update', $this->topB));
        $this->assertTrue(AclService::hasPermission($ed, 'update'));
        $this->assertTrue(AclService::check((object) ['id' => $this->topB], 'delete', (object) ['id' => $ed]));
        $this->assertTrue(AclService::canAdmin($ed));
        $this->assertFalse(AclService::isRepositoryScoped($ed));

        $admin = $this->user([100]);
        $this->assertTrue(AclService::hasPermission($admin->id, 'delete', $this->topA));
        $this->actingAs($admin);
        $this->assertNotSame(403, $this->get('/admin/icip/dashboard')->status());
    }

    public function test_repository_deny_narrows_an_editor_there_only(): void
    {
        $g = (int) DB::table('acl_group')->insertGetId(['parent_id' => 99, 'source_culture' => 'en', 'created_at' => now(), 'updated_at' => now()]);
        $this->rule($g, 'update', 0, $this->slugB);
        $ed = $this->user([101, $g])->id;

        $this->assertFalse(AclService::hasPermission($ed, 'update', $this->topB));
        $this->assertFalse(AclService::check((object) ['id' => $this->topB], 'update', (object) ['id' => $ed]));
        $this->assertTrue(AclService::hasPermission($ed, 'update', $this->childA));
        $this->assertFalse(AclService::isRepositoryScoped($ed));
    }

    public function test_record_rule_beats_repository_rule(): void
    {
        $u = $this->user([$this->scopedGroup])->id;
        DB::table('acl_permission')->insert([
            'group_id' => $this->scopedGroup, 'object_id' => $this->childA, 'action' => 'delete', 'grant_deny' => 0,
            'created_at' => now(), 'updated_at' => now(), 'serial_number' => 0,
        ]);
        $this->assertFalse(AclService::hasPermission($u, 'delete', $this->childA));
        $this->assertTrue(AclService::hasPermission($u, 'delete', $this->topA));
    }

    public function test_routes_refuse_outside_and_admit_inside(): void
    {
        $this->actingAs($this->user([$this->scopedGroup]));
        $out = 'test-io-'.$this->topB;
        $in = 'test-io-'.$this->childA;

        $this->put("/informationobject/{$out}", ['title' => 'x'])->assertStatus(403);
        $this->get("/informationobject/{$out}/edit")->assertStatus(403);
        $this->assertNotSame(403, $this->get("/informationobject/{$in}/edit")->status());
        $this->delete("/informationobject/{$out}")->assertStatus(403);
        $this->get("/informationobject/{$out}/delete")->assertStatus(403);
        $this->post("/informationobject/{$out}/update-status", ['publicationStatus' => 160])->assertStatus(403);
        $this->get('/admin/settings')->assertStatus(403);

        $this->assertNotSame(403, $this->get("/informationobject/{$in}/delete")->status());
        // Filing: under a sandbox parent, or top-level naming the sandbox, passes the
        // ACL (validation may then reject the empty form); anything else is 403.
        $this->assertNotSame(403, $this->post('/informationobject/store', ['parent_id' => $this->topA])->status());
        $this->assertNotSame(403, $this->post('/informationobject/store', ['parent_id' => 1, 'repository_id' => $this->repoA])->status());
        $this->post('/informationobject/store', ['parent_id' => 1, 'repository_id' => $this->repoB])->assertStatus(403);
        $this->post('/informationobject/store', ['parent_id' => $this->topA, 'repository_id' => $this->repoB])->assertStatus(403);
        $this->post('/informationobject/store', ['parent_id' => 1])->assertStatus(403);
    }

    public function test_uploads_follow_the_record_and_repository_records_stay_closed(): void
    {
        $this->actingAs($this->user([$this->scopedGroup]));
        $in = 'test-io-'.$this->childA;
        $out = 'test-io-'.$this->topB;

        $this->assertNotSame(403, $this->get("/{$in}/object/addDigitalObject")->status());
        $this->get("/{$out}/object/addDigitalObject")->assertStatus(403);

        // The repository's own record is not a description: closed, even the sandbox's.
        $this->get('/repository/add')->assertStatus(403);
        $this->get("/repository/{$this->slugA}/edit")->assertStatus(403);
        $this->get("/repository/{$this->slugB}/edit")->assertStatus(403);
        $this->get("/repository/{$this->slugB}/editTheme")->assertStatus(403);
        $this->post("/repository/{$this->slugB}/edit", ['authorized_form_of_name' => 'x'])->assertStatus(403);
        $this->post('/repository/add', ['authorized_form_of_name' => 'x'])->assertStatus(403);

        // Rights and embargo saves on a description follow its repository.
        foreach (["/{$in}/ext-rights/add", "/{$in}/ext-rights/embargo", "/rights/extended/{$in}/store", "/rights/embargo/{$in}/store", "/rights/manage/{$in}"] as $u) {
            $this->assertNotSame(403, $this->post($u, [])->status(), $u);
            $this->post(str_replace($in, $out, $u), [])->assertStatus(403);
        }

        // ICIP administration is staff only: community consent records.
        $this->get('/admin/icip/dashboard')->assertStatus(403);
        $this->get('/admin/icip/consent-list')->assertStatus(403);
        $this->get('/icip/consent')->assertStatus(403);

        // Server-side upload area and the accession "create description" action.
        $this->get('/ftpUpload/index')->assertStatus(403);
        $this->get('/ftpUpload/listFiles')->assertStatus(403);
        $acc = DB::table('accession')->join('slug', 'slug.object_id', '=', 'accession.id')->value('slug.slug') ?? 'no-such-accession';
        $this->post("/accession/{$acc}/create-io")->assertStatus(403);
    }

    public function test_staff_areas_are_closed_but_the_learners_own_work_is_open(): void
    {
        $this->actingAs($this->user([$this->scopedGroup]));
        $in = 'test-io-'.$this->childA;

        foreach (['/library/add', '/library-manage/marc', '/admin/library/isbn-providers', '/archaeology/object/create',
                  '/archaeology/site/create', '/term/add', '/ingest', '/admin/scan', '/research/workspace'] as $u) {
            $this->get($u)->assertStatus(403);
        }
        $this->post('/archaeology/site', ['name' => 'x'])->assertStatus(403);
        $this->post('/archaeology/object', ['accession_number' => 'x'])->assertStatus(403);

        $this->assertSame(200, $this->get('/')->status());
        $this->assertSame(200, $this->get('/informationobject/add')->status());
        $this->assertSame(200, $this->get("/informationobject/{$in}/edit")->status());
        $this->assertNotSame(403, $this->get('/informationobject/termAutocomplete?query=a')->status());
        $this->assertNotSame(403, $this->get("/{$in}/object/addDigitalObject")->status());
        $this->assertNotSame(403, $this->get('/user/profile')->status());

        // Not scoped: a contributor still reaches the pages it did before.
        $this->actingAs($this->user([102]));
        $this->assertNotSame(403, $this->get('/archaeology/object/create')->status());
    }

    public function test_shared_accounts_cannot_be_changed_through_the_site(): void
    {
        $shared = $this->user([$this->scopedGroup]);
        DB::table('slug')->insert(['object_id' => $shared->id, 'slug' => 'shared-'.$shared->id]);
        config(['heratio.shared_accounts' => [$shared->email]]);

        $this->actingAs($shared);
        $this->put('/user/password', ['current_password' => 'secret', 'password' => 'Another-pass-1', 'password_confirmation' => 'Another-pass-1'])->assertStatus(403);
        $this->put('/user/profile', ['username' => 'taken-over', 'email' => 'x@example.test'])->assertStatus(403);

        $this->actingAs($this->user([100]));
        $this->post("/user/shared-{$shared->id}/edit", ['username' => 'taken-over', 'email' => 'x@example.test'])->assertStatus(403);
        $this->delete("/user/shared-{$shared->id}/delete")->assertStatus(403);
        $this->assertSame($shared->email, DB::table('user')->where('id', $shared->id)->value('email'));

        // Anyone not on the list: unchanged.
        $other = $this->user([102]);
        DB::table('slug')->insert(['object_id' => $other->id, 'slug' => 'other-'.$other->id]);
        $this->assertNotSame(403, $this->post("/user/other-{$other->id}/edit", ['username' => 'renamed-'.$other->id, 'email' => 'r'.$other->id.'@example.test'])->status());
    }

    public function test_scoped_account_links_existing_terms_but_adds_none(): void
    {
        $existing = DB::table('term')->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 35)->where('term_i18n.culture', 'en')->whereNotNull('term_i18n.name')
            ->first(['term.id', 'term_i18n.name']);
        $new = 'Learner made-up term '.uniqid();
        $find = fn (string $n) => \AhgInformationObjectManage\Controllers\InformationObjectController::findOrCreateTerm($n, 35, 'en');

        $this->actingAs($this->user([$this->scopedGroup]));
        AclService::forgetUser();
        if ($existing) {
            $this->assertSame((int) $existing->id, $find($existing->name));
        }
        $this->assertSame(0, $find($new));
        $this->assertFalse(DB::table('term_i18n')->where('name', $new)->exists());

        $this->actingAs($this->user([101]));
        AclService::forgetUser();
        $this->assertGreaterThan(0, $find($new));
    }

    public function test_scoped_account_cannot_hold_api_keys(): void
    {
        $this->actingAs($this->user([$this->scopedGroup]));
        $this->get('/api/keys')->assertStatus(403);
        $this->post('/api/keys', ['name' => 'learner key'])->assertStatus(403);
        $this->assertSame(0, DB::table('ahg_api_key')->where('user_id', auth()->id())->count());
    }
}
