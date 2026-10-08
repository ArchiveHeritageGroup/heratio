<?php

/**
 * MergeTermTest - merge a duplicate term into the term kept.
 *
 * heratio#1533: there was no term merge. TermService::mergeInto() moves every
 * description link, narrower term and term-typed column to the term kept, adds
 * the duplicate's name as a use-for label, and deletes the duplicate.
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

use AhgCore\Services\ClosureMaintenanceService;
use AhgTermTaxonomy\Services\TermService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergeTermTest extends TestCase
{
    use DatabaseTransactions;

    private const SUBJECTS = 35;

    private const LEVELS = 34;

    private TermService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(TermService::class);
        if (! DB::table('term')->where('id', TermService::ROOT_TERM_ID)->exists()) {
            $this->markTestSkipped('term root 110 missing');
        }
    }

    private function term(string $name, int $taxonomy, int $parent = TermService::ROOT_TERM_ID): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitTerm', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('term')->insert(['id' => $id, 'taxonomy_id' => $taxonomy, 'parent_id' => $parent, 'lft' => 0, 'rgt' => 0, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'zz-merge-term-'.$id]);
        app(ClosureMaintenanceService::class)->addNode('term', $id, $parent);

        return $id;
    }

    private function description(): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'source_culture' => 'en']);

        return $id;
    }

    private function link(int $ioId, int $termId): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitObjectTermRelation', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('object_term_relation')->insert(['id' => $id, 'object_id' => $ioId, 'term_id' => $termId]);

        return $id;
    }

    public function test_a_duplicate_term_is_merged_into_the_term_kept(): void
    {
        $keep = $this->term('ZZ Mining', self::SUBJECTS);
        $dup = $this->term('ZZ Mines', self::SUBJECTS);
        $narrower = $this->term('ZZ Gold mines', self::SUBJECTS, $dup);
        $both = $this->description();
        $onlyDup = $this->description();
        $this->link($both, $keep);
        $this->link($both, $dup);
        $this->link($onlyDup, $dup);

        $result = $this->svc->mergeInto($dup, $keep);

        $this->assertFalse(DB::table('term')->where('id', $dup)->exists(), 'the duplicate is deleted');
        $this->assertSame(1, DB::table('object_term_relation')->where('object_id', $both)->where('term_id', $keep)->count());
        $this->assertTrue(DB::table('object_term_relation')->where('object_id', $onlyDup)->where('term_id', $keep)->exists());
        $this->assertSame($keep, (int) DB::table('term')->where('id', $narrower)->value('parent_id'));
        $this->assertTrue(DB::table('term_closure')->where('ancestor', $keep)->where('descendant', $narrower)->exists());
        $this->assertContains('ZZ Mines', DB::table('other_name')->join('other_name_i18n', 'other_name_i18n.id', '=', 'other_name.id')
            ->where('other_name.object_id', $keep)->pluck('other_name_i18n.name')->all());
        $this->assertSame(1, $result['narrower']);
    }

    public function test_a_term_typed_column_follows_the_merge_instead_of_being_nulled(): void
    {
        $keep = $this->term('ZZ Folder', self::LEVELS);
        $dup = $this->term('ZZ File folder', self::LEVELS);
        $io = $this->description();
        DB::table('information_object')->where('id', $io)->update(['level_of_description_id' => $dup]);

        $this->svc->mergeInto($dup, $keep);

        $this->assertSame($keep, (int) DB::table('information_object')->where('id', $io)->value('level_of_description_id'));
    }

    public function test_cross_taxonomy_system_and_narrower_merges_are_refused(): void
    {
        $subject = $this->term('ZZ A subject', self::SUBJECTS);
        $narrower = $this->term('ZZ Its narrower', self::SUBJECTS, $subject);
        $level = $this->term('ZZ A level', self::LEVELS);
        $status = (int) DB::table('term')->where('taxonomy_id', 59)->value('id');

        foreach ([[$subject, $level], [$subject, $narrower], [$status, (int) DB::table('term')->where('taxonomy_id', 59)->where('id', '!=', $status)->value('id')]] as [$loser, $winner]) {
            if (! $loser || ! $winner) {
                continue;
            }
            try {
                $this->svc->mergeInto($loser, $winner);
                $this->fail("merge {$loser} into {$winner} was allowed");
            } catch (\DomainException $e) {
                $this->assertTrue(DB::table('term')->where('id', $loser)->exists());
            }
        }
    }
}
