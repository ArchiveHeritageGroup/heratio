<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * #763 FRBR work-set clustering: add work-key column to library_item so
     * search can collapse multiple manifestations of the same Work into one
     * result row. Indexed for ES + DB-side aggregation.
     */
    public function up(): void
    {
        if (!Schema::hasTable('library_item')) {
            return;
        }
        if (!Schema::hasColumn('library_item', 'work_key')) {
            Schema::table('library_item', function (Blueprint $t) {
                $t->string('work_key', 32)->nullable()->after('id');
                $t->index('work_key', 'ix_library_item_work_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('library_item') && Schema::hasColumn('library_item', 'work_key')) {
            Schema::table('library_item', function (Blueprint $t) {
                $t->dropIndex('ix_library_item_work_key');
                $t->dropColumn('work_key');
            });
        }
    }
};
