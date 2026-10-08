<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1547. Tables and a column that live instances had but no source file
 * created: they were made lazily by the code that uses them (blog links, the
 * Fuseki orphan sweep, help-article link provenance), or by hand
 * (core_fixity_check_log, read by the Trust Console). A fresh install, CI and
 * the test database therefore lacked them. Definitions match heratio_dev; each
 * is created only where missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_fixity_check_log')) {
            Schema::create('core_fixity_check_log', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->integer('digital_object_id');
                $t->string('expected_checksum', 255)->nullable();
                $t->string('expected_algo', 50)->nullable();
                $t->string('computed_checksum', 255)->nullable();
                $t->string('result', 40)->default('error');
                $t->unsignedBigInteger('byte_size')->nullable();
                $t->string('detail', 255)->nullable();
                $t->dateTime('checked_at');
                $t->index('digital_object_id', 'core_fixity_check_log_do_idx');
                $t->index('result', 'core_fixity_check_log_result_idx');
                $t->index('checked_at', 'core_fixity_check_log_checked_idx');
            });
        }

        if (! Schema::hasTable('ahg_fuseki_orphan_log')) {
            Schema::create('ahg_fuseki_orphan_log', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('graph_uri', 512)->unique();
                $t->timestamp('first_seen_at');
                $t->timestamp('purged_at')->nullable();
            });
        }

        if (! Schema::hasTable('blog_post_link')) {
            Schema::create('blog_post_link', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('post_id');
                $t->unsignedBigInteger('related_post_id');
                $t->timestamp('created_at')->nullable()->useCurrent();
                $t->integer('sort_order')->default(0);
                $t->string('description', 500)->nullable();
                $t->unique(['post_id', 'related_post_id'], 'uq_blog_link');
                $t->index('related_post_id', 'idx_blog_link_related');
            });
        }

        if (Schema::hasTable('help_article_link') && ! Schema::hasColumn('help_article_link', 'source')) {
            Schema::table('help_article_link', fn (Blueprint $t) => $t->string('source', 16)->default('markdown'));
        }
    }

    public function down(): void
    {
        // Left in place: live instances had these before this migration.
    }
};
