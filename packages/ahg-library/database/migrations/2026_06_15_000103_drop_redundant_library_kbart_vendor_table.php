<?php

/**
 * heratio#1281 - drop the redundant library_kbart_vendor table created in the
 * interop-backbone migration (2026_06_15_000101).
 *
 * Verification showed heratio already has the KBART vendor-feed registry + scheduled
 * harvest under a different table name:
 *   - library_kbart_vendor  ->  superseded by heratio's library_kbart_feed
 *     (KbartRemoteService: automated feed scheduler with name/vendor/url/active/
 *      last_fetch_at/last_fetch_status/last_row_count/fingerprint; KbartAdminController:
 *      full feed-subscription CRUD; library_kbart_import_log: per-run audit).
 *
 * heratio's model is a superset (fingerprint dedup + scheduler + status), so porting the
 * PSIS vendor table/code would only duplicate existing capability. The table is empty
 * (created same-day, never populated), so dropping is non-destructive. The other genuine
 * backbone gaps (Z39.50/SRU server config/request/log - SRU now built; bindery; ILL
 * history; order-line fund; FRBR override) remain.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('library_kbart_vendor');
    }

    public function down(): void
    {
        // No-op: redundant duplicate of heratio's library_kbart_feed. Re-creating it on
        // rollback would just re-introduce the duplication. (2026_06_15_000101 remains the
        // historical record of its original shape.)
    }
};
