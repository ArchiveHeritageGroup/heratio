<?php

/**
 * #1464 - consolidate rights_embargo into embargo.
 *
 * There were two embargo tables, split-brain across write, display and expiry:
 *
 *   embargo         - written by ahg-information-object-manage and
 *                     ahg-rights-holder-manage; read by EVERY record show page
 *                     (io-manage, library, dam, museum), the core record
 *                     sidebar and the search rights-indicator; its expiry
 *                     command `ahg:embargo-process` IS scheduled.
 *   rights_embargo  - written by ahg-extended-rights (EmbargoService) and
 *                     ahg-scan (RightsEnforcementService); read by one context
 *                     menu; its expiry command `embargo:process` is a SECOND
 *                     command that is NOT scheduled.
 *
 * So an embargo created through the second pair does not show on any record
 * page and is never auto-lifted. Both failure directions matter: material that
 * looks unrestricted when it is not, and material that stays restricted past
 * its release date.
 *
 * `embargo` is authoritative - it is already the display and enforcement path,
 * and it carries the fields display needs (is_active, public_message,
 * is_perpetual). This migration folds in the workflow columns rights_embargo
 * had and it lacked, then merges the data.
 *
 * MERGE, NOT COPY: an object can already carry the same embargo in both tables
 * (recorded twice through the two paths). Blindly inserting would duplicate an
 * active restriction, so a rights_embargo row whose object already has an
 * embargo of the same type only enriches that row with the workflow fields;
 * only genuinely absent ones are inserted.
 *
 * rights_embargo is NOT dropped here. The writers are repointed in the same
 * release, but the table stays until a release has proven that in the field -
 * dropping an access-control table on the same deploy that changes its writers
 * leaves no fallback. Removal is a follow-up.
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
    /** rights_embargo columns embargo lacked. All nullable - purely additive. */
    private const ADDED = [
        'auto_release' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'lift automatically at end_date'",
        'review_date' => 'DATE NULL',
        'review_interval_months' => 'INT NULL',
        'last_reviewed_at' => 'DATETIME NULL',
        'last_reviewed_by' => 'INT NULL',
        'notification_sent' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'pre-expiry notice already sent'",
        'notify_emails' => "TEXT NULL COMMENT 'JSON array of additional recipients'",
    ];

    public function up(): void
    {
        if (! Schema::hasTable('embargo')) {
            return;
        }

        foreach (self::ADDED as $column => $definition) {
            if (! Schema::hasColumn('embargo', $column)) {
                DB::statement("ALTER TABLE `embargo` ADD COLUMN `{$column}` {$definition}");
            }
        }

        if (! Schema::hasTable('rights_embargo')) {
            return;
        }

        $merged = 0;
        $inserted = 0;

        foreach (DB::table('rights_embargo')->get() as $re) {
            // Same object + same kind of restriction = the same embargo recorded
            // twice, not two embargoes.
            $existing = DB::table('embargo')
                ->where('object_id', $re->object_id)
                ->where('embargo_type', $re->embargo_type)
                ->orderByDesc('id')
                ->first();

            $workflow = [
                'auto_release' => $re->auto_release ?? 0,
                'review_date' => $re->review_date ?? null,
                'review_interval_months' => $re->review_interval_months ?? null,
                'last_reviewed_at' => $re->last_reviewed_at ?? null,
                'last_reviewed_by' => $re->last_reviewed_by ?? null,
                'notification_sent' => $re->notification_sent ?? 0,
                'notify_emails' => $re->notify_emails ?? null,
            ];

            if ($existing) {
                DB::table('embargo')->where('id', $existing->id)->update($workflow + ['updated_at' => now()]);
                $merged++;

                continue;
            }

            // Note the transposed name: rights_embargo.notify_before_days is
            // embargo.notify_days_before.
            DB::table('embargo')->insert($workflow + [
                'object_id' => $re->object_id,
                'embargo_type' => $re->embargo_type,
                'reason' => $re->reason ?? null,
                'start_date' => $re->start_date ?? null,
                'end_date' => $re->end_date ?? null,
                'status' => $re->status ?? 'active',
                'is_active' => ($re->status ?? 'active') === 'active' ? 1 : 0,
                'lifted_at' => $re->lifted_at ?? null,
                'lifted_by' => $re->lifted_by ?? null,
                'lift_reason' => $re->lift_reason ?? null,
                'notify_days_before' => $re->notify_before_days ?? null,
                'created_by' => $re->created_by ?? null,
                'created_at' => $re->created_at ?? now(),
                'updated_at' => now(),
            ]);
            $inserted++;
        }

        Log::info("#1464 embargo consolidation: {$inserted} row(s) moved into `embargo`, {$merged} merged into an existing embargo.");
    }

    public function down(): void
    {
        // Not reverted. Dropping the added columns would discard review-cycle
        // data merged in from rights_embargo, and the merge itself cannot be
        // unpicked once an operator has edited the surviving row.
    }
};
