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

    // =========================================================================
    //  #125 derivative-file encryption
    // =========================================================================
    //
    // Same SENTINEL+Crypt pattern as the column path, applied to whole
    // files so digital_object derivatives (thumbnails / reference /
    // preservation copies / IIIF tiles / PDFs / transcripts etc.) can sit
    // encrypted at rest. Read path is symmetric: streamFileDecrypted writes
    // plaintext to a tmp file (or returns the bytes) for the streaming
    // controller to push through to the browser.
    //
    // Format: [16-byte FILE_SENTINEL] [Laravel::encrypt() body, base64'd].
    // The sentinel lets isFileEncrypted detect already-encrypted files
    // without trying to decrypt + catching - useful for the bulk-apply
    // sweep (re-running it is a no-op).
    //
    // Cantaloupe constraint: the IIIF tile server reads files directly
    // from disk via Java code that has no Heratio-aware decryption hook.
    // When encryption_encrypt_derivatives is on, operators must either
    // (a) point Cantaloupe at the PHP-side decrypted-stream endpoint
    // instead of the raw uploads_path, or (b) accept that IIIF tile
    // serving stays plaintext. Filed in #125's discussion thread.

    public const FILE_SENTINEL = "AHG_ENC_DERIV_v1\n";

    /**
     * Encrypt a file in-place. Idempotent (already-encrypted files are
     * left alone). Returns true on success, false when the category is
     * off or the file is missing.
     */
    public function encryptFile(string $path): bool
    {
        if (!$this->shouldEncryptDerivatives()) return false;
        if (!is_file($path) || !is_readable($path)) return false;
        if ($this->isFileEncrypted($path)) return true; // Already done

        try {
            $plain = @file_get_contents($path);
            if ($plain === false) return false;

            // Crypt::encryptString returns base64; the FILE_SENTINEL gives us
            // a cheap is-this-already-encrypted check without decrypting.
            $encrypted = self::FILE_SENTINEL . Crypt::encryptString($plain);

            // Atomic write via tmp + rename so a crash mid-write doesn't leave
            // a half-encrypted file on disk.
            $tmp = $path . '.enc.tmp';
            if (@file_put_contents($tmp, $encrypted) === false) return false;
            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                return false;
            }

            $this->writeAudit('encrypt', 'derivatives', null, null, $path, 'success');
            return true;
        } catch (\Throwable $e) {
            $this->writeAudit('encrypt', 'derivatives', null, null, $path, 'error', $e->getMessage());
            \Illuminate\Support\Facades\Log::warning('[encryption] file encrypt failed', [
                'path' => $path, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Decrypt a file in-place. Used by the bulk-revert command. For the
     * streaming hot path use streamFileDecrypted instead - it returns the
     * plaintext bytes without touching the on-disk file.
     */
    public function decryptFile(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) return false;
        if (!$this->isFileEncrypted($path)) return true; // Already plain

        try {
            $body = @file_get_contents($path);
            if ($body === false) return false;
            $cipher = substr($body, strlen(self::FILE_SENTINEL));
            $plain = Crypt::decryptString($cipher);

            $tmp = $path . '.dec.tmp';
            if (@file_put_contents($tmp, $plain) === false) return false;
            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                return false;
            }

            $this->writeAudit('decrypt', 'derivatives', null, null, $path, 'success');
            return true;
        } catch (\Throwable $e) {
            $this->writeAudit('decrypt', 'derivatives', null, null, $path, 'error', $e->getMessage());
            \Illuminate\Support\Facades\Log::warning('[encryption] file decrypt failed', [
                'path' => $path, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Cheap check via the FILE_SENTINEL prefix. Reads only the first
     * sentinel-length bytes so it's safe to call on multi-GB derivative
     * files (master TIFFs, IIIF tile cache, etc.).
     */
    public function isFileEncrypted(string $path): bool
    {
        $len = strlen(self::FILE_SENTINEL);
        $fp = @fopen($path, 'rb');
        if (!$fp) return false;
        $head = fread($fp, $len);
        fclose($fp);
        return $head === self::FILE_SENTINEL;
    }

    /**
     * Decrypt-on-stream: returns the plaintext bytes for an encrypted file.
     * Used by the streaming controller to serve the file without persisting
     * the decrypted form to disk. Returns null when the file isn't
     * encrypted (caller should serve the file directly via readfile or
     * X-Accel-Redirect).
     *
     * For very large files this loads everything into memory - acceptable
     * for thumbnails/reference (typically < 50MB) but not for full
     * preservation masters. Operator using encryption with multi-GB
     * masters should pair with sufficient PHP memory_limit / streaming
     * decrypt (the latter is a future enhancement).
     */
    public function streamFileDecrypted(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) return null;
        if (!$this->isFileEncrypted($path)) return null;

        try {
            $body = @file_get_contents($path);
            if ($body === false) return null;
            $cipher = substr($body, strlen(self::FILE_SENTINEL));
            return Crypt::decryptString($cipher);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[encryption] streamFileDecrypted failed', [
                'path' => $path, 'error' => $e->getMessage(),
            ]);
            return null;
        }
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
