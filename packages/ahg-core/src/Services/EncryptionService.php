<?php

/**
 * EncryptionService - field-level encryption gated by encryption_* settings
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

namespace AhgCore\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptionService
{
    /**
     * Magic prefix on encrypted strings so reads can tell encrypted from plain.
     * Lets the decrypt path no-op on values that were never encrypted (e.g.
     * legacy rows from before the operator turned encryption on for a given
     * category). The prefix is short to keep the column-overflow risk small.
     */
    public const SENTINEL = 'ENC2:';

    public const CATEGORY_ACCESS_RESTRICTIONS = 'access_restrictions';
    public const CATEGORY_CONTACT_DETAILS = 'contact_details';
    public const CATEGORY_DONOR_INFORMATION = 'donor_information';
    public const CATEGORY_FINANCIAL_DATA = 'financial_data';
    public const CATEGORY_PERSONAL_NOTES = 'personal_notes';

    /**
     * Master gate. When off, no encryption happens regardless of per-category
     * flags - encrypt() and decrypt() pass values through untouched.
     */
    public function isEnabled(): bool
    {
        return AhgSettingsService::getBool('encryption_enabled', false);
    }

    /**
     * Per-category flag. The settings page exposes
     * encryption_field_<category> for the 5 categories defined as constants
     * on this class.
     */
    public function shouldEncryptCategory(string $category): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        return AhgSettingsService::getBool('encryption_field_' . $category, false);
    }

    /**
     * Whether digital-object derivatives (thumbnails / reference / preservation
     * copies) should be written encrypted. Honoured by PhotoProcessor and any
     * other derivative-writer that reads this gate.
     */
    public function shouldEncryptDerivatives(): bool
    {
        return $this->isEnabled()
            && AhgSettingsService::getBool('encryption_encrypt_derivatives', false);
    }

    /**
     * Encrypt a value for a category. Returns the ciphertext (with sentinel
     * prefix) on success; the original value when the category is off; or
     * null when the input was null. Idempotent: calling encrypt() again on
     * an already-encrypted value is a no-op.
     *
     * Audit log: writes an `action='encrypt'` row to ahg_encryption_audit per
     * call, capturing target_table / target_column / target_id / user_id /
     * status. Failures are logged with status='error' + the exception
     * message.
     */
    public function encrypt(
        string $category,
        ?string $plain,
        ?string $targetTable = null,
        ?string $targetColumn = null,
        $targetId = null,
    ): ?string {
        if ($plain === null || $plain === '') {
            return $plain;
        }
        if (!$this->shouldEncryptCategory($category)) {
            return $plain;
        }
        if ($this->isCiphertext($plain)) {
            return $plain;
        }

        try {
            $cipher = self::SENTINEL . Crypt::encryptString($plain);
            $this->writeAudit('encrypt', $category, $targetTable, $targetColumn, $targetId, 'success');
            return $cipher;
        } catch (\Throwable $e) {
            $this->writeAudit('encrypt', $category, $targetTable, $targetColumn, $targetId, 'error', $e->getMessage());
            // Surface the failure rather than silently writing plaintext.
            throw $e;
        }
    }

    /**
     * Decrypt a stored value. Pass-through for plaintext (no sentinel) so
     * legacy rows continue to work after the operator enables encryption.
     * Returns null for null input.
     *
     * On Crypt::DecryptException (key mismatch, tampered ciphertext, etc.)
     * an audit-log row with status='error' is written and the exception is
     * re-thrown so callers can decide how to surface a corrupted-row error
     * to the operator.
     */
    public function decrypt(
        string $category,
        ?string $stored,
        ?string $targetTable = null,
        ?string $targetColumn = null,
        $targetId = null,
    ): ?string {
        if ($stored === null || $stored === '') {
            return $stored;
        }
        if (!$this->isCiphertext($stored)) {
            return $stored;
        }

        try {
            $plain = Crypt::decryptString(substr($stored, strlen(self::SENTINEL)));
            $this->writeAudit('decrypt', $category, $targetTable, $targetColumn, $targetId, 'success');
            return $plain;
        } catch (DecryptException $e) {
            $this->writeAudit('decrypt', $category, $targetTable, $targetColumn, $targetId, 'error', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Whether a value carries the encryption sentinel prefix.
     */
    public function isCiphertext(string $value): bool
    {
        return strncmp($value, self::SENTINEL, strlen(self::SENTINEL)) === 0;
    }

    /**
     * Look up the registered category for a (table, column) pair. Returns
     * null when nothing matches - callers should treat that as "never
     * encrypt this column" rather than fall through to a guess.
     */
    public function categoryForColumn(string $table, string $column): ?string
    {
        $row = DB::table('ahg_encrypted_fields')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->first();

        return $row ? $row->category : null;
    }

    /**
     * All registered fields, optionally narrowed to one category. Used by
     * the bulk-apply / bulk-revert commands to walk the registry.
     */
    public function listRegisteredFields(?string $category = null): array
    {
        $q = DB::table('ahg_encrypted_fields');
        if ($category !== null) {
            $q->where('category', $category);
        }
        return $q->orderBy('table_name')->orderBy('column_name')->get()->all();
    }

    /**
     * Mark a registered (table, column) tuple as encrypted at the registry
     * level. Called by the bulk-apply command after every row in the column
     * has been encrypted, so the operator can see which columns are live.
     */
    public function markRegistryEncrypted(string $table, string $column, bool $encrypted): void
    {
        DB::table('ahg_encrypted_fields')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->update([
                'is_encrypted' => $encrypted ? 1 : 0,
                'encrypted_at' => $encrypted ? now() : null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Write a single ahg_encryption_audit row. Swallows DB errors (the audit
     * write must not break the enclosing encrypt/decrypt) but logs them.
     */
    protected function writeAudit(
        string $action,
        string $category,
        ?string $targetTable,
        ?string $targetColumn,
        $targetId,
        string $status,
        ?string $errorMessage = null,
    ): void {
        try {
            DB::table('ahg_encryption_audit')->insert([
                'action' => $action,
                'target_type' => 'field',
                'target_id' => $targetId !== null ? (string) $targetId : null,
                'target_table' => $targetTable,
                'target_column' => $targetColumn,
                'user_id' => function_exists('auth') ? (auth()->id() ?? null) : null,
                'status' => $status,
                'error_message' => $errorMessage,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[encryption] audit-log write failed', [
                'action' => $action,
                'category' => $category,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
