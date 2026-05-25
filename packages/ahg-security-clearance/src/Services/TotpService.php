<?php

/**
 * TotpService — TOTP MFA backend for Heratio (issue #690).
 *
 * Wraps pragmarx/google2fa (RFC 6238) + bacon/bacon-qr-code (SVG QR generation)
 * with the Heratio user_totp_secret + user_mfa_recovery_code tables.
 *
 * Scope: TOTP + single-use recovery codes. WebAuthn / SMS / per-tenant policy
 * are tracked as separate follow-ups.
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

namespace AhgSecurityClearance\Services;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TotpService
{
    /** Window of ±N 30-second TOTP slots accepted (RFC 6238 recommends 1). */
    private const TOTP_WINDOW = 1;

    /** Number of single-use recovery codes minted per enrolment. */
    private const RECOVERY_CODE_COUNT = 10;

    /** Length of each recovery code (alphanumeric). */
    private const RECOVERY_CODE_LENGTH = 10;

    private Google2FA $google2fa;

    public function __construct(?Google2FA $google2fa = null)
    {
        $this->google2fa = $google2fa ?? new Google2FA();
    }

    /**
     * Begin enrolment: generate (and persist as pending) a fresh TOTP secret
     * for the user, return the otpauth:// URI and a base64-encoded SVG QR.
     *
     * The row is created with enabled_at NULL — the user must POST a valid
     * code via confirmEnrolment() before the secret becomes active.
     *
     * @return array{secret: string, otpauth_uri: string, qr_svg_data_uri: string}
     */
    public function beginEnrolment(int $userId, string $userIdentifier, string $issuer): array
    {
        $secret = $this->google2fa->generateSecretKey(32);

        DB::table('user_totp_secret')->updateOrInsert(
            ['user_id' => $userId],
            [
                'secret' => $secret,
                'enabled_at' => null,
                'last_used_at' => null,
                'recovery_codes_generated_at' => null,
                'updated_at' => now(),
            ]
        );

        $otpauth = $this->google2fa->getQRCodeUrl($issuer, $userIdentifier, $secret);

        return [
            'secret' => $secret,
            'otpauth_uri' => $otpauth,
            'qr_svg_data_uri' => $this->renderQrSvgDataUri($otpauth),
        ];
    }

    /**
     * Confirm enrolment by checking the user-supplied TOTP code against the
     * pending secret. On success: mark enabled_at, mint recovery codes, and
     * return the plaintext codes (caller MUST display these once and never
     * again — they're only stored as bcrypt hashes server-side).
     *
     * @return array{ok: bool, recovery_codes: array<int, string>}
     */
    public function confirmEnrolment(int $userId, string $code): array
    {
        $row = DB::table('user_totp_secret')->where('user_id', $userId)->first();
        if (! $row || ! empty($row->enabled_at)) {
            return ['ok' => false, 'recovery_codes' => []];
        }

        $valid = $this->google2fa->verifyKey($row->secret, $code, self::TOTP_WINDOW);
        if (! $valid) {
            return ['ok' => false, 'recovery_codes' => []];
        }

        $now = now();
        DB::table('user_totp_secret')->where('user_id', $userId)->update([
            'enabled_at' => $now,
            'last_used_at' => $now,
            'recovery_codes_generated_at' => $now,
            'updated_at' => $now,
        ]);

        $codes = $this->mintRecoveryCodes($userId);

        return ['ok' => true, 'recovery_codes' => $codes];
    }

    /**
     * Verify a code submitted during the post-login MFA gate. Accepts either
     * a 6-digit TOTP code or a recovery code (auto-detected by length/format).
     * Returns whether the code was valid; on recovery-code success, the code
     * is marked used and cannot be re-submitted.
     */
    public function verifyCode(int $userId, string $code): bool
    {
        $code = trim($code);

        $row = DB::table('user_totp_secret')->where('user_id', $userId)->first();
        if (! $row || empty($row->enabled_at)) {
            return false;
        }

        if (preg_match('/^\d{6}$/', $code)) {
            $valid = $this->google2fa->verifyKey($row->secret, $code, self::TOTP_WINDOW);
            if ($valid) {
                DB::table('user_totp_secret')
                    ->where('user_id', $userId)
                    ->update(['last_used_at' => now()]);
                return true;
            }
            return false;
        }

        return $this->redeemRecoveryCode($userId, $code);
    }

    /** True if the user has a confirmed (enabled_at IS NOT NULL) TOTP secret. */
    public function userHasMfa(int $userId): bool
    {
        return DB::table('user_totp_secret')
            ->where('user_id', $userId)
            ->whereNotNull('enabled_at')
            ->exists();
    }

    /**
     * Fully unenroll a user: drop the secret + every recovery code. Called by
     * the user's own opt-out path or by an admin via removeTwoFactor().
     */
    public function disable(int $userId): void
    {
        DB::table('user_mfa_recovery_code')->where('user_id', $userId)->delete();
        DB::table('user_totp_secret')->where('user_id', $userId)->delete();
    }

    /**
     * Regenerate recovery codes (e.g. user lost their printed sheet). Wipes
     * the existing batch and mints a new set. Returns plaintext (display once).
     *
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(int $userId): array
    {
        if (! $this->userHasMfa($userId)) {
            return [];
        }

        DB::table('user_mfa_recovery_code')->where('user_id', $userId)->delete();
        DB::table('user_totp_secret')
            ->where('user_id', $userId)
            ->update(['recovery_codes_generated_at' => now()]);

        return $this->mintRecoveryCodes($userId);
    }

    /** Count of unused recovery codes for a user (UI surfaces low-count warnings). */
    public function unusedRecoveryCodeCount(int $userId): int
    {
        return DB::table('user_mfa_recovery_code')
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->count();
    }

    // ─── internals ────────────────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    private function mintRecoveryCodes(int $userId): array
    {
        $plaintexts = [];
        $rows = [];
        $now = now();

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = $this->generateRecoveryCode();
            $plaintexts[] = $code;
            $rows[] = [
                'user_id' => $userId,
                'code_hash' => Hash::make($code),
                'used_at' => null,
                'created_at' => $now,
            ];
        }

        DB::table('user_mfa_recovery_code')->insert($rows);

        return $plaintexts;
    }

    private function generateRecoveryCode(): string
    {
        $raw = strtolower(Str::random(self::RECOVERY_CODE_LENGTH));
        return substr($raw, 0, 5).'-'.substr($raw, 5);
    }

    private function redeemRecoveryCode(int $userId, string $submitted): bool
    {
        $candidates = DB::table('user_mfa_recovery_code')
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->get();

        foreach ($candidates as $row) {
            if (Hash::check($submitted, $row->code_hash)) {
                DB::table('user_mfa_recovery_code')
                    ->where('id', $row->id)
                    ->update(['used_at' => now()]);
                DB::table('user_totp_secret')
                    ->where('user_id', $userId)
                    ->update(['last_used_at' => now()]);
                return true;
            }
        }

        return false;
    }

    private function renderQrSvgDataUri(string $otpauthUri): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd()
        );
        $svg = (new Writer($renderer))->writeString($otpauthUri);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
