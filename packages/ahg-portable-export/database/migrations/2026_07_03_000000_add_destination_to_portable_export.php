<?php

/**
 * Portable Export destination choice: 'zip' (default, downloadable ZIP) or
 * 'folder' (dump the bundle uncompressed to an operator-chosen directory/drive).
 * Large dumps can exceed ZIP limits / not fit alongside a temp staging copy, so
 * folder mode writes the bundle straight to the target path.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portable_export')) {
            return;
        }
        Schema::table('portable_export', function (Blueprint $table) {
            if (! Schema::hasColumn('portable_export', 'destination')) {
                $table->string('destination', 16)->default('zip')->after('mode');
            }
            if (! Schema::hasColumn('portable_export', 'destination_path')) {
                $table->string('destination_path', 1000)->nullable()->after('destination');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('portable_export')) {
            Schema::table('portable_export', function (Blueprint $table) {
                foreach (['destination', 'destination_path'] as $col) {
                    if (Schema::hasColumn('portable_export', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
