<?php

/**
 * LibraryFeatureTestCase - integration test harness for the library circulation
 * + ILL feature suites (#1093).
 *
 * RefreshDatabase / migrate:fresh is not usable in this repo's test database:
 * unrelated package migrations (e.g. ahg-version-control) declare hard foreign
 * keys onto core catalogue tables that live in database/core/*.sql rather than
 * in a migration, so a from-scratch `migrate:fresh` aborts before reaching the
 * library migrations. This harness instead boots an isolated in-memory SQLite
 * connection and runs ONLY the library circulation + ILL migration files added
 * for #1093 - which is what actually needs proving: that those migrations build
 * a schema the already-written services run against. Each test gets a fresh DB.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class LibraryFeatureTestCase extends TestCase
{
    /** Library migration files run, in order, to build the test schema. */
    protected array $migrationFiles = [
        '2026_06_02_000101_create_library_circulation_tables.php',
        '2026_06_02_000102_create_library_patron_category_table.php',
        '2026_06_02_000103_create_library_notice_tables.php',
        '2026_06_02_000104_create_library_ill_request_tables.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSqlite();
        $this->runLibraryMigrations();
    }

    /**
     * Point the default connection at a private in-memory SQLite database so
     * assertDatabaseHas() + the DB facade resolve against it.
     */
    protected function bootSqlite(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
            'foreign_key_constraints' => false,
        ]);

        $db = $this->app->make('db');
        $db->purge('sqlite');
        $db->setDefaultConnection('sqlite');
        // Force the Schema facade (a cached singleton) to rebuild against the
        // freshly-pointed default connection.
        $this->app->forgetInstance('db.schema');
        Schema::clearResolvedInstances();
    }

    protected function runLibraryMigrations(): void
    {
        $dir = dirname(__DIR__, 2) . '/database/migrations';
        foreach ($this->migrationFiles as $file) {
            $migration = require $dir . '/' . $file;
            $migration->up();
        }
    }
}
