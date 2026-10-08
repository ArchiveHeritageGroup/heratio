<?php

/**
 * RecordMergeTest - a dedupe merge really merges two descriptions.
 *
 * heratio#1533: "merge" only flagged the pair. It now moves the duplicate's
 * children, events, notes, access points, relations and custom field values to
 * the record kept, deletes the duplicate, keeps a copy in ahg_merge_log, and
 * redirects the duplicate's old URL.
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
use AhgDedupe\Services\RecordMergeService;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecordMergeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ahg_merge_log', 'ahg_duplicate_detection'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("{$t} missing");
            }
        }
    }

    private function io(string $title, ?int $parentId = null): int
    {
        $id = Io::create(['title' => $title] + ($parentId ? ['parent_id' => $parentId] : []), 'en');
        \AhgCore\Support\StatusRow::set($id, 158, 160);

        return $id;
    }

    private function slugOf(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }

    private function subjectTerm(string $name): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitTerm', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('term')->insert(['id' => $id, 'taxonomy_id' => 35, 'parent_id' => 110, 'lft' => 0, 'rgt' => 0, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);

        return $id;
    }

    private function link(int $ioId, int $termId): void
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitObjectTermRelation', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('object_term_relation')->insert(['id' => $id, 'object_id' => $ioId, 'term_id' => $termId]);
    }

    public function test_the_duplicate_is_merged_into_the_record_kept(): void
    {
        $winner = $this->io('ZZ Kept record');
        $loser = $this->io('ZZ Duplicate record');
        $child = $this->io('ZZ Child of the duplicate', $loser);
        $loserSlug = $this->slugOf($loser);

        $shared = $this->subjectTerm('ZZ Shared subject');
        $own = $this->subjectTerm('ZZ Duplicate-only subject');
        $this->link($winner, $shared);
        $this->link($loser, $shared);
        $this->link($loser, $own);

        $eventId = (int) DB::table('object')->insertGetId(['class_name' => 'QubitEvent', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('event')->insert(['id' => $eventId, 'object_id' => $loser, 'type_id' => 111, 'start_date' => '1901-01-01', 'source_culture' => 'en']);

        $pairId = (int) DB::table('ahg_duplicate_detection')->insertGetId([
            'record_a_id' => $winner, 'record_b_id' => $loser, 'similarity_score' => 0.95, 'detection_method' => 'title_match', 'status' => 'pending',
        ]);

        $logId = app(RecordMergeService::class)->merge($winner, $loser, null, $pairId);

        $this->assertFalse(DB::table('information_object')->where('id', $loser)->exists(), 'the duplicate is deleted');
        $this->assertSame($winner, (int) DB::table('information_object')->where('id', $child)->value('parent_id'));
        $this->assertTrue(DB::table('information_object_closure')->where('ancestor', $winner)->where('descendant', $child)->exists());
        $this->assertSame($winner, (int) DB::table('event')->where('id', $eventId)->value('object_id'));
        $this->assertSame(1, DB::table('object_term_relation')->where('object_id', $winner)->where('term_id', $shared)->count(), 'a shared term is not doubled');
        $this->assertTrue(DB::table('object_term_relation')->where('object_id', $winner)->where('term_id', $own)->exists());
        $this->assertSame('merged', DB::table('ahg_duplicate_detection')->where('id', $pairId)->value('status'));

        $log = DB::table('ahg_merge_log')->where('id', $logId)->first();
        $this->assertSame([$loserSlug], json_decode($log->slugs_redirected, true));
        $this->assertNotEmpty(json_decode((string) $log->loser_snapshot_json, true));

        $this->assertSame($this->slugOf($winner), RecordMergeService::redirectTarget((string) $loserSlug));
        $this->get('/'.$loserSlug)->assertRedirect('/'.$this->slugOf($winner))->assertStatus(301);
    }

    public function test_impossible_merges_are_refused(): void
    {
        $parent = $this->io('ZZ Parent');
        $child = $this->io('ZZ Its child', $parent);
        $svc = app(RecordMergeService::class);

        foreach ([[$parent, $parent], [$child, $parent], [1, $parent]] as [$winner, $loser]) {
            try {
                $svc->merge($winner, $loser);
                $this->fail("merge {$loser} into {$winner} was allowed");
            } catch (\DomainException $e) {
                $this->assertTrue(DB::table('information_object')->where('id', $loser)->exists());
            }
        }
    }

    public function test_the_dedupe_merge_page_runs_the_merge(): void
    {
        $winner = $this->io('ZZ Kept via page');
        $loser = $this->io('ZZ Duplicate via page');
        $pairId = (int) DB::table('ahg_duplicate_detection')->insertGetId([
            'record_a_id' => $winner, 'record_b_id' => $loser, 'similarity_score' => 0.9, 'detection_method' => 'title_match', 'status' => 'confirmed',
        ]);

        $this->actingAs($this->makeAdmin())
            ->post(route('dedupe.merge.execute', $pairId), ['primary_id' => $winner])
            ->assertRedirect(route('dedupe.browse'));

        $this->assertFalse(DB::table('information_object')->where('id', $loser)->exists());
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'merge-admin-'.$id, 'email' => uniqid('merge-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
