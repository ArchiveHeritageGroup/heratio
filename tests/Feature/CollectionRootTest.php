<?php

/**
 * CollectionRootTest - the record page's "Explore this collection" link finds
 * the top-level collection by walking up the parents, also for records with no
 * nested-set bounds (a NULL lft made the old range query throw a 500).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Services\CollectionRoot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CollectionRootTest extends TestCase
{
    use DatabaseTransactions;

    private function io(int $parent, ?int $lft): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent, 'source_culture' => 'en', 'lft' => $lft, 'rgt' => $lft === null ? null : $lft + 1]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'cr-'.$id]);

        return $id;
    }

    public function test_the_collection_is_found_with_and_without_nested_set_bounds(): void
    {
        $top = $this->io(1, null);
        $mid = $this->io($top, null);
        $leaf = $this->io($mid, null);
        $single = $this->io(1, 9500000);

        $this->assertSame($top, CollectionRoot::of($leaf)->id);
        $this->assertSame('cr-'.$top, CollectionRoot::of($mid)->slug);
        $this->assertSame($top, CollectionRoot::of($top)->id, 'a collection links to its own explorer');
        $this->assertNull(CollectionRoot::of($single), 'a record on its own has nothing to explore');
    }
}
