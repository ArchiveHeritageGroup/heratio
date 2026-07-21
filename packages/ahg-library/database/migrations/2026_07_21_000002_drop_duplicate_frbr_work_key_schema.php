<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1412 - remove the duplicate FRBR work-key schema.
 *
 * The interop-backbone migration (2026_06_15_000101) scaffolded a PARALLEL,
 * never-wired FRBR schema alongside the live one:
 *   LIVE  (kept): library_item.work_key (varchar 32) + table library_work_override
 *                 (override_key) + library_item.frbr_override_type - populated by
 *                 ahg-biblio-frbr WorkKeyService and read by WorkCluster/Override.
 *   DEAD (drop) : library_item.frbr_work_key (varchar 64) + table
 *                 library_item_frbr_override (target_work_key) - zero readers/writers
 *                 repo-wide, so anything pointed at them saw empty values.
 * Standardise on the live pair (decision on #1412-#1414: one model over library_item).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('library_item_frbr_override')) {
            Schema::drop('library_item_frbr_override');
        }
        if (Schema::hasColumn('library_item', 'frbr_work_key')) {
            Schema::table('library_item', function (Blueprint $table) {
                $table->dropColumn('frbr_work_key');
            });
        }
    }

    public function down(): void
    {
        // Recreate the (empty) dead structures so the migration is reversible.
        if (Schema::hasTable('library_item') && ! Schema::hasColumn('library_item', 'frbr_work_key')) {
            Schema::table('library_item', function (Blueprint $table) {
                $table->string('frbr_work_key', 64)->nullable()->comment('SHA-256 work identifier, first 20 chars');
            });
        }
        if (! Schema::hasTable('library_item_frbr_override')) {
            Schema::create('library_item_frbr_override', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('library_item_id');
                $table->string('target_work_key', 64);
                $table->timestamps();
                $table->index('library_item_id');
            });
        }
    }
};
