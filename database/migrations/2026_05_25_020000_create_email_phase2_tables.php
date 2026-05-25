<?php

/**
 * Phase 2 of #674 (Email + notifications)
 *
 * - ahg_email_bounce            : bounce/complaint webhook log
 * - ahg_tenant_email_branding   : per-tenant email branding (logo + colours +
 *                                 footer html + sender override). Web/admin
 *                                 branding lives in ahg_tenant_branding; this
 *                                 row is email-only so a tenant can ship a
 *                                 dedicated mail identity.
 * - user.email_bounced_at       : timestamp set on hard bounce; downstream
 *                                 send paths must skip when set.
 * - user.preferred_locale       : locale code (e.g. 'en', 'af') used by the
 *                                 LocaleAwareMailable trait to pick the
 *                                 right per-locale view.
 *
 * Idempotent. Safe to re-run.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------------------
        // ahg_email_bounce
        // -------------------------------------------------------------------
        if (! Schema::hasTable('ahg_email_bounce')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `ahg_email_bounce` (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `email` VARCHAR(255) NOT NULL,
                    `bounce_type` VARCHAR(40) NOT NULL DEFAULT 'unknown' COMMENT 'hard, soft, complaint, unknown',
                    `bounce_subtype` VARCHAR(80) DEFAULT NULL COMMENT 'provider-specific subtype (e.g. mailbox_full)',
                    `reason` VARCHAR(500) DEFAULT NULL,
                    `message_id` VARCHAR(255) DEFAULT NULL COMMENT 'upstream Message-ID, if known',
                    `provider` VARCHAR(40) DEFAULT NULL COMMENT 'postmark, ses, sparkpost, mailgun, generic',
                    `occurred_at` DATETIME DEFAULT NULL,
                    `payload_json` LONGTEXT DEFAULT NULL COMMENT 'raw webhook payload for audit',
                    `processed_at` DATETIME DEFAULT NULL COMMENT 'when the bounce was applied to the user record',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                    KEY `idx_eb_email` (`email`),
                    KEY `idx_eb_type` (`bounce_type`),
                    KEY `idx_eb_occurred` (`occurred_at`),
                    KEY `idx_eb_message` (`message_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        // -------------------------------------------------------------------
        // ahg_tenant_email_branding
        // -------------------------------------------------------------------
        if (! Schema::hasTable('ahg_tenant_email_branding')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `ahg_tenant_email_branding` (
                    `tenant_id` INT NOT NULL PRIMARY KEY,
                    `logo_url` VARCHAR(500) DEFAULT NULL,
                    `primary_color` VARCHAR(20) DEFAULT NULL,
                    `secondary_color` VARCHAR(20) DEFAULT NULL,
                    `footer_text_html` TEXT DEFAULT NULL,
                    `sender_name` VARCHAR(255) DEFAULT NULL,
                    `sender_email_override` VARCHAR(255) DEFAULT NULL,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

            // Best-effort FK - only if ahg_tenant exists.
            if (Schema::hasTable('ahg_tenant')) {
                try {
                    DB::statement('ALTER TABLE `ahg_tenant_email_branding`
                        ADD CONSTRAINT `fk_ateb_tenant`
                        FOREIGN KEY (`tenant_id`) REFERENCES `ahg_tenant` (`id`)
                        ON DELETE CASCADE');
                } catch (\Throwable $e) {
                    // FK already exists or tenant table missing - safe to ignore.
                }
            }
        }

        // -------------------------------------------------------------------
        // user.email_bounced_at + user.preferred_locale
        // -------------------------------------------------------------------
        if (Schema::hasTable('user')) {
            if (! Schema::hasColumn('user', 'email_bounced_at')) {
                DB::statement('ALTER TABLE `user` ADD COLUMN `email_bounced_at` DATETIME DEFAULT NULL COMMENT "set on hard bounce; suppresses outgoing mail"');
            }
            if (! Schema::hasColumn('user', 'preferred_locale')) {
                DB::statement('ALTER TABLE `user` ADD COLUMN `preferred_locale` VARCHAR(10) DEFAULT NULL COMMENT "preferred locale for outgoing email (e.g. en, af, fr)"');
            }
        }

        // Seed bounce-webhook shared secret if not already configured.
        if (Schema::hasTable('ahg_settings')) {
            $exists = DB::table('ahg_settings')
                ->where('setting_key', 'email_bounce_webhook_secret')
                ->exists();
            if (! $exists) {
                DB::table('ahg_settings')->insert([
                    'setting_key' => 'email_bounce_webhook_secret',
                    'setting_value' => bin2hex(random_bytes(24)),
                    'setting_group' => 'email',
                    'updated_at' => now(),
                ]);
            }
            $providerExists = DB::table('ahg_settings')
                ->where('setting_key', 'email_bounce_webhook_provider')
                ->exists();
            if (! $providerExists) {
                DB::table('ahg_settings')->insert([
                    'setting_key' => 'email_bounce_webhook_provider',
                    'setting_value' => 'generic',
                    'setting_group' => 'email',
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user')) {
            if (Schema::hasColumn('user', 'preferred_locale')) {
                DB::statement('ALTER TABLE `user` DROP COLUMN `preferred_locale`');
            }
            if (Schema::hasColumn('user', 'email_bounced_at')) {
                DB::statement('ALTER TABLE `user` DROP COLUMN `email_bounced_at`');
            }
        }
        Schema::dropIfExists('ahg_tenant_email_branding');
        Schema::dropIfExists('ahg_email_bounce');
    }
};
