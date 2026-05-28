<?php

/**
 * Heratio standalone multi-tenancy.
 *
 * Each tenant maps to a `repository` row; data scoping is enforced via the
 * existing `repository_id` FK on information_object / digital_object / etc.
 * Tenant roles (ahg_tenant_user.role) SUPPLEMENT ahg-acl — they do not
 * replace it.
 *
 * ISSUE: the service provider's install.sql ran on first boot before this
 * migration executed, creating ahg_tenant with only 5 columns (id, is_default,
 * name, created_at, updated_at). This migration ALTERs the existing table to
 * the full schema, then creates the related tables.  Each ADD COLUMN is a
 * separate statement so MySQL can process them individually.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function safeAlter(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            // Column already exists or other benign error — skip.
        }
    }

    public function up(): void
    {
        // -------------------------------------------------------------------
        // ahg_tenant — ALTER existing table (partial boot-run left it with
        // only 5 columns). Idempotent — only adds missing columns.
        // MySQL requires one ADD COLUMN per ALTER statement.
        // -------------------------------------------------------------------
        if (Schema::hasTable('ahg_tenant') && ! Schema::hasColumn('ahg_tenant', 'code')) {
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `code` VARCHAR(50) NOT NULL AFTER `id` COMMENT 'Unique tenant code / slug'");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `description` TEXT NULL AFTER `name`");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `domain` VARCHAR(255) NULL AFTER `description` COMMENT 'Full custom domain'");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `subdomain` VARCHAR(100) NULL AFTER `domain`");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `repository_id` INT UNSIGNED NULL AFTER `subdomain` COMMENT 'Primary repository'");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `contact_email` VARCHAR(255) NULL AFTER `repository_id`");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `contact_phone` VARCHAR(50) NULL AFTER `contact_email`");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `max_users` INT NULL AFTER `contact_phone` COMMENT 'Quota; NULL = unlimited'");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `max_storage_gb` INT NULL AFTER `max_users`");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `max_storage_gb`");
            $this->safeAlter("ALTER TABLE `ahg_tenant` MODIFY COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Fallback tenant when no host / session match'");
            $this->safeAlter("ALTER TABLE `ahg_tenant` MODIFY COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `status` VARCHAR(36) NOT NULL DEFAULT 'active' AFTER `is_default` COMMENT 'active, suspended, trial'");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `trial_ends_at` DATETIME NULL AFTER `status`");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `suspended_at` DATETIME NULL AFTER `trial_ends_at`");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `suspended_reason` VARCHAR(500) NULL AFTER `suspended_at`");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `settings` JSON NULL AFTER `suspended_reason` COMMENT 'Free-form per-tenant settings'");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD COLUMN `created_by` INT UNSIGNED NULL AFTER `settings`");

            // Constraints and indexes — wrap in try/catch for idempotency
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD UNIQUE INDEX `ahg_tenant_code_unique` (`code`)");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD UNIQUE INDEX `ahg_tenant_domain_unique` (`domain`)");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD UNIQUE INDEX `ahg_tenant_subdomain_unique` (`subdomain`)");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD INDEX `idx_tenant_status` (`status`, `is_active`)");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD INDEX `idx_tenant_repository` (`repository_id`)");
            $this->safeAlter("ALTER TABLE `ahg_tenant` ADD INDEX `idx_tenant_default` (`is_default`)");

            try {
                DB::statement("ALTER TABLE `ahg_tenant` ADD CONSTRAINT `fk_ahg_tenant_repository`
                    FOREIGN KEY (`repository_id`) REFERENCES `repository` (`id`) ON DELETE SET NULL");
            } catch (\Throwable $e) {
                // FK may already exist.
            }
        }

        // -------------------------------------------------------------------
        // ahg_tenant_user — per-tenant role assignments
        // -------------------------------------------------------------------
        if (! Schema::hasTable('ahg_tenant_user')) {
            Schema::create('ahg_tenant_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('ahg_tenant')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('user')->cascadeOnDelete();
                $table->string('role', 50)->default('viewer')
                    ->comment('owner, super_user, editor, contributor, viewer');
                $table->boolean('is_super_user')->default(false)
                    ->comment('Convenience flag mirroring role=super_user|owner');
                $table->boolean('is_primary')->default(false)
                    ->comment('Default tenant for this user when no host/session match');
                $table->dateTime('assigned_at')->useCurrent();
                $table->unsignedInteger('assigned_by')->nullable();

                $table->unique(['tenant_id', 'user_id'], 'uk_tenant_user');
                $table->index('user_id', 'idx_tu_user');
                $table->index('role', 'idx_tu_role');
                $table->index(['user_id', 'is_primary'], 'idx_tu_primary');
            });
        }

        // -------------------------------------------------------------------
        // ahg_tenant_branding — per-tenant theme overrides
        // -------------------------------------------------------------------
        if (! Schema::hasTable('ahg_tenant_branding')) {
            Schema::create('ahg_tenant_branding', function (Blueprint $table) {
                $table->foreignId('tenant_id')->primary()->constrained('ahg_tenant')->cascadeOnDelete();
                $table->string('logo_url', 500)->nullable();
                $table->string('primary_color', 20)->nullable();
                $table->string('secondary_color', 20)->nullable();
                $table->text('custom_css')->nullable();
                $table->string('favicon_url', 500)->nullable();
                $table->timestamps();
            });
        }

        // -------------------------------------------------------------------
        // ahg_tenant_settings — structured per-tenant settings
        // -------------------------------------------------------------------
        if (! Schema::hasTable('ahg_tenant_settings')) {
            Schema::create('ahg_tenant_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('ahg_tenant')->cascadeOnDelete();
                $table->string('setting_key', 100);
                $table->text('setting_value')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'setting_key'], 'uk_tenant_setting');
                $table->index('setting_key', 'idx_ts_key');
            });
        }

        // -------------------------------------------------------------------
        // Seed default tenant when ahg_tenant is empty
        // Use select count(*) directly — DB::table()->exists() can be false
        // even when a row is present if the previous migration run partially
        // succeeded (table created, seed failed, migration not recorded).
        // -------------------------------------------------------------------
        if (Schema::hasTable('ahg_tenant')) {
            $count = (int) DB::selectOne('SELECT COUNT(*) as cnt FROM `ahg_tenant`')->cnt ?? 0;
            if ($count === 0) {
                DB::table('ahg_tenant')->insert([
                    'code' => 'default',
                    'name' => 'Default',
                    'description' => 'Auto-seeded default tenant. Rename or replace as needed.',
                    'is_active' => true,
                    'is_default' => true,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_tenant_settings');
        Schema::dropIfExists('ahg_tenant_branding');
        Schema::dropIfExists('ahg_tenant_user');
        Schema::dropIfExists('ahg_tenant');
    }
};