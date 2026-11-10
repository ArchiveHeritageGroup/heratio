<?php

/**
 * EmbargoScopeTest - heratio#1588: one rule for "this embargo applies today".
 * An embargo saved from /embargo (is_active=1, status left 'pending') and a
 * future-dated one that has reached its start date are enforced by the access
 * gate; one lifted from /embargo (is_active=0, status still 'active') is not;
 * and the admin pages count the same embargoes as the gate.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Services\DisclosureGate;
use AhgCore\Support\EmbargoScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmbargoScopeTest extends TestCase
{
    use DatabaseTransactions;

    private function io(): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);

        return $id;
    }

    private function embargo(int $io, array $row): void
    {
        DB::table('embargo')->insert($row + ['object_id' => $io, 'embargo_type' => 'full', 'start_date' => now()->subDay()->toDateString(), 'created_at' => now()]);
    }

    public function test_the_gate_and_the_counts_follow_one_rule(): void
    {
        if (! Schema::hasTable('embargo') || ! Schema::hasColumn('embargo', 'is_active')) {
            $this->markTestSkipped('embargo table not installed.');
        }
        $fromEmbargoPage = $this->io();   // saved on /embargo before the fix
        $this->embargo($fromEmbargoPage, ['is_active' => 1, 'status' => 'pending']);
        $futureReached = $this->io();     // saved pending, start date now reached
        $this->embargo($futureReached, ['is_active' => 1, 'status' => 'pending', 'start_date' => now()->toDateString()]);
        $notYet = $this->io();            // starts tomorrow
        $this->embargo($notYet, ['is_active' => 1, 'status' => 'pending', 'start_date' => now()->addDay()->toDateString()]);
        $liftedOnEmbargoPage = $this->io(); // lifted on /embargo before the fix
        $this->embargo($liftedOnEmbargoPage, ['is_active' => 0, 'status' => 'active']);
        $liftedProperly = $this->io();
        $this->embargo($liftedProperly, ['is_active' => 1, 'status' => 'lifted']);
        $ended = $this->io();
        $this->embargo($ended, ['is_active' => 1, 'status' => 'active', 'end_date' => now()->subDay()->toDateString()]);
        $plain = $this->io();
        $this->embargo($plain, ['is_active' => 1, 'status' => 'active']);

        $gate = app(DisclosureGate::class)->embargoedIds();
        foreach (['/embargo save' => $fromEmbargoPage, 'start reached' => $futureReached, 'plain' => $plain] as $why => $id) {
            $this->assertContains($id, $gate, "enforced: {$why}");
        }
        foreach (['starts tomorrow' => $notYet, '/embargo lift' => $liftedOnEmbargoPage, 'lifted' => $liftedProperly, 'ended' => $ended] as $why => $id) {
            $this->assertNotContains($id, $gate, "not enforced: {$why}");
        }

        // The admin lists count exactly what the gate enforces.
        $ours = [$fromEmbargoPage, $futureReached, $notYet, $liftedOnEmbargoPage, $liftedProperly, $ended, $plain];
        $current = EmbargoScope::current(DB::table('embargo'))->whereIn('object_id', $ours)->pluck('object_id')->map('intval')->sort()->values()->all();
        $this->assertSame(collect([$fromEmbargoPage, $futureReached, $plain])->sort()->values()->all(), $current);
        $listed = app(\AhgExtendedRights\Services\ExtendedRightsService::class)->getActiveEmbargoes('active')->pluck('object_id')->map('intval')->intersect($ours)->sort()->values()->all();
        $this->assertSame($current, $listed, 'the extended-rights admin list agrees with the gate');
    }
}
