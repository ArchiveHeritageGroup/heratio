<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1533. A dedupe merge now deletes the duplicate. Its version history
 * cascades away with it, so the merge log keeps a full snapshot of the deleted
 * record for inspection or a restore by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ahg_merge_log') && ! Schema::hasColumn('ahg_merge_log', 'loser_snapshot_json')) {
            Schema::table('ahg_merge_log', function (Blueprint $t) {
                $t->longText('loser_snapshot_json')->nullable()->after('digital_objects_moved');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ahg_merge_log', 'loser_snapshot_json')) {
            Schema::table('ahg_merge_log', fn (Blueprint $t) => $t->dropColumn('loser_snapshot_json'));
        }
    }
};
