<?php

/**
 * EncryptionBulkRevertCommand - decrypt every registered field row
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

class EncryptionBulkRevertCommand extends Command
{
    protected $signature = 'ahg:encryption-bulk-revert
                            {--category= : Limit to one category}
                            {--dry-run : Count rows without writing}
                            {--force : Required to actually revert (writes plaintext to disk)}';

    protected $description = 'Decrypt every registered field row back to plaintext. Use only when retiring encryption for a category.';

    public function handle(EncryptionService $svc): int
    {
        if (!$this->option('dry-run') && !$this->option('force')) {
            $this->error('Reverting writes plaintext back to columns. Pass --force to confirm, or --dry-run to preview.');
            return self::FAILURE;
        }

        $fields = $svc->listRegisteredFields($this->option('category'));
        if (empty($fields)) {
            $this->warn('No registered fields match.');
            return self::SUCCESS;
        }

        $totalDecrypted = 0;
        $totalErrors = 0;

        foreach ($fields as $f) {
            $idCol = 'id';
            $count = DB::table($f->table_name)
                ->where($f->column_name, 'LIKE', EncryptionService::SENTINEL . '%')
                ->count();

            if ($this->option('dry-run')) {
                $this->info(sprintf('  - dry-run: would decrypt %d row(s) of %s.%s', $count, $f->table_name, $f->column_name));
                continue;
            }

            $this->line(sprintf('  - %s.%s ... walking %d row(s)', $f->table_name, $f->column_name, $count));

            DB::table($f->table_name)
                ->select($idCol, $f->column_name)
                ->where($f->column_name, 'LIKE', EncryptionService::SENTINEL . '%')
                ->orderBy($idCol)
                ->chunk(500, function ($rows) use ($svc, $f, $idCol, &$totalDecrypted, &$totalErrors) {
                    foreach ($rows as $row) {
                        try {
                            $plain = $svc->decrypt(
                                $f->category,
                                (string) $row->{$f->column_name},
                                $f->table_name,
                                $f->column_name,
                                $row->{$idCol} ?? null,
                            );
                            DB::table($f->table_name)
                                ->where($idCol, $row->{$idCol})
                                ->update([$f->column_name => $plain]);
                            $totalDecrypted++;
                        } catch (\Throwable $e) {
                            $this->error(sprintf(
                                '    decrypt %s.%s id=%s failed: %s',
                                $f->table_name, $f->column_name, $row->{$idCol} ?? '?', $e->getMessage(),
                            ));
                            $totalErrors++;
                        }
                    }
                });

            if ($totalErrors === 0) {
                $svc->markRegistryEncrypted($f->table_name, $f->column_name, false);
            }
        }

        $this->line('');
        $this->info(sprintf('Done. decrypted=%d errors=%d', $totalDecrypted, $totalErrors));
        return $totalErrors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
