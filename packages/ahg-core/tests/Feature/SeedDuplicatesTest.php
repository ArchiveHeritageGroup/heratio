<?php

/**
 * SeedDuplicatesTest - ahg:seed-duplicates removes identical copies of system
 * form templates (moving what points at them to the kept one) and landing
 * blocks on missing pages, and leaves alone anything that differs, user
 * templates and blocks on real pages (heratio#1556 clean-up).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeedDuplicatesTest extends TestCase
{
    use DatabaseTransactions;

    private function template(string $name, int $system, string $label = 'Title'): int
    {
        $id = (int) DB::table('ahg_form_template')->insertGetId(['name' => $name, 'form_type' => 'information_object',
            'is_system' => $system, 'config_json' => '{"layout":"single"}']);
        DB::table('ahg_form_field')->insert(['template_id' => $id, 'field_name' => 'title', 'field_type' => 'text', 'label' => $label, 'sort_order' => 1]);

        return $id;
    }

    public function test_identical_copies_and_orphan_blocks_go_and_everything_else_stays(): void
    {
        foreach (['ahg_form_template', 'ahg_form_field', 'ahg_form_assignment', 'atom_landing_page', 'atom_landing_page_block'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("{$t} not installed.");
            }
        }
        $name = 'ZZ seed dup '.bin2hex(random_bytes(3));
        $keep = $this->template($name, 1);
        $copy = $this->template($name, 1);
        $edited = $this->template($name, 1, 'Title (edited by a user)');
        $user = $this->template($name, 0);
        $assignment = DB::table('ahg_form_assignment')->insertGetId(['template_id' => $copy] + $this->assignmentDefaults());

        $page = (int) DB::table('atom_landing_page')->insertGetId(['name' => $name, 'slug' => 'zz-'.bin2hex(random_bytes(3)), 'is_default' => 0, 'is_active' => 1]);
        $type = (int) DB::table('atom_landing_page_block_type')->value('id');
        $real = DB::table('atom_landing_page_block')->insertGetId(['page_id' => $page, 'block_type_id' => $type, 'position' => 1]);
        $missing = (int) DB::table('atom_landing_page')->max('id') + 1000;
        // The installer runs seeds with foreign key checks off; that is how these got in.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $orphan = DB::table('atom_landing_page_block')->insertGetId(['page_id' => $missing, 'block_type_id' => $type, 'position' => 1]);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->artisan('ahg:seed-duplicates')->assertExitCode(1);
        $this->assertTrue(DB::table('ahg_form_template')->where('id', $copy)->exists(), 'a dry run writes nothing');

        $this->artisan('ahg:seed-duplicates', ['--apply' => true])->assertExitCode(0);

        $this->assertFalse(DB::table('ahg_form_template')->where('id', $copy)->exists(), 'the identical copy is gone');
        $this->assertSame(0, DB::table('ahg_form_field')->where('template_id', $copy)->count());
        $this->assertSame($keep, (int) DB::table('ahg_form_assignment')->where('id', $assignment)->value('template_id'), 'its assignment moved to the kept template');
        $this->assertTrue(DB::table('ahg_form_template')->where('id', $keep)->exists());
        $this->assertTrue(DB::table('ahg_form_template')->where('id', $edited)->exists(), 'a copy that differs is left alone');
        $this->assertTrue(DB::table('ahg_form_template')->where('id', $user)->exists(), 'user templates are never touched');
        $this->assertFalse(DB::table('atom_landing_page_block')->where('id', $orphan)->exists(), 'the orphan block is gone');
        $this->assertTrue(DB::table('atom_landing_page_block')->where('id', $real)->exists(), 'blocks on real pages stay');
    }

    /** Required ahg_form_assignment columns other than template_id, with harmless values. */
    private function assignmentDefaults(): array
    {
        $out = [];
        foreach (DB::select("SELECT column_name AS c, data_type AS t FROM information_schema.columns WHERE table_schema = DATABASE()
                AND table_name = 'ahg_form_assignment' AND is_nullable = 'NO' AND column_default IS NULL
                AND extra NOT LIKE '%auto_increment%' AND column_name <> 'template_id'") as $col) {
            $out[$col->c] = in_array($col->t, ['int', 'bigint', 'tinyint', 'smallint'], true) ? 0 : 'test';
        }

        return $out;
    }
}
