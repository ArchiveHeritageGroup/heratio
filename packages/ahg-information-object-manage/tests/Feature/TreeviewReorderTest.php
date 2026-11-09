<?php

/**
 * TreeviewReorderTest - sibling reorder in the hierarchy tree (heratio#1582).
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
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use AhgInformationObjectManage\Services\TreeviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TreeviewReorderTest extends TestCase
{
    use DatabaseTransactions;

    private int $fonds;

    /** @var array<string,int> */
    private array $kids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fonds = Io::create(['title' => 'ZZ Reorder fonds', 'identifier' => 'ZZR'], 'en');
        foreach (['A', 'B', 'C'] as $k) {
            $this->kids[$k] = Io::create(['title' => 'ZZ Reorder '.$k, 'identifier' => 'ZZR/'.$k, 'parent_id' => $this->fonds], 'en');
        }
        // A grandchild under B, so a move must carry B's subtree with it.
        $this->kids['B1'] = Io::create(['title' => 'ZZ Reorder B1', 'identifier' => 'ZZR/B/1', 'parent_id' => $this->kids['B']], 'en');
    }

    /** Children of the fonds in lft order, as letters. */
    private function order(): string
    {
        $flip = array_flip($this->kids);

        return DB::table('information_object')->where('parent_id', $this->fonds)->orderBy('lft')
            ->pluck('id')->map(fn ($id) => $flip[$id])->implode('');
    }

    private function nestedSetIsValid(): bool
    {
        $b = DB::table('information_object')->where('id', $this->kids['B'])->first(['lft', 'rgt']);
        $b1 = DB::table('information_object')->where('id', $this->kids['B1'])->first(['lft', 'rgt']);
        $f = DB::table('information_object')->where('id', $this->fonds)->first(['lft', 'rgt']);

        return $b1->lft > $b->lft && $b1->rgt < $b->rgt && $b->lft > $f->lft && $b->rgt < $f->rgt;
    }

    public function test_move_before_makes_a_node_the_first_child_and_move_after_puts_it_back(): void
    {
        $svc = new TreeviewService('en');
        $this->assertSame('ABC', $this->order());

        $this->assertTrue($svc->moveBefore($this->kids['C'], $this->kids['A']));
        $this->assertSame('CAB', $this->order());

        $this->assertTrue($svc->moveBefore($this->kids['B'], $this->kids['C']));
        $this->assertSame('BCA', $this->order());
        $this->assertTrue($this->nestedSetIsValid());

        $this->assertTrue($svc->moveAfter($this->kids['B'], $this->kids['A']));
        $this->assertSame('CAB', $this->order());
        $this->assertTrue($this->nestedSetIsValid());
    }

    public function test_a_node_cannot_move_relative_to_itself_or_a_non_sibling(): void
    {
        $svc = new TreeviewService('en');

        $this->assertFalse($svc->moveBefore($this->kids['A'], $this->kids['A']));
        $this->assertFalse($svc->moveAfter($this->kids['A'], $this->kids['B1']));
        $this->assertSame('ABC', $this->order());
    }

    public function test_the_endpoint_moves_for_an_editor_and_rejects_a_bad_position(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->postJson(route('io.treeviewSort'), ['id' => $this->kids['C'], 'target' => $this->kids['A'], 'position' => 'before'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('CAB', $this->order());

        $this->actingAs($admin)
            ->postJson(route('io.treeviewSort'), ['id' => $this->kids['C'], 'target' => $this->kids['A'], 'position' => 'inside'])
            ->assertStatus(400);
    }

    public function test_the_tree_offers_reorder_to_an_editor_only(): void
    {
        // Rendered directly: a new record is a draft, which a guest cannot open.
        $io = DB::table('information_object')->where('id', $this->kids['B'])->first();
        $render = fn () => view('ahg-information-object-manage::partials._treeview', ['io' => $io])->render();

        $this->assertStringContainsString('var canReorder = false', $render());
        $this->actingAs($this->makeAdmin());
        $this->assertStringContainsString('var canReorder = true', $render());
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'reorder-admin-'.$id, 'email' => uniqid('reorder-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
