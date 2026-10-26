<?php

/**
 * OpacPublicCatalogueTest - heratio#1554: the public catalogue at /opac is
 * reachable, renders, and shows only published, unrestricted items. Before
 * the fix the /{slug} catch-all answered /opac with 404, the index view did
 * not compile, the search queried a column that does not exist, and nothing
 * filtered drafts.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgCore\Services\DisclosureGate;
use AhgLibrary\Services\LibraryOpacSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OpacPublicCatalogueTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, object> published / draft: io, item, slug, title */
    private array $rec = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('library_item') || ! Schema::hasTable('status')) {
            $this->markTestSkipped('library tables not present in this install.');
        }
        $now = now();
        $tag = 'opac'.bin2hex(random_bytes(4));
        $lft = (int) DB::table('information_object')->max('rgt') + 100;
        foreach (['published' => DisclosureGate::STATUS_PUBLISHED, 'draft' => 159] as $kind => $statusId) {
            $io = DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
            $lft += 2;
            $title = "{$tag} {$kind} book";
            DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => $title]);
            DB::table('slug')->insert(['object_id' => $io, 'slug' => "{$tag}-{$kind}"]);
            DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $io, 'type_id' => DisclosureGate::STATUS_TYPE_PUBLICATION, 'status_id' => $statusId]);
            $item = DB::table('library_item')->insertGetId(['information_object_id' => $io, 'material_type' => 'monograph']);
            $this->rec[$kind] = (object) ['io' => $io, 'item' => $item, 'slug' => "{$tag}-{$kind}", 'title' => $title];
        }
        $this->tag = $tag;
    }

    private string $tag;

    public function test_the_opac_renders_and_shows_only_published_items(): void
    {
        $page = $this->get('/opac?q='.$this->tag);
        $page->assertOk();
        $page->assertSee($this->rec['published']->title);
        $page->assertDontSee($this->rec['draft']->title);

        $this->get('/opac/view/'.$this->rec['published']->slug)->assertOk();
        $this->get('/opac/view/'.$this->rec['draft']->slug)->assertNotFound();
    }

    public function test_the_search_index_path_excludes_draft_items(): void
    {
        $hidden = app(LibraryOpacSearchService::class)->hiddenItemIds();
        $this->assertContains($this->rec['draft']->item, $hidden);
        $this->assertNotContains($this->rec['published']->item, $hidden);
    }
}
