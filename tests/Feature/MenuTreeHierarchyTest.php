<?php

/**
 * MenuTreeHierarchyTest - the menu tree reads parent_id, the closure and the
 * sibling-order table, and rebuilds lft/rgt after a change (heratio#1541).
 * Reordering used to swap lft/rgt between siblings, which left an item's
 * children under the other sibling.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\MenuService as Nav;
use AhgMenuManage\Services\MenuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MenuTreeHierarchyTest extends TestCase
{
    use DatabaseTransactions;

    private MenuService $menus;

    private int $p;

    private int $a;

    private int $a1;

    private int $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menus = new MenuService('en');
        $this->p = $this->menus->create(['parentId' => MenuService::ROOT_ID, 'name' => 'zzP', 'label' => 'ZZ P']);
        $this->a = $this->menus->create(['parentId' => $this->p, 'name' => 'zzA', 'label' => 'ZZ A']);
        $this->a1 = $this->menus->create(['parentId' => $this->a, 'name' => 'zzA1', 'label' => 'ZZ A1']);
        $this->b = $this->menus->create(['parentId' => $this->p, 'name' => 'zzB', 'label' => 'ZZ B']);
    }

    private function admin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'menu-'.$id, 'email' => uniqid('menu-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    /** @return array<string, array> name => tree item, in tree order */
    private function tree(): array
    {
        return collect($this->menus->getTree())->filter(fn ($i) => str_starts_with((string) $i['name'], 'zz'))->keyBy('name')->all();
    }

    public function test_moving_an_item_down_takes_its_children_with_it(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('menu.moveDown', $this->a))->assertRedirect();
        $this->actingAs($admin)->get(route('menu.browse'))->assertOk()->assertSee('ZZ A1');
        $this->actingAs($admin)->get(route('menu.show', $this->a))->assertOk()->assertSee('ZZ A1');

        $tree = $this->tree();
        $this->assertSame(['zzP', 'zzB', 'zzA', 'zzA1'], array_keys($tree));
        $this->assertSame($tree['zzA']['depth'] + 1, $tree['zzA1']['depth']);

        Nav::clearCache();
        $p = Nav::getByName('zzP', 'en');
        $this->assertSame(['zzB', 'zzA'], array_map(fn ($c) => $c->name, $p->children));
        $this->assertSame(['zzA1'], array_map(fn ($c) => $c->name, $p->children[1]->children));

        // Deleting B must not take A's child with it.
        $this->menus->delete($this->b);
        $this->assertTrue(DB::table('menu')->where('id', $this->a1)->exists());
    }

    public function test_items_without_nested_set_bounds_keep_their_place(): void
    {
        DB::table('menu')->whereIn('id', [$this->a, $this->a1])->update(['lft' => null, 'rgt' => null]);

        $tree = $this->tree();
        $this->assertTrue($tree['zzA']['hasChildren']);
        $this->assertSame($tree['zzA']['depth'] + 1, $tree['zzA1']['depth']);

        $this->menus->delete($this->a);
        $this->assertFalse(DB::table('menu')->where('id', $this->a1)->exists());
        $this->assertTrue(DB::table('menu')->where('id', $this->b)->exists());
    }
}
