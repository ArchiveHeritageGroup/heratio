<?php

/**
 * IndexHelper - Conditional ALTER TABLE ADD INDEX helper
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgGis\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports the install.sql DELIMITER //CREATE PROCEDURE
 * ahg_gis_add_index(table, index, column) block to PHP. PDO can't parse
 * DELIMITER (it's a mysql-CLI directive, not server SQL), so the procedure
 * was never created via PackageInstaller. See issue #105.
 *
 * Generic helper — no callers in the codebase yet, kept available for any
 * future GIS migration that wants to add a single-column index conditionally.
 */
class IndexHelper
{
    public static function add(string $table, string $index, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $exists = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();

        if ($exists) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$column}`)");
    }
}
