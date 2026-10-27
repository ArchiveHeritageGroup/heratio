<?php

/**
 * PublicListsRestrictedTest - the sitemap and ResourceSync advertise only what
 * a visitor may see (heratio#1544): a published record under an active embargo
 * (or any other DisclosureGate restriction) is left out, as OAI-PMH already did.
 * Before, both listed it with its address and dates.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Services\DisclosureGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicListsRestrictedTest extends TestCase
{
    use DatabaseTransactions;

    private function published(): int
    {
        $now = now();
        $io = DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('slug')->insert(['object_id' => $io, 'slug' => 'public-lists-'.$io]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $io,
            'type_id' => DisclosureGate::STATUS_TYPE_PUBLICATION, 'status_id' => DisclosureGate::STATUS_PUBLISHED]);

        return $io;
    }

    /** Ids a protected or private query of $class returns among $ids. */
    private function listed(object $controller, string $method, array $ids): array
    {
        $m = new \ReflectionMethod($controller, $method);

        return $m->invoke($controller)->whereIn('io.id', $ids)->pluck('io.id')->map('intval')->sort()->values()->all();
    }

    public function test_embargoed_records_are_not_advertised(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('embargo')) {
            $this->markTestSkipped('embargo table not installed.');
        }
        $open = $this->published();
        $embargoed = $this->published();
        DB::table('embargo')->insert(['object_id' => $embargoed, 'embargo_type' => 'full', 'start_date' => now()->subDay()->toDateString(), 'status' => 'active']);
        app()->forgetInstance(DisclosureGate::class);

        $this->assertSame([$open], $this->listed(app(\AhgApi\Controllers\PublicSitemapController::class), 'publishedQuery', [$open, $embargoed]), 'sitemap');
        $this->assertSame([$open], $this->listed(app(\AhgResourceSync\Controllers\ResourceSyncController::class), 'publishedRecordQuery', [$open, $embargoed]), 'ResourceSync');

        $this->get('/sitemap.xml')->assertOk();
        $this->get('/resourcesync/resourcelist.xml')->assertOk();
    }
}
