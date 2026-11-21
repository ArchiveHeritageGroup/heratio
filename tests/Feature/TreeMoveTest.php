<?php

/**
 * TreeMoveTest - moving a description in the archival tree (heratio#1582).
 *
 * The drag-and-drop and the keyboard "Move..." picker post to one route,
 * which moves through InformationObjectService::moveUnder (nested set,
 * closure, sibling order) and then sets the position.
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
use AhgCore\Services\AclService;
use AhgCore\Services\HierarchyQueryService;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use AhgInformationObjectManage\Services\TreeMoveService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TreeMoveTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string,int> */
    private array $n = [];

    protected function setUp(): void
    {
        parent::setUp();
        AclService::forgetUser();
        // fondsA > seriesA1 > (item1, item2) ; fondsA > seriesA2 > itemY ; fondsB > seriesB1
        $this->n['fondsA'] = Io::create(['title' => 'ZZ Move fonds A'], 'en');
        $this->n['seriesA1'] = Io::create(['title' => 'ZZ Move series A1', 'parent_id' => $this->n['fondsA']], 'en');
        $this->n['item1'] = Io::create(['title' => 'ZZ Move item 1', 'parent_id' => $this->n['seriesA1']], 'en');
        $this->n['item2'] = Io::create(['title' => 'ZZ Move item 2', 'parent_id' => $this->n['seriesA1']], 'en');
        $this->n['seriesA2'] = Io::create(['title' => 'ZZ Move series A2', 'parent_id' => $this->n['fondsA']], 'en');
        $this->n['itemY'] = Io::create(['title' => 'ZZ Move item Y', 'parent_id' => $this->n['seriesA2']], 'en');
        $this->n['fondsB'] = Io::create(['title' => 'ZZ Move fonds B'], 'en');
        $this->n['seriesB1'] = Io::create(['title' => 'ZZ Move series B1', 'parent_id' => $this->n['fondsB']], 'en');
    }

    public function test_a_move_to_a_new_parent_keeps_the_nested_set_and_closure_consistent(): void
    {
        $this->actingAs($this->user(101))
            ->postJson(route('informationobject.tree-move'), ['id' => $this->n['seriesA1'], 'new_parent_id' => $this->n['seriesA2'], 'position' => 0])
            ->assertOk()->assertJson(['ok' => true, 'old_parent_id' => $this->n['fondsA'], 'new_parent_id' => $this->n['seriesA2'], 'new_position' => 0]);

        $h = app(HierarchyQueryService::class);
        $this->assertSame($this->n['seriesA2'], (int) DB::table('information_object')->where('id', $this->n['seriesA1'])->value('parent_id'));
        // Closure: the moved subtree's ancestors now run through seriesA2.
        foreach (['seriesA1', 'item1', 'item2'] as $k) {
            $anc = $h->ancestorIds('information_object', $this->n[$k]);
            $this->assertContains($this->n['seriesA2'], $anc, $k);
            $this->assertContains($this->n['fondsA'], $anc, $k);
        }
        $this->assertEqualsCanonicalizing(
            [$this->n['itemY'], $this->n['seriesA1'], $this->n['item1'], $this->n['item2']],
            $h->descendantIds('information_object', $this->n['seriesA2'])
        );
        // Position 0: first child, before itemY.
        $this->assertSame([$this->n['seriesA1'], $this->n['itemY']], app(TreeMoveService::class)->childIds($this->n['seriesA2']));
        $this->assertNestedSetMatchesParents();
    }

    public function test_moving_under_its_own_descendant_is_refused(): void
    {
        $this->actingAs($this->user(101))
            ->postJson(route('informationobject.tree-move'), ['id' => $this->n['seriesA1'], 'new_parent_id' => $this->n['item1']])
            ->assertStatus(422)->assertJson(['ok' => false, 'code' => 'descendant']);
        $this->assertSame($this->n['fondsA'], (int) DB::table('information_object')->where('id', $this->n['seriesA1'])->value('parent_id'));
    }

    public function test_a_move_into_another_fonds_needs_confirmation(): void
    {
        $editor = $this->user(101);
        $body = ['id' => $this->n['seriesA1'], 'new_parent_id' => $this->n['fondsB']];

        $this->actingAs($editor)->postJson(route('informationobject.tree-move'), $body)
            ->assertStatus(409)->assertJson(['ok' => false, 'code' => 'confirm']);
        $this->assertSame($this->n['fondsA'], (int) DB::table('information_object')->where('id', $this->n['seriesA1'])->value('parent_id'));

        $this->actingAs($editor)->postJson(route('informationobject.tree-move'), $body + ['confirm' => 1])
            ->assertOk()->assertJson(['ok' => true, 'cross_fonds' => true]);
        $this->assertSame($this->n['fondsB'], (int) DB::table('information_object')->where('id', $this->n['seriesA1'])->value('parent_id'));
        $this->assertContains($this->n['fondsB'], app(HierarchyQueryService::class)->ancestorIds('information_object', $this->n['item1']));
        $this->assertNestedSetMatchesParents();
    }

    public function test_undo_restores_the_old_parent_and_position(): void
    {
        $editor = $this->user(101);
        $moves = app(TreeMoveService::class);
        $before = $moves->childIds($this->n['seriesA1']);

        $undo = $this->actingAs($editor)
            ->postJson(route('informationobject.tree-move'), ['id' => $this->n['item1'], 'new_parent_id' => $this->n['seriesB1'], 'confirm' => 1])
            ->assertOk()->json('undo');
        $this->assertSame([$this->n['item2']], $moves->childIds($this->n['seriesA1']));

        $this->actingAs($editor)->postJson(route('informationobject.tree-move'), $undo)->assertOk();
        $this->assertSame($before, $moves->childIds($this->n['seriesA1']));
        $this->assertSame($this->n['item1'], $before[0]);
        $this->assertSame([], $moves->childIds($this->n['seriesB1']));
        $this->assertNestedSetMatchesParents();
    }

    public function test_a_plain_user_is_refused(): void
    {
        $this->actingAs($this->user())
            ->postJson(route('informationobject.tree-move'), ['id' => $this->n['item1'], 'new_parent_id' => $this->n['seriesA2']])
            ->assertForbidden();
        $this->actingAs($this->user())
            ->getJson(route('informationobject.tree-move.targets', ['q' => 'ZZ Move']))
            ->assertForbidden();
        $this->assertSame($this->n['seriesA1'], (int) DB::table('information_object')->where('id', $this->n['item1'])->value('parent_id'));
    }

    public function test_an_editor_denied_on_the_new_parent_is_refused(): void
    {
        $editor = $this->user(101);
        DB::table('acl_permission')->insert(['user_id' => $editor->id, 'object_id' => $this->n['seriesA2'], 'action' => 'update',
            'grant_deny' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($editor)
            ->postJson(route('informationobject.tree-move'), ['id' => $this->n['item1'], 'new_parent_id' => $this->n['seriesA2']])
            ->assertForbidden()->assertJson(['code' => 'forbidden']);
        $this->assertSame($this->n['seriesA1'], (int) DB::table('information_object')->where('id', $this->n['item1'])->value('parent_id'));
    }

    public function test_the_sibling_reorder_endpoint_uses_the_same_move(): void
    {
        $this->actingAs($this->user(101))
            ->postJson(route('io.treeviewSort'), ['id' => $this->n['item2'], 'target' => $this->n['item1'], 'position' => 'before'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame([$this->n['item2'], $this->n['item1']], app(TreeMoveService::class)->childIds($this->n['seriesA1']));
        $this->assertNestedSetMatchesParents();
    }

    public function test_a_record_without_nested_set_bounds_moves_to_a_position(): void
    {
        // Created the closure-only way (heratio#1541): no lft/rgt.
        $loose = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $loose, 'parent_id' => $this->n['seriesB1'], 'lft' => null, 'rgt' => null, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $loose, 'culture' => 'en', 'title' => 'ZZ Move loose']);
        app(\AhgCore\Services\ClosureMaintenanceService::class)->addNode('information_object', $loose, $this->n['seriesB1']);
        $lftBefore = DB::table('information_object')->whereIn('id', $this->n)->pluck('lft', 'id')->all();

        $this->actingAs($this->user(101))
            ->postJson(route('informationobject.tree-move'), ['id' => $loose, 'new_parent_id' => $this->n['seriesA1'], 'position' => 1, 'confirm' => 1])
            ->assertOk();

        $this->assertSame([$this->n['item1'], $loose, $this->n['item2']], app(TreeMoveService::class)->childIds($this->n['seriesA1']));
        $this->assertContains($this->n['seriesA1'], app(HierarchyQueryService::class)->ancestorIds('information_object', $loose));
        // No bounds to shift: nobody else's lft moved.
        $this->assertSame($lftBefore, DB::table('information_object')->whereIn('id', $this->n)->pluck('lft', 'id')->all());
    }

    public function test_the_picker_search_leaves_out_the_moving_subtree(): void
    {
        $ids = array_column($this->actingAs($this->user(101))
            ->getJson(route('informationobject.tree-move.targets', ['q' => 'ZZ Move', 'id' => $this->n['seriesA1']]))
            ->assertOk()->json('items'), 'id');

        $this->assertContains($this->n['seriesA2'], $ids);
        $this->assertNotContains($this->n['seriesA1'], $ids);
        $this->assertNotContains($this->n['item1'], $ids);
    }

    public function test_the_tree_offers_move_to_staff_only(): void
    {
        $io = DB::table('information_object')->where('id', $this->n['seriesA1'])->first();
        $render = fn () => view('ahg-information-object-manage::partials._treeview', ['io' => $io])->render();

        $this->assertStringContainsString('var canMove = false', $render());
        $this->assertStringNotContainsString('ahg-tree-move.js', $render());
        $this->actingAs($this->user(101));
        AclService::forgetUser();
        $this->assertStringContainsString('var canMove = true', $render());
    }

    /** Every test record sits inside its parent's lft/rgt, and siblings do not overlap. */
    private function assertNestedSetMatchesParents(): void
    {
        $rows = DB::table('information_object')->whereIn('id', $this->n)->get(['id', 'parent_id', 'lft', 'rgt'])->keyBy('id');
        foreach ($rows as $r) {
            $this->assertLessThan($r->rgt, $r->lft, "bounds of {$r->id}");
            if (isset($rows[$r->parent_id])) {
                $p = $rows[$r->parent_id];
                $this->assertTrue($r->lft > $p->lft && $r->rgt < $p->rgt, "{$r->id} inside its parent {$p->id}");
            }
            foreach ($rows as $o) {
                if ($o->id !== $r->id && $o->parent_id === $r->parent_id) {
                    $this->assertTrue($r->rgt < $o->lft || $o->rgt < $r->lft, "siblings {$r->id} and {$o->id} overlap");
                }
            }
        }
    }

    private function user(?int $group = null): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'tmv-'.$id, 'email' => uniqid('tmv-', true).'@example.test',
            'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
