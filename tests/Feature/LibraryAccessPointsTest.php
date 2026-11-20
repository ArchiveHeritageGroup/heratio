<?php

/**
 * LibraryAccessPointsTest - heratio#1786: a library record shows its own
 * name, subject and place access points, and an actor page lists top-level
 * records (such as a library collection) before the pages inside others.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgActorManage\Services\ActorService;
use AhgLibrary\Services\LibraryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LibraryAccessPointsTest extends TestCase
{
    use DatabaseTransactions;

    private function obj(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function io(int $parent, string $title, int $lft): int
    {
        $id = $this->obj('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent, 'source_culture' => 'en', 'lft' => $lft, 'rgt' => $lft + 1]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'lap-'.$id]);

        return $id;
    }

    private function nameLink(int $io, int $actor): void
    {
        $r = $this->obj('QubitRelation');
        DB::table('relation')->insert(['id' => $r, 'subject_id' => $io, 'object_id' => $actor, 'type_id' => 161, 'source_culture' => 'en']);
    }

    public function test_library_record_access_points_and_actor_ordering(): void
    {
        $actor = $this->obj('QubitActor');
        DB::table('actor')->insert(['id' => $actor, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $actor, 'culture' => 'en', 'authorized_form_of_name' => 'Zz Test Person']);
        DB::table('slug')->insert(['object_id' => $actor, 'slug' => 'zz-test-person-'.$actor]);

        $scrapbook = $this->io(1, 'A scrapbook', 9200000);
        $page = $this->io($scrapbook, 'Aaa page one', 9200002);
        $bookcase = $this->io(1, 'Zzz bookcase', 9200004);
        $this->nameLink($page, $actor);
        $this->nameLink($bookcase, $actor);

        $place = $this->obj('QubitTerm');
        DB::table('term')->insert(['id' => $place, 'taxonomy_id' => 42, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $place, 'culture' => 'en', 'name' => 'Irene']);
        DB::table('object_term_relation')->insert(['id' => $this->obj('QubitObjectTermRelation'), 'object_id' => $bookcase, 'term_id' => $place]);

        $ap = app(LibraryService::class)->getAccessPoints($bookcase);
        $this->assertSame(['Zz Test Person'], $ap['names']->pluck('name')->all());
        $this->assertSame('zz-test-person-'.$actor, $ap['names'][0]->slug);
        $this->assertSame(['Irene'], $ap['places']->pluck('name')->all());

        // The top-level bookcase comes before the page, whatever the titles.
        $items = app(ActorService::class)->getSubjectOfResourcesPaginated($actor, 1, 10)['items'];
        $this->assertSame([$bookcase, $page], $items->pluck('id')->map(fn ($i) => (int) $i)->all());
    }

    public function test_library_holdings_list_in_shelf_order_with_authors(): void
    {
        $case = $this->io(1, 'Test bookcase', 9300000);
        DB::table('library_item')->insert(['information_object_id' => $case]);
        $second = $this->io($case, 'Second on the shelf', 9300004);
        $first = $this->io($case, 'First on the shelf', 9300002);
        DB::table('information_object')->where('id', $first)->update(['identifier' => 'TST-S1-01']);
        $li = (int) DB::table('library_item')->insertGetId(['information_object_id' => $first]);
        DB::table('library_item_creator')->insert(['library_item_id' => $li, 'name' => 'Driesch, Hans', 'role' => 'author', 'sort_order' => 0]);

        $h = app(LibraryService::class)->getHoldings($case);
        $this->assertSame([$first, $second], $h->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame('Driesch, Hans', $h[0]->authors);
        $this->assertSame('TST-S1-01', $h[0]->identifier);
    }
}
