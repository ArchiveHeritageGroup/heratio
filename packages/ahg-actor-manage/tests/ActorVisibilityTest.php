<?php

/**
 * ActorVisibilityTest - Part B: draft / embargo on authority records.
 *
 * A guest must not see a draft or currently-embargoed authority record in the
 * actor autocomplete or on its show page; an editor/admin sees all. Published,
 * non-embargoed records stay public (the migration backfilled existing actors
 * to published so nothing disappeared).
 *
 * Seeds one QubitActor and toggles its status/embargo; DatabaseTransactions
 * rolls everything back. Skips cleanly where the schema isn't present.
 */

namespace AhgActorManage\Tests;

use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActorVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private int $actorId;

    private string $slug;

    private string $name;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['object', 'actor', 'actor_i18n', 'slug', 'status', 'user'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        if (! Schema::hasColumn('actor', 'embargo_until')) {
            $this->markTestSkipped('actor.embargo_until not migrated in this install.');
        }

        $this->actorId = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitActor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('actor')->insert(['id' => $this->actorId, 'source_culture' => 'en']);
        $this->name = 'Embargo Test Person '.$this->actorId;
        DB::table('actor_i18n')->insert([
            'id'                       => $this->actorId,
            'culture'                  => 'en',
            'authorized_form_of_name'  => $this->name,
        ]);
        $this->slug = 'embargo-test-'.$this->actorId;
        DB::table('slug')->insert(['object_id' => $this->actorId, 'slug' => $this->slug]);
        // Start published, no embargo.
        DB::table('status')->insert([
            'object_id' => $this->actorId,
            'type_id'   => 158,
            'status_id' => 160,
        ]);
    }

    private function setStatus(int $statusId, ?string $embargo = null): void
    {
        DB::table('status')->where('object_id', $this->actorId)->where('type_id', 158)
            ->update(['status_id' => $statusId]);
        DB::table('actor')->where('id', $this->actorId)->update(['embargo_until' => $embargo]);
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
            'username'      => 'actorvis-admin-'.$id,
            'email'         => uniqid('actorvis-', true).'@example.test',
            'password_hash' => Hash::make('secret'),
            'active'        => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function autocomplete(): string
    {
        return (string) $this->get('/actor/autocomplete?query='.urlencode($this->name))->getContent();
    }

    public function test_guest_sees_published_actor(): void
    {
        $this->assertStringContainsString($this->name, $this->autocomplete());
    }

    public function test_guest_cannot_see_draft_actor(): void
    {
        // list + search suppression is the scope's core requirement; the
        // show-page 404 for guests is verified against real records on the dev
        // box (getBySlug needs a fuller fixture than this unit seeds to render).
        $this->setStatus(159);
        $this->assertStringNotContainsString($this->name, $this->autocomplete());
    }

    public function test_guest_cannot_see_embargoed_actor(): void
    {
        $this->setStatus(160, '2099-01-01');
        $this->assertStringNotContainsString($this->name, $this->autocomplete());
    }

    public function test_guest_sees_actor_after_embargo_lapses(): void
    {
        $this->setStatus(160, '2000-01-01'); // past embargo => visible
        $this->assertStringContainsString($this->name, $this->autocomplete());
    }

    public function test_editor_sees_draft_actor(): void
    {
        $this->setStatus(159);
        $body = (string) $this->actingAs($this->makeAdmin())
            ->get('/actor/autocomplete?query='.urlencode($this->name))->getContent();
        $this->assertStringContainsString($this->name, $body);
    }
}
