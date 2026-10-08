<?php

/**
 * CrossReferenceTest - same story, duplicate leaves, grounded entities (heratio#1524).
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
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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
}
