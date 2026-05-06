<?php

namespace App\Auth;

use AhgCore\Services\AhgSettingsService;

/**
 * Centralised reader for the 10 security settings shown on
 * /admin/ahgSettings/security. Closes audit issue #90: every key on that
 * page is now consumed by either LoginController, the session-timeout
 * middleware, or one of the two scheduled commands. Defaults match the
 * seeded values so a fresh install behaves the same whether or not the
 * operator has visited the form.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */
class SecuritySettings
{
    public static function lockoutEnabled(): bool
    {
        return AhgSettingsService::getBool('security_lockout_enabled', true);
    }

    public static function lockoutMaxAttempts(): int
    {
        $n = AhgSettingsService::getInt('security_lockout_max_attempts', 5);
        return $n > 0 ? $n : 5;
    }

    public static function lockoutDurationMinutes(): int
    {
        $n = AhgSettingsService::getInt('security_lockout_duration_minutes', 15);
        return $n > 0 ? $n : 15;
    }

    public static function loginAttemptCleanupHours(): int
    {
        $n = AhgSettingsService::getInt('security_login_attempt_cleanup_hours', 24);
        return $n > 0 ? $n : 24;
    }

    public static function passwordExpiryDays(): int
    {
        return AhgSettingsService::getInt('password_expiry_days', 90);
    }

    public static function passwordExpiryEnabled(): bool
    {
        return self::passwordExpiryDays() > 0;
    }

    public static function passwordHistoryCount(): int
    {
        $n = AhgSettingsService::getInt('password_history_count', 5);
        return $n >= 0 ? $n : 5;
    }

    public static function forcePasswordChange(): bool
    {
        return AhgSettingsService::getBool('security_force_password_change', false);
    }

    /**
     * Timestamp the global force-change flag was last flipped on. Set by the
     * settings save handler when the operator ticks "Force password change".
     * Used to gate users whose last password change predates the flip.
     * Falls back to "never" (epoch zero) when unset, so the gate passes.
     */
    public static function forcePasswordChangeBaseline(): ?\Carbon\Carbon
    {
        $stamp = AhgSettingsService::get('security_force_password_change_baseline', '');
        if (!$stamp) return null;
        try {
            return \Carbon\Carbon::parse($stamp);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function passwordExpiryNotify(): bool
    {
        return AhgSettingsService::getBool('security_password_expiry_notify', true);
    }

    public static function passwordExpiryWarnDays(): int
    {
        $n = AhgSettingsService::getInt('security_password_expiry_warn_days', 14);
        return $n > 0 ? $n : 14;
    }

    public static function sessionTimeoutMinutes(): int
    {
        $n = AhgSettingsService::getInt('security_session_timeout_minutes', 30);
        return $n > 0 ? $n : 30;
    }
}
