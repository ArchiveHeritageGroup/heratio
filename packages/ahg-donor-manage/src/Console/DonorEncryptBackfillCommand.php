<?php

/**
 * DonorEncryptBackfillCommand - encrypt existing donor contact PII rows.
 *
 * #1261 follow-on: the encrypt-on-write path only protects donor contacts
 * written after the feature shipped. This command walks the existing donor
 * contact rows (contact_information + contact_information_i18n, scoped to
 * QubitDonor actors) and encrypts the registered contact_details columns
 * that are still in plaintext.
 *
 * Idempotent: already-encrypted values carry the ENC2: sentinel and are
 * skipped, so running it twice is safe and the second run reports zero
 * encrypted.
 *
 * Columns + category (must match DonorService / ahg_encrypted_fields):
 *   contact_information       email  -> contact_details  (VARCHAR(255), fits)
 *   contact_information_i18n  city   -> contact_details  (VARCHAR(1024), fits)
 *
 * NOTE: donor contacts live in the AtoM base table contact_information, which
 * is shared with repository / actor entities. The base table is READ-ONLY
 * (no ALTER), so only columns already wide enough to hold ENC2: ciphertext
 * (~205-233 chars) are encrypted - email + city. Other contact PII columns
 * (telephone/fax/postal_code/country_code) are VARCHAR(255) but are NOT in
 * the encrypted-fields registry and are NOT encrypted by RepositoryService,
 * so they are intentionally left untouched here to mirror that proven set.
 * The narrow contact_type column (VARCHAR(50)) could not hold ciphertext
 * anyway, but it is not PII.
 *
 * This backfill is scoped to donor actors (object.class_name='QubitDonor')
 * so it does not touch repository contacts (those have their own write-path
 * encryption and can be backfilled separately).
 *
 * Self-gates on encryption_enabled (and the contact_details per-category flag
 * inside EncryptionService::encrypt) so it is a no-op when encryption is off.
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

namespace AhgDonorManage\Console;

use AhgCore\Services\EncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DonorEncryptBackfillCommand extends Command
{
    protected $signature = 'ahg:donor-encrypt-backfill
                            {--dry-run : Count rows that would be encrypted without writing}';

    protected $description = 'Encrypt existing donor contact PII (contact_information.email + contact_information_i18n.city) for QubitDonor actors. Idempotent.';

    public function handle(EncryptionService $enc): int
    {
        if (! $enc->isEnabled()) {
            $this->error('encryption_enabled is off; refusing to run (nothing would be encrypted).');

            return self::FAILURE;
        }

        $category = EncryptionService::CATEGORY_CONTACT_DETAILS;
        if (! $enc->shouldEncryptCategory($category)) {
            $this->error("encryption_field_{$category} is off; refusing to run (nothing would be encrypted).");

            return self::FAILURE;
        }

        if (! Schema::hasTable('contact_information')) {
            $this->warn('contact_information table missing; nothing to do.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $encrypted = 0;
        $skipped = 0;
        $errors = 0;

        // contact_information.email — donor-scoped.
        DB::table('contact_information')
            ->join('object', 'contact_information.actor_id', '=', 'object.id')
            ->where('object.class_name', 'QubitDonor')
            ->whereNotNull('contact_information.email')
            ->where('contact_information.email', '!=', '')
            ->orderBy('contact_information.id')
            ->select('contact_information.id', 'contact_information.email')
            ->chunkById(500, function ($rows) use ($enc, $category, $dry, &$encrypted, &$skipped, &$errors) {
                foreach ($rows as $row) {
                    $current = (string) $row->email;
                    if ($enc->isCiphertext($current)) {
                        $skipped++;

                        continue;
                    }
                    if ($dry) {
                        $encrypted++;

                        continue;
                    }
                    try {
                        $cipher = $enc->encrypt($category, $current, 'contact_information', 'email', $row->id);
                        if ($cipher !== $current) {
                            DB::table('contact_information')->where('id', $row->id)->update(['email' => $cipher]);
                            $encrypted++;
                        } else {
                            $skipped++;
                        }
                    } catch (\Throwable $e) {
                        $this->error("    contact_information.email id={$row->id} failed: ".$e->getMessage());
                        $errors++;
                    }
                }
            }, 'contact_information.id', 'id');

        // contact_information_i18n.city — donor-scoped via the parent row.
        if (Schema::hasTable('contact_information_i18n')) {
            DB::table('contact_information_i18n')
                ->join('contact_information', 'contact_information_i18n.id', '=', 'contact_information.id')
                ->join('object', 'contact_information.actor_id', '=', 'object.id')
                ->where('object.class_name', 'QubitDonor')
                ->whereNotNull('contact_information_i18n.city')
                ->where('contact_information_i18n.city', '!=', '')
                ->orderBy('contact_information_i18n.id')
                ->select('contact_information_i18n.id', 'contact_information_i18n.culture', 'contact_information_i18n.city')
                ->chunkById(500, function ($rows) use ($enc, $category, $dry, &$encrypted, &$skipped, &$errors) {
                    foreach ($rows as $row) {
                        $current = (string) $row->city;
                        if ($enc->isCiphertext($current)) {
                            $skipped++;

                            continue;
                        }
                        if ($dry) {
                            $encrypted++;

                            continue;
                        }
                        try {
                            $cipher = $enc->encrypt($category, $current, 'contact_information_i18n', 'city', $row->id);
                            if ($cipher !== $current) {
                                DB::table('contact_information_i18n')
                                    ->where('id', $row->id)
                                    ->where('culture', $row->culture)
                                    ->update(['city' => $cipher]);
                                $encrypted++;
                            } else {
                                $skipped++;
                            }
                        } catch (\Throwable $e) {
                            $this->error("    contact_information_i18n.city id={$row->id} failed: ".$e->getMessage());
                            $errors++;
                        }
                    }
                }, 'contact_information_i18n.id', 'id');
        }

        $this->line('');
        $verb = $dry ? 'would encrypt' : 'encrypted';
        $this->info(sprintf('Done. %s=%d skipped(already-ciphertext)=%d errors=%d', $verb, $encrypted, $skipped, $errors));

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
