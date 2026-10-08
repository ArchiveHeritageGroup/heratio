<?php

/**
 * heratio:test-db-diff - list where the test database lags the live schema.
 *
 * heratio#1547: heratio_test drifted behind the live schema (columns added by
 * provider self-heal, one-off SQL and install scripts that lagged the code),
 * so tests patched the schema themselves or avoided whole pages. This lists
 * every table and column the reference database has and the target lacks,
 * so each gap can be closed at its source (install.sql or a migration).
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

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestDbDiffCommand extends Command
{
    protected $signature = 'heratio:test-db-diff
        {--reference= : Schema to compare against (default: this connection\'s database)}
        {--target=heratio_test : Schema expected to match it}
        {--ignore=* : Table-name prefix to leave out (tables another application keeps in the same database)}
        {--json : Print JSON instead of tables}';

    protected $description = 'List tables and columns the reference database has and the test database lacks';

    public function handle(): int
    {
        $reference = (string) ($this->option('reference') ?: DB::connection()->getDatabaseName());
        $target = (string) $this->option('target');

        $columns = fn (string $schema) => collect(DB::select(
            'SELECT TABLE_NAME AS t, COLUMN_NAME AS c, COLUMN_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?',
            [$schema]
        ))->groupBy('t')->map(fn ($rows) => $rows->keyBy('c'));

        $ignore = (array) $this->option('ignore');
        $ref = $columns($reference)->reject(fn ($cols, $table) => collect($ignore)->contains(fn ($p) => str_starts_with((string) $table, $p)));
        $tgt = $columns($target);
        if ($tgt->isEmpty()) {
            $this->error("Target schema '{$target}' has no tables (or this user cannot read it).");

            return self::FAILURE;
        }

        $missingTables = $ref->keys()->diff($tgt->keys())->sort()->values();
        $missingColumns = [];
        foreach ($ref as $table => $cols) {
            if (! $tgt->has($table)) {
                continue;
            }
            foreach ($cols as $col => $row) {
                if (! $tgt[$table]->has($col)) {
                    $missingColumns[] = ['table' => $table, 'column' => $col, 'type' => $row->type];
                }
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode(['reference' => $reference, 'target' => $target,
                'missing_tables' => $missingTables, 'missing_columns' => $missingColumns], JSON_PRETTY_PRINT));
        } else {
            $this->info("Comparing {$target} against {$reference}");
            $this->line(count($missingTables).' table(s) and '.count($missingColumns).' column(s) missing in '.$target.'.');
            if ($missingColumns) {
                $this->table(['table', 'column', 'type'], $missingColumns);
            }
            if ($missingTables->isNotEmpty()) {
                $this->line('Missing tables: '.$missingTables->implode(', '));
            }
        }

        return ($missingTables->isEmpty() && $missingColumns === []) ? self::SUCCESS : self::FAILURE;
    }
}
