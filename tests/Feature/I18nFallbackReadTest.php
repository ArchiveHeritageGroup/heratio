<?php

/**
 * I18nFallbackReadTest - heratio#1608: records, actors and terms that only
 * have an English i18n row still read in full when the interface is in
 * another language. Before the fix the read queries inner-joined the i18n
 * tables on the interface culture, so an English catalogue browsed in
 * Afrikaans lost its creators, subjects and places, and titles read
 * "Untitled".
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Support\I18nFallback;
use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class I18nFallbackReadTest extends TestCase
{
    use DatabaseTransactions;

    private const CREATION = 111;

    private const SUBJECTS = 35;

    private const PLACES = 42;

    private function obj(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function slug(int $id, string $prefix): string
    {
        $slug = $prefix.'-'.$id;
        DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);

        return $slug;
    }

    private function actor(string $name, string $class = 'QubitActor'): int
    {
        $id = $this->obj($class);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $id, 'culture' => 'en', 'authorized_form_of_name' => $name, 'history' => $name.' history']);
        $this->slug($id, 'zzi18n-actor');
        StatusRow::set($id, 158, 160);

        return $id;
    }

    private function term(int $taxonomy, string $name): int
    {
        $id = $this->obj('QubitTerm');
        DB::table('term')->insert(['id' => $id, 'taxonomy_id' => $taxonomy, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);
        $this->slug($id, 'zzi18n-term');

        return $id;
    }

    private function record(?int $parent, string $title, int $lft, int $rgt, ?int $repo = null): int
    {
        $id = $this->obj('QubitInformationObject');
        DB::table('information_object')->insert([
            'id' => $id, 'parent_id' => $parent ?? 1, 'source_culture' => 'en',
            'lft' => $lft, 'rgt' => $rgt, 'repository_id' => $repo,
        ]);
        DB::table('information_object_i18n')->insert([
            'id' => $id, 'culture' => 'en', 'title' => $title, 'scope_and_content' => $title.' scope',
        ]);
        $this->slug($id, 'zzi18n-io');
        StatusRow::set($id, 158, 160);

        return $id;
    }

    private function link(int $io, int $term): void
    {
        DB::table('object_term_relation')->insert(['id' => $this->obj('QubitObjectTermRelation'), 'object_id' => $io, 'term_id' => $term]);
    }

    /** An English-only fonds with one child, a creator, a subject, a place and a repository. */
    private function catalogue(): array
    {
        Cache::flush();
        $repo = $this->actor('ZZ Engelse Argief Repository', 'QubitRepository');
        DB::table('repository')->insert(['id' => $repo, 'source_culture' => 'en']);
        $base = (int) DB::table('information_object')->max('rgt') + 100;
        $fonds = $this->record(null, 'ZZ English Fonds', $base, $base + 5, $repo);
        $child = $this->record($fonds, 'ZZ English Child File', $base + 1, $base + 2, $repo);
        $sib = $this->record($fonds, 'ZZ English Second File', $base + 3, $base + 4, $repo);
        $creator = $this->actor('ZZ English Creator');
        DB::table('event')->insert([
            'id' => $this->obj('QubitEvent'), 'type_id' => self::CREATION, 'object_id' => $fonds,
            'actor_id' => $creator, 'start_date' => '1901-01-01', 'source_culture' => 'en',
        ]);
        $subject = $this->term(self::SUBJECTS, 'ZZ English Subject');
        $place = $this->term(self::PLACES, 'ZZ English Place');
        $this->link($fonds, $subject);
        $this->link($fonds, $place);

        return compact('repo', 'fonds', 'child', 'sib', 'creator', 'subject', 'place');
    }

    private function af(): self
    {
        app()->setLocale('af');

        return $this->withSession(['locale' => 'af']);
    }

    public function test_helper_falls_back_per_id_and_prefers_the_current_culture(): void
    {
        $a = $this->term(self::SUBJECTS, 'ZZ only English');
        $b = $this->term(self::SUBJECTS, 'ZZ English too');
        DB::table('term_i18n')->insert(['id' => $b, 'culture' => 'af', 'name' => 'ZZ ook Afrikaans']);

        $names = I18nFallback::pluck('term_i18n', [$a, $b], 'name', 'af');
        $this->assertSame('ZZ only English', $names[$a]);
        $this->assertSame('ZZ ook Afrikaans', $names[$b]);

        $rows = DB::table('term')
            ->tap(fn ($q) => I18nFallback::join($q, 'term_i18n', 'term.id', 'af', required: true))
            ->whereIn('term.id', [$a, $b])
            ->orderBy('term.id')
            ->pluck(I18nFallback::col('term_i18n', 'name', 'name'))
            ->all();
        $this->assertSame(['ZZ only English', 'ZZ ook Afrikaans'], $rows, 'one row per term, no duplicates');
    }

    public function test_the_record_page_shows_english_title_creators_subjects_and_places(): void
    {
        $c = $this->catalogue();
        $slug = DB::table('slug')->where('object_id', $c['fonds'])->value('slug');

        $html = $this->af()->get('/'.$slug)->assertOk()->getContent();

        $this->assertStringContainsString('ZZ English Fonds', $html);
        $this->assertStringContainsString('ZZ English Creator', $html);
        $this->assertStringContainsString('ZZ English Subject', $html);
        $this->assertStringContainsString('ZZ English Place', $html);
        $this->assertStringContainsString('ZZ Engelse Argief Repository', $html);
    }

    public function test_the_actor_and_term_pages_read_the_english_row(): void
    {
        $c = $this->catalogue();
        $actorSlug = DB::table('slug')->where('object_id', $c['creator'])->value('slug');
        $termSlug = DB::table('slug')->where('object_id', $c['subject'])->value('slug');
        $repoSlug = DB::table('slug')->where('object_id', $c['repo'])->value('slug');

        $this->af()->get('/actor/'.$actorSlug)->assertOk()->assertSee('ZZ English Creator');
        $this->af()->get('/term/'.$termSlug)->assertOk()->assertSee('ZZ English Subject');
        $this->af()->get('/repository/'.$repoSlug)->assertOk()->assertSee('ZZ Engelse Argief Repository');
    }

    public function test_the_browse_lists_english_only_records(): void
    {
        $c = $this->catalogue();

        $html = $this->af()->get('/glam/browse?topLevel=0&type=all&ancestor='.$c['fonds'])->assertOk()->getContent();

        $this->assertStringContainsString('ZZ English Child File', $html);
        $this->assertStringContainsString('ZZ English Second File', $html);
    }

    public function test_the_treeview_lists_english_titles(): void
    {
        $c = $this->catalogue();

        $data = $this->af()->getJson('/informationobject/treeview-data?id='.$c['fonds'])->assertOk()->json();
        $this->assertSame('ZZ English Fonds', $data['current']['title']);
        $titles = array_column($data['children'], 'title');
        $this->assertContains('ZZ English Child File', $titles);
        $this->assertContains('ZZ English Second File', $titles);

        $leaf = $this->af()->getJson('/informationobject/treeview-data?id='.$c['child'])->assertOk()->json();
        $this->assertSame('ZZ English Fonds', $leaf['ancestors'][0]['title'] ?? null);
        $this->assertContains('ZZ English Second File', array_column($leaf['nextSiblings'], 'title'));
    }

    public function test_the_api_returns_english_only_records(): void
    {
        $c = $this->catalogue();
        $slug = DB::table('slug')->where('object_id', $c['fonds'])->value('slug');

        $json = $this->af()->getJson('/api/v1/informationobjects/'.$slug)->assertOk()->json();
        $flat = json_encode($json);

        $this->assertStringContainsString('ZZ English Fonds', $flat);
        $this->assertStringContainsString('ZZ English Creator', $flat);
        $this->assertStringContainsString('ZZ English Subject', $flat);
        $this->assertStringContainsString('ZZ English Place', $flat);
    }
}
