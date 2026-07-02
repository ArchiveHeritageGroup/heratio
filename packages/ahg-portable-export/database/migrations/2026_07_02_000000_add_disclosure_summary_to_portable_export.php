<?php

/**
 * #1389 — record the disclosure gates a portable-export run applied, so the
 * operator can see what was withheld (and why) from the offline package.
 * JSON blob: {"unpublished":N,"icip":N,"odrl":N,"redacted_objects":N,"kept":N}.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portable_export')
            && ! Schema::hasColumn('portable_export', 'disclosure_summary')) {
            Schema::table('portable_export', function (Blueprint $table) {
                $table->text('disclosure_summary')->nullable()->after('error_message');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('portable_export') && Schema::hasColumn('portable_export', 'disclosure_summary')) {
            Schema::table('portable_export', function (Blueprint $table) {
                $table->dropColumn('disclosure_summary');
            });
        }
    }
};
