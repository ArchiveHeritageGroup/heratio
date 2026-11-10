<?php

/**
 * GlamBrowseCountsTest - heratio#1589: the "with digital objects" figure counts
 * the filtered results, not the whole site; and the default sector's filter can
 * be removed (type=all) instead of coming straight back.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlamBrowseCountsTest extends TestCase
{
    use DatabaseTransactions;

    private function record(string $title, bool $withFile): int
    {
        $obj = fn (string $class) => (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
        $id = $obj('QubitInformationObject');
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'gbc-'.$id]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $id, 'type_id' => 158, 'status_id' => 160]);
        if ($withFile) {
            DB::table('digital_object')->insert(['id' => $obj('QubitDigitalObject'), 'object_id' => $id, 'usage_id' => 140, 'name' => 'f.jpg', 'path' => '/x/', 'mime_type' => 'image/jpeg']);
        }

        return $id;
    }

    private function setting(string $key, string $value): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value]);
        \AhgCore\Services\AhgSettingsService::clearCache();
    }

    public function test_the_digital_object_figure_counts_the_filtered_results(): void
    {
        $this->setting('ahg_display_count_cache_ttl', '0');
        $term = 'zzgbc'.substr(uniqid(), -6);
        $this->record("A {$term} one", true);
        $this->record("A {$term} two", false);

        $this->get('/glam/browse?topLevel=0&query='.$term)->assertOk()
            ->assertSee('2 results', false)->assertSee('1 with digital objects', false);
    }

    public function test_the_default_sector_can_be_removed(): void
    {
        $this->setting('default_sector', 'archive');

        $page = $this->get('/glam/browse')->assertOk();
        $page->assertSee(e(route('glam.browse', ['type' => 'all'])), false); // "Clear all" keeps the default off
        $this->assertStringContainsString('type=all', $page->getContent(), 'the chip removes the default instead of reloading it');

        $all = $this->get('/glam/browse?type=all')->assertOk()->getContent();
        $this->assertStringNotContainsString(__('Active:'), $all, 'type=all shows no type filter');
    }
}
