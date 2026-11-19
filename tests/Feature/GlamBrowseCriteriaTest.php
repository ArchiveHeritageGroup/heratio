<?php

/**
 * GlamBrowseCriteriaTest - heratio#1765: the advanced-search criteria rows
 * (so[]/sq[]/sf[]) combine with and/or/not, left to right.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlamBrowseCriteriaTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        // The settings cache outlives the rolled-back row; leave no TTL 0 behind
        // for the next test (GlamLiveFacetCacheTest failed in CI after this one).
        \AhgCore\Services\AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function record(string $title): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'gbcr-'.$id]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $id, 'type_id' => 158, 'status_id' => 160]);

        return $id;
    }

    private function results(string $qs): string
    {
        return $this->get('/glam/browse?topLevel=0&type=all&'.$qs)->assertOk()->getContent();
    }

    public function test_criteria_rows_combine_with_and_or_not(): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'ahg_display_count_cache_ttl'], ['setting_value' => '0']);
        \AhgCore\Services\AhgSettingsService::clearCache();
        $a = 'zzcra'.substr(uniqid(), -6);
        $b = 'zzcrb'.substr(uniqid(), -6);
        $this->record("{$a} only");
        $this->record("{$a} {$b} both");
        $this->record("{$b} only");

        $this->assertStringContainsString('3 results', $this->results("sq[]={$a}&sq[]={$b}&so[]=and&so[]=or"));
        $this->assertStringContainsString('1 results', $this->results("sq[]={$a}&sq[]={$b}&so[]=and&so[]=and&sf[]=title&sf[]=title"));
        $only = $this->results("sq[]={$a}&sq[]={$b}&so[]=and&so[]=not");
        $this->assertStringContainsString("{$a} only", $only);
        $this->assertStringNotContainsString("{$a} {$b} both", $only);
    }
}
