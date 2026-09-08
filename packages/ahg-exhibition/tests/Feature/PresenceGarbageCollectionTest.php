<?php

/**
 * PresenceGarbageCollectionTest - stale presence rows are invisible at once and
 * collected eventually, and the two are separate promises.
 *
 * The walkthrough beats 2-3 times a second per visitor, and every beat used to
 * sweep the whole building for stale rows. That made GC load grow with the
 * square of the audience, and on 7 Sep 2026 it deadlocked in production against
 * the upsert immediately above it: the upsert holds a row via uq_building_token
 * and wants the (building_id, last_seen) range, while a concurrent delete holds
 * that range and wants the row. Both indexes are correct and present - the
 * collision is lock ordering, not the query plan.
 *
 * The sweep is now sampled, roughly one beat in twenty-five, which is only safe
 * because it was never load-bearing: both reads filter on last_seen >= now-12s,
 * so a stale row is invisible to visitors whether or not it has been deleted.
 * That is the property this file pins first, because it is the one a visitor
 * could actually notice.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

namespace Tests\Feature;

use AhgExhibition\Services\ExhibitionSpaceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PresenceGarbageCollectionTest extends TestCase
{
    use DatabaseTransactions;

    private ExhibitionSpaceService $svc;

    private string $building;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ExhibitionSpaceService::class);
        // Own the fixture: this table is shared by every building on the
        // instance, and a test that assumed an empty one would be testing the
        // demo's leftovers.
        $this->building = 'phpunit-presence-'.bin2hex(random_bytes(4));
    }

    private function space(): object
    {
        return (object) ['building_id' => $this->building, 'slug' => $this->building];
    }

    private function ghost(int $secondsAgo): void
    {
        DB::table('ahg_exhibition_presence')->insert([
            'building_id' => $this->building,
            'session_token' => 'ghost-'.$secondsAgo,
            'display_name' => 'Ghost',
            'role' => 'visitor',
            'tour_active' => 0,
            'last_seen' => now()->subSeconds($secondsAgo),
        ]);
    }

    private function rows(): int
    {
        return DB::table('ahg_exhibition_presence')->where('building_id', $this->building)->count();
    }

    /**
     * The property that matters to a visitor, and it holds on the very first
     * beat - before any sweep can have run. Sampling the GC cannot regress it.
     */
    public function test_a_stale_visitor_is_never_shown_even_while_the_row_survives(): void
    {
        $this->ghost(60);

        $result = $this->svc->presenceBeat($this->space(), ['token' => 'live', 'name' => 'Live']);

        $this->assertCount(0, $result['peers'], 'a visitor last seen 60s ago is not a peer');
        $this->assertGreaterThanOrEqual(1, $this->rows(), 'and the row may well still be there - that is fine');
    }

    /** A live co-visitor IS shown, so the filter is not simply hiding everyone. */
    public function test_a_live_visitor_is_still_shown(): void
    {
        $this->svc->presenceBeat($this->space(), ['token' => 'other', 'name' => 'Other']);

        $result = $this->svc->presenceBeat($this->space(), ['token' => 'live', 'name' => 'Live']);

        $this->assertCount(1, $result['peers'], 'a visitor beating right now is a peer');
    }

    /**
     * Collection still happens, just not on every beat.
     *
     * Sampled at 1 in 25, so 300 beats miss every time with probability
     * (24/25)^300, about one run in five million. Stated rather than hidden:
     * this is the one assertion here that is probabilistic, and it is the
     * housekeeping promise rather than the safety one.
     */
    public function test_stale_rows_are_collected_eventually(): void
    {
        $this->ghost(60);
        $this->ghost(90);
        $this->assertSame(2, $this->rows(), 'precondition: two stale rows');

        for ($i = 0; $i < 300; $i++) {
            $this->svc->presenceBeat($this->space(), ['token' => 'live', 'name' => 'Live']);
        }

        $this->assertSame(1, $this->rows(), 'only the live visitor should remain');
    }

    /**
     * A row inside the 15s window is not collected - the sweep must not evict
     * someone who is merely between beats.
     */
    public function test_a_recently_seen_visitor_is_not_collected(): void
    {
        $this->ghost(5);

        for ($i = 0; $i < 300; $i++) {
            $this->svc->presenceBeat($this->space(), ['token' => 'live', 'name' => 'Live']);
        }

        $this->assertSame(2, $this->rows(), 'a 5s-old row is still live and must survive the sweep');
    }
}
