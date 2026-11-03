<?php

/**
 * FusekiOrphanTrackingTest - a RiC URI that orphans again after its tracking
 * row was cleaned reopens that row instead of inserting a duplicate, which
 * failed the unique key and the whole nightly ahg:fuseki-orphan-cleanup run.
 * A human 'retained' decision is not overwritten.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgRic\Console\Commands\FusekiOrphanCleanupCommand;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FusekiOrphanTrackingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ric_orphan_tracking')) {
            $this->markTestSkipped('ric_orphan_tracking not installed.');
        }
    }

    private function track(string $uri): void
    {
        $m = new \ReflectionMethod(FusekiOrphanCleanupCommand::class, 'trackOrphan');
        $m->invoke(app(FusekiOrphanCleanupCommand::class), $uri, 'activity');
    }

    public function test_a_cleaned_orphan_that_returns_reopens_its_row(): void
    {
        $uri = 'urn:ahg:ric:activity:test-'.uniqid();
        $this->track($uri);
        DB::table('ric_orphan_tracking')->where('ric_uri', $uri)->update(['status' => 'cleaned', 'cleaned_at' => now(), 'resolved_at' => now()]);

        $this->track($uri);
        $this->track($uri);

        $rows = DB::table('ric_orphan_tracking')->where('ric_uri', $uri)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('detected', $rows[0]->status);
        $this->assertNull($rows[0]->cleaned_at);
        $this->assertNull($rows[0]->resolved_at);
    }

    public function test_a_retained_decision_is_left_alone(): void
    {
        $uri = 'urn:ahg:ric:activity:test-'.uniqid();
        $this->track($uri);
        DB::table('ric_orphan_tracking')->where('ric_uri', $uri)->update(['status' => 'retained']);

        $this->track($uri);

        $this->assertSame('retained', DB::table('ric_orphan_tracking')->where('ric_uri', $uri)->value('status'));
    }
}
