<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The heritage_timeline_period seed used INSERT IGNORE but the table had no
 * unique key beyond the primary id, so every re-run inserted another full copy
 * of the periods. Each period ended up in the table ~5x, which repeated the
 * labels and squashed the "Explore by Time" timeline. This removes the
 * duplicates and adds the unique key the INSERT IGNORE always assumed existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('heritage_timeline_period')) {
            return;
        }

        // Keep the lowest id per (short_name, start_year); drop the rest.
        DB::statement(
            'DELETE t1 FROM heritage_timeline_period t1
             JOIN heritage_timeline_period t2
               ON t1.short_name <=> t2.short_name
              AND t1.start_year <=> t2.start_year
              AND t1.id > t2.id'
        );

        $hasUnique = collect(DB::select(
            "SHOW INDEX FROM heritage_timeline_period WHERE Key_name = 'uniq_htp_shortname_start'"
        ))->isNotEmpty();

        if (! $hasUnique) {
            try {
                Schema::table('heritage_timeline_period', function (Blueprint $t) {
                    $t->unique(['short_name', 'start_year'], 'uniq_htp_shortname_start');
                });
            } catch (\Throwable $e) {
                // A residual dup (e.g. NULL short_name) could block the index; leave
                // the table as-is rather than fail - the query-side de-dup still
                // keeps the timeline correct.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('heritage_timeline_period')) {
            return;
        }
        try {
            Schema::table('heritage_timeline_period', function (Blueprint $t) {
                $t->dropUnique('uniq_htp_shortname_start');
            });
        } catch (\Throwable $e) {
            // index may not exist
        }
    }
};
