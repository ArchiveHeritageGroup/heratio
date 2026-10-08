<?php

/**
 * BulkEditTest - bulk edits of archival descriptions (heratio#1542).
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
use AhgInformationObjectManage\Services\BulkEditService;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BulkEditTest extends TestCase
{
    use DatabaseTransactions;

    private BulkEditService $svc;

    private int $fonds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(BulkEditService::class);
        $this->fonds = Io::create(['title' => 'ZZ Bulk fonds', 'identifier' => 'ZZB'], 'en');
    }

    private function child(string $title, string $identifier, string $scope = ''): int
    {
        return Io::create(['title' => $title, 'identifier' => $identifier, 'scope_and_content' => $scope, 'parent_id' => $this->fonds], 'en');
    }

    private function text(int $id, string $col = 'scope_and_content'): ?string
    {
        return DB::table('information_object_i18n')->where('id', $id)->where('culture', 'en')->value($col);
    }

    private function runBatch(string $kind, array $params): int
    {
        $id = $this->svc->queue($kind, $params + ['culture' => 'en'], ['ancestor_id' => $this->fonds], null);
        $this->svc->run($id);

        return $id;
    }

    public function test_find_and_replace_is_scoped_previewed_applied_and_undone(): void
    {
        $a = $this->child('ZZ Letter A', 'ZZB/1', 'Letters from Pretoria and pretoria.');
        $b = $this->child('ZZ Letter B', 'ZZB/2', 'No match here.');
        $outside = Io::create(['title' => 'ZZ Outside', 'scope_and_content' => 'Pretoria too.'], 'en');

        $preview = $this->svc->preview('find_replace', ['column' => 'scope_and_content', 'pattern' => 'pretoria', 'replacement' => 'Tshwane', 'case_sensitive' => false, 'culture' => 'en'], ['ancestor_id' => $this->fonds]);
        $this->assertSame(1, $preview['total']);
        $this->assertSame('Letters from Pretoria and pretoria.', $this->text($a), 'preview writes nothing');

        $batch = $this->runBatch('find_replace', ['column' => 'scope_and_content', 'pattern' => 'pretoria', 'replacement' => 'Tshwane', 'case_sensitive' => false]);
        $this->assertSame('Letters from Tshwane and Tshwane.', $this->text($a), 'case-insensitive replaces every casing');
        $this->assertSame('No match here.', $this->text($b));
        $this->assertSame('Pretoria too.', $this->text($outside), 'records outside the scope are untouched');
        $this->assertSame('done', DB::table('ahg_bulk_edit')->where('id', $batch)->value('status'));

        $this->svc->undo($batch, null);
        $this->assertSame('Letters from Pretoria and pretoria.', $this->text($a));
    }

    public function test_undo_leaves_a_value_edited_after_the_batch(): void
    {
        $a = $this->child('ZZ Item A', 'ZZB/1', 'old text');
        $b = $this->child('ZZ Item B', 'ZZB/2', 'old text');
        $batch = $this->runBatch('find_replace', ['column' => 'scope_and_content', 'pattern' => 'old', 'replacement' => 'new', 'case_sensitive' => true]);
        DB::table('information_object_i18n')->where('id', $b)->update(['scope_and_content' => 'edited by hand']);

        $r = $this->svc->undo($batch, null);

        $this->assertSame(['restored' => 1, 'skipped' => 1], $r);
        $this->assertSame('old text', $this->text($a));
        $this->assertSame('edited by hand', $this->text($b));
    }

    public function test_set_field_and_rename(): void
    {
        $a = $this->child('ZZ Draft A', 'ZZB/7');
        $published = (int) DB::table('term')->where('taxonomy_id', 60)->where('id', 160)->value('id');

        $this->runBatch('set_field', ['field' => 'publication_status_id', 'value' => (string) $published]);
        $this->assertSame($published, (int) DB::table('status')->where('object_id', $a)->where('type_id', 158)->value('status_id'));

        $this->runBatch('rename', ['template' => '{identifier} {title}']);
        $this->assertSame('ZZB/7 ZZ Draft A', $this->text($a, 'title'));
    }

    public function test_children_sort_naturally_by_identifier_and_the_sort_undoes(): void
    {
        $c10 = $this->child('ZZ Ten', 'ZZB/10');
        $c2 = $this->child('ZZ Two', 'ZZB/2');
        $c1 = $this->child('ZZ One', 'ZZB/1');
        $order = fn () => DB::table('information_object')->where('parent_id', $this->fonds)->orderBy('lft')->pluck('id')->map('intval')->all();
        $this->assertSame([$c10, $c2, $c1], $order());

        $id = $this->svc->queue('sort_children', ['mode' => 'natural'], ['ancestor_id' => $this->fonds], null);
        $this->svc->run($id);
        $this->assertSame([$c1, $c2, $c10], $order());

        $this->svc->undo($id, null);
        $this->assertSame([$c10, $c2, $c1], $order());
    }

    public function test_the_screens_preview_and_queue_a_batch(): void
    {
        $this->child('ZZ Screen item', 'ZZB/1', 'alpha');
        $slug = DB::table('slug')->where('object_id', $this->fonds)->value('slug');
        $admin = $this->makeAdmin();
        $form = ['kind' => 'find_replace', 'scope_slug' => $slug, 'column' => 'scope_and_content', 'pattern' => 'alpha', 'replacement' => 'beta', 'case_sensitive' => '1'];

        $this->actingAs($admin)->get(route('bulk-edit.index'))->assertOk()->assertSee(__('Bulk edit'));
        $this->actingAs($admin)->post(route('bulk-edit.index'), $form)->assertOk()->assertSee('beta');
        $this->actingAs($admin)->post(route('bulk-edit.run'), $form)->assertRedirect();
        $this->assertSame(1, DB::table('ahg_bulk_edit')->where('kind', 'find_replace')->where('created_by', $admin->id)->count());
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'bulk-admin-'.$id, 'email' => uniqid('bulk-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
