<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * #768 KBART scheduler completions:
     * - library_kbart_import_log: per-fetch history (date, vendor, row count, errors, diff)
     * - library_kbart_feed.refresh_frequency: per-vendor cron expression
     * - library_kbart_feed.last_diff: JSON snapshot of last successful import for diff detection
     */
    public function up(): void
    {
        if (Schema::hasTable('library_kbart_feed') && !Schema::hasColumn('library_kbart_feed', 'refresh_frequency')) {
            Schema::table('library_kbart_feed', function (Blueprint $t) {
                $t->string('refresh_frequency', 50)->nullable()->default('daily')->after('active')
                  ->comment('daily | weekly | monthly | hourly | <cron expression>');
            });
        }
        if (Schema::hasTable('library_kbart_feed') && !Schema::hasColumn('library_kbart_feed', 'last_diff')) {
            Schema::table('library_kbart_feed', function (Blueprint $t) {
                $t->json('last_diff')->nullable()->after('last_error');
            });
        }
        if (Schema::hasTable('library_kbart_feed') && !Schema::hasColumn('library_kbart_feed', 'fingerprint')) {
            Schema::table('library_kbart_feed', function (Blueprint $t) {
                $t->string('fingerprint', 64)->nullable()->after('last_row_count')
                  ->comment('sha256 of last fetched TSV body - skips re-import when unchanged');
            });
        }

        if (!Schema::hasTable('library_kbart_import_log')) {
            Schema::create('library_kbart_import_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('feed_id');
                $t->string('status', 16)->comment('success | fail | skipped');
                $t->unsignedInteger('row_count')->default(0);
                $t->integer('added')->default(0);
                $t->integer('removed')->default(0);
                $t->integer('changed')->default(0);
                $t->string('fingerprint', 64)->nullable();
                $t->text('error')->nullable();
                $t->json('diff_sample')->nullable();
                $t->unsignedInteger('elapsed_ms')->default(0);
                $t->timestamp('created_at')->useCurrent();
                $t->index('feed_id', 'ix_library_kbart_import_log_feed');
                $t->index('created_at', 'ix_library_kbart_import_log_created');
            });
        }

        // ahg_notification is created by ahg-core's own install path and uses
        // the (user_id, type, title, message, link, related_type, related_id,
        // is_read) shape. This migration intentionally does NOT recreate it -
        // a previous draft did and shipped with a divergent shape that
        // collided at insert-time. Consumers must always use the real schema.
    }

    public function down(): void
    {
        Schema::dropIfExists('library_kbart_import_log');
        if (Schema::hasTable('library_kbart_feed')) {
            Schema::table('library_kbart_feed', function (Blueprint $t) {
                if (Schema::hasColumn('library_kbart_feed', 'refresh_frequency')) $t->dropColumn('refresh_frequency');
                if (Schema::hasColumn('library_kbart_feed', 'last_diff')) $t->dropColumn('last_diff');
                if (Schema::hasColumn('library_kbart_feed', 'fingerprint')) $t->dropColumn('fingerprint');
            });
        }
    }
};
