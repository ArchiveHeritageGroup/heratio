<?php

/**
 * MoveTermTaxonomyTest - move a term, with its subtree, into another taxonomy.
 *
 * heratio#1534: a term's taxonomy could not be changed (the select is disabled
 * on edit and update() never wrote taxonomy_id). TermService::moveToTaxonomy()
 * now moves the term and every narrower term, re-parents a nested term to the
 * top of the target taxonomy, keeps the closure and nested set right, leaves
 * description links alone, and refuses system taxonomies.
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
use AhgCore\Services\ClosureMaintenanceService;
use AhgTermTaxonomy\Services\TermService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MoveTermTaxonomyTest extends TestCase
{
    use DatabaseTransactions;

    private const SUBJECTS = 35;

    private const PLACES = 42;

    private TermService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(TermService::class);
        if (! DB::table('term')->where('id', TermService::ROOT_TERM_ID)->exists()) {
            $this->markTestSkipped('term root 110 missing');
        }
    }

    private function term(string $name, int $taxonomy, int $parent): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitTerm', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('term')->insert(['id' => $id, 'taxonomy_id' => $taxonomy, 'parent_id' => $parent, 'lft' => 0, 'rgt' => 0, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'zz-term-'.$id]);
        app(ClosureMaintenanceService::class)->addNode('term', $id, $parent);

        return $id;
    }

    public function test_a_top_level_term_moves_with_its_narrower_terms_and_keeps_its_links(): void
    {
        $broad = $this->term('ZZ Broad', self::SUBJECTS, TermService::ROOT_TERM_ID);
        $narrow = $this->term('ZZ Narrow', self::SUBJECTS, $broad);
        $ioLink = (int) DB::table('object')->insertGetId(['class_name' => 'QubitObjectTermRelation', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('object_term_relation')->insert(['id' => $ioLink, 'object_id' => 1, 'term_id' => $narrow]);

        $result = $this->svc->moveToTaxonomy($broad, self::PLACES);

        $this->assertSame(['moved' => 2, 'reparented' => false], $result);
        $this->assertSame(self::PLACES, (int) DB::table('term')->where('id', $broad)->value('taxonomy_id'));
        $this->assertSame(self::PLACES, (int) DB::table('term')->where('id', $narrow)->value('taxonomy_id'));
        $this->assertSame($broad, (int) DB::table('term')->where('id', $narrow)->value('parent_id'), 'the narrower term must stay under its broader term');
        $this->assertSame($narrow, (int) DB::table('object_term_relation')->where('id', $ioLink)->value('term_id'));
    }

    public function test_a_nested_term_is_placed_at_the_top_of_the_target_taxonomy(): void
    {
        $parent = $this->term('ZZ Parent', self::SUBJECTS, TermService::ROOT_TERM_ID);
        $child = $this->term('ZZ Child', self::SUBJECTS, $parent);
        $grandchild = $this->term('ZZ Grandchild', self::SUBJECTS, $child);

        $result = $this->svc->moveToTaxonomy($child, self::PLACES);

        $this->assertSame(['moved' => 2, 'reparented' => true], $result);
        $this->assertSame(TermService::ROOT_TERM_ID, (int) DB::table('term')->where('id', $child)->value('parent_id'));
        $this->assertSame(self::SUBJECTS, (int) DB::table('term')->where('id', $parent)->value('taxonomy_id'), 'the old parent stays');
        $this->assertSame(self::PLACES, (int) DB::table('term')->where('id', $grandchild)->value('taxonomy_id'));
        // Closure: the old parent no longer contains the moved subtree; the root does.
        $this->assertFalse(DB::table('term_closure')->where('ancestor', $parent)->where('descendant', $grandchild)->exists());
        $this->assertTrue(DB::table('term_closure')->where('ancestor', TermService::ROOT_TERM_ID)->where('descendant', $grandchild)->exists());
        // Nested set rebuilt: the child's range sits inside the root's and holds the grandchild.
        $c = DB::table('term')->where('id', $child)->first(['lft', 'rgt']);
        $g = DB::table('term')->where('id', $grandchild)->first(['lft', 'rgt']);
        $this->assertTrue($c->lft < $g->lft && $g->rgt < $c->rgt);
    }

    public function test_system_taxonomies_and_no_op_moves_are_refused(): void
    {
        $t = $this->term('ZZ Subject', self::SUBJECTS, TermService::ROOT_TERM_ID);

        foreach ([60, self::SUBJECTS] as $target) { // publication status (locked), and its own taxonomy
            try {
                $this->svc->moveToTaxonomy($t, $target);
                $this->fail("move to {$target} was allowed");
            } catch (\DomainException $e) {
                $this->assertSame(self::SUBJECTS, (int) DB::table('term')->where('id', $t)->value('taxonomy_id'));
            }
        }
    }

    public function test_the_edit_page_action_moves_the_term(): void
    {
        $t = $this->term('ZZ Via form', self::SUBJECTS, TermService::ROOT_TERM_ID);
        $slug = 'zz-term-'.$t;

        $this->actingAs($this->makeAdmin())
            ->post(route('term.move-taxonomy', $slug), ['target_taxonomy_id' => self::PLACES])
            ->assertRedirect(route('term.edit', $slug));

        $this->assertSame(self::PLACES, (int) DB::table('term')->where('id', $t)->value('taxonomy_id'));
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'mt-admin-'.$id, 'email' => uniqid('mt-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
