<?php

namespace AhgSecurityClearance\Providers;

use AhgSecurityClearance\Http\Middleware\EnforceMfaPolicy;
use AhgSecurityClearance\Services\MfaPolicyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgSecurityClearanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Issue #723 - MfaPolicyService is a stateless lookup helper, fine
        // as a singleton.
        $this->app->singleton(MfaPolicyService::class);
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-security-clearance');

        // Issue #723 - middleware alias so bootstrap/app.php and individual
        // route groups can refer to it as 'mfa.policy' without importing
        // the FQCN.
        $router = $this->app['router'];
        $router->aliasMiddleware('mfa.policy', EnforceMfaPolicy::class);

        // Issue #690 / #721 / #722 - auto-install user_totp_secret +
        // user_mfa_recovery_code + ahg_webauthn_credential + ahg_otp_factor +
        // ahg_otp_challenge + (#723) ahg_mfa_policy. Single install.sql is
        // idempotent (CREATE TABLE IF NOT EXISTS throughout), so a missing
        // probe just re-runs the whole script once. Probe + install live
        // inside one try/catch so the CI sqlite stub doesn't 500 the boot.
        try {
            if (! Schema::hasTable('user_totp_secret')
                || ! Schema::hasTable('user_mfa_recovery_code')
                || ! Schema::hasTable('ahg_webauthn_credential')
                || ! Schema::hasTable('ahg_otp_factor')
                || ! Schema::hasTable('ahg_otp_challenge')
                || ! Schema::hasTable('ahg_mfa_policy')) {
                $sql = file_get_contents(__DIR__.'/../../database/install.sql');
                if ($sql !== false && trim($sql) !== '') {
                    DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-security-clearance] schema install skipped: '.$e->getMessage());
        }

        // Issue #722 - second-pass installer just for the OTP tables. The
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

        // Issue #723 - second-pass installer for the MFA policy table, plus
        // the global default row + dropdown vocabulary seed. Standalone so a
        // partial-failure on the big install.sql doesn't take down the
        // tenant-policy feature.
        try {
            if (! Schema::hasTable('ahg_mfa_policy')) {
                DB::unprepared(<<<'SQL'
                    CREATE TABLE IF NOT EXISTS `ahg_mfa_policy` (
                      `id` int unsigned NOT NULL AUTO_INCREMENT,
                      `tenant_id` int DEFAULT NULL,
                      `enforcement` varchar(32) NOT NULL DEFAULT 'optional',
                      `grace_period_days` int NOT NULL DEFAULT 7,
                      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `uq_mfa_policy_tenant` (`tenant_id`),
                      KEY `idx_mfa_policy_enforcement` (`enforcement`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
                SQL
                );
            }

            // Seed the global default row exactly once.
            if (Schema::hasTable('ahg_mfa_policy')) {
                $globalExists = DB::table('ahg_mfa_policy')->whereNull('tenant_id')->exists();
                if (! $globalExists) {
                    DB::table('ahg_mfa_policy')->insert([
                        'tenant_id' => null,
                        'enforcement' => 'optional',
                        'grace_period_days' => 7,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Seed the enforcement vocabulary into ahg_dropdown the first time.
            if (Schema::hasTable('ahg_dropdown')) {
                $seeded = DB::table('ahg_dropdown')->where('taxonomy', 'mfa_enforcement')->exists();
                if (! $seeded) {
                    $rows = [
                        ['code' => 'off', 'label' => 'Off', 'color' => '#6c757d', 'icon' => 'slash-circle', 'sort_order' => 10],
                        ['code' => 'optional', 'label' => 'Optional', 'color' => '#0d6efd', 'icon' => 'circle', 'sort_order' => 20],
                        ['code' => 'required_for_admins', 'label' => 'Required for admins', 'color' => '#fd7e14', 'icon' => 'shield-lock', 'sort_order' => 30],
                        ['code' => 'required', 'label' => 'Required for everyone', 'color' => '#dc3545', 'icon' => 'shield-fill-check', 'sort_order' => 40],
                    ];
                    foreach ($rows as $r) {
                        DB::table('ahg_dropdown')->insertOrIgnore([
                            'taxonomy' => 'mfa_enforcement',
                            'taxonomy_label' => 'MFA Enforcement',
                            'taxonomy_section' => 'security',
                            'code' => $r['code'],
                            'label' => $r['label'],
                            'color' => $r['color'],
                            'icon' => $r['icon'],
                            'sort_order' => $r['sort_order'],
                            'is_active' => 1,
                            'created_at' => now(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-security-clearance] MFA policy install skipped: '.$e->getMessage());
        }
    }
}
