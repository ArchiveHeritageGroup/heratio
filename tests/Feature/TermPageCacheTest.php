<?php

/**
 * TermPageCacheTest - the term page's counts come from the cache on repeat
 * views, and visitors see only published descriptions in its list. A crawler
 * paging /term/ on a 454k-record install kept the host at full CPU, and the
 * list showed drafts' titles to anyone (28 Oct 2026).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TermPageCacheTest extends TestCase
{
    use DatabaseTransactions;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        Cache::flush();
        $subject = $this->term(35, 'ZZ Cache subject', null);
        $narrower = $this->term(35, 'ZZ Cache narrower', $subject);
        $this->record('ZZ Cache published', 160, $subject);
        $this->record('ZZ Cache draft', 159, $subject);
        $this->record('ZZ Cache narrower record', 160, $narrower);
        $this->slug = 'tpc-'.$subject;
    }

    private function obj(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function term(int $taxonomy, string $name, ?int $parent): int
    {
        $id = $this->obj('QubitTerm');
        DB::table('term')->insert(['id' => $id, 'taxonomy_id' => $taxonomy, 'parent_id' => $parent, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'tpc-'.$id]);

        return $id;
    }

    private function record(string $title, int $status, int $term): void
    {
        $id = $this->obj('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'tpc-'.$id]);
        StatusRow::set($id, 158, $status);
        DB::table('object_term_relation')->insert(['id' => $this->obj('QubitObjectTermRelation'), 'object_id' => $id, 'term_id' => $term]);
    }

    private function admin(): User
    {
        $id = $this->obj('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'tpc-'.$id, 'email' => uniqid('tpc-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_visitors_see_published_descriptions_including_narrower_terms(): void
    {
        $this->get('/term/'.$this->slug)->assertOk()
            ->assertSee('ZZ Cache published')
            ->assertSee('ZZ Cache narrower record')
            ->assertDontSee('ZZ Cache draft');

        $this->actingAs($this->admin())->get('/term/'.$this->slug)->assertOk()->assertSee('ZZ Cache draft');
    }

    public function test_repeat_views_do_not_recount(): void
    {
        $this->get('/term/'.$this->slug)->assertOk();

        DB::enableQueryLog();
        $this->get('/term/'.$this->slug.'?sort=alphabetic&listPage=1')->assertOk();
        $counts = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'count(*)') && str_contains($q['query'], 'object_term_relation'));
        $this->assertCount(0, $counts, 'a re-sorted view reuses the cached counts');

        $this->get('/term/browse?taxonomy=35')->assertOk();
        DB::flushQueryLog();
        $this->get('/term/browse?taxonomy=35')->assertOk();
        $rowCounts = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'count(*)') && str_contains($q['query'], 'object_term_relation'));
        $this->assertCount(0, $rowCounts, 'browse reuses the cached per-term counts');
    }
}
