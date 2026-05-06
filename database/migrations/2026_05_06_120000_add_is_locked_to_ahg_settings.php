<?php

/**
 * Add is_locked flag to ahg_settings.
 *
 * Some settings ship as configurable in the UI but their consumer is not
 * built yet (currently the AHG Central tile - see GitHub issue #67). For
 * those we want to keep the form visible (so the user knows the feature is
 * planned) but refuse writes so a save can't flip a flag the rest of the
 * codebase can't honour. This column is the storage side of that lock; the
 * save handler in SettingsController checks it before writing.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ahg_settings')) {
            return;
        }
        if (!Schema::hasColumn('ahg_settings', 'is_locked')) {
            DB::statement("ALTER TABLE ahg_settings ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_sensitive");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ahg_settings') && Schema::hasColumn('ahg_settings', 'is_locked')) {
            DB::statement("ALTER TABLE ahg_settings DROP COLUMN is_locked");
        }
    }
};
