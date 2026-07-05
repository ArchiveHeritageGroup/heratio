<?php

/**
 * SecretCrypto - Service for Heratio
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

/**
 * #1395(D) — encryption-at-rest for integration secrets stored in the settings
 * tables (`setting`/`setting_i18n`, `ahg_settings`, `icip_config`). A DB dump of
 * those tables must not expose usable API keys / passwords.
 *
 * Uses Laravel's Crypt (AES-256-CBC keyed by APP_KEY, MAC-authenticated). The
 * two operations are deliberately backward-compatible so the roll-out never
 * breaks a live integration:
 *
 *  - reveal()  decrypt-or-passthrough. A value that isn't valid ciphertext
 *              (a legacy plaintext row, or one written by a path not yet
 *              concealing) is returned unchanged. Every consumer of a secret
 *              setting wraps its read in reveal(), so it works before AND after
 *              the backfill runs.
 *  - conceal() encrypt, idempotently. An already-encrypted value is returned
 *              unchanged (never double-encrypted); a blank value stays blank so
 *              write-only "leave blank to keep current" fields aren't disturbed.
 *
 * Because reveal() is a safe no-op on plaintext and conceal() is idempotent, the
 * pair is order-independent: encrypt-on-write and the one-off backfill can land
 * in any order, and reveal() covers both the pre- and post-migration state.
 */
class SecretCrypto
{
    /**
     * Return the plaintext for a stored secret value. If $stored is not valid
     * Laravel ciphertext (legacy plaintext, empty, or null) it is returned as-is.
     */
    public static function reveal(?string $stored): string
    {
        if ($stored === null || $stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException $e) {
            // Not ciphertext (legacy plaintext / not-yet-backfilled) — pass through.
            return $stored;
        }
    }

    /**
     * Return the at-rest (encrypted) form for a plaintext secret. Idempotent:
     * a value that is already ciphertext is returned unchanged, and a blank
     * value stays blank (so a skipped write-only field is never encrypted into
     * a non-empty blob).
     */
    public static function conceal(?string $plaintext): string
    {
        if ($plaintext === null || $plaintext === '') {
            return '';
        }

        if (self::isEncrypted($plaintext)) {
            return $plaintext;
        }

        return Crypt::encryptString($plaintext);
    }

    /**
     * True when $value is a decryptable Laravel ciphertext produced by conceal().
     */
    public static function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException $e) {
            return false;
        }
    }
}
