<?php

/**
 * Issue #675 Phase 3 - per-user preferred locale
 *
 * Adds (or re-asserts) `user.preferred_locale CHAR(8) DEFAULT NULL` so the
 * SetLocale middleware can prefer an authenticated user's saved choice
 * before falling back to the Accept-Language header.
 *
 * Heratio authenticates against AtoM's class-table-inheritance `user` table
 * (NOT Laravel's `users` table). The column may already exist if the email
 * Phase 2 migration (2026_05_25_020000_create_email_phase2_tables.php) has
 * run on this instance - that migration introduced the same column as
 * VARCHAR(10) for the LocaleAwareMailable trait. This migration is therefore
 * idempotent: it only creates the column when missing, and if a pre-existing
 * VARCHAR(10) variant is present it is left as-is (compatible storage).
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
        if (! Schema::hasTable('user')) {
            // Fresh-install / CI scaffolding without the AtoM CTI tables.
            return;
        }

        if (! Schema::hasColumn('user', 'preferred_locale')) {
            DB::statement(
                'ALTER TABLE `user` ADD COLUMN `preferred_locale` CHAR(8) DEFAULT NULL '
                .'COMMENT "preferred UI locale (e.g. en, af, fr_CA); consulted by SetLocale middleware + LocaleAwareMailable"'
            );
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: the column is also a dependency of the email
        // Phase 2 migration, and dropping it here would break LocaleAwareMailable
        // on any instance where Phase 3 is rolled back but Phase 2 stays. The
        // Phase 2 down() handles the column drop in its own teardown path.
    }
};
