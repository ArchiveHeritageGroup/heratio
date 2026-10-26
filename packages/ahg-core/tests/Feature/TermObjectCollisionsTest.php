<?php

/**
 * TermObjectCollisionsTest - heratio#1556: a term that shares its id with
 * another object is moved to an object row of its own, every reference
 * follows it, and deleting the other object no longer deletes the term.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Tests\Feature;

use AhgCore\Commands\TermObjectCollisionsCommand;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TermObjectCollisionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_colliding_level_term_is_moved_to_its_own_object(): void
    {
        $now = now();
        // An object that belongs to something else, and a level term that took its id.
        $shared = DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => $now, 'updated_at' => $now]);
        $parent = (int) DB::table('term')->where('taxonomy_id', 34)->value('parent_id');
        DB::table('term')->insert(['id' => $shared, 'taxonomy_id' => 34, 'parent_id' => $parent ?: null, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $shared, 'culture' => 'en', 'name' => 'Collision level']);
        DB::table('level_of_description_sector')->insert(['term_id' => $shared, 'sector' => 'museum', 'display_order' => 99]);

        $io = DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1,
            'level_of_description_id' => $shared, 'source_culture' => 'en']);

        $this->assertContains($shared, array_map(fn ($r) => (int) $r->id, TermObjectCollisionsCommand::collisions()));

        $this->artisan('ahg:term-object-collisions')->assertExitCode(1); // report only
        $this->assertTrue(DB::table('term')->where('id', $shared)->exists(), 'a dry run writes nothing');

        $this->artisan('ahg:term-object-collisions', ['--apply' => true])->assertExitCode(0);

        $new = (int) DB::table('information_object')->where('id', $io)->value('level_of_description_id');
        $this->assertNotSame($shared, $new, 'the record follows the term to its new id');
        $this->assertSame('QubitTerm', DB::table('object')->where('id', $new)->value('class_name'));
        $this->assertSame('Collision level', DB::table('term_i18n')->where('id', $new)->value('name'));
        $this->assertSame(1, DB::table('level_of_description_sector')->where('term_id', $new)->count());
        $this->assertSame('level-collision-level', DB::table('slug')->where('object_id', $new)->value('slug'));
        $this->assertFalse(DB::table('term')->where('id', $shared)->exists());
        $this->assertSame('QubitDigitalObject', DB::table('object')->where('id', $shared)->value('class_name'), 'the other object keeps its row');

        // The point of the repair: removing the other object leaves the level alone.
        DB::table('object')->where('id', $shared)->delete();
        $this->assertTrue(DB::table('term')->where('id', $new)->exists());
        $this->assertSame($new, (int) DB::table('information_object')->where('id', $io)->value('level_of_description_id'));

        $this->assertSame([], TermObjectCollisionsCommand::collisions());
        $this->artisan('ahg:term-object-collisions')->assertExitCode(0);
    }
}
