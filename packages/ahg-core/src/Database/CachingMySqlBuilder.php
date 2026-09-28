<?php

/**
 * CachingMySqlBuilder - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgCore\Database;

use Closure;
use Illuminate\Database\Schema\MySqlBuilder;

/**
 * MySqlBuilder whose existence checks consult SchemaExistenceCache first.
 * A cached "exists" answers without a query; anything else falls through to
 * the live check, and a live "exists" is remembered. Schema changes made
 * through this builder forget what they touch.
 */
class CachingMySqlBuilder extends MySqlBuilder
{
    public function hasTable($table)
    {
        // A schema-qualified name (db.table) is outside what the cache keys on.
        if (str_contains((string) $table, '.')) {
            return parent::hasTable($table);
        }
        $key = $this->connection->getTablePrefix().$table;
        if (SchemaExistenceCache::hasTable($key)) {
            return true;
        }
        $exists = parent::hasTable($table);
        if ($exists) {
            SchemaExistenceCache::rememberTable($key);
        }

        return $exists;
    }

    public function hasColumn($table, $column)
    {
        return $this->hasColumns($table, [$column]);
    }

    public function hasColumns($table, array $columns)
    {
        if (str_contains((string) $table, '.')) {
            return parent::hasColumns($table, $columns);
        }
        $key = $this->connection->getTablePrefix().$table;
        $all = true;
        foreach ($columns as $c) {
            if (! SchemaExistenceCache::hasColumn($key, $c)) {
                $all = false;
                break;
            }
        }
        if ($all) {
            return true;
        }

        $listing = $this->getColumnListing($table);
        SchemaExistenceCache::rememberColumns($key, $listing);
        $lower = array_map(strtolower(...), $listing);
        foreach ($columns as $c) {
            if (! in_array(strtolower($c), $lower, true)) {
                return false;
            }
        }

        return true;
    }

    public function table($table, Closure $callback)
    {
        parent::table($table, $callback);
        SchemaExistenceCache::forgetColumns($this->connection->getTablePrefix().$table);
    }

    public function drop($table)
    {
        parent::drop($table);
        SchemaExistenceCache::forgetTable($this->connection->getTablePrefix().$table);
    }

    public function dropIfExists($table)
    {
        parent::dropIfExists($table);
        SchemaExistenceCache::forgetTable($this->connection->getTablePrefix().$table);
    }

    public function dropColumns($table, $columns)
    {
        parent::dropColumns($table, $columns);
        SchemaExistenceCache::forgetColumns($this->connection->getTablePrefix().$table);
    }

    public function rename($from, $to)
    {
        parent::rename($from, $to);
        SchemaExistenceCache::forgetTable($this->connection->getTablePrefix().$from);
        SchemaExistenceCache::forgetTable($this->connection->getTablePrefix().$to);
    }
}
