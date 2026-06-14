<?php

/**
 * VendorEncryptBackfillCommand - encrypt existing vendor + contact PII rows.
 *
 * #1264 follow-on: the encrypt-on-write path only protects rows written
 * after the feature shipped. This command walks every existing row of the
 * registered PII columns and encrypts the ones still in plaintext. It is
 * idempotent - already-encrypted values carry the ENC2: sentinel and are
 * skipped, so running it twice is safe and the second run reports zero
 * encrypted.
 *
 * Columns + categories (must match VendorService):
 *   ahg_vendors          email/phone/phone_alt/fax  -> contact_details
 *   ahg_vendors          bank_*                     -> financial_data
 *   ahg_vendor_contacts  phone/mobile/email         -> contact_details
 *
 * Self-gates on encryption_enabled (and each per-category flag inside
 * EncryptionService::encrypt) so it is a no-op when encryption is off.
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

namespace AhgVendor\Console;

use AhgCore\Services\EncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorEncryptBackfillCommand extends Command
{
    protected $signature = 'ahg:vendor-encrypt-backfill
                            {--dry-run : Count rows that would be encrypted without writing}';

    protected $description = 'Encrypt existing vendor + vendor-contact PII rows (contact_details + financial_data). Idempotent.';

    /**
     * table => [category => [columns...]]
     */
    private const TARGETS = [
        'ahg_vendors' => [
            EncryptionService::CATEGORY_CONTACT_DETAILS => ['email', 'phone', 'phone_alt', 'fax'],
            EncryptionService::CATEGORY_FINANCIAL_DATA => [
                'bank_name', 'bank_branch', 'bank_account_number', 'bank_branch_code', 'bank_account_type',
            ],
        ],
        'ahg_vendor_contacts' => [
            EncryptionService::CATEGORY_CONTACT_DETAILS => ['phone', 'mobile', 'email'],
        ],
    ];

    public function handle(EncryptionService $enc): int
    {
        if (! $enc->isEnabled()) {
            $this->error('encryption_enabled is off; refusing to run (nothing would be encrypted).');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $encrypted = 0;
        $skipped = 0;
        $errors = 0;

        foreach (self::TARGETS as $table => $byCategory) {
            if (! Schema::hasTable($table)) {
                $this->warn("  - {$table} missing; skipping");

                continue;
            }

            foreach ($byCategory as $category => $columns) {
                if (! $enc->shouldEncryptCategory($category)) {
                    $this->line("  - skipping {$table} ({$category} disabled)");

                    continue;
                }

                foreach ($columns as $col) {
                    if (! Schema::hasColumn($table, $col)) {
                        continue;
                    }

                    DB::table($table)
                        ->select('id', $col)
                        ->whereNotNull($col)
                        ->where($col, '!=', '')
                        ->orderBy('id')
                        ->chunkById(500, function ($rows) use ($enc, $table, $col, $category, $dry, &$encrypted, &$skipped, &$errors) {
                            foreach ($rows as $row) {
                                $current = (string) $row->{$col};
                                if ($enc->isCiphertext($current)) {
                                    $skipped++;

                                    continue;
                                }
                                if ($dry) {
                                    $encrypted++; // count of would-encrypt

                                    continue;
                                }
                                try {
                                    $cipher = $enc->encrypt($category, $current, $table, $col, $row->id);
                                    if ($cipher !== $current) {
                                        DB::table($table)->where('id', $row->id)->update([$col => $cipher]);
                                        $encrypted++;
                                    } else {
                                        // category gate flipped off mid-run, or no-op
                                        $skipped++;
                                    }
                                } catch (\Throwable $e) {
                                    $this->error("    {$table}.{$col} id={$row->id} failed: ".$e->getMessage());
                                    $errors++;
                                }
                            }
                        });
                }
            }
        }

        $this->line('');
        $verb = $dry ? 'would encrypt' : 'encrypted';
        $this->info(sprintf('Done. %s=%d skipped(already-ciphertext)=%d errors=%d', $verb, $encrypted, $skipped, $errors));

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
