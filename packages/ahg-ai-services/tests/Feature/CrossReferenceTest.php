<?php

/**
 * CrossReferenceTest - same story, duplicate leaves, grounded entities, grouping
 * by printed form, the gazetteer check and the explorer links (heratio#1524).
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

use AhgAiServices\Services\CrossReferenceService;
use AhgAiServices\Services\GazetteerService;
use AhgApi\Services\ExplorerDataService;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrossReferenceTest extends TestCase
{
    use DatabaseTransactions;

    private const TELEGRAM = 'LADYSMITH Monday The relief column under General Buller crossed the Tugela this morning and the garrison heard the guns of the naval brigade all day long';

    public function test_the_same_telegram_in_two_papers_is_linked_and_unrelated_items_are_not(): void
    {
        $svc = new CrossReferenceService;
        $texts = [
            1 => self::TELEGRAM.' (Cape Times)',
            2 => 'From our correspondent. '.self::TELEGRAM,
            3 => 'Advertisement for Sunlight Soap, sold by every grocer in the colony at sixpence the bar, best value.',
        ];

        $pairs = $svc->sameStory($texts);

        $this->assertCount(1, $pairs);
        $this->assertSame([1, 2], [$pairs[0]['a'], $pairs[0]['b']]);
        $this->assertGreaterThanOrEqual(0.9, $pairs[0]['overlap']);
    }

    public function test_stock_phrases_shared_by_many_items_do_not_link(): void
    {
        $texts = [];
        for ($i = 1; $i <= 25; $i++) {
            $texts[$i] = "Item {$i} about something different number {$i} entirely. All rights reserved by the publishers of this paper.";
        }

        $this->assertSame([], (new CrossReferenceService)->sameStory($texts, 0.3, 20));
    }

    public function test_pages_sharing_three_identical_items_are_flagged_and_links_are_stored(): void
    {
        $vol = Io::create(['title' => 'ZZ Volume'], 'en');
        $pageA = Io::create(['title' => 'ZZ Page A', 'parent_id' => $vol], 'en');
        $pageB = Io::create(['title' => 'ZZ Page B', 'parent_id' => $vol], 'en');
        $stories = [
            'The mail steamer arrived at Durban harbour yesterday with troops and horses for the front line',
            'A fire broke out in the market square of Pietermaritzburg late on Saturday night destroying two shops',
            'The town council met on Tuesday to discuss the water supply and the new reservoir on the hill above town',
        ];
        $ids = [];
        foreach ($stories as $s) {
            $ids[] = Io::create(['title' => 'ZZ cutting', 'scope_and_content' => $s, 'parent_id' => $pageA], 'en');
            $ids[] = Io::create(['title' => 'ZZ cutting', 'scope_and_content' => $s, 'parent_id' => $pageB], 'en');
        }
        $svc = new CrossReferenceService;
        $texts = $svc->items($vol);
        $pairs = $svc->sameStory($texts);

        $this->assertSame([['page_a' => min($pageA, $pageB), 'page_b' => max($pageA, $pageB), 'shared_items' => 3]], $svc->duplicateLeaves($pairs));
        $this->assertSame(3, $svc->storeLinks(array_keys($texts), $pairs));
        $this->assertSame(3, $svc->storeLinks(array_keys($texts), $pairs), 'a re-run replaces its own links, not doubles them');
        $this->assertSame(3, DB::table('relation')->where('type_id', CrossReferenceService::RELATED_TYPE)->whereIn('subject_id', $ids)->count());
    }

    public function test_only_names_printed_in_the_text_are_kept(): void
    {
        $io = Io::create(['title' => 'ZZ Entity item', 'scope_and_content' => 'General Buller and the Natal Carbineers reached Colenso.'], 'en');
        $svc = new CrossReferenceService(fn () => [
            'people' => ['General Buller', 'General Louis Botha Joubert'],   // the second is the model's invention
            'places' => ['Colenso'],
            'units' => ['Natal Carbineers'],
            'newspapers' => [],
        ]);

        $kept = $svc->entities($io, 'General Buller and the Natal Carbineers reached Colenso.');

        $this->assertEqualsCanonicalizing(['General Buller', 'Colenso', 'Natal Carbineers'], array_column($kept, 'value'));
        $this->assertSame(3, DB::table('ahg_ner_entity')->where('object_id', $io)->where('status', 'pending')->count());
    }

    public function test_names_group_by_printed_form_with_titles_and_ocr_slips_but_keep_different_people_apart(): void
    {
        $groups = (new CrossReferenceService)->group([
            [1, 'person', 'Gen. Buller'], [2, 'person', 'General Buller'], [3, 'person', 'Buller'],
            [4, 'person', 'A. Joubert'], [5, 'person', 'General Joubert'],
            [6, 'place', 'Ladysmith'], [7, 'place', 'Ladysmith'], [8, 'place', 'Ladysmlth'], [9, 'place', 'LADYSMITH'],
        ]);
        $by = collect($groups)->keyBy('name');

        $this->assertSame([1, 2, 3], $by['General Buller']['items'], 'titles are dropped for grouping only');
        $this->assertArrayHasKey('A. Joubert', $by->all(), 'an initial in front is a different man');
        $this->assertArrayHasKey('General Joubert', $by->all());
        $this->assertSame([6, 7, 8, 9], $by['Ladysmith']['items'], 'a one-character OCR slip is the same place');
        $this->assertContains('Ladysmlth', $by['Ladysmith']['variants']);
    }

    public function test_places_are_checked_against_an_offline_gazetteer_and_listed_for_review(): void
    {
        $dump = tempnam(sys_get_temp_dir(), 'gaz');
        file_put_contents($dump, implode("\n", [
            "990001\tColenso\tColenso\t\t-28.73\t29.82\tP\tPPL\tZA",
            "990002\tSwartkop\tSwartkop\tSwartkops\t-25.80\t28.15\tT\tHLL\tZA",
            "990003\tFrere\tFrere\t\t-28.88\t29.78\tP\tPPL\tZA",
        ])."\n");
        $svc = new GazetteerService;
        $this->assertSame(4, $svc->load($dump, 'test'));
        $this->assertSame(0, $svc->load($dump, 'test'), 'loading the same file again adds nothing');

        $rows = collect($svc->check([
            ['name' => 'Colenso', 'variants' => ['Colenso'], 'items' => [1, 2]],
            ['name' => 'Zwartkop', 'variants' => ['Zwartkop'], 'items' => [3]],
            ['name' => 'Frere Camp', 'variants' => ['Frere Camp'], 'items' => [4]],
            ['name' => 'Colensoo', 'variants' => ['Colensoo'], 'items' => [5]],
            ['name' => 'Qqxyzzy', 'variants' => ['Qqxyzzy'], 'items' => [6]],
        ]))->keyBy('name');
        unlink($dump);

        $this->assertSame('found', $rows['Colenso']['status']);
        $this->assertSame('Swartkop', $rows['Zwartkop']['match'], 'the period spelling Zw- is Sw-');
        $this->assertStringContainsString('by name part', $rows['Frere Camp']['feature']);
        $this->assertStringStartsWith('possible misspelling', $rows['Colensoo']['status']);
        $this->assertSame('Colenso', $rows['Colensoo']['match']);
        $this->assertSame('not in gazetteer', $rows['Qqxyzzy']['status']);
        $this->assertStringStartsWith('possible', $svc->check([['name' => 'Colensoo', 'variants' => [], 'items' => []], ['name' => 'Colenso', 'variants' => [], 'items' => []]])[0]['status'], 'misspellings come first');
    }

    public function test_the_command_links_stories_extracts_grounded_names_and_writes_the_place_review_list(): void
    {
        DB::table(GazetteerService::TABLE)->insert(['source' => 'test', 'source_id' => '990001', 'name' => 'Colenso', 'name_key' => 'colenso', 'feature_class' => 'P', 'country_code' => 'ZA']);
        $vol = Io::create(['title' => 'ZZ Cuttings volume'], 'en');
        $a = Io::create(['title' => 'ZZ cutting A', 'scope_and_content' => self::TELEGRAM.' Buller reached Colenzo.', 'parent_id' => $vol], 'en');
        $b = Io::create(['title' => 'ZZ cutting B', 'scope_and_content' => 'Reuter. '.self::TELEGRAM, 'parent_id' => $vol], 'en');
        $slug = DB::table('slug')->where('object_id', $vol)->value('slug');
        Http::fake(['*/ollama/api/chat' => Http::response(['message' => ['content' => json_encode(
            ['people' => ['General Buller'], 'places' => ['Colenzo', 'Ladysmith', 'Pretoria'], 'units' => [], 'newspapers' => []])]])]);
        $csv = tempnam(sys_get_temp_dir(), 'places');

        $this->artisan('ahg:cross-reference', ['slug' => $slug, '--entities' => true, '--places' => $csv])->assertSuccessful();

        Http::assertSent(fn ($r) => $r->url() === 'https://ai.theahg.co.za/ai/v1/ollama/api/chat', 'entities go through the gateway only');
        $rel = DB::table('relation as r')->join('ric_relation_meta as m', 'm.relation_id', '=', 'r.id')
            ->where('r.type_id', CrossReferenceService::RELATED_TYPE)->whereIn('r.subject_id', [$a, $b])->first();
        $this->assertNotNull($rel, 'the same-story link carries its RiC meaning');
        $this->assertSame('rico:isAssociatedWith', $rel->rico_predicate);
        $this->assertSame('probable', $rel->certainty);
        $this->assertFalse(DB::table('ahg_ner_entity')->where('object_id', $b)->where('entity_value', 'Pretoria')->exists(), 'a name not printed in the item is dropped');

        $lines = array_map('str_getcsv', file($csv, FILE_IGNORE_NEW_LINES));
        unlink($csv);
        $this->assertSame(['Decision', 'Correct form'], array_slice($lines[0], -2));
        $colenzo = collect($lines)->first(fn ($l) => $l[1] === 'Colenzo');
        $this->assertStringStartsWith('possible misspelling', $colenzo[0]);
        $this->assertSame('Colenso', $colenzo[4]);
        // The telegram prints LADYSMITH: the stored name is the printed form, not the model's casing.
        $this->assertEqualsCanonicalizing(['Colenzo', 'LADYSMITH'], array_column(array_slice($lines, 1), 1), 'every printed place is listed, nothing else');

        $data = ExplorerDataService::build([$a, $b], 'https://example.org');
        $rec = collect($data['records'])->keyBy('id');
        $this->assertSame((string) $b, $rec[(string) $a]['same'][0][0], 'the explorer shows the other printing');
    }

    public function test_the_explorer_takes_client_colours_from_declared_site_tokens_only(): void
    {
        $style = ExplorerDataService::brandStyle(':root{--gcid-primary-color:#E1B34F;--gcid-secondary-color:#10373E;--gcid-link-color:#653332;--gcid-x:red}'
            .' .a{--headtext: url(javascript:alert(1))}');

        $this->assertStringContainsString('--primary: #E1B34F;', $style);
        $this->assertStringContainsString('--head: #10373E;', $style);
        $this->assertStringContainsString('--accent: #653332;', $style);
        $this->assertStringNotContainsString('javascript', $style);
        $this->assertSame('', ExplorerDataService::brandStyle('body { color: red }'));
    }

    public function test_pairs_on_a_leaf_photographed_twice_are_not_same_story_links(): void
    {
        $vol = Io::create(['title' => 'ZZ Vol'], 'en');
        $p1 = Io::create(['title' => 'ZZ P1', 'parent_id' => $vol], 'en');
        $p2 = Io::create(['title' => 'ZZ P2', 'parent_id' => $vol], 'en');
        $p3 = Io::create(['title' => 'ZZ P3', 'parent_id' => $vol], 'en');
        $x = Io::create(['title' => 'x', 'parent_id' => $p1], 'en');
        $y = Io::create(['title' => 'y', 'parent_id' => $p2], 'en');
        $z = Io::create(['title' => 'z', 'parent_id' => $p3], 'en');
        $pairs = [['a' => $x, 'b' => $y], ['a' => $x, 'b' => $z]];

        $kept = (new CrossReferenceService)->withoutLeafRepeats($pairs, [['page_a' => min($p1, $p2), 'page_b' => max($p1, $p2), 'shared_items' => 3]]);

        $this->assertSame([['a' => $x, 'b' => $z]], $kept);
    }
}
