<?php

/**
 * SeedRerunTest - heratio#1556: the form-template and landing-page seeds can
 * run again without duplicating rows or attaching rows to ids that belong to
 * nothing. Before the fix every run added another copy of each system form
 * template, and landing blocks went to stale LAST_INSERT_ID() page ids.
 *
 * Statements are split the way the installer splits them and run on one
 * connection, inside a transaction that the test rolls back.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeedRerunTest extends TestCase
{
    use DatabaseTransactions;

    /** The DML statements of an install.sql from $marker on (no DDL, which would commit). */
    private function runSeed(string $package, string $marker): void
    {
        $sql = (string) file_get_contents(base_path("packages/{$package}/database/install.sql"));
        $sql = substr($sql, (int) strrpos(substr($sql, 0, (int) strpos($sql, $marker)), "\n"));
        $sql = implode("\n", array_filter(preg_split('/\r?\n/', $sql), fn ($l) => ! str_starts_with(ltrim($l), '--')));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if (preg_match('/^(CREATE|ALTER|DROP)\b/i', $stmt)) {
                continue;
            }
            DB::statement($stmt);
        }
    }

    public function test_form_templates_are_seeded_once_with_their_fields(): void
    {
        if (! Schema::hasTable('ahg_form_template')) {
            $this->markTestSkipped('ahg-forms not installed.');
        }
        DB::table('ahg_form_field')->whereIn('template_id', DB::table('ahg_form_template')->where('is_system', 1)->select('id'))->delete();
        DB::table('ahg_form_template')->where('is_system', 1)->delete();
        // A stale LAST_INSERT_ID() from an unrelated insert, as an earlier statement would leave.
        DB::table('object')->insertGetId(['class_name' => 'QubitTaxonomy', 'created_at' => now(), 'updated_at' => now()]);

        $this->runSeed('ahg-forms', 'SEED DATA');
        $templates = DB::table('ahg_form_template')->where('is_system', 1)->count();
        $fields = DB::table('ahg_form_field')->count();
        $this->assertSame(5, $templates);
        $this->assertSame(0, DB::table('ahg_form_field')->whereNotIn('template_id', DB::table('ahg_form_template')->select('id'))->count(), 'no field belongs to a missing template');
        $this->assertGreaterThan(0, DB::table('ahg_form_field')->whereIn('template_id', DB::table('ahg_form_template')->where('is_system', 1)->select('id'))->count());

        $this->runSeed('ahg-forms', 'SEED DATA');
        $this->assertSame($templates, DB::table('ahg_form_template')->where('is_system', 1)->count(), 'a re-run adds no template');
        $this->assertSame($fields, DB::table('ahg_form_field')->count(), 'a re-run adds no field');
    }

    public function test_landing_blocks_go_to_the_home_page_only_once(): void
    {
        if (! Schema::hasTable('atom_landing_page')) {
            $this->markTestSkipped('ahg-landing-page not installed.');
        }
        DB::table('atom_landing_page_block')->delete();
        DB::table('object')->insertGetId(['class_name' => 'QubitTaxonomy', 'created_at' => now(), 'updated_at' => now()]);

        $this->runSeed('ahg-landing-page', 'DEFAULT LANDING PAGE');
        $home = (int) DB::table('atom_landing_page')->where('slug', 'home')->value('id');
        $blocks = DB::table('atom_landing_page_block')->count();
        $this->assertGreaterThan(0, $blocks);
        $this->assertSame($blocks, DB::table('atom_landing_page_block')->where('page_id', $home)->count(), 'every block is on the home page');

        $this->runSeed('ahg-landing-page', 'DEFAULT LANDING PAGE');
        $this->assertSame($blocks, DB::table('atom_landing_page_block')->count(), 'a re-run adds no block');
    }
}
