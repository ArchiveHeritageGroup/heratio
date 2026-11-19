<?php

/**
 * ExploreCollectionTest - "Explore this collection": /explore/{slug} draws the
 * whole collection as the relationship explorer does, linking records through
 * names read from their own text (reviewed names only - not pending or
 * redacted ones) as well as catalogued access points, with each record's
 * picture and text in the side panel, and nothing a guest may not see.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExploreCollectionTest extends TestCase
{
    use DatabaseTransactions;

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(?int $parent, string $title, int $lft, int $rgt, bool $published = true, string $scope = ''): int
    {
        $id = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent ?? 1, 'source_culture' => 'en', 'lft' => $lft, 'rgt' => $rgt]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title, 'scope_and_content' => $scope]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'zzexp-'.$id]);
        StatusRow::set($id, 158, $published ? 160 : 159);

        return $id;
    }

    private function ner(int $io, string $type, string $value, string $status): void
    {
        DB::table('ahg_ner_entity')->insert(['object_id' => $io, 'entity_type' => $type, 'entity_value' => $value, 'status' => $status, 'confidence' => 0.95]);
    }

    private function data(string $html): array
    {
        $this->assertSame(1, preg_match('/window\.DATA = (.*?); window\.LIVE = /s', $html, $m));

        return json_decode($m[1], true);
    }

    public function test_the_collection_explorer_links_records_through_names_in_their_text(): void
    {
        if (! Schema::hasTable('ahg_ner_entity')) {
            $this->markTestSkipped('No NER table');
        }
        Cache::flush();
        $fonds = $this->record(null, 'ZZ Funeral scrapbook', 7000000, 7000009);
        $a = $this->record($fonds, 'ZZ Cutting one', 7000001, 7000002, true, 'General Smuts lay in state at the Union Buildings.');
        $b = $this->record($fonds, 'ZZ Cutting two', 7000003, 7000004, true, 'Mrs Smuts received the mourners.');
        $c = $this->record($fonds, 'ZZ Cutting three', 7000005, 7000006);
        $draft = $this->record($fonds, 'ZZ Draft cutting', 7000007, 7000008, false);
        foreach ([$a, $b] as $io) {
            $this->ner($io, 'PERSON', 'Jan Smuts', 'linked');
        }
        $this->ner($c, 'PERSON', 'Jan Smuts', 'pending');      // unreviewed: not used
        $this->ner($a, 'PERSON', 'Private Person', 'redacted'); // never shown
        $this->ner($a, 'GPE', 'Pretoria', 'linked');
        $this->ner($b, 'GPE', 'Pretoria', 'linked');
        $this->ner($draft, 'PERSON', 'Jan Smuts', 'linked');   // draft record: a guest does not see it
        $m = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $m, 'object_id' => $a, 'usage_id' => 140, 'name' => 'p1.jpg', 'path' => '/uploads/r/zzexp/', 'mime_type' => 'image/jpeg']);
        DB::table('digital_object')->insert(['id' => $this->objectRow('QubitDigitalObject'), 'parent_id' => $m, 'usage_id' => 141, 'name' => 'p1_141.jpg', 'path' => '/uploads/r/zzexp/', 'mime_type' => 'image/jpeg']);

        $this->get(route('explore.collection', 'zzexp-'.$fonds))->assertRedirect();
        $html = $this->get(route('glam.browse', ['ancestor' => $fonds, 'view' => 'graph', 'topLevel' => 0, 'type' => 'all']))->assertOk()->getContent();
        $d = $this->data($html);

        $ids = array_column($d['records'], 'id');
        $this->assertNotContains((string) $draft, $ids, 'a draft is not drawn for a guest');
        $this->assertContains((string) $a, $ids);
        $ents = collect($d['entities'])->keyBy('name');
        $this->assertSame([(string) $a, (string) $b], collect($ents['Jan Smuts']['recs'])->sort()->values()->all(), 'linked through the name in their text; the pending one is left out');
        $this->assertSame('place', $ents['Pretoria']['type']);
        $this->assertArrayNotHasKey('Private Person', $ents->all());

        $ra = collect($d['records'])->firstWhere('id', (string) $a);
        $this->assertStringEndsWith('/uploads/r/zzexp/p1_141.jpg', $ra['img']);
        $this->assertStringContainsString('Union Buildings', $ra['text']);
        $this->assertSame('ZZ Funeral scrapbook', $d['site']['title']);
    }
}
