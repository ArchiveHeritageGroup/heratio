<?php

/**
 * EncryptionBulkApplyCommand - encrypt every registered field for enabled categories
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

namespace AhgCore\Commands;

use AhgCore\Services\EncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EncryptionBulkApplyCommand extends Command
{
    protected $signature = 'ahg:encryption-bulk-apply
                            {--category= : Limit to one category (e.g. contact_details)}
                            {--dry-run : Count rows without writing}';

    protected $description = 'Encrypt every registered field row for categories whose encryption_field_<cat> setting is on.';

    public function handle(EncryptionService $svc): int
    {
        if (!$svc->isEnabled()) {
            $this->error('encryption_enabled is off; refusing to run.');
            return self::FAILURE;
        }

        $fields = $svc->listRegisteredFields($this->option('category'));
        if (empty($fields)) {
            $this->warn('No registered fields match.');
            return self::SUCCESS;
        }

        $totalEncrypted = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($fields as $f) {
            if (!$svc->shouldEncryptCategory($f->category)) {
                $this->line(sprintf(
                    '  - skipping %s.%s (category %s disabled)',
                    $f->table_name, $f->column_name, $f->category,
                ));
                continue;
            }

            if (!Schema::hasTable($f->table_name) || !Schema::hasColumn($f->table_name, $f->column_name)) {
                $this->warn(sprintf(
                    '  - %s.%s missing; skipping',
                    $f->table_name, $f->column_name,
                ));
                continue;
            }

            $idCol = $this->primaryKey($f->table_name);
            $rowCount = DB::table($f->table_name)
                ->whereNotNull($f->column_name)
                ->count();

            if ($this->option('dry-run')) {
                $this->info(sprintf('  - dry-run: would encrypt up to %d row(s) of %s.%s', $rowCount, $f->table_name, $f->column_name));
                continue;
            }

            $this->line(sprintf('  - %s.%s ... walking %d row(s)', $f->table_name, $f->column_name, $rowCount));

            DB::table($f->table_name)
                ->select($idCol, $f->column_name)
                ->whereNotNull($f->column_name)
                ->orderBy($idCol)
                ->chunk(500, function ($rows) use ($svc, $f, $idCol, &$totalEncrypted, &$totalSkipped, &$totalErrors) {
                    foreach ($rows as $row) {
                        $current = $row->{$f->column_name};
                        if ($svc->isCiphertext((string) $current)) {
                            $totalSkipped++;
                            continue;
                        }
                        try {
                            $cipher = $svc->encrypt(
                                $f->category,
                                (string) $current,
                                $f->table_name,
                                $f->column_name,
                                $row->{$idCol} ?? null,
                            );
                            DB::table($f->table_name)
                                ->where($idCol, $row->{$idCol})
                                ->update([$f->column_name => $cipher]);
                            $totalEncrypted++;
                        } catch (\Throwable $e) {
                            $this->error(sprintf(
                                '    encrypt %s.%s id=%s failed: %s',
                                $f->table_name, $f->column_name, $row->{$idCol} ?? '?', $e->getMessage(),
                            ));
                            $totalErrors++;
                        }
                    }
                });

            // Mark column as encrypted in the registry only if no errors and at
            // least one row encrypted (or zero rows existed, in which case the
            // column is trivially fully encrypted going forward).
            if ($totalErrors === 0) {
                $svc->markRegistryEncrypted($f->table_name, $f->column_name, true);
            }
        }

        $this->line('');
        $this->info(sprintf(
            'Done. encrypted=%d skipped=%d errors=%d',
            $totalEncrypted, $totalSkipped, $totalErrors,
        ));
        return $totalErrors === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Best-effort lookup of the primary-key column. Falls back to 'id' which
     * matches every Heratio table I've seen.
     */
    protected function primaryKey(string $table): string
    {
        $row = DB::selectOne(
            "SELECT k.column_name AS pk
             FROM information_schema.table_constraints t
             JOIN information_schema.key_column_usage k USING (constraint_name, table_schema, table_name)
             WHERE t.constraint_type = 'PRIMARY KEY'
               AND t.table_schema = DATABASE()
               AND t.table_name = ?
             LIMIT 1",
            [$table],
        );
        return $row->pk ?? 'id';
    }
}
