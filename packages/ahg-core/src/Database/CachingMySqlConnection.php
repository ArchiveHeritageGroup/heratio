<?php

/**
 * CachingMySqlConnection - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgCore\Database;

use Illuminate\Database\MySqlConnection;

/** The stock MySQL connection, handing out the caching schema builder. */
class CachingMySqlConnection extends MySqlConnection
{
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new CachingMySqlBuilder($this);
    }
}
