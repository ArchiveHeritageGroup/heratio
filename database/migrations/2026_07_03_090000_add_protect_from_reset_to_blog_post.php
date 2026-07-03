<?php

/**
 * Add blog_post.protect_from_reset - marks an article to be preserved across the
 * nightly demo DB reset (heratio-demo-reset.sh restores a fixed baseline, which
 * would otherwise wipe post-baseline articles and reset every article's reads).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @copyright Plain Sailing Information Systems
 *
 * @license AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_post') && ! Schema::hasColumn('blog_post', 'protect_from_reset')) {
            Schema::table('blog_post', function (Blueprint $table) {
                $table->boolean('protect_from_reset')->default(false)->after('view_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_post') && Schema::hasColumn('blog_post', 'protect_from_reset')) {
            Schema::table('blog_post', function (Blueprint $table) {
                $table->dropColumn('protect_from_reset');
            });
        }
    }
};
