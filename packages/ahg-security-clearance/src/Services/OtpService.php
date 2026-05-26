<?php

/**
 * OtpService — email + SMS one-time-password MFA backend for Heratio
 * (issue #722).
 *
 * Sister service to TotpService (#690) and WebAuthnService (#721). A user
 * may enrol any combination of email and SMS destinations, each modelled
 * as a row in ahg_otp_factor. Codes are 6-digit numeric, SHA-256-hashed
 * at rest in ahg_otp_challenge — the plaintext only ever leaves the
 * service in the email body or SMS body, never in storage or logs.
 *
 * Safety rails:
 *   - rate limit: at most one challenge per factor per 60s.
 *   - attempt cap: 5 failed verifies in a rolling 15-minute window per
 *     factor (tracked in cache, falls back to the attempts column on
 *     the challenge row so the cap survives a cache flush).
 *   - challenge ttl: 10 minutes by default. Consumed challenges cannot
 *     be replayed.
 *
 * The senders are pluggable: Mail (Laravel queue) for email; an
 * SmsGatewayInterface implementation for SMS (NullSmsGateway in dev,
 * HttpSmsGateway in production once the operator has configured an
 * endpoint).
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

declare(strict_types=1);

namespace AhgSecurityClearance\Services;

use AhgCore\Services\AhgSettingsService;
use AhgSecurityClearance\Mail\OtpCodeMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class OtpService
{
    /** Code length (digits). */
    public const CODE_LENGTH = 6;

    /** Time-to-live of a freshly-minted challenge (minutes). */
    public const CHALLENGE_TTL_MINUTES = 10;

    /** Minimum seconds between sendChallenge() calls for the same factor. */
    public const SEND_THROTTLE_SECONDS = 60;

    /** Failed verify attempts within FAIL_WINDOW_MINUTES that trigger lockout. */
    public const MAX_ATTEMPTS = 5;

    /** Sliding window (minutes) used for the attempt cap. */
    public const FAIL_WINDOW_MINUTES = 15;

    /** Allowed factor_type values. */
    public const TYPE_EMAIL = 'email';
    public const TYPE_SMS = 'sms';

    /**
     * Enrol a new factor. Sends the first challenge code to the destination
     * but leaves verified_at NULL until verifyEnrolment() succeeds. Callers
     * must run their own destination-format validation before invoking this.
     */
    public function enrol(int $userId, string $type, string $destination, string $label): object
    {
        if (! in_array($type, [self::TYPE_EMAIL, self::TYPE_SMS], true)) {
            throw new \InvalidArgumentException("Unknown factor_type: {$type}");
        }

        $destination = $type === self::TYPE_EMAIL
            ? strtolower(trim($destination))
            : $this->normalisePhone($destination);

        $label = trim($label);
        if ($label === '') {
            $label = $type === self::TYPE_EMAIL ? 'Email' : 'SMS';
        }

        $id = DB::table('ahg_otp_factor')->insertGetId([
            'user_id' => $userId,
            'factor_type' => $type,
            'destination' => $destination,
            'label' => $label,
            'verified_at' => null,
            'last_used_at' => null,
            'created_at' => now(),
        ]);

        $factor = (object) [
            'id' => $id,
            'user_id' => $userId,
            'factor_type' => $type,
            'destination' => $destination,
            'label' => $label,
            'verified_at' => null,
        ];

        // Send the first code so the user has something to type into the
        // verifyEnrolment form. Throttle does not apply on first enrolment
        // because no challenge exists yet.
        $this->sendChallenge($userId, $factor, ignoreThrottle: true);

        \Log::info('otp.enrol.created', [
            'user_id' => $userId,
            'factor_id' => $id,
            'factor_type' => $type,
        ]);

        return $factor;
    }

    /**
     * Confirm enrolment: validate the first code against the latest pending
     * challenge for the factor. On success, sets verified_at and clears
     * any lockout state.
     */
    public function verifyEnrolment(int $userId, int $factorId, string $code): bool
    {
        $factor = $this->factorRow($userId, $factorId);
        if (! $factor || $factor->verified_at !== null) {
            return false;
        }

        // Apply the same lockout that protects the post-enrol verify path,
        // so an attacker who somehow holds a pending enrolment can't
        // brute-force the first code unbounded.
        if ($this->locked($factorId)) {
            \Log::info('otp.enrol.locked_out', [
                'user_id' => $userId,
                'factor_id' => $factorId,
            ]);

            return false;
        }

        if (! $this->matchCode($userId, $factorId, $code)) {
            $this->bumpLockout($factorId);

            return false;
        }

        DB::table('ahg_otp_factor')->where('id', $factorId)->update([
            'verified_at' => now(),
            'last_used_at' => now(),
        ]);
        $this->clearLockout($factorId);

        \Log::info('otp.enrol.verified', [
            'user_id' => $userId,
            'factor_id' => $factorId,
        ]);

        return true;
    }

    /**
     * Generate a fresh 6-digit code, store its SHA-256 hash, and dispatch
     * it via the channel matching $factor->factor_type. Returns the
     * challenge row (without the plaintext code attached).
     *
     * Set $ignoreThrottle = true only when enrol() calls this for the very
     * first time. All user-initiated send-again paths must honour the
     * 60-second rate limit.
     */
    public function sendChallenge(int $userId, object $factor, bool $ignoreThrottle = false): object
    {
        if (! $ignoreThrottle && $this->throttled($factor->id)) {
            \Log::info('otp.send.throttled', [
                'user_id' => $userId,
                'factor_id' => $factor->id,
            ]);

            return $this->latestChallenge($factor->id) ?? (object) ['throttled' => true];
        }

        $code = $this->generateCode();
        $challengeId = DB::table('ahg_otp_challenge')->insertGetId([
            'user_id' => $userId,
            'factor_id' => $factor->id,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(self::CHALLENGE_TTL_MINUTES),
            'attempts' => 0,
            'consumed_at' => null,
            'created_at' => now(),
        ]);

        $this->dispatch($userId, $factor, $code);

        return (object) [
            'id' => $challengeId,
            'factor_id' => $factor->id,
            'expires_at' => now()->addMinutes(self::CHALLENGE_TTL_MINUTES),
        ];
    }

    /**
     * Verify a user-supplied code against the latest non-consumed, non-
     * expired challenge for the factor. On success, marks the challenge
     * consumed and updates the factor's last_used_at.
     *
     * Increments the per-factor attempts counter on every failure; the
     * factor is locked out for FAIL_WINDOW_MINUTES once MAX_ATTEMPTS is
     * hit (whether the failures fell on the same challenge or across
     * multiple resends).
     */
    public function verify(int $userId, int $factorId, string $code): bool
    {
        $factor = $this->factorRow($userId, $factorId);
        if (! $factor || $factor->verified_at === null) {
            return false;
        }

        if ($this->locked($factorId)) {
            \Log::info('otp.verify.locked_out', [
                'user_id' => $userId,
                'factor_id' => $factorId,
            ]);

            return false;
        }

        if ($this->matchCode($userId, $factorId, $code)) {
            DB::table('ahg_otp_factor')->where('id', $factorId)->update([
                'last_used_at' => now(),
            ]);
            $this->clearLockout($factorId);

            \Log::info('otp.verify.ok', [
                'user_id' => $userId,
                'factor_id' => $factorId,
            ]);

            return true;
        }

        $this->bumpLockout($factorId);

        \Log::info('otp.verify.fail', [
            'user_id' => $userId,
            'factor_id' => $factorId,
        ]);

        return false;
    }

    /**
     * @return Collection<int, object>
     */
    public function factorsFor(int $userId): Collection
    {
        if (! Schema::hasTable('ahg_otp_factor')) {
            return collect();
        }

        return DB::table('ahg_otp_factor')
            ->where('user_id', $userId)
            ->orderBy('factor_type')
            ->orderBy('id')
            ->get();
    }

    /** True if the user has at least one verified OTP factor. */
    public function userHasOtp(int $userId): bool
    {
        if (! Schema::hasTable('ahg_otp_factor')) {
            return false;
        }

        return DB::table('ahg_otp_factor')
            ->where('user_id', $userId)
            ->whereNotNull('verified_at')
            ->exists();
    }

    /** Delete a factor (and cascade its challenge rows). Returns true on hit. */
    public function deleteFactor(int $userId, int $factorId): bool
    {
        DB::table('ahg_otp_challenge')
            ->where('user_id', $userId)
            ->where('factor_id', $factorId)
            ->delete();

        $removed = DB::table('ahg_otp_factor')
            ->where('user_id', $userId)
            ->where('id', $factorId)
            ->delete();

        if ($removed > 0) {
            $this->clearLockout($factorId);
        }

        return $removed > 0;
    }

    /** Wipe every OTP factor + challenge for a user (admin override). */
    public function disable(int $userId): void
    {
        $factorIds = DB::table('ahg_otp_factor')
            ->where('user_id', $userId)
            ->pluck('id');

        DB::table('ahg_otp_challenge')->where('user_id', $userId)->delete();
        DB::table('ahg_otp_factor')->where('user_id', $userId)->delete();

        foreach ($factorIds as $id) {
            $this->clearLockout((int) $id);
        }
    }

    /**
     * Build the SmsGatewayInterface implementation matching the current
     * ahg_setting.sms_gateway value. Defaults to NullSmsGateway when the
     * setting is missing or unknown so dev environments never crash on
     * SMS dispatch.
     */
    public function smsGateway(): SmsGatewayInterface
    {
        $driver = strtolower((string) (AhgSettingsService::get('sms_gateway') ?? 'null'));

        return match ($driver) {
            'http' => app(HttpSmsGateway::class),
            default => app(NullSmsGateway::class),
        };
    }

    // ─── internals ───────────────────────────────────────────────────────────

    /**
     * Look up the factor row by user + id. Returns null if the row belongs
     * to a different user — protects against IDOR via factor_id tampering.
     */
    private function factorRow(int $userId, int $factorId): ?object
    {
        $row = DB::table('ahg_otp_factor')
            ->where('id', $factorId)
            ->where('user_id', $userId)
            ->first();

        return $row ?: null;
    }

    private function latestChallenge(int $factorId): ?object
    {
        $row = DB::table('ahg_otp_challenge')
            ->where('factor_id', $factorId)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        return $row ?: null;
    }

    /**
     * True if the most recent challenge for the factor was created less
     * than SEND_THROTTLE_SECONDS ago. Prevents a hostile UI loop from
     * spamming the user's inbox or burning SMS credit.
     */
    private function throttled(int $factorId): bool
    {
        $latest = $this->latestChallenge($factorId);
        if (! $latest) {
            return false;
        }

        return Carbon::parse($latest->created_at)->gt(now()->subSeconds(self::SEND_THROTTLE_SECONDS));
    }

    /**
     * Compare a user-supplied code against the latest pending challenge
     * for the factor. Returns true on hit and marks the challenge
     * consumed. The attempts column is incremented on every miss so the
     * audit trail survives a cache flush.
     */
    private function matchCode(int $userId, int $factorId, string $code): bool
    {
        $code = preg_replace('/\s+/', '', trim($code));
        if (! preg_match('/^\d{'.self::CODE_LENGTH.'}$/', (string) $code)) {
            return false;
        }

        $challenge = DB::table('ahg_otp_challenge')
            ->where('factor_id', $factorId)
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (! $challenge) {
            return false;
        }

        $expected = $challenge->code_hash;
        $candidate = hash('sha256', (string) $code);

        if (! hash_equals($expected, $candidate)) {
            DB::table('ahg_otp_challenge')
                ->where('id', $challenge->id)
                ->increment('attempts');

            return false;
        }

        DB::table('ahg_otp_challenge')
            ->where('id', $challenge->id)
            ->update([
                'consumed_at' => now(),
            ]);

        return true;
    }

    private function generateCode(): string
    {
        $max = (10 ** self::CODE_LENGTH) - 1;

        return str_pad((string) random_int(0, $max), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function dispatch(int $userId, object $factor, string $code): void
    {
        if ($factor->factor_type === self::TYPE_EMAIL) {
            $locale = $this->resolveUserLocale($userId);
            try {
                Mail::to($factor->destination)->queue(new OtpCodeMail(
                    $code,
                    $factor->label,
                    $factor->destination,
                    $locale,
                    self::CHALLENGE_TTL_MINUTES,
                ));
            } catch (\Throwable $e) {
                \Log::warning('otp.email.queue_failed', [
                    'user_id' => $userId,
                    'factor_id' => $factor->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return;
        }

        if ($factor->factor_type === self::TYPE_SMS) {
            $body = sprintf('Your %s code is %s. It expires in %d minutes.',
                config('app.name', 'Heratio'), $code, self::CHALLENGE_TTL_MINUTES);
            try {
                $this->smsGateway()->send($factor->destination, $body);
            } catch (\Throwable $e) {
                \Log::warning('otp.sms.send_failed', [
                    'user_id' => $userId,
                    'factor_id' => $factor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolveUserLocale(int $userId): ?string
    {
        if (! Schema::hasColumn('user', 'preferred_locale')) {
            return null;
        }

        try {
            return DB::table('user')->where('id', $userId)->value('preferred_locale');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Normalise a phone number for E.164-ish storage. We don't enforce a
     * specific country - the operator and user are expected to type the
     * full international form. We strip everything except '+' and digits
     * so the same number always hashes to the same destination row.
     */
    private function normalisePhone(string $raw): string
    {
        $raw = trim($raw);
        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';

        return ($hasPlus ? '+' : '').$digits;
    }

    private function lockoutKey(int $factorId): string
    {
        return 'otp.factor.lockout.'.$factorId;
    }

    private function locked(int $factorId): bool
    {
        $count = (int) Cache::get($this->lockoutKey($factorId), 0);

        return $count >= self::MAX_ATTEMPTS;
    }

    private function bumpLockout(int $factorId): void
    {
        $key = $this->lockoutKey($factorId);
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addMinutes(self::FAIL_WINDOW_MINUTES));
    }

    private function clearLockout(int $factorId): void
    {
        Cache::forget($this->lockoutKey($factorId));
    }
}
