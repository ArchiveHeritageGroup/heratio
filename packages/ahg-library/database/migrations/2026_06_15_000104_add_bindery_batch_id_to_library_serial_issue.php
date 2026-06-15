<?php

/**
 * heratio#1281 - link serial issues to their bindery batch (serials bindery feature).
 *
 * Adds library_serial_issue.bindery_batch_id (FK-style ref to library_bindery_batch,
 * created in 2026_06_15_000101). Idempotent. Pairs with the SerialService bindery
 * methods + the bindery dashboard. heratio already tracks per-serial binding via
 * library_binding; this adds the vendor-consignment BATCH workflow PSIS has and
 * heratio lacked (send received issues to a bindery, receive them back as bound).
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_serial_issue')) {
            return;
        }
        if (! Schema::hasColumn('library_serial_issue', 'bindery_batch_id')) {
            Schema::table('library_serial_issue', function (Blueprint $table) {
                $table->unsignedBigInteger('bindery_batch_id')->nullable()
                    ->comment('library_bindery_batch.id - the batch this issue was sent to bindery in');
                $table->index('bindery_batch_id', 'idx_serial_issue_bindery_batch');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('library_serial_issue') && Schema::hasColumn('library_serial_issue', 'bindery_batch_id')) {
            Schema::table('library_serial_issue', function (Blueprint $table) {
                $table->dropIndex('idx_serial_issue_bindery_batch');
                $table->dropColumn('bindery_batch_id');
            });
        }
    }
};
