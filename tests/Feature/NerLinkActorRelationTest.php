<?php

/**
 * NerLinkActorRelationTest - heratio#1785: linking a reviewed person name to
 * a record writes a complete relation row (source_culture is NOT NULL) and
 * never leaves a half-written object row behind.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgAiServices\Services\NerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NerLinkActorRelationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_name_access_point_relation_is_written_with_its_culture(): void
    {
        $obj = fn (string $c) => (int) DB::table('object')->insertGetId(['class_name' => $c, 'created_at' => now(), 'updated_at' => now()]);
        $io = $obj('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $io, 'source_culture' => 'en', 'lft' => 9100000, 'rgt' => 9100001]);
        $actor = $obj('QubitActor');
        DB::table('actor')->insert(['id' => $actor, 'source_culture' => 'en']);

        $link = new \ReflectionMethod(NerService::class, 'linkActorToObject');
        $link->invoke(app(NerService::class), $io, $actor);
        $link->invoke(app(NerService::class), $io, $actor); // a second link is a no-op

        $rows = DB::table('relation')->where('subject_id', $io)->where('object_id', $actor)->get();
        $this->assertCount(1, $rows);
        $this->assertNotEmpty($rows[0]->source_culture);
        $this->assertSame('QubitRelation', DB::table('object')->where('id', $rows[0]->id)->value('class_name'));
    }
}
