<?php

/**
 * LibraryLegacyBranchDropTest - the #1473 follow-up migration drops a legacy
 * free-text branch column only when nothing in it is still unmatched, so an
 * install that has not reconciled its branch names keeps the text. Runs on a
 * throwaway in-memory SQLite connection, never the real database.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LibraryLegacyBranchDropTest extends TestCase
{
    private string $previous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous = config('database.default');
        config(['database.connections.branch_drop' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        config(['database.default' => 'branch_drop']);
        DB::purge('branch_drop');
        DB::statement('CREATE TABLE library_copy (id INTEGER PRIMARY KEY, shelf_location TEXT, branch TEXT, branch_id INTEGER)');
        DB::statement('CREATE TABLE library_hold (id INTEGER PRIMARY KEY, expiry_date TEXT, pickup_branch TEXT, branch_id INTEGER)');
    }

    protected function tearDown(): void
    {
        DB::purge('branch_drop');
        config(['database.default' => $this->previous]);
        parent::tearDown();
    }

    private function migrate(): void
    {
        (require base_path('packages/ahg-library/database/migrations/2026_11_03_090000_drop_legacy_library_branch_text.php'))->up();
    }

    public function test_an_unmatched_name_keeps_its_column_and_a_clean_one_is_dropped(): void
    {
        DB::table('library_copy')->insert(['id' => 1, 'branch' => 'Old Town', 'branch_id' => null]);
        DB::table('library_hold')->insert(['id' => 1, 'pickup_branch' => 'Main', 'branch_id' => 7]);

        $this->migrate();

        $this->assertTrue(Schema::hasColumn('library_copy', 'branch'), 'unmatched text must survive');
        $this->assertSame('Old Town', DB::table('library_copy')->value('branch'));
        $this->assertFalse(Schema::hasColumn('library_hold', 'pickup_branch'));
    }

    public function test_the_command_drops_only_after_the_rows_are_reconciled(): void
    {
        DB::table('library_copy')->insert(['id' => 1, 'branch' => 'Old Town', 'branch_id' => null]);

        Artisan::call('ahg:library-branch-unmatched', ['--drop' => true]);
        $this->assertTrue(Schema::hasColumn('library_copy', 'branch'));

        DB::table('library_copy')->update(['branch_id' => 3]);
        Artisan::call('ahg:library-branch-unmatched', ['--drop' => true]);
        $this->assertFalse(Schema::hasColumn('library_copy', 'branch'));
        $this->assertFalse(Schema::hasColumn('library_hold', 'pickup_branch'));
        $this->assertSame(3, (int) DB::table('library_copy')->value('branch_id'));
    }
}
