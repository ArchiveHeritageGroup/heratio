<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Part B - draft/embargo for authority records.
 *
 * Authority records have never had a publish/draft flag (only description_status
 * + icip_sensitivity). This adds:
 *   1. actor.embargo_until (nullable date) - hide a published record from the
 *      public until a date passes (living-persons / GDPR-POPIA use case).
 *   2. A backfill: every existing actor with no publication-status row is set to
 *      Published (status type_id 158, status_id 160). WITHOUT this, the moment
 *      the guest visibility filter goes live all 400+ existing authority records
 *      would disappear from public browse/search (no 160 row = not published).
 *      New records get their status from the ISAAR form via the controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actor', function (Blueprint $table) {
            if (! Schema::hasColumn('actor', 'embargo_until')) {
                $table->date('embargo_until')->nullable();
            }
        });

        // Backfill existing actors to Published so they stay publicly visible.
        // status.id is a plain auto-increment PK (no FK to object), so a direct
        // INSERT...SELECT is safe; object_id -> actor.id satisfies status_FK_1.
        DB::statement(
            'INSERT INTO status (object_id, type_id, status_id) '.
            'SELECT a.id, 158, 160 FROM actor a '.
            'WHERE NOT EXISTS (SELECT 1 FROM status s WHERE s.object_id = a.id AND s.type_id = 158)'
        );
    }

    public function down(): void
    {
        Schema::table('actor', function (Blueprint $table) {
            if (Schema::hasColumn('actor', 'embargo_until')) {
                $table->dropColumn('embargo_until');
            }
        });
        // The backfilled 'published' status rows are correct and left in place.
    }
};
