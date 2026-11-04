<?php

/**
 * ExplorerExportTest - ahg:explorer-export writes an offline explorer for a
 * collection from its catalogue links: published records only, spellings
 * that differ only in case or a leading "The" grouped under one name, and
 * names on fewer than --min records left out (heratio#1524).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgApi\Services\ExplorerDataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExplorerExportTest extends TestCase
{
    use DatabaseTransactions;

    private function obj(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(int $lft, int $rgt, int $status, ?int $parent = null): int
    {
        $id = $this->obj('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent, 'lft' => $lft, 'rgt' => $rgt, 'source_culture' => 'en', 'identifier' => "EX-$id"]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => "Record $id"]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => "ex-rec-$id"]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $id, 'type_id' => 158, 'status_id' => $status]);

        return $id;
    }

    private function term(int $taxonomy, string $name): int
    {
        $id = $this->obj('QubitTerm');
        DB::table('term')->insert(['id' => $id, 'taxonomy_id' => $taxonomy, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);

        return $id;
    }

    private function tag(int $record, int $term): void
    {
        DB::table('object_term_relation')->insert(['id' => $this->obj('QubitObjectTermRelation'), 'object_id' => $record, 'term_id' => $term]);
    }

    public function test_groups_printed_forms_keeps_published_and_drops_rare_names(): void
    {
        $base = 2000000000;
        $fonds = $this->record($base, $base + 9, 160);
        $a = $this->record($base + 1, $base + 2, 160, $fonds);
        $b = $this->record($base + 3, $base + 4, 160, $fonds);
        $draft = $this->record($base + 5, $base + 6, 159, $fonds);

        $this->tag($a, $this->term(35, 'The Foo Congress'));
        $this->tag($b, $this->term(35, 'FOO CONGRESS'));
        $this->tag($draft, $this->term(35, 'foo congress'));
        $this->tag($a, $this->term(42, 'Onlyonce'));

        $out = sys_get_temp_dir().'/explorer-test-'.uniqid();
        $this->artisan('ahg:explorer-export', ['--ancestor' => $fonds, '--out' => $out, '--base-url' => 'https://example.test', '--hide' => 'World, THE foo congress'])->assertSuccessful();

        $js = file_get_contents("$out/data.js");
        $data = json_decode(substr($js, strpos($js, '{'), strrpos($js, '}') - strpos($js, '{') + 1), true);
        $this->assertFileExists("$out/index.html");
        $this->assertFileExists("$out/cytoscape.min.js");

        $this->assertEqualsCanonicalizing([(string) $fonds, (string) $a, (string) $b], array_column($data['records'], 'id'), 'the draft is left out');
        $this->assertCount(1, $data['entities'], 'the place on one record is left out');
        $e = $data['entities'][0];
        $this->assertSame('subject:foocongress', $e['id']);
        $this->assertEqualsCanonicalizing(['The Foo Congress', 'FOO CONGRESS'], $e['variants']);
        $this->assertEqualsCanonicalizing([(string) $a, (string) $b], $e['recs']);
        $this->assertTrue($e['hidden'] ?? false, 'a --hide word is marked hidden whatever its case or leading The');
        $this->assertSame("https://example.test/ex-rec-$a", collect($data['records'])->firstWhere('id', (string) $a)['url']);

        array_map('unlink', glob("$out/*"));
        rmdir($out);
    }

    public function test_group_key_merges_only_case_punctuation_and_a_leading_the(): void
    {
        $this->assertSame(ExplorerDataService::groupKey('The A.N.C.'), ExplorerDataService::groupKey('anc'));
        $this->assertNotSame(ExplorerDataService::groupKey('ANC'), ExplorerDataService::groupKey('African National Congress'));
        $this->assertNotSame(ExplorerDataService::groupKey('A. Joubert'), ExplorerDataService::groupKey('General Joubert'));
    }

    public function test_refuses_the_whole_catalogue(): void
    {
        $this->artisan('ahg:explorer-export')->assertFailed();
    }
}
