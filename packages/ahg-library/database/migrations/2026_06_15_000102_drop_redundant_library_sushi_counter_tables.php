<?php

/**
 * heratio#1281 - drop the 3 redundant SUSHI/COUNTER tables created in the prior
 * interop-backbone migration (2026_06_15_000101).
 *
 * Verification after that migration showed heratio already has a fuller SUSHI/COUNTER
 * implementation than the PSIS source the audit compared against:
 *   - library_usage_event       -> superseded by heratio's library_counter_log (per-event log,
 *                                    written by LibraryUsageService::recordAccess/recordCheckout)
 *   - library_sushi_access_log  -> superseded by heratio's library_sushi_audit_log
 *                                    (written by SushiServerController::audit())
 *   - library_counter_settings  -> heratio stores COUNTER config in config('library.counter.*'),
 *                                    not a DB settings table
 *
 * heratio's LibraryUsageService::buildCounterReport() already supports all six COUNTER R5
 * reports (PR, TR, TR_J1, TR_J3, DR, IR) and SushiServerController exposes the SUSHI 5.0
 * server, so porting the PSIS counter/sushi code would only duplicate existing capability.
 * The other 8 interop-backbone tables (Z39.50/SRU server, KBART vendor, bindery, ILL history,
 * order-line fund, FRBR override) are genuine gaps and are kept.
 *
 * The tables are empty (created same-day, never populated), so dropping is non-destructive.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The 3 redundant tables to remove (the 8 genuine-gap tables are untouched). */
    private array $redundant = [
        'library_usage_event',
        'library_counter_settings',
        'library_sushi_access_log',
    ];

    public function up(): void
    {
        foreach ($this->redundant as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // No-op: these tables were redundant duplicates of heratio's own
        // library_counter_log / library_sushi_audit_log / config('library.counter.*').
        // Re-creating them on rollback would just re-introduce the duplication, so the
        // rollback intentionally leaves them absent. (The 2026_06_15_000101 migration
        // remains the historical record of their original shape.)
    }
};
