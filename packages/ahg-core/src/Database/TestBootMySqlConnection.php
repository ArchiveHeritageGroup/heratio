<?php

/**
 * TestBootMySqlConnection - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgCore\Database;

use Illuminate\Database\Schema\MySqlBuilder;

/**
 * The connection used under tests (heratio#1502). Only the default connection
 * gets the memoising builder, because the memo is keyed by table name alone.
 * Any raw DROP, ALTER or RENAME clears the memo, so a table a test removed is
 * seen as missing at the next boot and its provider recreates it.
 *
 * ponytail: DDL sent straight to PDO or from another process is not seen. No
 * MySQL test does that today; AHG_SCHEMA_CACHE=false is the way out if one must.
 */
class TestBootMySqlConnection extends CachingMySqlConnection
{
    private const DDL = '/\b(DROP\s+(TEMPORARY\s+)?TABLES?|ALTER\s+TABLE|RENAME\s+TABLES?|DROP\s+(DATABASE|SCHEMA))\b/i';

    public function getSchemaBuilder()
    {
        if ($this->getName() === config('database.default')) {
            return parent::getSchemaBuilder();
        }
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new MySqlBuilder($this);
    }

    public function statement($query, $bindings = [])
    {
        $this->noteDdl($query);

        return parent::statement($query, $bindings);
    }

    public function affectingStatement($query, $bindings = [])
    {
        $this->noteDdl($query);

        return parent::affectingStatement($query, $bindings);
    }

    public function unprepared($query)
    {
        $this->noteDdl($query);

        return parent::unprepared($query);
    }

    private function noteDdl($query): void
    {
        if (is_string($query) && preg_match(self::DDL, $query)) {
            SchemaExistenceCache::flushTestBootMemo();
        }
    }
}
