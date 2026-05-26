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

        // Issue #690 / #721 — auto-install user_totp_secret +
        // user_mfa_recovery_code + ahg_webauthn_credential. Single install.sql
        // is idempotent (CREATE TABLE IF NOT EXISTS throughout), so a missing
        // probe just re-runs the whole script once. Probe + install live
        // inside one try/catch so the CI sqlite stub doesn't 500 the boot.
        try {
            if (! Schema::hasTable('user_totp_secret')
                || ! Schema::hasTable('user_mfa_recovery_code')
                || ! Schema::hasTable('ahg_webauthn_credential')) {
                $sql = file_get_contents(__DIR__.'/../../database/install.sql');
                if ($sql !== false && trim($sql) !== '') {
                    DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-security-clearance] schema install skipped: '.$e->getMessage());
        }
    }
}
