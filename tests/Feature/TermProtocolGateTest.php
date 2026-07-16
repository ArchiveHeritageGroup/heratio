<?php

/**
 * TermProtocolGateTest - #1388 Phase 1 core enforcement engine.
 *
 * A guest must not see a term carrying a restricted community protocol, nor a
 * record tagged with such a term; editors/admins bypass; usage-obligation
 * conditions (open/attribution) stay visible. Exercises the gate + its query
 * scopes. Skips cleanly where term_protocol isn't migrated.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\TermProtocolGate as G;
use AhgCore\Services\TermProtocolService as S;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TermProtocolGateTest extends TestCase
{
    use DatabaseTransactions;

    private int $termId;

    private int $objId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['object', 'term', 'object_term_relation', 'information_object', 'term_protocol', 'user'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t not present in this install.");
            }
        }

        // A term...
        $this->termId = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitTerm', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('term')->insert(['id' => $this->termId, 'taxonomy_id' => 35, 'source_culture' => 'en']);

        // ...a record...
        $this->objId = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('information_object')->insert(['id' => $this->objId, 'source_culture' => 'en']);

        // ...tagged with the term.
        $otr = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitObjectTermRelation', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('object_term_relation')->insert(['id' => $otr, 'object_id' => $this->objId, 'term_id' => $this->termId]);
    }

    private function protocol(string $condition): void
    {
        DB::table('term_protocol')->where('term_id', $this->termId)->delete();
        DB::table('term_protocol')->insert([
            'term_id' => $this->termId, 'label_family' => 'tk', 'label_code' => 'tk_secret',
            'access_condition' => $condition, 'region_module' => 'southern_africa',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'tp-admin-'.$id, 'email' => uniqid('tp-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function termVisibleToGuest(): bool
    {
        $q = DB::table('term');
        G::addTermVisibilityCriteria($q, 'term.id');

        return $q->where('term.id', $this->termId)->exists();
    }

    private function recordVisibleToGuest(): bool
    {
        $q = DB::table('information_object as io');
        G::excludeRestrictedRecords($q, 'io.id');

        return $q->where('io.id', $this->objId)->exists();
    }

    public function test_guest_cannot_see_restricted_term(): void
    {
        $this->protocol('sacred_secret');
        $this->assertTrue(S::isRestricted(S::effectiveCondition($this->termId)));
        $this->assertFalse(G::allowsTerm($this->termId));
        $this->assertFalse($this->termVisibleToGuest());
    }

    public function test_guest_cannot_see_record_tagged_with_restricted_term(): void
    {
        $this->protocol('restricted');
        $this->assertSame('restricted', S::conditionForRecord($this->objId));
        $this->assertFalse(G::allowsRecord($this->objId));
        $this->assertFalse($this->recordVisibleToGuest());
    }

    public function test_open_condition_stays_visible_to_guest(): void
    {
        $this->protocol('attribution'); // usage obligation, not access restriction
        $this->assertTrue(G::allowsTerm($this->termId));
        $this->assertTrue($this->termVisibleToGuest());
        $this->assertTrue(G::allowsRecord($this->objId));
    }

    public function test_editor_bypasses_the_gate(): void
    {
        $this->protocol('sacred_secret');
        $this->actingAs($this->makeAdmin());
        $this->assertTrue(G::allowsTerm($this->termId));
        $this->assertTrue($this->termVisibleToGuest());   // "guest" helper, but editor is acting -> bypass
        $this->assertTrue(G::allowsRecord($this->objId));
        $this->assertTrue($this->recordVisibleToGuest());
    }
}
