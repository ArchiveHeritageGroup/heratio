<?php

/**
 * Collapse repeated identical errors into one row with an occurrence count.
 *
 * `bootstrap/app.php` inserts a row into `ahg_error_log` for every reported
 * throwable, with no deduplication. That is fine for distinct faults and useless
 * for a persistent one: when the AI gateway key hit its quota on 2026-08-20, the
 * scheduler failed every five minutes and wrote the SAME row - "Scheduled command
 * [ahg:cron-run] failed with exit code [1]" - 13 times an hour, on two instances,
 * for 11 hours. Clearing the log refilled it within minutes, because the condition
 * had not changed; only the row count had.
 *
 * An operator opening /admin/errorLog then sees 76 identical rows and no signal.
 * That is alert fatigue, and it buries the one-off errors that actually need
 * reading underneath a repeating one that does not.
 *
 * These three columns let the reporter UPDATE instead of INSERT when the same
 * fault recurs:
 *
 *   fingerprint   - sha1(exception_class|message|file|line), indexed
 *   occurrences   - how many times this exact fault has been seen
 *   last_seen_at  - when it was last seen (created_at stays the FIRST sighting)
 *
 * Nothing is hidden: a repeating error still shows, with its true count and the
 * span it covers, which is more information than 76 identical rows carried.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_error_log')) {
            return;
        }

        Schema::table('ahg_error_log', function (Blueprint $table) {
            if (! Schema::hasColumn('ahg_error_log', 'fingerprint')) {
                $table->string('fingerprint', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('ahg_error_log', 'occurrences')) {
                $table->unsignedInteger('occurrences')->default(1);
            }
            if (! Schema::hasColumn('ahg_error_log', 'last_seen_at')) {
                $table->dateTime('last_seen_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ahg_error_log')) {
            return;
        }

        Schema::table('ahg_error_log', function (Blueprint $table) {
            foreach (['fingerprint', 'occurrences', 'last_seen_at'] as $col) {
                if (Schema::hasColumn('ahg_error_log', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
