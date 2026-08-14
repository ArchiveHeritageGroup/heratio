<?php

/**
 * Clear the seeded-but-unreachable AI condition service URL.
 *
 * `ahg-condition/database/install.sql` seeded `ai_condition_service_url` as
 * 'http://localhost:8100', but no service on that port ships with Heratio. Every
 * instance therefore carried a URL pointing at nothing, `ahg:services-check`
 * probed it, reported DOWN, and exited 1 - and because the scheduler logs any
 * non-zero exit, that produced a failed-command entry on every single run.
 *
 * Blanking it makes the probe report "not configured" and skip, which is what
 * iiif_server_url already does and what the setting honestly is.
 *
 * ONLY the exact seeded default is cleared. If an operator has pointed this at a
 * real service - their own host, or the AI gateway - that value is theirs and is
 * left alone.
 *
 * This does not disable the feature. Setting a URL in Settings -> AI condition
 * turns the probe back on immediately; nothing here is one-way.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_settings')) {
            return;
        }

        $cleared = DB::table('ahg_settings')
            ->where('setting_key', 'ai_condition_service_url')
            ->where('setting_value', 'http://localhost:8100')
            ->update(['setting_value' => '']);

        if ($cleared) {
            Log::info('ai_condition_service_url pointed at the unreachable seeded default and has been cleared; services-check will now skip it.');
        }
    }

    public function down(): void
    {
        // Not restored: putting back a URL that points at nothing would only
        // re-break the health check.
    }
};
