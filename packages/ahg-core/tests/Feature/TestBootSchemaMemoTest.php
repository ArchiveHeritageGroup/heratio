<?php

/**
 * TestBootSchemaMemoTest - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Database\SchemaExistenceCache;
use AhgCore\Database\TestBootMySqlConnection;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * heratio#1502. The boot memo must save queries on a repeat boot without ever
 * changing what a test sees: off in test bodies, and cleared by raw DDL so a
 * provider can still recreate a table a test removed.
 */
class TestBootSchemaMemoTest extends TestCase
{
    private string $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->table = 'zz_boot_memo_test_'.getmypid();
        DB::statement("DROP TABLE IF EXISTS {$this->table}");
    }

    protected function tearDown(): void
    {
        DB::statement("DROP TABLE IF EXISTS {$this->table}");
        parent::tearDown();
    }

    public function test_the_memo_is_off_once_boot_has_finished(): void
    {
        $this->requireMemo();
        $this->assertInstanceOf(TestBootMySqlConnection::class, DB::connection());
        $this->assertFalse(SchemaExistenceCache::enabled());
    }

    public function test_a_test_body_sees_a_raw_drop_immediately(): void
    {
        DB::statement("CREATE TABLE {$this->table} (id INT)");
        $this->assertTrue(Schema::hasTable($this->table));

        DB::statement("DROP TABLE {$this->table}");
        $this->assertFalse(Schema::hasTable($this->table));
    }

    public function test_raw_ddl_clears_what_the_next_boot_would_trust(): void
    {
        $this->requireMemo();
        DB::statement("CREATE TABLE {$this->table} (id INT)");
        SchemaExistenceCache::enableTestBootMemo('memo-test');
        Schema::hasTable($this->table);
        $this->assertTrue(SchemaExistenceCache::hasTable($this->table));

        DB::statement("DROP TABLES {$this->table}");
        $this->assertFalse(SchemaExistenceCache::hasTable($this->table));
        $this->assertFalse(Schema::hasTable($this->table));
        SchemaExistenceCache::disable();
    }

    public function test_a_repeat_boot_runs_far_fewer_schema_queries(): void
    {
        $this->requireMemo();
        SchemaExistenceCache::flushTestBootMemo();
        $first = $this->schemaQueriesDuringBoot();
        $second = $this->schemaQueriesDuringBoot();

        $this->assertLessThan(max(1, (int) ($first / 2)), $second, "first boot {$first}, repeat boot {$second}");
    }

    private function requireMemo(): void
    {
        if (! filter_var(config('ahg-env.ahg_schema_cache') ?? true, FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('AHG_SCHEMA_CACHE=false switches the boot memo off.');
        }
    }

    private function schemaQueriesDuringBoot(): int
    {
        $app = require base_path('bootstrap/app.php');
        $n = 0;
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\RegisterFacades::class, function ($a) use (&$n) {
            $a['events']->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($e) use (&$n) {
                if (stripos($e->sql, 'information_schema') !== false) {
                    $n++;
                }
            });
        });
        $app->make(Kernel::class)->bootstrap();

        return $n;
    }
}
