<?php

/**
 * SchemaExistenceCacheTest - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Database\CachingMySqlBuilder;
use AhgCore\Database\SchemaExistenceCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The cache may only ever say "exists" for something that exists. What must
 * hold: a present table/column is answered from the cache (no query); a
 * missing one is always re-checked live, so create-if-missing still runs;
 * a column added later is seen; a drop through the builder is forgotten;
 * and the answers survive a new process via the file.
 *
 * DDL commits implicitly in MySQL, so this uses a throwaway table and drops
 * it in tearDown rather than a transaction.
 */
class SchemaExistenceCacheTest extends TestCase
{
    private string $table;

    private string $file;

    private CachingMySqlBuilder $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->table = 'zz_schema_cache_test_'.getmypid();
        $this->file = sys_get_temp_dir().'/ahg-schema-test-'.getmypid().'.php';
        @unlink($this->file);
        SchemaExistenceCache::enable($this->file);
        $conn = DB::connection();
        $conn->useDefaultSchemaGrammar();
        $this->schema = new CachingMySqlBuilder($conn);
        $this->schema->dropIfExists($this->table);
    }

    protected function tearDown(): void
    {
        $this->schema->dropIfExists($this->table);
        SchemaExistenceCache::disable();
        @unlink($this->file);
        parent::tearDown();
    }

    private function schemaQueries(callable $fn): int
    {
        $n = 0;
        DB::listen(function ($q) use (&$n) {
            if (stripos($q->sql, 'information_schema') !== false) {
                $n++;
            }
        });
        $fn();

        return $n;
    }

    public function test_missing_table_is_rechecked_live_and_then_remembered_once_created(): void
    {
        $this->assertFalse($this->schema->hasTable($this->table));
        // Negative answers are never cached - it must ask again.
        $this->assertSame(1, $this->schemaQueries(fn () => $this->schema->hasTable($this->table)));

        $this->schema->create($this->table, fn (Blueprint $t) => $t->id());
        $this->assertTrue($this->schema->hasTable($this->table));
        // Now answered from the cache.
        $this->assertSame(0, $this->schemaQueries(fn () => $this->assertTrue($this->schema->hasTable($this->table))));
    }

    public function test_a_column_added_later_is_seen(): void
    {
        $this->schema->create($this->table, fn (Blueprint $t) => $t->id());
        $this->assertTrue($this->schema->hasColumn($this->table, 'id'));
        $this->assertFalse($this->schema->hasColumn($this->table, 'extra'));

        $this->schema->table($this->table, fn (Blueprint $t) => $t->string('extra')->nullable());
        $this->assertTrue($this->schema->hasColumn($this->table, 'extra'));
        $this->assertSame(0, $this->schemaQueries(fn () => $this->assertTrue($this->schema->hasColumns($this->table, ['id', 'extra']))));
    }

    public function test_drop_through_the_builder_is_forgotten(): void
    {
        $this->schema->create($this->table, fn (Blueprint $t) => $t->id());
        $this->assertTrue($this->schema->hasTable($this->table));
        $this->schema->drop($this->table);
        $this->assertFalse($this->schema->hasTable($this->table));
        $this->assertFalse(SchemaExistenceCache::hasColumn($this->table, 'id'));
    }

    public function test_answers_survive_to_a_new_process_through_the_file(): void
    {
        $this->schema->create($this->table, fn (Blueprint $t) => $t->id());
        $this->assertTrue($this->schema->hasColumn($this->table, 'id'));
        SchemaExistenceCache::save();

        // A new process: fresh static state, same file.
        SchemaExistenceCache::enable($this->file);
        $this->assertTrue(SchemaExistenceCache::hasTable($this->table));
        $this->assertTrue(SchemaExistenceCache::hasColumn($this->table, 'id'));
        $this->assertFalse(SchemaExistenceCache::hasColumn($this->table, 'nope'));
    }
}
