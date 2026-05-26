<?php

/**
 * Phase 3 of #674 (Email + notifications)
 *
 * - ahg_sent_email                            : per-message audit trail
 *   (queued/sent/failed/suppressed), populated by EmailAuditListener
 *   from the framework's MessageSending / MessageSent / MessageFailed
 *   events plus explicit suppression-gate writes at dispatch sites.
 * - ahg_workflow_task.last_overdue_notification_at : nag-suppression
 *   stamp so workflow:notify-overdue does not spam the same overdue
 *   task daily. Re-notifies after the configurable repeat interval
 *   (default 7 days, exposed via ahg_settings.workflow_overdue_repeat_days).
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
        // ahg_sent_email
        // -------------------------------------------------------------------
        if (! Schema::hasTable('ahg_sent_email')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `ahg_sent_email` (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `mailable_class` VARCHAR(255) NOT NULL,
                    `recipient_email` VARCHAR(255) NOT NULL,
                    `recipient_user_id` BIGINT DEFAULT NULL,
                    `subject` VARCHAR(512) DEFAULT NULL,
                    `locale` CHAR(8) DEFAULT NULL,
                    `tenant_id` INT UNSIGNED DEFAULT NULL,
                    `queue_job_id` VARCHAR(64) DEFAULT NULL,
                    `message_id` VARCHAR(255) DEFAULT NULL COMMENT 'Symfony Message-ID header when known',
                    `queued_at` DATETIME(3) DEFAULT NULL,
                    `sent_at` DATETIME(3) DEFAULT NULL,
                    `status` VARCHAR(16) NOT NULL DEFAULT 'queued' COMMENT 'queued | sent | failed | suppressed',
                    `error` TEXT DEFAULT NULL,
                    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                    KEY `idx_se_recipient` (`recipient_email`, `sent_at`),
                    KEY `idx_se_status` (`status`, `queued_at`),
                    KEY `idx_se_mailable` (`mailable_class`, `sent_at`),
                    KEY `idx_se_tenant` (`tenant_id`, `sent_at`),
                    KEY `idx_se_message` (`message_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        // -------------------------------------------------------------------
        // ahg_workflow_task.last_overdue_notification_at
        // -------------------------------------------------------------------
        if (Schema::hasTable('ahg_workflow_task')
            && ! Schema::hasColumn('ahg_workflow_task', 'last_overdue_notification_at')) {
            DB::statement('ALTER TABLE `ahg_workflow_task`
                ADD COLUMN `last_overdue_notification_at` DATETIME DEFAULT NULL
                COMMENT "Phase 3 #674: timestamp of the last WorkflowTaskOverdueMail dispatch; suppresses daily re-nag"');

            // Helpful index for the nightly sweep WHERE clause.
            try {
                DB::statement('ALTER TABLE `ahg_workflow_task`
                    ADD INDEX `idx_overdue_sweep` (`status`, `due_date`, `last_overdue_notification_at`)');
            } catch (\Throwable $e) {
                // Index may already exist (re-run); harmless.
            }
        }

        // -------------------------------------------------------------------
        // Seed Phase 3 settings if not already configured.
        // -------------------------------------------------------------------
        if (Schema::hasTable('ahg_settings')) {
            $defaults = [
                // workflow nag interval (days) for overdue-task re-notification
                'workflow_overdue_repeat_days' => '7',
                // ops mailbox for DOI mint failures (comma-separated allowed)
                'doi_failure_notify' => '',
                // ops mailbox for SharePoint sync errors (comma-separated allowed)
                'sharepoint_ops_email' => '',
                // master toggle for the EmailAuditListener insert path
                'email_audit_enabled' => '1',
            ];
            foreach ($defaults as $key => $value) {
                $exists = DB::table('ahg_settings')->where('setting_key', $key)->exists();
                if (! $exists) {
                    DB::table('ahg_settings')->insert([
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'setting_group' => 'email',
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_sent_email');

        if (Schema::hasTable('ahg_workflow_task')
            && Schema::hasColumn('ahg_workflow_task', 'last_overdue_notification_at')) {
            try {
                DB::statement('ALTER TABLE `ahg_workflow_task` DROP INDEX `idx_overdue_sweep`');
            } catch (\Throwable $e) {
                // ignore
            }
            DB::statement('ALTER TABLE `ahg_workflow_task` DROP COLUMN `last_overdue_notification_at`');
        }
    }
};
