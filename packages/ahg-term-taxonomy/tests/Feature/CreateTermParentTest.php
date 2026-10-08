<?php

/**
 * CreateTermParentTest - where TermService::create() files a new term.
 *
 * heratio#1546: a new term was made a child of whichever term in its taxonomy
 * had the smallest lft, and the nested set was shifted for that taxonomy only.
 * It now goes under the term root (110), or under a broad term of the same
 * taxonomy given on the form, as that parent's last child in the global set.
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

use AhgTermTaxonomy\Services\TermService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreateTermParentTest extends TestCase
{
    use DatabaseTransactions;

    private const SUBJECTS = 35;

    private const PLACES = 42;

    private TermService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(TermService::class);
        if (! DB::table('term')->where('id', TermService::ROOT_TERM_ID)->exists()) {
            $this->markTestSkipped('term root 110 missing');
        }
    }

    private function make(string $name, int $taxonomy, mixed $broad = null): object
    {
        $slug = $this->svc->create(['taxonomy_id' => $taxonomy, 'name' => $name, 'parent_id' => $broad], 'en');

        return DB::table('term')->join('slug', 'slug.object_id', '=', 'term.id')
            ->where('slug.slug', $slug)->first(['term.id', 'term.parent_id', 'term.lft', 'term.rgt']);
    }

    public function test_a_new_term_goes_under_the_root_not_an_existing_term(): void
    {
        $first = $this->make('ZZ First subject', self::SUBJECTS);
        $second = $this->make('ZZ Second subject', self::SUBJECTS);

        $this->assertSame(TermService::ROOT_TERM_ID, (int) $first->parent_id);
        $this->assertSame(TermService::ROOT_TERM_ID, (int) $second->parent_id);
        $this->assertTrue(DB::table('term_closure')->where('ancestor', TermService::ROOT_TERM_ID)->where('descendant', $second->id)->where('depth', 1)->exists());
        $this->assertFalse(DB::table('term_closure')->where('ancestor', $first->id)->where('descendant', $second->id)->exists());
    }

    public function test_a_broad_term_of_the_same_taxonomy_is_honoured_by_name_or_id(): void
    {
        $broad = $this->make('ZZ Broad subject', self::SUBJECTS);
        $byName = $this->make('ZZ Narrow by name', self::SUBJECTS, 'ZZ Broad subject');
        $byId = $this->make('ZZ Narrow by id', self::SUBJECTS, (string) $broad->id);

        $this->assertSame((int) $broad->id, (int) $byName->parent_id);
        $this->assertSame((int) $broad->id, (int) $byId->parent_id);
        // Both sit inside the broad term's range in the nested set.
        $b = DB::table('term')->where('id', $broad->id)->first(['lft', 'rgt']);
        foreach ([$byName->id, $byId->id] as $id) {
            $n = DB::table('term')->where('id', $id)->first(['lft', 'rgt']);
            $this->assertTrue($b->lft < $n->lft && $n->rgt < $b->rgt);
        }
    }

    public function test_a_broad_term_from_another_taxonomy_is_ignored(): void
    {
        $place = $this->make('ZZ Some place', self::PLACES);
        $subject = $this->make('ZZ Subject with foreign broad', self::SUBJECTS, (string) $place->id);

        $this->assertSame(TermService::ROOT_TERM_ID, (int) $subject->parent_id);
    }
}
