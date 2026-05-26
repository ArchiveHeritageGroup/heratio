<?php

namespace AhgSecurityClearance\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgSecurityClearanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-security-clearance');

        // Issue #690 / #721 / #722 — auto-install user_totp_secret +
        // user_mfa_recovery_code + ahg_webauthn_credential + ahg_otp_factor +
        // ahg_otp_challenge. Single install.sql is idempotent (CREATE TABLE
        // IF NOT EXISTS throughout), so a missing probe just re-runs the
        // whole script once. Probe + install live inside one try/catch so
        // the CI sqlite stub doesn't 500 the boot.
        try {
            if (! Schema::hasTable('user_totp_secret')
                || ! Schema::hasTable('user_mfa_recovery_code')
                || ! Schema::hasTable('ahg_webauthn_credential')
                || ! Schema::hasTable('ahg_otp_factor')
                || ! Schema::hasTable('ahg_otp_challenge')) {
                $sql = file_get_contents(__DIR__.'/../../database/install.sql');
                if ($sql !== false && trim($sql) !== '') {
                    DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-security-clearance] schema install skipped: '.$e->getMessage());
        }

        // Issue #722 — second-pass installer just for the OTP tables. The
        // monolithic install.sql can fail partway through on stale FK or
        // dump-time artefacts left over from prior phases; that should not
        // block the OTP factor schema from going down. The standalone DDL
        // below has no foreign keys outside its own pair, so it succeeds
        // independently.
        try {
            if (! Schema::hasTable('ahg_otp_factor') || ! Schema::hasTable('ahg_otp_challenge')) {
                DB::unprepared(<<<'SQL'
                    CREATE TABLE IF NOT EXISTS `ahg_otp_factor` (
                      `id` int unsigned NOT NULL AUTO_INCREMENT,
                      `user_id` int unsigned NOT NULL,
                      `factor_type` varchar(16) NOT NULL,
                      `destination` varchar(255) NOT NULL,
                      `label` varchar(255) NOT NULL DEFAULT '',
                      `verified_at` datetime DEFAULT NULL,
                      `last_used_at` datetime DEFAULT NULL,
                      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      KEY `idx_otp_factor_user` (`user_id`),
                      KEY `idx_otp_factor_user_type` (`user_id`, `factor_type`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

                    CREATE TABLE IF NOT EXISTS `ahg_otp_challenge` (
                      `id` int unsigned NOT NULL AUTO_INCREMENT,
                      `user_id` int unsigned NOT NULL,
                      `factor_id` int unsigned NOT NULL,
                      `code_hash` char(64) NOT NULL,
                      `expires_at` datetime NOT NULL,
                      `attempts` int NOT NULL DEFAULT 0,
                      `consumed_at` datetime DEFAULT NULL,
                      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      KEY `idx_otp_challenge_factor` (`factor_id`, `consumed_at`, `expires_at`),
                      KEY `idx_otp_challenge_user` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
                SQL
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-security-clearance] OTP schema install skipped: '.$e->getMessage());
        }
    }
}
