<?php

/**
 * heratio#1281 - drop the redundant library_ill_status_history table created in the
 * interop-backbone migration (2026_06_15_000101).
 *
 * Verification showed heratio already logs ILL status transitions, under a different name:
 *   - library_ill_status_history  ->  superseded by heratio's library_ill_audit
 *     (LibraryIllService::transitionTo() runs the ISO 10160 state machine and calls
 *      logTransition(), writing ill_number/from_status/to_status/description/changed_by/
 *      created_at on every transition). heratio's table is a superset - it also records
 *      the actor (changed_by), which the PSIS history table lacked.
 *
 * Porting the PSIS table/code would only duplicate existing capability. The table is empty
 * (created same-day, never populated), so dropping is non-destructive. The remaining genuine
 * backbone gaps (Z39.50 binary-server config/request - for the future daemon; SRU log - built;
 * order-line fund; FRBR override; serials bindery - built) are unaffected.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('library_ill_status_history');
    }

    public function down(): void
    {
        // No-op: redundant duplicate of heratio's library_ill_audit. Re-creating it on
        // rollback would just re-introduce the duplication. (2026_06_15_000101 remains the
        // historical record of its original shape.)
    }
};
